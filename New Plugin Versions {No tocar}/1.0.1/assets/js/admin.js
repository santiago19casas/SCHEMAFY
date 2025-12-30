/**
 * Custom Schema by BASEO - Admin JavaScript
 * Version: 1.0.1
 * Author: BASEO Team
 */

// Translation helper function
function t(key, fallback = '') {
    try {
        return (window.baseo_ajax && baseo_ajax.i18n && baseo_ajax.i18n[key]) || fallback;
    } catch(e) {
        return fallback;
    }
}

// Main Admin JavaScript
jQuery(document).ready(function($) {
    
    // Global variables
    var currentPage = 1;
    var currentPerPage = 20;
    var currentSchemaType = '';
    var popstateHandlerBound = false;
    
    // URL parameter helpers
    function getUrlParam(param) {
        var urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(param);
    }
    
    function updateUrlParam(param, value) {
        var url = new URL(window.location);
        if (value !== null && value !== undefined && value !== '' && value !== 1) {
            url.searchParams.set(param, value);
        } else {
            url.searchParams.delete(param);
        }
        window.history.replaceState({}, '', url);
    }
    
    // Build schemas URL helper
    function buildSchemasUrl(params = {}) {
        var restBase = (baseo_ajax.rest_base || '').replace(/\/$/, '');
        var page = params.page || currentPage || 1;
        var perPage = params.per_page || currentPerPage || 20;
        var schemaType = params.schema_type !== undefined ? params.schema_type : currentSchemaType;
        
        var url = restBase + '/schemas?page=' + page + '&per_page=' + perPage;
        
        if (schemaType) {
            url += '&schema_type=' + encodeURIComponent(schemaType);
        }
        
        return url;
    }
    
    // Function to render the admin UI
    function renderAdminUI(skipInitialLoad = false) {
        var container = $('#baseo-schema-app');
        if (!container.length) return;
        
        var html = '';
        
        // Header
        html += '<div class="baseo-header">';
        html += '<div class="baseo-header-content">';
        html += '<div class="baseo-logo-section">';
        html += '<h1><span class="baseo-logo">🚀</span>';
        html += '<span class="baseo-title">Custom Schema</span>';
        html += '<span class="baseo-by">by</span>';
        html += '<span class="baseo-brand">' + (baseo_ajax.brand_name || 'BASEO') + '</span></h1>';
        html += '</div></div></div>';
        
        // Main content
        html += '<div class="baseo-main-content">';
        html += '<div class="baseo-content-grid">';
        
        // Left panel: Add Schema Form
        html += '<div class="baseo-panel baseo-add-schema">';
        html += '<div class="baseo-panel-header">';
        html += '<h2>' + t('add_new_schema', '➕ Add New Schema') + '</h2>';
        html += '</div>';
        
        html += '<form id="baseo-schema-form" class="baseo-form">';
        
        // Schema Name field
        html += '<div class="baseo-form-group">';
        html += '<label for="baseo-schema-name" class="baseo-label">';
        html += '✏️ ' + t('schema_name', 'Schema Name') + ' <span class="baseo-required">*</span>';
        html += '</label>';
        html += '<input type="text" id="baseo-schema-name" name="schema_name" class="baseo-input" required />';
        html += '</div>';
        
        // URL field
        html += '<div class="baseo-form-group">';
        html += '<label for="baseo-url" class="baseo-label">';
        html += '🔗 ' + t('page_url', 'Page URL') + ' <span class="baseo-required">*</span>';
        html += '</label>';
        html += '<input type="text" id="baseo-url" name="url" class="baseo-input" placeholder="' + baseo_ajax.site_url + '/your-page" required />';
        html += '</div>';
        
        // Schema Type select
        html += '<div class="baseo-form-group">';
        html += '<label for="baseo-schema-type" class="baseo-label">📋 ' + t('schema_type', 'Schema Type') + '</label>';
        html += '<select id="baseo-schema-type" name="schema_type" class="baseo-select">';
        html += '<option value="WebPage">📄 WebPage</option>';
        html += '<option value="Article">📝 Article</option>';
        html += '<option value="Product">🛍️ Product</option>';
        html += '<option value="Organization">🏢 Organization</option>';
        html += '<option value="LocalBusiness">🏪 LocalBusiness</option>';
        html += '<option value="Person">👤 Person</option>';
        html += '<option value="Event">🎉 Event</option>';
        html += '<option value="Recipe">🍳 Recipe</option>';
        html += '<option value="Review">⭐ Review</option>';
        html += '<option value="FAQ">❓ FAQ</option>';
        html += '</select>';
        html += '</div>';
        
        // JSON-LD Code textarea
        html += '<div class="baseo-form-group">';
        html += '<label for="baseo-schema-data" class="baseo-label">';
        html += '📊 ' + t('json_ld_code', 'JSON-LD Code') + ' <span class="baseo-required">*</span>';
        html += '</label>';
        html += '<div class="baseo-textarea-container">';
        html += '<textarea id="baseo-schema-data" name="schema_data" rows="12" class="baseo-textarea" ';
        html += 'placeholder=\'{"@context": "https://schema.org", "@type": "Organization", "name": "Your Company"}\' required></textarea>';
        html += '<div class="baseo-textarea-tools">';
        html += '<button type="button" id="baseo-validate-json" class="baseo-btn-small">' + t('validate', '✔ Validate') + '</button>';
        html += '<button type="button" id="baseo-format-json" class="baseo-btn-small">' + t('format', '🎨 Format') + '</button>';
        html += '<button type="button" id="baseo-clear-json" class="baseo-btn-small">' + t('clear', '🗑️ Clear') + '</button>';
        html += '</div></div></div>';
        
        // Form actions
        html += '<div class="baseo-form-actions">';
        html += '<button type="submit" class="baseo-btn baseo-btn-primary">' + t('save_schema', '💾 Save Schema') + '</button>';
        html += '<button type="button" id="baseo-test-schema" class="baseo-btn baseo-btn-secondary">' + t('test_with_google', '🧪 Test with Google') + '</button>';
        html += '</div>';
        
        html += '</form>';
        html += '</div>';
        
        // Right panel: Schemas list
        html += '<div class="baseo-panel baseo-schemas-list">';
        html += '<div class="baseo-panel-header">';
        html += '<h2>' + t('configured_schemas', '📋 Configured Schemas') + '</h2>';
        html += '</div>';
        
        // Filters row
        html += '<div class="baseo-filters-row" style="padding: 15px 25px; background: #f8f9fa; border-bottom: 1px solid #e1e5e9;">';
        html += '<label for="baseo-filter-type" style="margin-right: 10px; font-weight: 600;">';
        html += t('filter_by_type', 'Filter by type:') + '</label>';
        html += '<select id="baseo-filter-type" class="baseo-select" style="width: 200px;" aria-label="' + t('filter_by_type', 'Filter by type') + '">';
        html += '<option value="">' + t('all_types', 'All') + '</option>';
        html += '<option value="WebPage">📄 WebPage</option>';
        html += '<option value="Article">📝 Article</option>';
        html += '<option value="Product">🛍️ Product</option>';
        html += '<option value="Organization">🏢 Organization</option>';
        html += '<option value="LocalBusiness">🏪 LocalBusiness</option>';
        html += '<option value="Person">👤 Person</option>';
        html += '<option value="Event">🎉 Event</option>';
        html += '<option value="Recipe">🍳 Recipe</option>';
        html += '<option value="Review">⭐ Review</option>';
        html += '<option value="FAQ">❓ FAQ</option>';
        html += '</select>';
        
        html += '<label for="baseo-filter-perpage" style="margin-left: 20px; margin-right: 10px; font-weight: 600;">';
        html += t('items_per_page', 'Items per page:') + '</label>';
        html += '<select id="baseo-filter-perpage" class="baseo-select" style="width: 100px;" aria-label="' + t('items_per_page', 'Items per page') + '">';
        html += '<option value="10">10</option>';
        html += '<option value="20" selected>20</option>';
        html += '<option value="50">50</option>';
        html += '<option value="100">100</option>';
        html += '</select>';
        html += '</div>';
        
        html += '<div id="baseo-schemas-container" class="baseo-schemas-container" aria-live="polite">';
        html += '<div class="baseo-loading"><div class="baseo-spinner"></div><p>' + t('loading_schemas', 'Loading schemas...') + '</p></div>';
        html += '</div>';
        html += '</div>';
        
        html += '</div>'; // content-grid
        html += '</div>'; // main-content
        
        // Insert HTML into container
        container.html(html);
        
        // Read parameters from URL
        var urlSchemaType = getUrlParam('schema_type');
        var urlPage = parseInt(getUrlParam('page')) || 1;
        var urlPerPage = parseInt(getUrlParam('per_page')) || 20;
        
        // Validate per_page (min 1, max 100)
        urlPerPage = Math.min(100, Math.max(1, urlPerPage));
        
        // Get valid types from select
        var allowedTypes = [];
        $('#baseo-filter-type option').each(function() {
            var value = $(this).val();
            if (value) allowedTypes.push(value);
        });
        
        // Validate schema_type
        if (urlSchemaType && !allowedTypes.includes(urlSchemaType)) {
            urlSchemaType = '';
        }
        
        // Apply values from URL
        if (urlSchemaType) {
            $('#baseo-filter-type').val(urlSchemaType);
            currentSchemaType = urlSchemaType;
        }
        $('#baseo-filter-perpage').val(urlPerPage);
        currentPerPage = urlPerPage;
        currentPage = urlPage;
        
        // Load schemas with filter applied
        if (!skipInitialLoad) {
            loadSchemas(currentPage, false);
        }
    }
    
    // Render UI if container exists
    if ($('#baseo-schema-app').length) {
        renderAdminUI();
    }
    
    // Function to load schemas using REST API
    function loadSchemas(page = 1, resetFilter = false, isRetry = false) {
        // Guard para evitar loops infinitos
        if (isRetry && page === currentPage) {
            return;
        }
        
        if (resetFilter) {
            currentPage = 1;
        } else {
            currentPage = page;
        }
        
        // Obtener tipo seleccionado
        currentSchemaType = $('#baseo-filter-type').val() || '';
        
        // Actualizar URL con página y filtro
        updateUrlParam('page', currentPage > 1 ? currentPage : null);
        updateUrlParam('schema_type', currentSchemaType || null);
        updateUrlParam('per_page', currentPerPage !== 20 ? currentPerPage : null);
        
        $('#baseo-schemas-container').html('<div class="baseo-loading"><div class="baseo-spinner"></div><p>' + t('loading_schemas', 'Loading schemas...') + '</p></div>');
        
        // Construir URL con parámetros
        var apiUrl = buildSchemasUrl({
            page: currentPage,
            per_page: currentPerPage,
            schema_type: currentSchemaType
        });
        
        // Llamada REST con nonce
        $.ajax({
            url: apiUrl,
            method: 'GET',
            beforeSend: function(xhr) {
                // IMPORTANTE: Enviar nonce en header
                xhr.setRequestHeader('X-WP-Nonce', baseo_ajax.rest_nonce);
            },
            success: function(data, textStatus, xhr) {
                // Obtener headers de paginación
                var totalItems = parseInt(xhr.getResponseHeader('X-WP-Total'));
                var totalPages = parseInt(xhr.getResponseHeader('X-WP-TotalPages'));
                var responsePage = parseInt(xhr.getResponseHeader('X-WP-Page')) || currentPage;
                var responsePerPage = parseInt(xhr.getResponseHeader('X-WP-Per-Page')) || currentPerPage;
                
                // Sincronizar valores del servidor
                currentPage = responsePage;
                currentPerPage = responsePerPage;
                $('#baseo-filter-perpage').val(responsePerPage);
                updateUrlParam('per_page', currentPerPage !== 20 ? currentPerPage : null);
                updateUrlParam('page', currentPage > 1 ? currentPage : null);
                
                // Normalizar valores
                totalPages = (isNaN(totalPages) || totalPages < 1) ? 1 : totalPages;
                totalItems = (isNaN(totalItems) || totalItems < 0) ? 0 : totalItems;
                
                // Verificar si la página actual excede el total
                if (!isRetry && currentPage > totalPages && totalPages > 0) {
                    // Ajustar a la última página y recargar
                    currentPage = totalPages;
                    loadSchemas(currentPage, false, true);
                    return;
                }
                
                displaySchemasWithPagination(data, totalItems, totalPages);
                
                // Log de depuración condicional
                if (window.BASEO_DEBUG) {
                    console.debug('[BASEO] schemas loaded', {page: currentPage, totalPages: totalPages, schemaType: currentSchemaType});
                }
            },
            error: function(xhr) {
                var errorMsg = t('error_loading', 'Error loading schemas');
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg += ': ' + xhr.responseJSON.message;
                }
                showNotification(errorMsg, 'error');
                $('#baseo-schemas-container').html('<div class="baseo-empty-state"><p>' + errorMsg + '</p></div>');
            }
        });
    }
    
    // Function to display schemas with pagination
    function displaySchemasWithPagination(schemas, totalItems, totalPages) {
        var container = $('#baseo-schemas-container');
        
        if (schemas.length === 0) {
            container.html('<div class="baseo-empty-state"><p>' + t('empty_state', '🌟 Add your first schema to get started!') + '</p></div>');
            return;
        }
        
        // Organize schemas by URL
        var schemasByUrl = {};
        schemas.forEach(function(schema) {
            if (!schemasByUrl[schema.url]) {
                schemasByUrl[schema.url] = [];
            }
            schemasByUrl[schema.url].push(schema);
        });
        
        var html = '';
        
        // Generate HTML organized by URL
        Object.keys(schemasByUrl).forEach(function(url) {
            var urlSchemas = schemasByUrl[url];
            var activeCount = urlSchemas.filter(s => s.is_active == '1').length;
            var totalCount = urlSchemas.length;
            
            // Clean URL display
            var cleanUrl = url.replace(/\/\d+\/\d+$/, '').replace(/\/$/, '');
            
            html += '<div class="baseo-url-group">';
            html += '<div class="baseo-url-header" onclick="baseoToggleUrlGroup(this)">';
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
            
            // Sort schemas by creation date
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
                html += '<span class="baseo-schema-type" data-type="' + schema.schema_type + '">' + getSchemaIcon(schema.schema_type) + ' ' + schema.schema_type + '</span>';
                html += '<span class="baseo-date">' + t('updated_label', 'Updated: ') + formatDate(schema.updated_at) + '</span>';
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
                html += '<button class="baseo-btn-micro baseo-test-schema" data-url="' + schema.url + '">' + t('test_on_google', '🧪 Test on Google') + '</button>';
                html += '<button class="baseo-btn-micro baseo-copy-schema" data-schema=\'' + JSON.stringify(schema.schema_data) + '\'>' + t('copy_json', '📋 Copy JSON') + '</button>';
                html += '</div>';
                html += '</div>';
                
                html += '</div>';
            });
            
            html += '</div>';
            html += '</div>';
        });
        
        container.html(html);
        
        // Add pagination controls
        if (totalPages > 1) {
            var paginationHtml = '<div class="baseo-pagination" style="padding: 20px; text-align: center; border-top: 1px solid #e1e5e9;">';
            
            // Previous button
            if (currentPage > 1) {
                paginationHtml += '<button type="button" class="baseo-btn-small baseo-page-btn" data-page="' + 
                    (currentPage - 1) + '" aria-label="' + t('prev', 'Previous page') + '">' + 
                    t('prev', '← Previous') + '</button> ';
            }
            
            // Page info
            paginationHtml += '<span style="margin: 0 15px;">' + 
                t('page_label', 'Page') + ' ' + currentPage + ' ' + 
                t('of_label', 'of') + ' ' + totalPages + 
                ' (' + t('items_per_page_short', 'showing') + ' ' + currentPerPage + ', ' +
                totalItems + ' ' + t('items_label', 'items') + ' total)</span>';
            
            // Next button
            if (currentPage < totalPages) {
                paginationHtml += ' <button type="button" class="baseo-btn-small baseo-page-btn" data-page="' + 
                    (currentPage + 1) + '" aria-label="' + t('next', 'Next page') + '">' + 
                    t('next', 'Next →') + '</button>';
            }
            
            paginationHtml += '</div>';
            container.append(paginationHtml);
        }
    }
    
    // Event Listeners
    
    // Filter change
    $(document).on('change', '#baseo-filter-type', function() {
        var selectedType = $(this).val();
        updateUrlParam('schema_type', selectedType);
        loadSchemas(1, true);
    });
    
    // Per page change
    $(document).on('change', '#baseo-filter-perpage', function() {
        currentPerPage = parseInt($(this).val()) || 20;
        updateUrlParam('per_page', currentPerPage !== 20 ? currentPerPage : null);
        loadSchemas(1, true);
    });
    
    // Pagination buttons
    $(document).on('click', '.baseo-page-btn', function() {
        var page = $(this).data('page');
        loadSchemas(page, false);
    });
    
    // Popstate handler
    if (!popstateHandlerBound) {
        $(window).on('popstate', function() {
            // Guard: check if select exists
            if ($('#baseo-filter-type').length === 0) {
                renderAdminUI(true);
                
                // Retry hasta 3 veces
                var retryCount = 0;
                var retryInterval = setInterval(function() {
                    retryCount++;
                    
                    if ($('#baseo-filter-type').length > 0 || retryCount >= 3) {
                        clearInterval(retryInterval);
                        
                        var urlSchemaType = getUrlParam('schema_type');
                        var urlPage = parseInt(getUrlParam('page')) || 1;
                        var urlPerPage = parseInt(getUrlParam('per_page')) || 20;
                        
                        // Validate values
                        urlPerPage = Math.min(100, Math.max(1, urlPerPage));
                        
                        var allowedTypes = [];
                        $('#baseo-filter-type option').each(function() {
                            var value = $(this).val();
                            if (value) allowedTypes.push(value);
                        });
                        
                        if (urlSchemaType && !allowedTypes.includes(urlSchemaType)) {
                            urlSchemaType = '';
                        }
                        
                        $('#baseo-filter-type').val(urlSchemaType || '');
                        $('#baseo-filter-perpage').val(urlPerPage);
                        currentSchemaType = urlSchemaType || '';
                        currentPage = urlPage;
                        currentPerPage = urlPerPage;
                        
                        loadSchemas(currentPage, false);
                    }
                }, 100);
                return;
            }
            
            // Normal popstate handling
            var urlSchemaType = getUrlParam('schema_type');
            var urlPage = parseInt(getUrlParam('page')) || 1;
            var urlPerPage = parseInt(getUrlParam('per_page')) || 20;
            
            urlPerPage = Math.min(100, Math.max(1, urlPerPage));
            
            var allowedTypes = [];
            $('#baseo-filter-type option').each(function() {
                var value = $(this).val();
                if (value) allowedTypes.push(value);
            });
            
            if (urlSchemaType && !allowedTypes.includes(urlSchemaType)) {
                urlSchemaType = '';
            }
            
            $('#baseo-filter-type').val(urlSchemaType || '');
            $('#baseo-filter-perpage').val(urlPerPage);
            currentSchemaType = urlSchemaType || '';
            currentPage = urlPage;
            currentPerPage = urlPerPage;
            
            loadSchemas(currentPage, false);
        });
        popstateHandlerBound = true;
    }
    
    // Handle form submission (mantener AJAX tradicional para crear/editar)
    $('#baseo-schema-form').on('submit', function(e) {
        e.preventDefault();
        
        var url = $('#baseo-url').val().trim();
        var schemaName = $('#baseo-schema-name').val().trim();
        var schemaData = $('#baseo-schema-data').val().trim();
        var schemaType = $('#baseo-schema-type').val();
        var editId = $(this).attr('data-edit-id');
        var isEdit = editId ? true : false;
        
        // Validate required fields
        if (!schemaName) {
            showNotification(t('error_prefix', '⌛ Error: ') + t('json_invalid', 'Invalid JSON: Please check the syntax.'), 'error');
            $('#baseo-schema-name').focus();
            return;
        }
        
        // Validate domain
        if (!isValidDomain(url)) {
            showNotification(t('url_domain_error', '🚨 URL must be from the same domain as your website'), 'error');
            $('#baseo-url').focus();
            return;
        }
        
        // Detect script tags
        if (hasScriptTags(schemaData)) {
            showScriptTagsModal();
            return;
        }
        
        // Validate JSON before sending
        try {
            JSON.parse(schemaData);
        } catch (e) {
            showNotification(t('json_invalid', '⌛ Invalid JSON: Please check the syntax.'), 'error');
            return;
        }
        
        // Show loading
        var $submitBtn = $('button[type="submit"]');
        var originalText = $submitBtn.html();
        $submitBtn.html(isEdit ? t('updating', '⏱ Updating...') : t('saving', '⏱ Saving...')).prop('disabled', true);
        
        var ajaxData = {
            action: isEdit ? 'baseo_update_schema' : 'baseo_save_schema',
            nonce: baseo_ajax.nonce,
            url: url,
            schema_name: schemaName,
            schema_data: schemaData,
            schema_type: schemaType
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
                        '✅ "' + schemaName + '" ' + t('schema_updated', 'schema updated successfully!') : 
                        '✅ "' + schemaName + '" ' + t('schema_saved', 'schema saved successfully!');
                    showNotification(message, 'success');
                    
                    if (isEdit) {
                        resetForm();
                    } else {
                        $('#baseo-schema-form')[0].reset();
                    }
                    loadSchemas(currentPage, false);
                } else {
                    showNotification(t('error_prefix', '⌛ Error: ') + response.data, 'error');
                }
            },
            error: function() {
                showNotification(t('connection_error', '⌛ Connection error. Please try again.'), 'error');
            },
            complete: function() {
                $submitBtn.html(originalText).prop('disabled', false);
            }
        });
    });
    
    // Schema actions (mantienen funcionalidad actual)
    
    // Validate JSON
    $('#baseo-validate-json').on('click', function() {
        var schemaData = $('#baseo-schema-data').val();
        var $button = $(this);
        
        if (!schemaData.trim()) {
            showNotification(t('schema_field_empty', '⚠️ Schema field is empty'), 'warning');
            return;
        }
        
        // Check for script tags
        if (hasScriptTags(schemaData)) {
            showScriptTagsModal();
            return;
        }
        
        try {
            var parsed = JSON.parse(schemaData);
            $button.html(t('valid', '✅ Valid')).css('background', 'var(--baseo-success)').css('color', 'white');
            showNotification(t('json_valid', '✅ JSON valid - Ready to save'), 'success');
            setTimeout(function() {
                $button.html(t('validate', '✔ Validate')).css('background', '').css('color', '');
            }, 2000);
        } catch (e) {
            $button.html(t('error', '❌ Error')).css('background', 'var(--baseo-error)').css('color', 'white');
            showNotification(t('json_invalid', '⌛ Invalid JSON: Please check the syntax.') + ': ' + e.message, 'error');
            setTimeout(function() {
                $button.html(t('validate', '✔ Validate')).css('background', '').css('color', '');
            }, 3000);
        }
    });
    
    // Format JSON
    $('#baseo-format-json').on('click', function() {
        var schemaData = $('#baseo-schema-data').val();
        try {
            var formatted = JSON.stringify(JSON.parse(schemaData), null, 2);
            $('#baseo-schema-data').val(formatted);
            showNotification(t('json_formatted', '🎨 JSON formatted correctly'), 'success');
        } catch (e) {
            showNotification(t('json_cannot_format', '⌛ Cannot format: Invalid JSON'), 'error');
        }
    });
    
    // Clear textarea
    $('#baseo-clear-json').on('click', function() {
        if (confirm(t('clear_confirm', 'Are you sure you want to clear the content?'))) {
            $('#baseo-schema-data').val('');
            showNotification(t('content_cleared', '🗑️ Content cleared'), 'info');
        }
    });
    
    // Test with Google
    $('#baseo-test-schema').on('click', function() {
        var url = $('#baseo-url').val();
        if (!url) {
            showNotification(t('enter_url_first', '⚠️ Please enter a URL to test first'), 'warning');
            return;
        }
        window.open('https://search.google.com/test/rich-results?url=' + encodeURIComponent(url), '_blank');
    });
    
    // Delete schema
    $(document).on('click', '.baseo-delete-schema', function(e) {
        e.preventDefault();
        
        if (!confirm(t('confirm_delete', 'Are you sure you want to delete this schema?'))) {
            return;
        }
        
        var schemaId = $(this).data('id');
        var $item = $(this).closest('.baseo-schema-item');
        
        $.ajax({
            url: baseo_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'baseo_delete_schema',
                nonce: baseo_ajax.nonce,
                id: schemaId
            },
            success: function(response) {
                if (response.success) {
                    $item.fadeOut(300, function() {
                        $(this).remove();
                        loadSchemas(currentPage, false);
                    });
                    showNotification(t('schema_deleted', '🗑️ Schema deleted successfully'), 'success');
                } else {
                    showNotification(t('error_deleting', '⌛ Error deleting schema'), 'error');
                }
            }
        });
    });
    
    // Edit schema
    $(document).on('click', '.baseo-edit-schema', function() {
        var schemaId = $(this).data('id');
        
        $.ajax({
            url: baseo_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'baseo_get_single_schema',
                nonce: baseo_ajax.nonce,
                id: schemaId
            },
            success: function(response) {
                if (response.success) {
                    var schema = response.data;
                    
                    // Fill form with schema data
                    $('#baseo-schema-name').val(schema.schema_name);
                    $('#baseo-url').val(schema.url);
                    $('#baseo-schema-type').val(schema.schema_type);
                    $('#baseo-schema-data').val(schema.schema_data);
                    
                    // Change form to edit mode
                    $('#baseo-schema-form').attr('data-edit-id', schemaId);
                    $('#baseo-schema-form button[type="submit"]').html(t('update_schema', '🔄 Update Schema')).removeClass('baseo-btn-primary').addClass('baseo-btn-warning');
                    
                    // Add cancel button
                    if (!$('#baseo-cancel-edit').length) {
                        $('#baseo-schema-form .baseo-form-actions').append('<button type="button" id="baseo-cancel-edit" class="baseo-btn baseo-btn-secondary">' + t('cancel', '❌ Cancel') + '</button>');
                    }
                    
                    // Scroll to form
                    $('html, body').animate({
                        scrollTop: $('#baseo-schema-form').offset().top - 100
                    }, 500);
                    
                    $('#baseo-schema-name').focus();
                    
                    showNotification(t('editing', '✏️ Editing') + ' "' + schema.schema_name + '"', 'info');
                } else {
                    showNotification(t('error_loading', '⌛ Error loading schema data'), 'error');
                }
            },
            error: function() {
                showNotification(t('connection_error', '⌛ Connection error'), 'error');
            }
        });
    });
    
    // Cancel edit
    $(document).on('click', '#baseo-cancel-edit', function() {
        resetForm();
        showNotification(t('edit_cancelled', '⌛ Edit cancelled'), 'info');
    });
    
    // Toggle schema status
    $(document).on('click', '.baseo-toggle-schema', function() {
        var schemaId = $(this).data('id');
        var isActive = $(this).data('active') == '1';
        var newStatus = isActive ? '0' : '1';
        
        $.ajax({
            url: baseo_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'baseo_toggle_schema',
                nonce: baseo_ajax.nonce,
                id: schemaId,
                active: newStatus
            },
            success: function(response) {
                if (response.success) {
                    showNotification(isActive ? t('schema_deactivated', '⏸️ Schema deactivated') : t('schema_activated', '▶️ Schema activated'), 'success');
                    loadSchemas(currentPage, false);
                }
            }
        });
    });
    
    // URL actions
    $(document).on('click', '.baseo-add-to-url', function(e) {
        e.stopPropagation();
        var url = $(this).data('url');
        $('#baseo-url').val(url);
        $('#baseo-schema-name').focus();
        showNotification(t('url_prefilled', '🔍 URL pre-filled, now add the schema name'), 'info');
    });
    
    $(document).on('click', '.baseo-visit-url', function(e) {
        e.stopPropagation();
        var url = $(this).data('url');
        
        if (!url) {
            showNotification(t('invalid_url', '⚠️ Invalid URL'), 'warning');
            return;
        }
        
        try {
            window.open(url, '_blank');
            showNotification(t('opening_url', '🔗 Opening URL in new tab'), 'info');
        } catch (error) {
            showNotification(t('error_prefix', '⌛ Error: ') + error.message, 'error');
            console.error('Error opening URL:', error);
        }
    });
    
    // Schema preview toggle
    $(document).on('click', '.baseo-schema-name', function() {
        $(this).closest('.baseo-schema-item').find('.baseo-schema-preview-container').slideToggle(300);
    });
    
    // Test schema
    $(document).on('click', '.baseo-test-schema', function() {
        var url = $(this).data('url');
        window.open('https://search.google.com/test/rich-results?url=' + encodeURIComponent(url), '_blank');
    });
    
    // Copy schema
    $(document).on('click', '.baseo-copy-schema', function() {
        var schemaData = $(this).data('schema');
        if (navigator.clipboard) {
            navigator.clipboard.writeText(schemaData).then(function() {
                showNotification(t('schema_copied', '📋 Schema copied to clipboard'), 'success');
            });
        } else {
            var textArea = document.createElement('textarea');
            textArea.value = schemaData;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            showNotification(t('schema_copied', '📋 Schema copied to clipboard'), 'success');
        }
    });
    
    // Helper Functions
    
    // Function to get schema icons
    function getSchemaIcon(type) {
        var icons = {
            'WebPage': '📄',
            'Article': '📝',
            'Product': '🛍️',
            'Organization': '🏢',
            'LocalBusiness': '🏪',
            'Person': '👤',
            'Event': '🎉',
            'Recipe': '🍳',
            'Review': '⭐',
            'FAQ': '❓'
        };
        return icons[type] || '📋';
    }
    
    // Function to format date
    function formatDate(dateString) {
        var date = new Date(dateString);
        return date.toLocaleDateString(undefined, {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
    
    // Function to show notifications
    function showNotification(message, type) {
        var className = 'baseo-notification baseo-' + type;
        var $notification = $('<div class="' + className + '">' + message + '</div>');
        
        $('body').append($notification);
        
        setTimeout(function() {
            $notification.addClass('show');
        }, 100);
        
        setTimeout(function() {
            $notification.removeClass('show');
            setTimeout(function() {
                $notification.remove();
            }, 300);
        }, 4000);
    }
    
    // Function to validate domain
    function isValidDomain(url) {
        try {
            var urlObj = new URL(url);
            var siteUrlObj = new URL(baseo_ajax.site_url);
            return urlObj.hostname === siteUrlObj.hostname;
        } catch (e) {
            return false;
        }
    }
    
    // Function to detect script tags
    function hasScriptTags(text) {
        return /<script[\s\S]*?>[\s\S]*?<\/script>/i.test(text) || /<script[\s\S]*?>/i.test(text);
    }
    
    // Function to show script tags modal
    function showScriptTagsModal() {
        var modal = $('<div class="baseo-script-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 999999; display: flex; align-items: center; justify-content: center;">' +
            '<div style="background: white; padding: 40px; border-radius: 16px; max-width: 500px; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.3);">' +
            '<h3 style="color: #e74c3c; margin-bottom: 20px;">' + t('script_tags_title', '🚨 Script Tags Detected') + '</h3>' +
            '<p style="margin-bottom: 30px; line-height: 1.6;">' + t('script_tags_detected', '🚨 Script tags detected! Please remove <script> tags from your JSON-LD code. Only paste the JSON content.') + '</p>' +
            '<button class="baseo-btn baseo-btn-primary" onclick="$(\'.baseo-script-modal\').remove()">' + t('got_it', 'Got it!') + '</button>' +
            '</div>' +
            '</div>');
        
        $('body').append(modal);
        
        modal.on('click', function(e) {
            if (e.target === this) {
                $(this).remove();
            }
        });
    }
    
    // Reset form function
    function resetForm() {
        $('#baseo-schema-form')[0].reset();
        $('#baseo-schema-form').removeAttr('data-edit-id');
        $('#baseo-schema-form button[type="submit"]').html(t('save_schema', '💾 Save Schema')).removeClass('baseo-btn-warning').addClass('baseo-btn-primary');
        $('#baseo-cancel-edit').remove();
    }
    
    // Global function for URL group toggle
    window.baseoToggleUrlGroup = function(header) {
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
});

// Meta Box JavaScript
jQuery(document).ready(function($) {
    // Validate JSON in meta box
    $('#baseo-validate-meta-json').on('click', function() {
        var schemaData = $('#baseo_meta_schema_data').val();
        var $button = $(this);
        
        if (!schemaData.trim()) {
            alert(t('schema_field_empty', '⚠️ Schema field is empty'));
            return;
        }
        
        try {
            JSON.parse(schemaData);
            $button.text(t('valid', '✅ Valid')).css('color', '#27ae60');
            setTimeout(function() {
                $button.text(t('validate_json', '✔ Validate JSON')).css('color', '');
            }, 2000);
        } catch (e) {
            alert(t('invalid_json_prefix', '❌ Invalid JSON: ') + e.message);
            $button.text(t('error', '❌ Error')).css('color', '#e74c3c');
            setTimeout(function() {
                $button.text(t('validate_json', '✔ Validate JSON')).css('color', '');
            }, 3000);
        }
    });
    
    // Format JSON in meta box
    $('#baseo-format-meta-json').on('click', function() {
        var schemaData = $('#baseo_meta_schema_data').val();
        try {
            var formatted = JSON.stringify(JSON.parse(schemaData), null, 2);
            $('#baseo_meta_schema_data').val(formatted);
        } catch (e) {
            alert(t('json_cannot_format', '⌛ Cannot format: Invalid JSON'));
        }
    });
});

// Frontend Editor JavaScript (mantener funcionalidad actual)
var baseoEditorOpen = false;

function baseoToggleSchemaEditor() {
    var editor = document.getElementById('baseo-frontend-editor');
    if (baseoEditorOpen) {
        editor.style.display = 'none';
        baseoEditorOpen = false;
    } else {
        editor.style.display = 'block';
        baseoEditorOpen = true;
    }
}

// Footer branding console log
if (typeof baseo_ajax !== 'undefined' && baseo_ajax.is_plugin_page) {
    console.log('🚀 Custom Schema by ' + baseo_ajax.brand_name + ' v' + baseo_ajax.version + ' - Boosting your SEO!');
}