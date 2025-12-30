/**
 * Custom Schema by BASEO - ContentEditable Editor
 * Version: 1.0.2
 * Author: BASEO Team
 * Depends on: admin-utils.js
 */

(function() {
    'use strict';
    
    // Ensure BASEO namespace exists
    window.BASEO = window.BASEO || {};
    
    /**
     * Initialize ContentEditable editor with syntax highlighting
     * @param {string} editorId - ID of contenteditable div
     * @param {string} textareaId - ID of hidden textarea for form submission
     */
    window.BASEO.initContentEditableEditor = function(editorId, textareaId) {
        var $editor = jQuery('#' + editorId);
        var $hiddenTextarea = jQuery('#' + textareaId);
        
        if (!$editor.length) {
            console.warn('[BASEO Editor] Editor not found:', editorId);
            return;
        }
        
        console.log('[BASEO] Initializing editor:', editorId);
        
        // Force visible text color immediately
        var forceVisibleStyles = 'color: #2c3e50 !important; -webkit-text-fill-color: #2c3e50 !important; background: white !important;';
        $editor.attr('style', $editor.attr('style') ? $editor.attr('style') + '; ' + forceVisibleStyles : forceVisibleStyles);
        
        // Apply syntax highlighting
        function applySyntaxHighlighting() {
            var selection = saveSelection($editor[0]);
            var text = getPlainText($editor[0]);
            
            // Escape HTML
            var escaped = text
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
            
            // Highlight variables with inline styles
            var highlighted = escaped.replace(
                /\{\{(meta_title|meta_description)\}\}/g,
                '<span class="baseo-variable" style="color: #8e44ad !important; background: rgba(142, 68, 173, 0.15) !important; -webkit-text-fill-color: #8e44ad !important;" contenteditable="false">{{$1}}</span>'
            );
            
            // Update editor
            $editor.html(highlighted);
            
            // Force color again after update
            var currentStyle = $editor.attr('style') || '';
            if (currentStyle.indexOf('-webkit-text-fill-color') === -1) {
                $editor.attr('style', currentStyle + '; ' + forceVisibleStyles);
            }
            
            // Sync with hidden textarea
            $hiddenTextarea.val(text);
            
            // Restore cursor
            restoreSelection($editor[0], selection);
        }
        
        // Get plain text from contenteditable
        function getPlainText(element) {
            var text = '';
            for (var i = 0; i < element.childNodes.length; i++) {
                var node = element.childNodes[i];
                if (node.nodeType === Node.TEXT_NODE) {
                    text += node.textContent;
                } else if (node.nodeType === Node.ELEMENT_NODE) {
                    if (node.classList && node.classList.contains('baseo-variable')) {
                        text += node.textContent;
                    } else {
                        text += getPlainText(node);
                    }
                }
            }
            return text;
        }
        
        // Save cursor position
        function saveSelection(element) {
            var selection = window.getSelection();
            if (selection.rangeCount > 0) {
                var range = selection.getRangeAt(0);
                var preSelectionRange = range.cloneRange();
                preSelectionRange.selectNodeContents(element);
                preSelectionRange.setEnd(range.startContainer, range.startOffset);
                return preSelectionRange.toString().length;
            }
            return 0;
        }
        
        // Restore cursor position
        function restoreSelection(element, offset) {
            var charIndex = 0, range = document.createRange();
            range.setStart(element, 0);
            range.collapse(true);
            var nodeStack = [element], node, foundStart = false;
            
            while (!foundStart && (node = nodeStack.pop())) {
                if (node.nodeType === Node.TEXT_NODE) {
                    var nextCharIndex = charIndex + node.length;
                    if (offset >= charIndex && offset <= nextCharIndex) {
                        range.setStart(node, offset - charIndex);
                        foundStart = true;
                    }
                    charIndex = nextCharIndex;
                } else {
                    var i = node.childNodes.length;
                    while (i--) {
                        nodeStack.push(node.childNodes[i]);
                    }
                }
            }
            
            var selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
        }
        
        // Handle input
        $editor.on('input', function() {
            applySyntaxHighlighting();
        });
        
        // Handle paste - strip formatting
        $editor.on('paste', function(e) {
            e.preventDefault();
            var text = (e.originalEvent || e).clipboardData.getData('text/plain');
            document.execCommand('insertText', false, text);
        });
        
        // Sync on blur
        $editor.on('blur', function() {
            var text = getPlainText(this);
            $hiddenTextarea.val(text);
        });
        
        // Placeholder handling
        if ($editor.text().trim() === '') {
            $editor.addClass('baseo-editor-empty');
        }
        
        $editor.on('focus', function() {
            jQuery(this).removeClass('baseo-editor-empty');
            var currentStyle = jQuery(this).attr('style') || '';
            jQuery(this).attr('style', currentStyle + '; ' + forceVisibleStyles);
        });
        
        $editor.on('blur', function() {
            if (jQuery(this).text().trim() === '') {
                jQuery(this).addClass('baseo-editor-empty');
            }
        });
        
        // Set placeholder
        setTimeout(function() {
            if ($editor.length && !$editor.attr('data-placeholder')) {
                $editor.attr('data-placeholder', '{"@context": "https://schema.org", "@type": "Organization", "name": "Your Company"}');
            }
        }, 100);
        
        console.log('[BASEO] Editor initialized:', editorId);
    };
    
    /**
     * Set editor content programmatically
     */
    window.BASEO.setEditorContent = function(editorId, textareaId, text) {
        var $editor = jQuery('#' + editorId);
        var $hiddenTextarea = jQuery('#' + textareaId);
        
        if ($editor.length) {
            // Escape HTML
            var escaped = text
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
            
            // Highlight variables
            var highlighted = escaped.replace(
                /\{\{(meta_title|meta_description)\}\}/g,
                '<span class="baseo-variable" style="color: #8e44ad !important; background: rgba(142, 68, 173, 0.15) !important; -webkit-text-fill-color: #8e44ad !important;" contenteditable="false">{{$1}}</span>'
            );
            
            $editor.html(highlighted);
            $hiddenTextarea.val(text);
            $editor.removeClass('baseo-editor-empty');
            
            // Force visible text color
            $editor.attr('style', 
                'color: #2c3e50 !important; ' +
                '-webkit-text-fill-color: #2c3e50 !important; ' +
                'background: white !important;'
            );
        }
    };
    
    /**
     * Get editor content as plain text
     */
    window.BASEO.getEditorContent = function(editorId) {
        var $editor = jQuery('#' + editorId);
        if ($editor.length) {
            return getPlainTextFromElement($editor[0]);
        }
        return '';
    };
    
    function getPlainTextFromElement(element) {
        var text = '';
        for (var i = 0; i < element.childNodes.length; i++) {
            var node = element.childNodes[i];
            if (node.nodeType === Node.TEXT_NODE) {
                text += node.textContent;
            } else if (node.nodeType === Node.ELEMENT_NODE) {
                if (node.classList && node.classList.contains('baseo-variable')) {
                    text += node.textContent;
                } else {
                    text += getPlainTextFromElement(node);
                }
            }
        }
        return text;
    }
    
    console.log('✅ BASEO Editor loaded');
})();