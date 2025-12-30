/**
 * Custom Schema by BASEO - Main Initialization & Tabs
 * Version: 1.1.0
 * Depends on: ALL previous modules
 */

jQuery(document).ready(function($) {
    'use strict';
    
    var t = window.BASEO.t;
    
    /**
     * Switch between tabs
     */
    window.BASEO.switchTab = function(tabName) {
    console.log('[BASEO] Switching to tab:', tabName);
    
    // Update state
    window.BASEO.state.currentTab = tabName;
    
    // PASO 1: Remover todas las clases active
    $('.baseo-tab-btn').removeClass('active');
    $('.baseo-tab-content').removeClass('active').hide();
    
    // PASO 2: Agregar active solo al tab correcto
    $('.baseo-tab-btn[data-tab="' + tabName + '"]').addClass('active');
    $('#baseo-tab-' + tabName).addClass('active').show();
    
    // PASO 3: Forzar estilos inline (redundancia para asegurar)
    $('.baseo-tab-btn').css({
        'color': '#6c757d',
        'background': 'transparent',
        'border-bottom-color': 'transparent'
    });
    
    $('.baseo-tab-btn[data-tab="' + tabName + '"]').css({
        'color': 'var(--baseo-primary)',
        'background': 'linear-gradient(180deg, rgba(255, 107, 53, 0.08) 0%, rgba(255, 107, 53, 0.12) 100%)',
        'border-bottom-color': 'var(--baseo-primary)'
    });
    
    // PASO 4: Asegurar visibilidad correcta
    $('#baseo-tab-' + tabName).css('display', 'block');
    $('.baseo-tab-content').not('#baseo-tab-' + tabName).css('display', 'none');
    
    // PASO 5: Load bulk URLs if switching to bulk tab
    if (tabName === 'bulk' && window.BASEO.bulkState.allUrls.length === 0) {
        console.log('[BASEO] Loading bulk URLs...');
        window.BASEO.loadBulkUrls();
    }
    
    console.log('[BASEO] Tab switched successfully to:', tabName);
};
    
    /**
     * Get Single Schema Panel HTML
     */
    function getSingleSchemaPanel() {
        var html = '<div class="baseo-panel baseo-add-schema">';
        html += '<div class="baseo-panel-header">';
        html += '<h2>' + t('add_new_schema', '➕ Add New Schema') + '</h2>';
        html += '</div>';
        
        html += '<form id="baseo-schema-form" class="baseo-form">';
        
        // Schema Name
        html += '<div class="baseo-form-group">';
        html += '<label for="baseo-schema-name" class="baseo-label">✏️ ' + t('schema_name', 'Schema Name') + ' <span class="baseo-required">*</span></label>';
        html += '<input type="text" id="baseo-schema-name" name="schema_name" class="baseo-input" required />';
        html += '</div>';
        
        // URL
        html += '<div class="baseo-form-group">';
        html += '<label for="baseo-url" class="baseo-label">🔗 ' + t('page_url', 'Page URL') + ' <span class="baseo-required">*</span></label>';
        html += '<input type="text" id="baseo-url" name="url" class="baseo-input" placeholder="' + baseo_ajax.site_url + '/your-page" required />';
        html += '</div>';
        
        // Meta Title
        html += '<div class="baseo-form-group">';
        html += '<label for="baseo-meta-title" class="baseo-label">📄 ' + t('meta_title', 'Meta Title') + ' <span class="baseo-optional">(Optional)</span></label>';
        html += '<input type="text" id="baseo-meta-title" name="meta_title" class="baseo-input" maxlength="70" placeholder="(No meta title set - Will use post title as fallback)" />';
        html += '<div class="baseo-char-counter baseo-counter-optimal" id="baseo-meta-title-counter">';
        html += '<span class="baseo-char-count">0</span>/<span class="baseo-char-limit">70</span> - <span class="baseo-char-remaining">70 ' + t('chars_left', 'characters left') + '</span>';
        html += '</div>';
        html += '</div>';
        
        // Meta Description
        html += '<div class="baseo-form-group">';
        html += '<label for="baseo-meta-description" class="baseo-label">📝 ' + t('meta_description', 'Meta Description') + ' <span class="baseo-optional">(Optional)</span></label>';
        html += '<textarea id="baseo-meta-description" name="meta_description" rows="3" class="baseo-input" maxlength="160" placeholder="(No meta description set - Will use post excerpt as fallback)"></textarea>';
        html += '<div class="baseo-char-counter baseo-counter-optimal" id="baseo-meta-description-counter">';
        html += '<span class="baseo-char-count">0</span>/<span class="baseo-char-limit">160</span> - <span class="baseo-char-remaining">160 ' + t('chars_left', 'characters left') + '</span>';
        html += '</div>';
        html += '</div>';
        
        // Schema Type
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
        
        // JSON-LD Editor
        html += '<div class="baseo-form-group">';
        html += '<label for="baseo-schema-data" class="baseo-label">📊 ' + t('json_ld_code', 'JSON-LD Code') + ' <span class="baseo-required">*</span></label>';
        html += '<div class="baseo-editor-wrapper">';
        html += '<div id="baseo-schema-editor" class="baseo-schema-editor" contenteditable="true"></div>';
        html += '<textarea id="baseo-schema-data" name="schema_data" style="display:none;" required></textarea>';
        html += '<div class="baseo-textarea-tools">';
        html += '<button type="button" id="baseo-validate-json" class="baseo-btn-small">' + t('validate', '✓ Validate') + '</button>';
        html += '<button type="button" id="baseo-format-json" class="baseo-btn-small">' + t('format', '🎨 Format') + '</button>';
        html += '<button type="button" id="baseo-clear-json" class="baseo-btn-small">' + t('clear', '🗑️ Clear') + '</button>';
        html += '<button type="button" id="baseo-insert-variables" class="baseo-btn-small baseo-btn-variables">' + t('insert_variables', '📖 Insert Variables') + '</button>';
        html += '</div>';
        html += '<div id="baseo-variables-dropdown" class="baseo-variables-dropdown" style="display: none;">';
        html += '<div class="baseo-variable-item" data-variable="{{meta_title}}">';
        html += '<span class="baseo-variable-code">{{meta_title}}</span>';
        html += '<span class="baseo-variable-desc">' + t('click_to_copy', 'Click to copy') + '</span>';
        html += '</div>';
        html += '<div class="baseo-variable-item" data-variable="{{meta_description}}">';
        html += '<span class="baseo-variable-code">{{meta_description}}</span>';
        html += '<span class="baseo-variable-desc">' + t('click_to_copy', 'Click to copy') + '</span>';
        html += '</div>';
        html += '</div>';
        html += '</div>';
        html += '</div>';
        
        // Form actions
        html += '<div class="baseo-form-actions">';
        html += '<button type="submit" class="baseo-btn baseo-btn-primary">' + t('save_schema', '💾 Save Schema') + '</button>';
        html += '<button type="button" id="baseo-test-schema" class="baseo-btn baseo-btn-secondary">' + t('test_with_google', '🧪 Test with Google') + '</button>';
        html += '</div>';
        
        html += '</form>';
        html += '</div>';
        
        return html;
    }
    
    /**
     * Get Bulk Schema Panel HTML
     */
    function getBulkSchemaPanel() {
        var html = '<div class="baseo-panel baseo-bulk-schema">';
        html += '<div class="baseo-panel-header">';
        html += '<h2>' + t('bulk_schema', '📦 Apply Schema to Multiple URLs') + '</h2>';
        html += '<p>' + t('bulk_description', 'Select multiple pages and apply the same schema to all') + '</p>';
        html += '</div>';
        
        html += '<div class="baseo-content-grid" style="grid-template-columns: 1fr 1fr; gap: 30px;">';
        
        // LEFT: Form
        html += '<div>';
        html += '<form id="baseo-bulk-schema-form" class="baseo-form">';
        
        // Schema Name
        html += '<div class="baseo-form-group">';
        html += '<label for="baseo-bulk-schema-name" class="baseo-label">✏️ ' + t('schema_name', 'Schema Name') + ' <span class="baseo-required">*</span></label>';
        html += '<input type="text" id="baseo-bulk-schema-name" name="schema_name" class="baseo-input" required />';
        html += '</div>';
        
        // Meta Title
        html += '<div class="baseo-form-group">';
        html += '<label for="baseo-bulk-meta-title" class="baseo-label">📄 ' + t('meta_title', 'Meta Title') + ' <span class="baseo-optional">(Optional)</span></label>';
        html += '<input type="text" id="baseo-bulk-meta-title" name="meta_title" class="baseo-input" maxlength="70" placeholder="(Will apply to all selected URLs)" />';
        html += '<div class="baseo-char-counter baseo-counter-optimal" id="baseo-bulk-meta-title-counter">';
        html += '<span class="baseo-char-count">0</span>/<span class="baseo-char-limit">70</span> - <span class="baseo-char-remaining">70 ' + t('chars_left', 'characters left') + '</span>';
        html += '</div>';
        html += '</div>';

        // Meta Description
        html += '<div class="baseo-form-group">';
        html += '<label for="baseo-bulk-meta-description" class="baseo-label">📝 ' + t('meta_description', 'Meta Description') + ' <span class="baseo-optional">(Optional)</span></label>';
        html += '<textarea id="baseo-bulk-meta-description" name="meta_description" rows="3" class="baseo-input" maxlength="160" placeholder="(Will apply to all selected URLs)"></textarea>';
        html += '<div class="baseo-char-counter baseo-counter-optimal" id="baseo-bulk-meta-description-counter">';
        html += '<span class="baseo-char-count">0</span>/<span class="baseo-char-limit">160</span> - <span class="baseo-char-remaining">160 ' + t('chars_left', 'characters left') + '</span>';
        html += '</div>';
        html += '</div>';
        
        // Schema Type
        html += '<div class="baseo-form-group">';
        html += '<label for="baseo-bulk-schema-type" class="baseo-label">📋 ' + t('schema_type', 'Schema Type') + '</label>';
        html += '<select id="baseo-bulk-schema-type" name="schema_type" class="baseo-select">';
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
        
        // JSON-LD Editor
        html += '<div class="baseo-form-group">';
        html += '<label for="baseo-bulk-schema-data" class="baseo-label">📊 ' + t('json_ld_code', 'JSON-LD Code') + ' <span class="baseo-required">*</span></label>';
        html += '<div class="baseo-editor-wrapper">';
        html += '<div id="baseo-bulk-schema-editor" class="baseo-schema-editor" contenteditable="true"></div>';
        html += '<textarea id="baseo-bulk-schema-data" name="schema_data" style="display:none;" required></textarea>';
        html += '<div class="baseo-textarea-tools">';
        html += '<button type="button" id="baseo-bulk-validate-json" class="baseo-btn-small">' + t('validate', '✓ Validate') + '</button>';
        html += '<button type="button" id="baseo-bulk-format-json" class="baseo-btn-small">' + t('format', '🎨 Format') + '</button>';
        html += '<button type="button" id="baseo-bulk-clear-json" class="baseo-btn-small">' + t('clear', '🗑️ Clear') + '</button>';
        html += '<button type="button" id="baseo-bulk-insert-variables" class="baseo-btn-small baseo-btn-variables">' + t('insert_variables', '📖 Variables') + '</button>';
        html += '</div>';
        html += '<div id="baseo-bulk-variables-dropdown" class="baseo-variables-dropdown" style="display: none;">';
        html += '<div class="baseo-variable-item" data-variable="{{meta_title}}">';
        html += '<span class="baseo-variable-code">{{meta_title}}</span>';
        html += '<span class="baseo-variable-desc">' + t('click_to_copy', 'Click to copy') + '</span>';
        html += '</div>';
        html += '<div class="baseo-variable-item" data-variable="{{meta_description}}">';
        html += '<span class="baseo-variable-code">{{meta_description}}</span>';
        html += '<span class="baseo-variable-desc">' + t('click_to_copy', 'Click to copy') + '</span>';
        html += '</div>';
        html += '</div>';
        html += '</div>';
        html += '</div>';
        
        html += '</form>';
        html += '</div>';
        
        // RIGHT: URL Selector
            html += '<div>';
            html += '<div class="baseo-panel-header">';
            html += '<h3>📂 ' + t('select_urls', 'Select URLs') + ' <span id="baseo-bulk-url-count">0</span></h3>';

            // Filter row
            html += '<div class="baseo-bulk-filter-row">';
            html += '<select id="baseo-bulk-filter-type" class="baseo-select">';
            html += '<option value="all">' + t('all_types', 'All Types') + '</option>';
            html += '<option value="page">📄 ' + t('pages', 'Pages') + '</option>';
            html += '<option value="post">📝 ' + t('posts', 'Posts') + '</option>';
            html += '<option value="product">🛍️ ' + t('products', 'Products') + '</option>';
            html += '</select>';
            html += '</div>';

            // Action buttons
            html += '<div class="baseo-bulk-actions">';
            html += '<button type="button" id="baseo-select-all-urls" class="baseo-btn-small">☑️ ' + t('select_all', 'Select All') + '</button>';
            html += '<button type="button" id="baseo-unselect-all-urls" class="baseo-btn-small">☐ ' + t('unselect_all', 'Unselect All') + '</button>';
            html += '</div>';

            // Status text
            html += '<div id="baseo-bulk-urls-loaded"></div>';
            html += '</div>';

            // URL Selector
            html += '<div id="baseo-url-selector" class="baseo-url-selector"></div>';

            // Apply button
            html += '<button type="submit" id="baseo-bulk-apply-btn" form="baseo-bulk-schema-form" class="baseo-btn baseo-btn-warning" style="width: 100%; margin-top: 20px;" disabled>';
            html += '⚡ ' + t('apply_bulk_schema', 'Apply Bulk Schema') + ' (<span id="baseo-bulk-apply-count">0</span> URLs)';
            html += '</button>';

            html += '</div>';

            html += '</div>'; // content-grid
            html += '</div>'; // panel

            return html;
    }
    
    /**
     * Get Schemas List Panel HTML
     */
    function getSchemasList() {
        var html = '<div class="baseo-panel baseo-schemas-list">';
        html += '<div class="baseo-panel-header">';
        html += '<div style="display: flex; justify-content: space-between; align-items: center; gap: 20px;">';
        html += '<h2 style="margin: 0;">' + t('configured_schemas', '📋 Configured Schemas') + '</h2>';
        html += '<input type="text" id="baseo-search-schemas" class="baseo-input" style="width: 320px; margin: 0; padding: 12px 20px;" placeholder="🔍 ' + t('search_schemas', 'Search schemas...') + '" />';
        html += '</div>';
        html += '</div>';
        
        // Filters
        html += '<div class="baseo-filters-row">';
        html += '<label for="baseo-filter-type" style="margin-right: 10px; font-weight: 600;">' + t('filter_by_type', 'Filter by type:') + '</label>';
        html += '<select id="baseo-filter-type" class="baseo-select" style="width: 200px;">';
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
        
        html += '<label for="baseo-filter-perpage" style="margin-left: 20px; margin-right: 10px; font-weight: 600;">' + t('items_per_page', 'Items per page:') + '</label>';
        html += '<select id="baseo-filter-perpage" class="baseo-select" style="width: 100px;">';
        html += '<option value="10">10</option>';
        html += '<option value="20" selected>20</option>';
        html += '<option value="50">50</option>';
        html += '<option value="100">100</option>';
        html += '</select>';
        html += '</div>';
        
        html += '<div id="baseo-schemas-container" class="baseo-schemas-container">';
        html += '<div class="baseo-loading"><div class="baseo-spinner"></div><p>' + t('loading_schemas', 'Loading schemas...') + '</p></div>';
        html += '</div>';
        
        html += '</div>';
        
        return html;
    }
    
    /**
     * Render Admin UI
     */
    window.BASEO.renderAdminUI = function(skipInitialLoad = false) {
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
        
        // Tabs
        html += '<div class="baseo-tabs">';
        html += '<button class="baseo-tab-btn active" data-tab="single">➕ ' + t('add_single_schema', 'Add Single Schema') + '</button>';
        html += '<button class="baseo-tab-btn" data-tab="bulk">📦 ' + t('bulk_upload', 'Bulk Schema') + '</button>';
        html += '</div>';
        
        // Tab Contents
        html += '<div id="baseo-tab-single" class="baseo-tab-content active">';
        html += '<div class="baseo-content-grid">';
        html += getSingleSchemaPanel();
        html += getSchemasList();
        html += '</div>';
        html += '</div>';
        html += '<div id="baseo-tab-bulk" class="baseo-tab-content" style="display: none !important;">';
        html += getBulkSchemaPanel();
        html += '</div>';
        
        html += '</div>'; // main-content
        
        container.html(html);

        // Inicializar estado de tabs EXPLÍCITAMENTE
setTimeout(function() {
    console.log('[BASEO] Initializing tab state...');
    
    // Forzar estado inicial: Single activo, Bulk inactivo
    $('.baseo-tab-btn').removeClass('active');
    $('.baseo-tab-content').removeClass('active').hide();
    
    $('.baseo-tab-btn[data-tab="single"]').addClass('active');
    $('#baseo-tab-single').addClass('active').show();
    
    // Aplicar estilos inline para asegurar
    $('.baseo-tab-btn[data-tab="single"]').css({
        'color': 'var(--baseo-primary)',
        'background': 'linear-gradient(180deg, rgba(255, 107, 53, 0.08) 0%, rgba(255, 107, 53, 0.12) 100%)',
        'border-bottom-color': 'var(--baseo-primary)',
        'font-weight': '700'
    });
    
    $('.baseo-tab-btn[data-tab="bulk"]').css({
        'color': '#6c757d',
        'background': 'transparent',
        'border-bottom-color': 'transparent',
        'font-weight': '600'
    });
    
    $('#baseo-tab-single').css('display', 'block');
    $('#baseo-tab-bulk').css('display', 'none');
    
    console.log('[BASEO] Tab state initialized. Single should be active.');
    console.log('[BASEO] Verification:');
    console.log('  Single btn active?', $('.baseo-tab-btn[data-tab="single"]').hasClass('active'));
    console.log('  Single content display:', $('#baseo-tab-single').css('display'));
    console.log('  Bulk btn active?', $('.baseo-tab-btn[data-tab="bulk"]').hasClass('active'));
    console.log('  Bulk content display:', $('#baseo-tab-bulk').css('display'));
}, 200);
        
        // Initialize character counters
        $('#baseo-meta-title, #baseo-bulk-meta-title').on('input', function() {
            var counterId = $(this).attr('id') + '-counter';
            window.BASEO.updateCharCounter(this, counterId, 70);
        });
        
        $('#baseo-meta-description, #baseo-bulk-meta-description').on('input', function() {
            var counterId = $(this).attr('id') + '-counter';
            window.BASEO.updateCharCounter(this, counterId, 160);
        });
        
        // Read URL parameters
        var urlSchemaType = window.BASEO.getUrlParam('schema_type');
        var urlPage = parseInt(window.BASEO.getUrlParam('page')) || 1;
        var urlPerPage = parseInt(window.BASEO.getUrlParam('per_page')) || 20;
        var urlSearch = window.BASEO.getUrlParam('search');
        
        // Validate and apply
        urlPerPage = Math.min(100, Math.max(1, urlPerPage));
        
        var allowedTypes = [];
        $('#baseo-filter-type option').each(function() {
            var value = $(this).val();
            if (value) allowedTypes.push(value);
        });
        
        if (urlSchemaType && !allowedTypes.includes(urlSchemaType)) {
            urlSchemaType = '';
        }
        
        if (urlSchemaType) {
            $('#baseo-filter-type').val(urlSchemaType);
            window.BASEO.state.currentSchemaType = urlSchemaType;
        }
        $('#baseo-filter-perpage').val(urlPerPage);
        window.BASEO.state.currentPerPage = urlPerPage;
        window.BASEO.state.currentPage = urlPage;
        
        if (urlSearch) {
            window.BASEO.state.currentSearchQuery = urlSearch;
            $('#baseo-search-schemas').val(urlSearch);
        }
        
        // Initialize editors
        window.BASEO.initContentEditableEditor('baseo-schema-editor', 'baseo-schema-data');
        window.BASEO.initContentEditableEditor('baseo-bulk-schema-editor', 'baseo-bulk-schema-data');
        
        // Load schemas
        if (!skipInitialLoad) {
            window.BASEO.loadSchemas(window.BASEO.state.currentPage, false);
        }
    };
    
    // ======================
    // EVENT LISTENERS
    // ======================
    
    // Check if container exists
    if ($('#baseo-schema-app').length) {
        window.BASEO.renderAdminUI();
    }
    
// Tab switching - CON DEBUGGING
$(document).on('click', '.baseo-tab-btn', function(e) {
    e.preventDefault();
    e.stopPropagation();
    
    var tab = $(this).data('tab');
    console.log('[BASEO] Tab button clicked:', tab);
    console.log('[BASEO] Button element:', this);
    console.log('[BASEO] Current active tab:', window.BASEO.state.currentTab);
    
    // Verificar que el tab existe
    if (!$('#baseo-tab-' + tab).length) {
        console.error('[BASEO] Tab content not found for:', tab);
        return;
    }
    
    window.BASEO.switchTab(tab);
    
    // Verificar después del switch
    setTimeout(function() {
        console.log('[BASEO] After switch - Active classes:');
        $('.baseo-tab-btn').each(function() {
            console.log('  Button:', $(this).data('tab'), 'Active?', $(this).hasClass('active'));
        });
        console.log('[BASEO] After switch - Display states:');
        $('.baseo-tab-content').each(function() {
            console.log('  Content:', this.id, 'Display:', $(this).css('display'), 'Active?', $(this).hasClass('active'));
        });
    }, 100);
});

    // Single Schema Form Submit
    $(document).on('submit', '#baseo-schema-form', window.BASEO.handleSingleSchemaSubmit);
    
    // Bulk Schema Form Submit
    $(document).on('submit', '#baseo-bulk-schema-form', window.BASEO.handleBulkSchemaSubmit);
    
    // Single Schema JSON Tools
    $(document).on('click', '#baseo-validate-json', function() {
        window.BASEO.handleValidateJSON('baseo-schema-editor', 'baseo-validate-json');
    });
    
    $(document).on('click', '#baseo-format-json', function() {
        window.BASEO.handleFormatJSON('baseo-schema-editor', 'baseo-schema-data');
    });
    
    $(document).on('click', '#baseo-clear-json', function() {
        window.BASEO.handleClearJSON('baseo-schema-editor', 'baseo-schema-data');
    });
    
    // Bulk Schema JSON Tools
    $(document).on('click', '#baseo-bulk-validate-json', function() {
        window.BASEO.handleValidateJSON('baseo-bulk-schema-editor', 'baseo-bulk-validate-json');
    });
    
    $(document).on('click', '#baseo-bulk-format-json', function() {
        window.BASEO.handleFormatJSON('baseo-bulk-schema-editor', 'baseo-bulk-schema-data');
    });
    
    $(document).on('click', '#baseo-bulk-clear-json', function() {
        window.BASEO.handleClearJSON('baseo-bulk-schema-editor', 'baseo-bulk-schema-data');
    });
    
    // Variables dropdown (Single)
    $(document).on('click', '#baseo-insert-variables', function(e) {
        e.preventDefault();
        $('#baseo-variables-dropdown').toggle();
    });
    
    // Variables dropdown (Bulk)
    $(document).on('click', '#baseo-bulk-insert-variables', function(e) {
        e.preventDefault();
        $('#baseo-bulk-variables-dropdown').toggle();
    });
    
    // Variable item click
    $(document).on('click', '.baseo-variable-item', function() {
        var variable = $(this).data('variable');
        
        if (navigator.clipboard) {
            navigator.clipboard.writeText(variable).then(function() {
                window.BASEO.showNotification(t('variable_copied', '✅ Variable copied!'), 'success');
            });
        } else {
            var textArea = document.createElement('textarea');
            textArea.value = variable;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            window.BASEO.showNotification(t('variable_copied', '✅ Variable copied!'), 'success');
        }
        
        $('.baseo-variables-dropdown').hide();
    });
    
    // Close dropdowns when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#baseo-insert-variables, #baseo-variables-dropdown, #baseo-bulk-insert-variables, #baseo-bulk-variables-dropdown').length) {
            $('.baseo-variables-dropdown').hide();
        }
    });
    
    // Test with Google
    $(document).on('click', '#baseo-test-schema', function() {
        var url = $('#baseo-url').val();
        if (!url) {
            window.BASEO.showNotification(t('enter_url_first', '⚠️ Enter URL first'), 'warning');
            return;
        }
        window.open('https://search.google.com/test/rich-results?url=' + encodeURIComponent(url), '_blank');
    });
    
    // Filters
    $(document).on('change', '#baseo-filter-type', function() {
        var selectedType = $(this).val();
        window.BASEO.updateUrlParam('schema_type', selectedType);
        window.BASEO.loadSchemas(1, true);
    });
    
    $(document).on('change', '#baseo-filter-perpage', function() {
        window.BASEO.state.currentPerPage = parseInt($(this).val()) || 20;
        window.BASEO.updateUrlParam('per_page', window.BASEO.state.currentPerPage !== 20 ? window.BASEO.state.currentPerPage : null);
        window.BASEO.loadSchemas(1, true);
    });
    
    // Search with debounce
    $(document).on('input', '#baseo-search-schemas', function() {
        clearTimeout(window.BASEO.state.searchDebounceTimer);
        var searchValue = $(this).val().trim();
        window.BASEO.state.searchDebounceTimer = setTimeout(function() {
            window.BASEO.state.currentSearchQuery = searchValue;
            window.BASEO.updateUrlParam('search', searchValue || null);
            window.BASEO.loadSchemas(1, true);
        }, 500);
    });
    
    // Pagination
    $(document).on('click', '.baseo-page-btn', function() {
        var page = $(this).data('page');
        window.BASEO.loadSchemas(page, false);
    });
    
