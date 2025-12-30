/**
 * Custom Schema by BASEO - Form Handlers (Single + Bulk)
 * Version: 1.0.2
 * Depends on: admin-utils.js, admin-editor.js
 */

(function($) {
    'use strict';
    
    window.BASEO = window.BASEO || {};
    var t = window.BASEO.t;
    
    // Bulk state
    window.BASEO.bulkState = {
        allUrls: [],
        selectedUrls: [],
        currentFilter: 'all'
    };
    
    /**
     * Handle Single Schema Form Submit
     */
    window.BASEO.handleSingleSchemaSubmit = function(e) {
        e.preventDefault();
        
        var url = $('#baseo-url').val().trim();
        var schemaName = $('#baseo-schema-name').val().trim();
        var schemaData = $('#baseo-schema-data').val().trim();
        var schemaType = $('#baseo-schema-type').val();
        var metaTitle = $('#baseo-meta-title').val().trim();
        var metaDescription = $('#baseo-meta-description').val().trim();
        var editId = $('#baseo-schema-form').attr('data-edit-id');
        var isEdit = editId ? true : false;
        
        // Validate required fields
        if (!schemaName) {
            window.BASEO.showNotification(t('error_prefix', '❌ Error: ') + t('json_invalid', 'Invalid JSON'), 'error');
            $('#baseo-schema-name').focus();
            return;
        }
        
        // Validate domain
        if (!window.BASEO.isValidDomain(url)) {
            window.BASEO.showNotification(t('url_domain_error', '🚨 URL must be from the same domain'), 'error');
            $('#baseo-url').focus();
            return;
        }
        
        // Detect script tags
        if (window.BASEO.hasScriptTags(schemaData)) {
            window.BASEO.showScriptTagsModal();
            return;
        }
        
        // Validate JSON
        try {
            JSON.parse(schemaData);
        } catch (e) {
            window.BASEO.showNotification(t('json_invalid', '❌ Invalid JSON'), 'error');
            return;
        }
        
        // Show loading
        var $submitBtn = $('button[type="submit"]', '#baseo-schema-form');
        var originalText = $submitBtn.html();
        $submitBtn.html(isEdit ? t('updating', '⏱ Updating...') : t('saving', '⏱ Saving...')).prop('disabled', true);
        
        var ajaxData = {
            action: isEdit ? 'baseo_update_schema' : 'baseo_save_schema',
            nonce: baseo_ajax.nonce,
            url: url,
            schema_name: schemaName,
            schema_data: schemaData,
            schema_type: schemaType,
            meta_title: metaTitle,
            meta_description: metaDescription
        };
        
        if (isEdit) {
            ajaxData.id = editId;
        }
        
        $.ajax({
            url: baseo_ajax.ajax_url,
            type: 'POST',
            data: ajaxData,
            success: function(response) {
                if (response.success) {
                    var message = isEdit ? 
                        '✅ "' + schemaName + '" ' + t('schema_updated', 'updated!') : 
                        '✅ "' + schemaName + '" ' + t('schema_saved', 'saved!');
                    window.BASEO.showNotification(message, 'success');
                    
                    if (isEdit) {
                        window.BASEO.resetForm('baseo-schema-form');
                    } else {
                        $('#baseo-schema-form')[0].reset();
                        window.BASEO.setEditorContent('baseo-schema-editor', 'baseo-schema-data', '');
                        window.BASEO.updateCharCounter('#baseo-meta-title', 'baseo-meta-title-counter', 70);
                        window.BASEO.updateCharCounter('#baseo-meta-description', 'baseo-meta-description-counter', 160);
                    }
                    
                    if (window.BASEO.loadSchemas) {
                        window.BASEO.loadSchemas(window.BASEO.state.currentPage, false);
                    }
                } else {
                    window.BASEO.showNotification(t('error_prefix', '❌ Error: ') + response.data, 'error');
                }
            },
            error: function() {
                window.BASEO.showNotification(t('connection_error', '❌ Connection error'), 'error');
            },
            complete: function() {
                $submitBtn.html(originalText).prop('disabled', false);
            }
        });
    };
    
    /**
     * Handle Bulk Schema Form Submit
     */
    window.BASEO.handleBulkSchemaSubmit = function(e) {
        e.preventDefault();
        
        var schemaName = $('#baseo-bulk-schema-name').val().trim();
        var schemaData = $('#baseo-bulk-schema-data').val().trim();
        var schemaType = $('#baseo-bulk-schema-type').val();
        var metaTitle = $('#baseo-bulk-meta-title').val().trim();
        var metaDescription = $('#baseo-bulk-meta-description').val().trim();
        var selectedUrls = window.BASEO.bulkState.selectedUrls;
        
        // Validate required fields
        if (!schemaName || !schemaData) {
            window.BASEO.showNotification(t('frontend_fill_required', '❌ Fill all required fields'), 'error');
            return;
        }
        
        if (selectedUrls.length === 0) {
            window.BASEO.showNotification(t('no_urls_selected', '⚠️ Please select at least one URL'), 'warning');
            return;
        }
        
        // Validate JSON
        try {
            JSON.parse(schemaData);
        } catch (e) {
            window.BASEO.showNotification(t('json_invalid', '❌ Invalid JSON'), 'error');
            return;
        }
        
        // Detect script tags
        if (window.BASEO.hasScriptTags(schemaData)) {
            window.BASEO.showScriptTagsModal();
            return;
        }
        
        // Confirm
        var confirmMsg = t('bulk_confirm', 'Apply schema to %d URLs?').replace('%d', selectedUrls.length);
        if (!confirm(confirmMsg)) {
            return;
        }
        
        // Show loading
        var $submitBtn = $('#baseo-bulk-apply-btn');
        var originalText = $submitBtn.html();
        $submitBtn.html('⏱ ' + t('bulk_applying', 'Applying to') + ' ' + selectedUrls.length + ' URLs...').prop('disabled', true);
        
        // Call REST API
        $.ajax({
            url: baseo_ajax.rest_base + '/schemas/bulk-apply',
            type: 'POST',
            contentType: 'application/json',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', baseo_ajax.rest_nonce);
            },
            data: JSON.stringify({
                schema_name: schemaName,
                schema_type: schemaType,
                schema_data: schemaData,
                meta_title: metaTitle,
                meta_description: metaDescription,
                urls: selectedUrls
            }),
            success: function(response) {
                if (response.success >= 0) {
                    var message;
                    if (response.failed === 0) {
                        message = '✅ ' + t('bulk_success', '%d schemas created').replace('%d', response.success);
                    } else {
                        message = '⚠️ ' + t('bulk_partial', '%d created, %d failed')
                            .replace('%d', response.success)
                            .replace('%d', response.failed);
                    }
                    window.BASEO.showNotification(message, response.failed === 0 ? 'success' : 'warning');
                    
                    // Reset form
                    $('#baseo-bulk-schema-form')[0].reset();
                    window.BASEO.setEditorContent('baseo-bulk-schema-editor', 'baseo-bulk-schema-data', '');
                    window.BASEO.updateCharCounter('#baseo-bulk-meta-title', 'baseo-bulk-meta-title-counter', 70);
                    window.BASEO.updateCharCounter('#baseo-bulk-meta-description', 'baseo-bulk-meta-description-counter', 160);
                    
                    // Uncheck all
                    $('.baseo-url-checkbox').prop('checked', false);
                    window.BASEO.bulkState.selectedUrls = [];
                    window.BASEO.updateUrlCounter();
                    
                    // Switch to Single tab
                    window.BASEO.switchTab('single');
                    
                    // Reload schemas
                    if (window.BASEO.loadSchemas) {
                        window.BASEO.loadSchemas(1, false);
                    }
                } else {
                    window.BASEO.showNotification(t('error_prefix', '❌ Error'), 'error');
                }
            },
            error: function(xhr) {
                var errorMsg = t('connection_error', '❌ Connection error');
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg += ': ' + xhr.responseJSON.message;
                }
                window.BASEO.showNotification(errorMsg, 'error');
            },
            complete: function() {
                $submitBtn.html(originalText).prop('disabled', false);
            }
        });
    };
    
    /**
     * Load URLs for bulk selector
     */
    window.BASEO.loadBulkUrls = function() {
        $('#baseo-url-selector').html('<div class="baseo-loading"><div class="baseo-spinner"></div><p>' + t('loading_urls', '⏱ Loading URLs...') + '</p></div>');
        
        // Load all types simultaneously
        var promises = [
            $.ajax({
                url: baseo_ajax.ajax_url,
                type: 'POST',
                data: { action: 'baseo_get_bulk_urls', nonce: baseo_ajax.nonce, type: 'all-pages' }
            }),
            $.ajax({
                url: baseo_ajax.ajax_url,
                type: 'POST',
                data: { action: 'baseo_get_bulk_urls', nonce: baseo_ajax.nonce, type: 'all-posts' }
            }),
            $.ajax({
                url: baseo_ajax.ajax_url,
                type: 'POST',
                data: { action: 'baseo_get_bulk_urls', nonce: baseo_ajax.nonce, type: 'all-products' }
            })
        ];
        
        Promise.all(promises).then(function(results) {
            var allUrls = [];
            
            results.forEach(function(response) {
                if (response.success && response.data && response.data.urls) {
                    allUrls = allUrls.concat(response.data.urls);
                }
            });
            
            window.BASEO.bulkState.allUrls = allUrls;
            window.BASEO.renderUrlSelector(allUrls);
            
            $('#baseo-bulk-urls-loaded').text('✅ ' + t('all_urls_loaded', 'All URLs loaded') + ' (' + allUrls.length + ')');
        }).catch(function() {
            window.BASEO.showNotification(t('error_loading', '❌ Error loading URLs'), 'error');
            $('#baseo-url-selector').html('<div class="baseo-empty-state"><p>' + t('error_loading', 'Error loading URLs') + '</p></div>');
        });
    };
    
    /**
     * Render URL selector
     */
    window.BASEO.renderUrlSelector = function(urls) {
        var html = '<div class="baseo-url-selector">';
        
        urls.forEach(function(urlData) {
            var typeClass = 'baseo-type-' + urlData.type;
            var typeIcon = urlData.type === 'page' ? '📄' : (urlData.type === 'post' ? '📝' : '🛍️');
            var typeLabel = urlData.type === 'page' ? t('pages', 'Page') : (urlData.type === 'post' ? t('posts', 'Post') : t('products', 'Product'));
            
            html += '<div class="baseo-url-item ' + typeClass + '" data-type="' + urlData.type + '">';
            html += '<input type="checkbox" class="baseo-url-checkbox" data-url="' + urlData.url + '" />';
            html += '<div class="baseo-url-info">';
            html += '<div class="baseo-url-title">' + urlData.title + '</div>';
            html += '<div class="baseo-url-path">' + urlData.path + '</div>';
            html += '<div class="baseo-url-meta">';
            html += '<span class="baseo-url-type">' + typeIcon + ' ' + typeLabel + '</span>';
            if (urlData.schema_count > 0) {
                html += '<span class="baseo-url-schemas">⚙️ ' + t('has_schemas', 'Has %d schema(s)').replace('%d', urlData.schema_count) + '</span>';
            }
            html += '</div>';
            html += '</div>';
            html += '</div>';
        });
        
        html += '</div>';
        
        $('#baseo-url-selector').html(html);
    };
    
    /**
     * Update selected URL counter
     */
    window.BASEO.updateUrlCounter = function() {
        var count = window.BASEO.bulkState.selectedUrls.length;
        $('#baseo-bulk-url-count').text(count);
        $('#baseo-bulk-apply-count').text(count);
        
        // Update button state
        if (count === 0) {
            $('#baseo-bulk-apply-btn').prop('disabled', true);
        } else {
            $('#baseo-bulk-apply-btn').prop('disabled', false);
        }
    };
    
    /**
     * Filter URLs by type
     */
    window.BASEO.filterUrlsByType = function(type) {
        window.BASEO.bulkState.currentFilter = type;
        
        if (type === 'all') {
            $('.baseo-url-item').removeClass('hidden');
        } else {
            $('.baseo-url-item').addClass('hidden');
            $('.baseo-url-item.baseo-type-' + type).removeClass('hidden');
        }
    };
    
    /**
     * Validate JSON button handler
     */
    window.BASEO.handleValidateJSON = function(editorId, buttonId) {
        var schemaData = $('#' + editorId).prev('textarea').val();
        var $button = $('#' + buttonId);
        
        if (!schemaData.trim()) {
            window.BASEO.showNotification(t('schema_field_empty', '⚠️ Schema field is empty'), 'warning');
            return;
        }
        
        if (window.BASEO.hasScriptTags(schemaData)) {
            window.BASEO.showScriptTagsModal();
            return;
        }
        
        try {
            JSON.parse(schemaData);
            $button.html(t('valid', '✅ Valid')).css('background', 'var(--baseo-success)').css('color', 'white');
            window.BASEO.showNotification(t('json_valid', '✅ JSON valid'), 'success');
            setTimeout(function() {
                $button.html(t('validate', '✓ Validate')).css('background', '').css('color', '');
            }, 2000);
        } catch (e) {
            $button.html(t('error', '❌ Error')).css('background', 'var(--baseo-error)').css('color', 'white');
            window.BASEO.showNotification(t('json_invalid', '❌ Invalid JSON') + ': ' + e.message, 'error');
            setTimeout(function() {
                $button.html(t('validate', '✓ Validate')).css('background', '').css('color', '');
            }, 3000);
        }
    };
    
    /**
     * Format JSON button handler
     */
    window.BASEO.handleFormatJSON = function(editorId, textareaId) {
        var schemaData = $('#' + textareaId).val();
        try {
            var formatted = JSON.stringify(JSON.parse(schemaData), null, 2);
            window.BASEO.setEditorContent(editorId, textareaId, formatted);
            window.BASEO.showNotification(t('json_formatted', '🎨 JSON formatted'), 'success');
        } catch (e) {
            window.BASEO.showNotification(t('json_cannot_format', '❌ Cannot format'), 'error');
        }
    };
    
    /**
     * Clear JSON button handler
     */
    window.BASEO.handleClearJSON = function(editorId, textareaId) {
        if (confirm(t('clear_confirm', 'Clear content?'))) {
            window.BASEO.setEditorContent(editorId, textareaId, '');
            window.BASEO.showNotification(t('content_cleared', '🗑️ Cleared'), 'info');
        }
    };
    
    console.log('✅ BASEO Forms loaded');
})(jQuery);