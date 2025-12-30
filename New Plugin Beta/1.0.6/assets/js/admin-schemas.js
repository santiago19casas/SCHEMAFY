/**
 * Custom Schema by BASEO - Schema List & CRUD Operations
 * Version: 1.0.2
 * Depends on: admin-utils.js, admin-editor.js
 */

(function($) {
    'use strict';
    
    window.BASEO = window.BASEO || {};
    var t = window.BASEO.t;
    
    /**
     * Load schemas from REST API
     */
    window.BASEO.loadSchemas = function(page = 1, resetFilter = false, isRetry = false) {
        if (isRetry && page === window.BASEO.state.currentPage) {
            return;
        }
        
        if (resetFilter) {
            window.BASEO.state.currentPage = 1;
        } else {
            window.BASEO.state.currentPage = page;
        }
        
        window.BASEO.state.currentSchemaType = $('#baseo-filter-type').val() || '';
        
        window.BASEO.updateUrlParam('page', window.BASEO.state.currentPage > 1 ? window.BASEO.state.currentPage : null);
        window.BASEO.updateUrlParam('schema_type', window.BASEO.state.currentSchemaType || null);
        window.BASEO.updateUrlParam('per_page', window.BASEO.state.currentPerPage !== 20 ? window.BASEO.state.currentPerPage : null);
        window.BASEO.updateUrlParam('search', window.BASEO.state.currentSearchQuery || null);
        
        var loadingMsg = window.BASEO.state.currentSearchQuery ? 
            t('searching', '🔍 Searching...') : 
            t('loading_schemas', 'Loading schemas...');
        $('#baseo-schemas-container').html('<div class="baseo-loading"><div class="baseo-spinner"></div><p>' + loadingMsg + '</p></div>');
        
        var apiUrl = window.BASEO.buildSchemasUrl({
            page: window.BASEO.state.currentPage,
            per_page: window.BASEO.state.currentPerPage,
            schema_type: window.BASEO.state.currentSchemaType
        });
        
        $.ajax({
            url: apiUrl,
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', baseo_ajax.rest_nonce);
            },
            success: function(data, textStatus, xhr) {
                var totalItems = parseInt(xhr.getResponseHeader('X-WP-Total'));
                var totalPages = parseInt(xhr.getResponseHeader('X-WP-TotalPages'));
                var responsePage = parseInt(xhr.getResponseHeader('X-WP-Page')) || window.BASEO.state.currentPage;
                var responsePerPage = parseInt(xhr.getResponseHeader('X-WP-Per-Page')) || window.BASEO.state.currentPerPage;
                
                window.BASEO.state.currentPage = responsePage;
                window.BASEO.state.currentPerPage = responsePerPage;
                $('#baseo-filter-perpage').val(responsePerPage);
                
                totalPages = (isNaN(totalPages) || totalPages < 1) ? 1 : totalPages;
                totalItems = (isNaN(totalItems) || totalItems < 0) ? 0 : totalItems;
                
                if (!isRetry && window.BASEO.state.currentPage > totalPages && totalPages > 0) {
                    window.BASEO.state.currentPage = totalPages;
                    window.BASEO.loadSchemas(window.BASEO.state.currentPage, false, true);
                    return;
                }
                
                window.BASEO.displaySchemasWithPagination(data, totalItems, totalPages);
            },
            error: function(xhr) {
                var errorMsg = t('error_loading', 'Error loading schemas');
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg += ': ' + xhr.responseJSON.message;
                }
                window.BASEO.showNotification(errorMsg, 'error');
                $('#baseo-schemas-container').html('<div class="baseo-empty-state"><p>' + errorMsg + '</p></div>');
            }
        });
    };
    
    /**
     * Display schemas with pagination
     */
    window.BASEO.displaySchemasWithPagination = function(schemas, totalItems, totalPages) {
        var container = $('#baseo-schemas-container');
        
        if (schemas.length === 0) {
            var emptyMsg = window.BASEO.state.currentSearchQuery ? 
                t('no_results', 'No schemas found') + ' "' + window.BASEO.state.currentSearchQuery + '"' : 
                t('empty_state', '🌟 Add your first schema!');
            container.html('<div class="baseo-empty-state"><p>' + emptyMsg + '</p></div>');
            return;
        }
        
        // Organize by URL
        var schemasByUrl = {};
        schemas.forEach(function(schema) {
            if (!schemasByUrl[schema.url]) {
                schemasByUrl[schema.url] = [];
            }
            schemasByUrl[schema.url].push(schema);
        });
        
        var html = '';
        
        Object.keys(schemasByUrl).forEach(function(url) {
            var urlSchemas = schemasByUrl[url];
            var activeCount = urlSchemas.filter(s => s.is_active == '1').length;
            var totalCount = urlSchemas.length;
            var cleanUrl = url.replace(/\/\d+\/\d+$/, '').replace(/\/$/, '');
            
            html += '<div class="baseo-url-group">';
            html += '<div class="baseo-url-header" onclick="window.BASEO.toggleUrlGroup(this)">';
            html += '<div class="baseo-url-info">';
            html += '<span class="baseo-toggle-icon">▼</span>';
            html += '<span class="baseo-url-title">' + cleanUrl + '</span>';
            html += '<span class="baseo-url-stats">' + activeCount + '/' + totalCount + ' ' + t('active_short', 'active') + '</span>';
            html += '</div>';
            html += '<div class="baseo-url-actions">';
            html += '<button class="baseo-btn-micro baseo-visit-url" data-url="' + url + '">' + t('visit', '🔗 Visit') + '</button>';
            html += '<button class="baseo-btn-micro baseo-add-to-url" data-url="' + url + '">' + t('add_schema', '➕ Add Schema') + '</button>';
            html += '</div>';
            html += '</div>';
            
            html += '<div class="baseo-url-content">';
            
            urlSchemas.sort(function(a, b) {
                return new Date(b.created_at) - new Date(a.created_at);
            });
            
            urlSchemas.forEach(function(schema) {
                var preview = JSON.stringify(JSON.parse(schema.schema_data), null, 2);
                if (preview.length > 250) {
                    preview = preview.substring(0, 250) + '...';
                }
                
                var statusClass = schema.is_active == '1' ? 'baseo-active' : 'baseo-inactive';
                var statusIcon = schema.is_active == '1' ? '✅' : '⏸️';
                
                html += '<div class="baseo-schema-item ' + statusClass + '">';
                html += '<div class="baseo-schema-header">';
                html += '<div class="baseo-schema-info">';
                html += '<div class="baseo-schema-name">';
                html += '<span class="baseo-status-icon">' + statusIcon + '</span>';
                html += '<span class="baseo-name-text">' + schema.schema_name + '</span>';
                html += '</div>';
                html += '<div class="baseo-schema-meta">';
                html += '<span class="baseo-schema-type">' + window.BASEO.getSchemaIcon(schema.schema_type) + ' ' + schema.schema_type + '</span>';
                html += '<span class="baseo-date">' + t('updated_label', 'Updated: ') + window.BASEO.formatDate(schema.updated_at) + '</span>';
                html += '</div>';
                html += '</div>';
                html += '<div class="baseo-schema-actions">';
                html += '<button class="baseo-btn-micro baseo-edit-schema" data-id="' + schema.id + '">' + t('edit', '✏️ Edit') + '</button>';
                html += '<button class="baseo-btn-micro baseo-toggle-schema" data-id="' + schema.id + '" data-active="' + schema.is_active + '">';
                html += (schema.is_active == '1' ? t('deactivate', '⏸️ Deactivate') : t('activate', '▶️ Activate'));
                html += '</button>';
                html += '<button class="baseo-btn-micro baseo-delete-schema" data-id="' + schema.id + '">' + t('delete', '🗑️ Delete') + '</button>';
                html += '</div>';
                html += '</div>';
                
                html += '<div class="baseo-schema-preview-container" style="display: none;">';
                html += '<div class="baseo-schema-preview"><pre>' + preview + '</pre></div>';
                html += '<div class="baseo-preview-actions">';
                html += '<button class="baseo-btn-micro baseo-test-schema" data-url="' + schema.url + '">' + t('test_on_google', '🧪 Test') + '</button>';
                html += '<button class="baseo-btn-micro baseo-copy-schema" data-schema=\'' + JSON.stringify(schema.schema_data) + '\'>' + t('copy_json', '📋 Copy') + '</button>';
                html += '</div>';
                html += '</div>';
                html += '</div>';
            });
            
            html += '</div>';
            html += '</div>';
        });
        
        container.html(html);
        
        // Pagination
        if (totalPages > 1) {
            var paginationHtml = '<div class="baseo-pagination">';
            
            if (window.BASEO.state.currentPage > 1) {
                paginationHtml += '<button type="button" class="baseo-btn-small baseo-page-btn" data-page="' + 
                    (window.BASEO.state.currentPage - 1) + '">' + t('prev', '← Previous') + '</button> ';
            }
            
            paginationHtml += '<span>' + t('page_label', 'Page') + ' ' + window.BASEO.state.currentPage + ' ' + 
                t('of_label', 'of') + ' ' + totalPages + ' (' + totalItems + ' ' + t('items_label', 'items') + ')</span>';
            
            if (window.BASEO.state.currentPage < totalPages) {
                paginationHtml += ' <button type="button" class="baseo-btn-small baseo-page-btn" data-page="' + 
                    (window.BASEO.state.currentPage + 1) + '">' + t('next', 'Next →') + '</button>';
            }
            
            paginationHtml += '</div>';
            container.append(paginationHtml);
        }
    };
    
    /**
     * Toggle URL group expand/collapse
     */
    window.BASEO.toggleUrlGroup = function(header) {
        var $header = $(header);
        var $content = $header.next('.baseo-url-content');
        var $icon = $header.find('.baseo-toggle-icon');
        
        if ($content.is(':visible')) {
            $content.slideUp(300);
            $icon.text('▶');
            $header.removeClass('expanded');
        } else {
            $content.slideDown(300);
            $icon.text('▼');
            $header.addClass('expanded');
        }
    };
    
    console.log('✅ BASEO Schemas loaded');
})(jQuery);