// Schema CRUD operations
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
                
                // Switch to Single tab
                window.BASEO.switchTab('single');
                
                // Fill form fields
                $('#baseo-schema-name').val(schema.schema_name);
                $('#baseo-url').val(schema.url);
                $('#baseo-schema-type').val(schema.schema_type);
                window.BASEO.setEditorContent('baseo-schema-editor', 'baseo-schema-data', schema.schema_data);
                
                // ==========================================
                // NUEVO: Cargar meta tags con placeholders
                // ==========================================
                
                // Meta Title
                var metaTitle = schema.meta_title || '';
                $('#baseo-meta-title').val(metaTitle);
                
                // Actualizar placeholder si está vacío
                if (!metaTitle) {
                    $('#baseo-meta-title').attr('placeholder', '(No meta title set for this URL)');
                } else {
                    $('#baseo-meta-title').attr('placeholder', '');
                }
                
                // Meta Description
                var metaDescription = schema.meta_description || '';
                $('#baseo-meta-description').val(metaDescription);
                
                // Actualizar placeholder si está vacío
                if (!metaDescription) {
                    $('#baseo-meta-description').attr('placeholder', '(No meta description set for this URL)');
                } else {
                    $('#baseo-meta-description').attr('placeholder', '');
                }
                
                // ==========================================
                // Force visible color in editor
                // ==========================================
                setTimeout(function() {
                    var $editor = $('#baseo-schema-editor');
                    var forceStyles = 'color: #2c3e50 !important; -webkit-text-fill-color: #2c3e50 !important; background: white !important;';
                    var currentStyle = $editor.attr('style') || '';
                    currentStyle = currentStyle
                        .replace(/color:[^;]+;?/gi, '')
                        .replace(/-webkit-text-fill-color:[^;]+;?/gi, '')
                        .replace(/background:[^;]+;?/gi, '');
                    $editor.attr('style', currentStyle + '; ' + forceStyles);
                }, 100);
                
                // ==========================================
                // Update character counters
                // ==========================================
                window.BASEO.updateCharCounter('#baseo-meta-title', 'baseo-meta-title-counter', 70);
                window.BASEO.updateCharCounter('#baseo-meta-description', 'baseo-meta-description-counter', 160);
                
                // ==========================================
                // Change to edit mode
                // ==========================================
                $('#baseo-schema-form').attr('data-edit-id', schemaId);
                $('#baseo-schema-form button[type="submit"]')
                    .html(t('update_schema', '🔄 Update Schema'))
                    .removeClass('baseo-btn-primary')
                    .addClass('baseo-btn-warning');
                
                // Add cancel button if not exists
                if (!$('#baseo-cancel-edit').length) {
                    $('#baseo-schema-form .baseo-form-actions').append(
                        '<button type="button" id="baseo-cancel-edit" class="baseo-btn baseo-btn-secondary">' + 
                        t('cancel', '❌ Cancel') + 
                        '</button>'
                    );
                }
                
                // Scroll to form
                $('html, body').animate({ 
                    scrollTop: $('#baseo-schema-form').offset().top - 100 
                }, 500);
                
                $('#baseo-schema-name').focus();
                
                window.BASEO.showNotification(
                    t('editing', '✏️ Editing') + ' "' + schema.schema_name + '"', 
                    'info'
                );
            } else {
                window.BASEO.showNotification(
                    t('error_loading', '❌ Error loading schema'), 
                    'error'
                );
            }
        },
        error: function() {
            window.BASEO.showNotification(
                t('connection_error', '❌ Connection error'), 
                'error'
            );
        }
    });
});
    
    $(document).on('click', '#baseo-cancel-edit', function() {
        window.BASEO.resetForm('baseo-schema-form');
        window.BASEO.showNotification(t('edit_cancelled', '❌ Cancelled'), 'info');
    });
    
    $(document).on('click', '.baseo-delete-schema', function(e) {
        e.preventDefault();
        
        if (!confirm(t('confirm_delete', 'Delete this schema?'))) {
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
                        window.BASEO.loadSchemas(window.BASEO.state.currentPage, false);
                    });
                    window.BASEO.showNotification(t('schema_deleted', '🗑️ Deleted'), 'success');
                }
            }
        });
    });
    
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
                    window.BASEO.showNotification(isActive ? t('schema_deactivated', '⏸️ Deactivated') : t('schema_activated', '▶️ Activated'), 'success');
                    window.BASEO.loadSchemas(window.BASEO.state.currentPage, false);
                }
            }
        });
    });
    
    $(document).on('click', '.baseo-add-to-url', function(e) {
        e.stopPropagation();
        var url = $(this).data('url');
        window.BASEO.switchTab('single');
        $('#baseo-url').val(url);
        $('#baseo-schema-name').focus();
        window.BASEO.showNotification(t('url_prefilled', '📝 URL pre-filled'), 'info');
    });
    
    $(document).on('click', '.baseo-visit-url', function(e) {
        e.stopPropagation();
        window.open($(this).data('url'), '_blank');
    });
    
    $(document).on('click', '.baseo-schema-name', function() {
        $(this).closest('.baseo-schema-item').find('.baseo-schema-preview-container').slideToggle(300);
    });
    
    $(document).on('click', '.baseo-test-schema', function() {
        var url = $(this).data('url');
        window.open('https://search.google.com/test/rich-results?url=' + encodeURIComponent(url), '_blank');
    });
    
    $(document).on('click', '.baseo-copy-schema', function() {
        var schemaData = $(this).data('schema');
        if (navigator.clipboard) {
            navigator.clipboard.writeText(schemaData).then(function() {
                window.BASEO.showNotification(t('schema_copied', '📋 Copied'), 'success');
            });
        }
    });
    
    // BULK: URL checkbox change
    $(document).on('change', '.baseo-url-checkbox', function() {
        var url = $(this).data('url');
        if ($(this).is(':checked')) {
            if (!window.BASEO.bulkState.selectedUrls.includes(url)) {
                window.BASEO.bulkState.selectedUrls.push(url);
            }
        } else {
            window.BASEO.bulkState.selectedUrls = window.BASEO.bulkState.selectedUrls.filter(u => u !== url);
        }
        window.BASEO.updateUrlCounter();
    });
    
    // BULK: Filter by type
    $(document).on('change', '#baseo-bulk-filter-type', function() {
        var type = $(this).val();
        window.BASEO.filterUrlsByType(type);
    });
    
    // BULK: Select/Unselect All
    $(document).on('click', '#baseo-select-all-urls', function() {
        $('.baseo-url-item:not(.hidden) .baseo-url-checkbox').prop('checked', true).each(function() {
            var url = $(this).data('url');
            if (!window.BASEO.bulkState.selectedUrls.includes(url)) {
                window.BASEO.bulkState.selectedUrls.push(url);
            }
        });
        window.BASEO.updateUrlCounter();
    });
    
    $(document).on('click', '#baseo-unselect-all-urls', function() {
        $('.baseo-url-item:not(.hidden) .baseo-url-checkbox').prop('checked', false).each(function() {
            var url = $(this).data('url');
            window.BASEO.bulkState.selectedUrls = window.BASEO.bulkState.selectedUrls.filter(u => u !== url);
        });
        window.BASEO.updateUrlCounter();
    });
    
    // Popstate handler
    if (!window.BASEO.state.popstateHandlerBound) {
        $(window).on('popstate', function() {
            if ($('#baseo-filter-type').length === 0) {
                window.BASEO.renderAdminUI(true);
                setTimeout(function() {
                    var urlSchemaType = window.BASEO.getUrlParam('schema_type');
                    var urlPage = parseInt(window.BASEO.getUrlParam('page')) || 1;
                    var urlPerPage = parseInt(window.BASEO.getUrlParam('per_page')) || 20;
                    var urlSearch = window.BASEO.getUrlParam('search');
                    
                    $('#baseo-filter-type').val(urlSchemaType || '');
                    $('#baseo-filter-perpage').val(urlPerPage);
                    $('#baseo-search-schemas').val(urlSearch || '');
                    window.BASEO.state.currentSchemaType = urlSchemaType || '';
                    window.BASEO.state.currentPage = urlPage;
                    window.BASEO.state.currentPerPage = urlPerPage;
                    window.BASEO.state.currentSearchQuery = urlSearch || '';
                    
                    window.BASEO.loadSchemas(window.BASEO.state.currentPage, false);
                }, 100);
                return;
            }
            
            var urlSchemaType = window.BASEO.getUrlParam('schema_type');
            var urlPage = parseInt(window.BASEO.getUrlParam('page')) || 1;
            var urlPerPage = parseInt(window.BASEO.getUrlParam('per_page')) || 20;
            var urlSearch = window.BASEO.getUrlParam('search');
            
            $('#baseo-filter-type').val(urlSchemaType || '');
            $('#baseo-filter-perpage').val(urlPerPage);
            $('#baseo-search-schemas').val(urlSearch || '');
            window.BASEO.state.currentSchemaType = urlSchemaType || '';
            window.BASEO.state.currentPage = urlPage;
            window.BASEO.state.currentPerPage = urlPerPage;
            window.BASEO.state.currentSearchQuery = urlSearch || '';
            
            window.BASEO.loadSchemas(window.BASEO.state.currentPage, false);
        });
        window.BASEO.state.popstateHandlerBound = true;
    }
    
    console.log('✅ BASEO Main loaded');
});

// Footer branding
if (typeof baseo_ajax !== 'undefined' && baseo_ajax.is_plugin_page) {
    console.log('🚀 Custom Schema by ' + baseo_ajax.brand_name + ' v' + baseo_ajax.version + ' - Bulk Upload Feature Active!');
}