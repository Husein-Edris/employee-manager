/**
 * RT Employee Manager Debug JavaScript
 * Comprehensive client-side debugging and tracking
 */
(function($) {
    'use strict';
    
    // Initialize debugging
    window.rtEmployeeDebug = {
        enabled: false,
        logs: [],
        maxLogs: 1000
    };
    
    // Check if debugging is enabled
    if (typeof rtEmployeeVars !== 'undefined' && rtEmployeeVars.debugEnabled) {
        window.rtEmployeeDebug.enabled = true;
    }
    
    /**
     * Debug logging function
     */
    function log(level, message, data, context) {
        if (!window.rtEmployeeDebug.enabled) return;
        
        var logEntry = {
            timestamp: new Date().toISOString(),
            level: level.toUpperCase(),
            message: message,
            data: data || {},
            context: context || {},
            url: window.location.href,
            userAgent: navigator.userAgent,
            viewport: {
                width: $(window).width(),
                height: $(window).height()
            }
        };
        
        // Add to internal log
        window.rtEmployeeDebug.logs.push(logEntry);
        
        // Keep only recent logs
        if (window.rtEmployeeDebug.logs.length > window.rtEmployeeDebug.maxLogs) {
            window.rtEmployeeDebug.logs = window.rtEmployeeDebug.logs.slice(-window.rtEmployeeDebug.maxLogs);
        }
        
        // Console output with styling
        var style = getLogStyle(level);
        console.log(
            `%c[RT-EMP-${level.toUpperCase()}] ${message}`,
            style,
            data
        );
        
        // Send critical errors to server
        if (level === 'error' || level === 'critical') {
            sendLogToServer(logEntry);
        }
    }
    
    /**
     * Get console styling for log level
     */
    function getLogStyle(level) {
        var styles = {
            debug: 'color: #666; font-size: 12px;',
            info: 'color: #2196F3; font-weight: bold;',
            warning: 'color: #FF9800; font-weight: bold;',
            error: 'color: #F44336; font-weight: bold; background: #ffebee;',
            critical: 'color: #fff; background: #d32f2f; padding: 2px 4px; font-weight: bold;'
        };
        return styles[level] || styles.info;
    }
    
    /**
     * Send log to server via AJAX
     */
    function sendLogToServer(logEntry) {
        if (typeof rtEmployeeVars === 'undefined' || !rtEmployeeVars.ajaxurl) return;
        
        $.ajax({
            url: rtEmployeeVars.ajaxurl,
            type: 'POST',
            data: {
                action: 'rt_log_client_error',
                nonce: rtEmployeeVars.nonce,
                log_data: JSON.stringify(logEntry)
            },
            timeout: 5000
        }).fail(function() {
            console.warn('[RT-EMP] Failed to send log to server');
        });
    }
    
    /**
     * Public API
     */
    window.rtEmployeeLog = {
        debug: function(message, data, context) { log('debug', message, data, context); },
        info: function(message, data, context) { log('info', message, data, context); },
        warning: function(message, data, context) { log('warning', message, data, context); },
        error: function(message, data, context) { log('error', message, data, context); },
        critical: function(message, data, context) { log('critical', message, data, context); },
        getLogs: function() { return window.rtEmployeeDebug.logs; },
        clearLogs: function() { window.rtEmployeeDebug.logs = []; }
    };
    
    $(document).ready(function() {
        rtEmployeeLog.info('RT Employee Manager Debug System Initialized', {
            enabled: window.rtEmployeeDebug.enabled,
            jquery_version: $.fn.jquery,
            page_type: $('body').attr('class'),
            admin_page: typeof pagenow !== 'undefined' ? pagenow : null
        });
        
        // Track all AJAX requests
        $(document).ajaxSend(function(event, jqxhr, settings) {
            rtEmployeeLog.info('AJAX Request Started', {
                url: settings.url,
                type: settings.type,
                data: settings.data,
                context: 'ajax_send'
            });
        });
        
        $(document).ajaxComplete(function(event, jqxhr, settings) {
            rtEmployeeLog.info('AJAX Request Completed', {
                url: settings.url,
                type: settings.type,
                status: jqxhr.status,
                response_length: jqxhr.responseText ? jqxhr.responseText.length : 0,
                context: 'ajax_complete'
            });
        });
        
        $(document).ajaxError(function(event, jqxhr, settings, thrownError) {
            rtEmployeeLog.error('AJAX Request Failed', {
                url: settings.url,
                type: settings.type,
                status: jqxhr.status,
                error: thrownError,
                response: jqxhr.responseText,
                context: 'ajax_error'
            });
        });
        
        // Track form submissions
        $(document).on('submit', 'form', function(e) {
            var $form = $(this);
            var formData = {};
            
            // Collect form data (excluding sensitive fields)
            $form.serializeArray().forEach(function(field) {
                if (!isSensitiveField(field.name)) {
                    formData[field.name] = field.value;
                }
            });
            
            rtEmployeeLog.info('Form Submission Started', {
                form_id: $form.attr('id'),
                form_action: $form.attr('action'),
                form_method: $form.attr('method'),
                field_count: $form.find('input, textarea, select').length,
                form_data: formData,
                context: 'form_submission'
            });
        });
        
        // Track JavaScript errors
        window.onerror = function(message, source, lineno, colno, error) {
            rtEmployeeLog.error('JavaScript Error', {
                message: message,
                source: source,
                line: lineno,
                column: colno,
                stack: error ? error.stack : null,
                context: 'js_error'
            });
            return false; // Don't suppress default error handling
        };
        
        // Track unhandled promise rejections
        window.addEventListener('unhandledrejection', function(event) {
            rtEmployeeLog.error('Unhandled Promise Rejection', {
                reason: event.reason,
                promise: event.promise,
                context: 'promise_rejection'
            });
        });
        
        // Track clicks on important elements
        $(document).on('click', '.rt-btn, .gform_button, .button, [data-employee-id]', function(e) {
            var $element = $(this);
            rtEmployeeLog.debug('Important Element Clicked', {
                element_type: $element.prop('tagName'),
                element_class: $element.attr('class'),
                element_id: $element.attr('id'),
                employee_id: $element.data('employee-id'),
                text: $element.text().trim().substring(0, 50),
                context: 'user_interaction'
            });
        });
        
        // Track Gravity Forms events
        $(document).on('gform_post_render', function(event, form_id, current_page) {
            rtEmployeeLog.info('Gravity Form Rendered', {
                form_id: form_id,
                current_page: current_page,
                context: 'gravity_form'
            });
        });
        
        // Track validation errors
        $(document).on('gform_validation_error', function(event, form_id) {
            rtEmployeeLog.warning('Gravity Form Validation Error', {
                form_id: form_id,
                context: 'form_validation'
            });
        });
        
        // Track page visibility changes
        $(document).on('visibilitychange', function() {
            rtEmployeeLog.debug('Page Visibility Changed', {
                hidden: document.hidden,
                visibility_state: document.visibilityState,
                context: 'page_visibility'
            });
        });
        
        // Track window resize (throttled)
        var resizeTimer;
        $(window).on('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                rtEmployeeLog.debug('Window Resized', {
                    width: $(window).width(),
                    height: $(window).height(),
                    context: 'window_resize'
                });
            }, 250);
        });
        
        // Performance monitoring
        if (window.performance && window.performance.timing) {
            $(window).on('load', function() {
                setTimeout(function() {
                    var timing = window.performance.timing;
                    var loadTime = timing.loadEventEnd - timing.navigationStart;
                    var domTime = timing.domContentLoadedEventEnd - timing.navigationStart;
                    
                    rtEmployeeLog.info('Page Performance', {
                        load_time: loadTime,
                        dom_time: domTime,
                        dns_time: timing.domainLookupEnd - timing.domainLookupStart,
                        server_response_time: timing.responseEnd - timing.requestStart,
                        context: 'performance'
                    });
                }, 100);
            });
        }
        
        // Add debug panel (only if enabled and user is admin)
        if (window.rtEmployeeDebug.enabled && typeof rtEmployeeVars !== 'undefined' && rtEmployeeVars.isAdmin) {
            addDebugPanel();
        }
    });
    
    /**
     * Check if field contains sensitive data
     */
    function isSensitiveField(fieldName) {
        var sensitivePatterns = [
            'password', 'pwd', 'pass', 'token', 'secret', 'key',
            'sozialversicherungsnummer', 'svnr', 'ssn', 'credit_card'
        ];
        
        return sensitivePatterns.some(function(pattern) {
            return fieldName.toLowerCase().includes(pattern);
        });
    }
    
    /**
     * Add floating debug panel
     */
    function addDebugPanel() {
        var $panel = $(`
            <div id="rt-debug-panel" style="
                position: fixed;
                top: 100px;
                right: 10px;
                width: 300px;
                max-height: 400px;
                background: #fff;
                border: 1px solid #ccc;
                border-radius: 4px;
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
                z-index: 999999;
                font-size: 12px;
                display: none;
            ">
                <div style="background: #0073aa; color: white; padding: 8px; border-radius: 4px 4px 0 0;">
                    <strong>RT Employee Debug</strong>
                    <button id="rt-debug-close" style="float: right; background: none; border: none; color: white; cursor: pointer;">×</button>
                    <button id="rt-debug-clear" style="float: right; background: none; border: none; color: white; cursor: pointer; margin-right: 10px;">Clear</button>
                </div>
                <div id="rt-debug-content" style="padding: 8px; max-height: 350px; overflow-y: auto; font-family: monospace; font-size: 11px;">
                    <div>Debug panel initialized...</div>
                </div>
            </div>
            
            <div id="rt-debug-trigger" style="
                position: fixed;
                top: 50px;
                right: 10px;
                background: #0073aa;
                color: white;
                padding: 5px 10px;
                border-radius: 4px;
                cursor: pointer;
                z-index: 999998;
                font-size: 12px;
                font-weight: bold;
            ">DEBUG</div>
        `);
        
        $('body').append($panel);
        
        // Toggle panel
        $('#rt-debug-trigger').on('click', function() {
            $('#rt-debug-panel').toggle();
            updateDebugPanel();
        });
        
        // Close panel
        $('#rt-debug-close').on('click', function() {
            $('#rt-debug-panel').hide();
        });
        
        // Clear logs
        $('#rt-debug-clear').on('click', function() {
            rtEmployeeLog.clearLogs();
            updateDebugPanel();
        });
        
        // Auto-update panel every 2 seconds
        setInterval(updateDebugPanel, 2000);
    }
    
    /**
     * Update debug panel content
     */
    function updateDebugPanel() {
        if (!$('#rt-debug-panel').is(':visible')) return;
        
        var logs = rtEmployeeLog.getLogs();
        var recentLogs = logs.slice(-20); // Show last 20 logs
        
        var html = recentLogs.map(function(log) {
            var time = new Date(log.timestamp).toLocaleTimeString();
            var levelColor = {
                DEBUG: '#666',
                INFO: '#2196F3',
                WARNING: '#FF9800',
                ERROR: '#F44336',
                CRITICAL: '#d32f2f'
            }[log.level] || '#333';
            
            return `
                <div style="margin-bottom: 5px; border-bottom: 1px solid #eee; padding-bottom: 3px;">
                    <div style="color: ${levelColor}; font-weight: bold;">
                        [${time}] ${log.level}: ${log.message}
                    </div>
                    ${Object.keys(log.data).length > 0 ? 
                        `<div style="color: #666; margin-left: 10px;">${JSON.stringify(log.data, null, 1)}</div>` : ''}
                </div>
            `;
        }).join('');
        
        $('#rt-debug-content').html(html || '<div>No logs yet...</div>');
        
        // Auto-scroll to bottom
        var $content = $('#rt-debug-content');
        $content.scrollTop($content[0].scrollHeight);
    }
    
    // Export for global access
    window.rtDebugPanel = {
        show: function() { $('#rt-debug-panel').show(); },
        hide: function() { $('#rt-debug-panel').hide(); },
        toggle: function() { $('#rt-debug-panel').toggle(); }
    };
    
})(jQuery);