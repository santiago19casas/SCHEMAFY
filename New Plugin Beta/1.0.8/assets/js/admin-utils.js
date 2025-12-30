/**
 * Custom Schema by BASEO - Utilities & Helpers
 * Version: 1.0.2
 * Author: BASEO Team
 */

// Global namespace
window.BASEO = window.BASEO || {};

// Global state
window.BASEO.state = {
    currentPage: 1,
    currentPerPage: 20,
    currentSchemaType: '',
    currentSearchQuery: '',
    searchDebounceTimer: null,
    popstateHandlerBound: false,
    currentTab: 'single' // 'single' or 'bulk'
};

/**
 * Translation helper function
 */
window.BASEO.t = function(key, fallback = '') {
    try {
        return (window.baseo_ajax && baseo_ajax.i18n && baseo_ajax.i18n[key]) || fallback;
    } catch(e) {
        return fallback;
    }
};

// Alias for convenience
var t = window.BASEO.t;

/**
 * URL parameter helpers
 */
window.BASEO.getUrlParam = function(param) {
    var urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(param);
};

window.BASEO.updateUrlParam = function(param, value) {
    var url = new URL(window.location);
    
    // NUNCA eliminar el parámetro 'page'
    if (param === 'page') {
        return;
    }
    
    if (value !== null && value !== undefined && value !== '' && value !== 1) {
        url.searchParams.set(param, value);
    } else {
        url.searchParams.delete(param);
    }
    
    // Asegurar que 'page' siempre esté presente
    if (!url.searchParams.has('page')) {
        url.searchParams.set('page', 'baseo-custom-schema');
    }
    
    window.history.replaceState({}, '', url);
};

/**
 * Build schemas REST API URL
 */
window.BASEO.buildSchemasUrl = function(params = {}) {
    var restBase = (baseo_ajax.rest_base || '').replace(/\/$/, '');
    var page = params.page || window.BASEO.state.currentPage || 1;
    var perPage = params.per_page || window.BASEO.state.currentPerPage || 20;
    var schemaType = params.schema_type !== undefined ? params.schema_type : window.BASEO.state.currentSchemaType;
    
    var url = restBase + '/schemas?page=' + page + '&per_page=' + perPage;
    
    if (schemaType) {
        url += '&schema_type=' + encodeURIComponent(schemaType);
    }
    
    if (window.BASEO.state.currentSearchQuery) {
        url += '&search=' + encodeURIComponent(window.BASEO.state.currentSearchQuery);
    }
    
    return url;
};

/**
 * Character counter for input/textarea
 */
window.BASEO.updateCharCounter = function(input, counterId, maxChars) {
    var $input = jQuery(input);
    var $counter = jQuery('#' + counterId);
    var currentLength = $input.val().length;
    var remaining = maxChars - currentLength;
    
    // Update counter text
    $counter.find('.baseo-char-count').text(currentLength);
    $counter.find('.baseo-char-limit').text(maxChars);
    $counter.find('.baseo-char-remaining').text(remaining + ' ' + t('chars_left', 'characters left'));
    
    // Update color based on length
    $counter.removeClass('baseo-counter-optimal baseo-counter-warning baseo-counter-danger');
    
    if (currentLength > maxChars) {
        $counter.addClass('baseo-counter-danger');
    } else if (currentLength > maxChars - 10) {
        $counter.addClass('baseo-counter-warning');
    } else {
        $counter.addClass('baseo-counter-optimal');
    }
};

/**
 * Show notification toast
 */
window.BASEO.showNotification = function(message, type) {
    var className = 'baseo-notification baseo-' + type;
    var $notification = jQuery('<div class="' + className + '">' + message + '</div>');
    
    jQuery('body').append($notification);
    
    setTimeout(function() {
        $notification.addClass('show');
    }, 100);
    
    setTimeout(function() {
        $notification.removeClass('show');
        setTimeout(function() {
            $notification.remove();
        }, 300);
    }, 4000);
};

/**
 * Validate if URL is from same domain
 */
window.BASEO.isValidDomain = function(url) {
    try {
        var urlObj = new URL(url);
        var siteUrlObj = new URL(baseo_ajax.site_url);
        return urlObj.hostname === siteUrlObj.hostname;
    } catch (e) {
        return false;
    }
};

/**
 * Detect script tags in text
 */
window.BASEO.hasScriptTags = function(text) {
    return /<script[\s\S]*?>[\s\S]*?<\/script>/i.test(text) || /<script[\s\S]*?>/i.test(text);
};

/**
 * Show script tags warning modal
 */
window.BASEO.showScriptTagsModal = function() {
    var modal = jQuery('<div class="baseo-script-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 999999; display: flex; align-items: center; justify-content: center;">' +
        '<div style="background: white; padding: 40px; border-radius: 16px; max-width: 500px; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.3);">' +
        '<h3 style="color: #e74c3c; margin-bottom: 20px;">' + t('script_tags_title', '🚨 Script Tags Detected') + '</h3>' +
        '<p style="margin-bottom: 30px; line-height: 1.6;">' + t('script_tags_detected', '🚨 Script tags detected! Please remove <script> tags from your JSON-LD code. Only paste the JSON content.') + '</p>' +
        '<button class="baseo-btn baseo-btn-primary" onclick="jQuery(\'.baseo-script-modal\').remove()">' + t('got_it', 'Got it!') + '</button>' +
        '</div>' +
        '</div>');
    
    jQuery('body').append(modal);
    
    modal.on('click', function(e) {
        if (e.target === this) {
            jQuery(this).remove();
        }
    });
};

/**
 * Get schema icon by type
 */
window.BASEO.getSchemaIcon = function(type) {
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
};

/**
 * Format date string
 */
window.BASEO.formatDate = function(dateString) {
    var date = new Date(dateString);
    return date.toLocaleDateString(undefined, {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

/**
 * Reset form to initial state
 */
window.BASEO.resetForm = function(formId) {
    var $form = jQuery('#' + formId);
    $form[0].reset();
    $form.removeAttr('data-edit-id');
    $form.find('button[type="submit"]')
        .html(t('save_schema', '💾 Save Schema'))
        .removeClass('baseo-btn-warning')
        .addClass('baseo-btn-primary');
    jQuery('#baseo-cancel-edit').remove();
    
    // Reset editor if it's the single form
    if (formId === 'baseo-schema-form') {
        if (window.BASEO.setEditorContent) {
            window.BASEO.setEditorContent('baseo-schema-editor', 'baseo-schema-data', '');
        }
        window.BASEO.updateCharCounter('#baseo-meta-title', 'baseo-meta-title-counter', 70);
        window.BASEO.updateCharCounter('#baseo-meta-description', 'baseo-meta-description-counter', 160);
    }
};

console.log('✅ BASEO Utils loaded');