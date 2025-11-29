<?php

/**
 * RT Employee Manager Debug Dashboard
 * Administrative interface for viewing debug logs and system status
 */

// Security check
if (!defined('ABSPATH')) {
    exit('Direct access forbidden.');
}

class RT_Employee_Manager_Debug_Dashboard
{
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_debug_menu'), 15); // Higher priority than admin settings
        add_action('wp_ajax_rt_log_client_error', array($this, 'handle_client_error_log'));
        add_action('wp_ajax_rt_debug_action', array($this, 'handle_debug_actions'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_debug_scripts'));
        add_action('admin_notices', array($this, 'show_debug_menu_notice'));
    }
    
    /**
     * Add debug menu to admin
     */
    public function add_debug_menu()
    {
        // Only show to administrators
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Force create parent menu if it doesn't exist
        global $admin_page_hooks;
        if (!isset($admin_page_hooks['rt-employee-manager'])) {
            add_menu_page(
                __('Mitarbeiterverwaltung', 'rt-employee-manager'),
                __('Mitarbeiterverwaltung', 'rt-employee-manager'),
                'manage_options',
                'rt-employee-manager',
                '__return_null', // No callback needed
                'dashicons-groups',
                26
            );
        }
        
        add_submenu_page(
            'rt-employee-manager',
            __('Debug Logs', 'rt-employee-manager'),
            __('Debug Logs', 'rt-employee-manager'),
            'manage_options',
            'rt-employee-debug',
            array($this, 'render_debug_page')
        );
        
        // Also add a top-level debug menu for quick access
        if (get_option('rt_employee_debug_enabled') || (defined('WP_DEBUG') && WP_DEBUG)) {
            add_menu_page(
                __('RT Debug', 'rt-employee-manager'),
                __('RT Debug', 'rt-employee-manager'),
                'manage_options',
                'rt-debug-quick',
                array($this, 'render_debug_page'),
                'dashicons-bug',
                99
            );
        }
    }
    
    /**
     * Show admin notice about debug menu
     */
    public function show_debug_menu_notice()
    {
        // Only show to administrators on RT Employee Manager pages
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'rt-employee') === false) {
            return;
        }
        
        // Only show if debug is enabled
        $debug_enabled = get_option('rt_employee_debug_enabled') || (defined('WP_DEBUG') && WP_DEBUG);
        if (!$debug_enabled) {
            return;
        }
        
        ?>
        <div class="notice notice-info is-dismissible rt-employee-info">
            <p>
                <strong>RT Employee Manager Debug System Active:</strong>
                <a href="<?php echo admin_url('admin.php?page=rt-employee-debug'); ?>" class="button button-small">
                    View Debug Logs
                </a>
                <a href="<?php echo admin_url('admin.php?page=rt-employee-form-diagnostics'); ?>" class="button button-small">
                    Form Diagnostics
                </a>
                <?php if (get_option('rt_employee_debug_enabled') || (defined('WP_DEBUG') && WP_DEBUG)): ?>
                    <a href="<?php echo admin_url('admin.php?page=rt-debug-quick'); ?>" class="button button-small">
                        Quick Debug
                    </a>
                <?php endif; ?>
            </p>
        </div>
        <?php
    }
    
    /**
     * Enqueue debug scripts
     */
    public function enqueue_debug_scripts($hook)
    {
        // Only load on RT Employee Manager pages
        if (strpos($hook, 'rt-employee') === false) {
            return;
        }
        
        wp_enqueue_script(
            'rt-employee-debug',
            RT_EMPLOYEE_MANAGER_PLUGIN_URL . 'assets/js/debug.js',
            array('jquery'),
            RT_EMPLOYEE_MANAGER_VERSION,
            true
        );
        
        // Pass debug settings to JavaScript
        wp_localize_script('rt-employee-debug', 'rtEmployeeVars', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rt_debug_nonce'),
            'debugEnabled' => get_option('rt_employee_debug_enabled', false) || (defined('WP_DEBUG') && WP_DEBUG),
            'isAdmin' => current_user_can('manage_options')
        ));
    }
    
    /**
     * Handle client-side error logging
     */
    public function handle_client_error_log()
    {
        check_ajax_referer('rt_debug_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $log_data = json_decode(stripslashes($_POST['log_data']), true);
        
        if ($log_data) {
            rt_employee_debug()->error('Client-side Error', $log_data, ['type' => 'client_error']);
            wp_send_json_success();
        } else {
            wp_send_json_error('Invalid log data');
        }
    }
    
    /**
     * Handle debug actions (clear logs, toggle debug, etc.)
     */
    public function handle_debug_actions()
    {
        check_ajax_referer('rt_debug_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $action = sanitize_text_field($_POST['debug_action']);
        
        switch ($action) {
            case 'toggle_debug':
                $enabled = get_option('rt_employee_debug_enabled', false);
                update_option('rt_employee_debug_enabled', !$enabled);
                wp_send_json_success(array(
                    'message' => !$enabled ? 'Debug enabled' : 'Debug disabled',
                    'enabled' => !$enabled
                ));
                break;
                
            case 'clear_logs':
                $this->clear_all_logs();
                wp_send_json_success(array('message' => 'All logs cleared'));
                break;
                
            case 'export_logs':
                $logs = rt_employee_debug()->get_recent_logs(1000);
                wp_send_json_success(array(
                    'logs' => $logs,
                    'filename' => 'rt-employee-debug-' . date('Y-m-d-H-i-s') . '.json'
                ));
                break;
                
            default:
                wp_send_json_error('Unknown action');
        }
    }
    
    /**
     * Render debug dashboard page
     */
    public function render_debug_page()
    {
        $debug_enabled = get_option('rt_employee_debug_enabled', false) || (defined('WP_DEBUG') && WP_DEBUG);
        $recent_logs = rt_employee_debug()->get_recent_logs(100);
        $system_info = $this->get_system_info();
        
        ?>
        <div class="wrap">
            <h1><?php _e('RT Employee Manager - Debug Dashboard', 'rt-employee-manager'); ?></h1>
            
            <div class="notice notice-info">
                <p><strong><?php _e('Debug Status:', 'rt-employee-manager'); ?></strong> 
                    <span id="debug-status"><?php echo $debug_enabled ? 'ENABLED' : 'DISABLED'; ?></span>
                    <button id="toggle-debug" class="button button-small" style="margin-left: 10px;">
                        <?php echo $debug_enabled ? 'Disable' : 'Enable'; ?> Debug
                    </button>
                </p>
            </div>
            
            <div style="display: flex; gap: 20px;">
                <!-- System Information -->
                <div style="flex: 1;">
                    <div class="card" style="padding: 15px;">
                        <h2><?php _e('System Information', 'rt-employee-manager'); ?></h2>
                        <table class="widefat striped">
                            <?php foreach ($system_info as $key => $value): ?>
                                <tr>
                                    <td style="font-weight: bold;"><?php echo esc_html($key); ?></td>
                                    <td><?php echo esc_html($value); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                    
                    <!-- Quick Stats -->
                    <div class="card" style="padding: 15px; margin-top: 20px;">
                        <h2><?php _e('Debug Statistics', 'rt-employee-manager'); ?></h2>
                        <?php $stats = $this->get_log_statistics(); ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px;">
                            <div style="text-align: center; padding: 10px; background: #f9f9f9; border-radius: 4px;">
                                <div style="font-size: 24px; font-weight: bold; color: #2196F3;"><?php echo $stats['total']; ?></div>
                                <div>Total Logs</div>
                            </div>
                            <div style="text-align: center; padding: 10px; background: #f9f9f9; border-radius: 4px;">
                                <div style="font-size: 24px; font-weight: bold; color: #F44336;"><?php echo $stats['errors']; ?></div>
                                <div>Errors</div>
                            </div>
                            <div style="text-align: center; padding: 10px; background: #f9f9f9; border-radius: 4px;">
                                <div style="font-size: 24px; font-weight: bold; color: #FF9800;"><?php echo $stats['warnings']; ?></div>
                                <div>Warnings</div>
                            </div>
                            <div style="text-align: center; padding: 10px; background: #f9f9f9; border-radius: 4px;">
                                <div style="font-size: 24px; font-weight: bold; color: #d32f2f;"><?php echo $stats['security']; ?></div>
                                <div>Security Issues</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Debug Actions -->
                <div style="width: 300px;">
                    <div class="card" style="padding: 15px;">
                        <h2><?php _e('Debug Actions', 'rt-employee-manager'); ?></h2>
                        <p>
                            <button id="clear-logs" class="button button-secondary" style="width: 100%; margin-bottom: 10px;">
                                Clear All Logs
                            </button>
                        </p>
                        <p>
                            <button id="export-logs" class="button button-secondary" style="width: 100%; margin-bottom: 10px;">
                                Export Logs (JSON)
                            </button>
                        </p>
                        <p>
                            <button id="refresh-logs" class="button button-secondary" style="width: 100%; margin-bottom: 10px;">
                                Refresh Logs
                            </button>
                        </p>
                        <hr>
                        <p>
                            <strong>Auto-refresh:</strong>
                            <label>
                                <input type="checkbox" id="auto-refresh" checked> Every 10 seconds
                            </label>
                        </p>
                    </div>
                    
                    <!-- Log Filter -->
                    <div class="card" style="padding: 15px; margin-top: 20px;">
                        <h2><?php _e('Filter Logs', 'rt-employee-manager'); ?></h2>
                        <p>
                            <label for="log-level-filter">Level:</label>
                            <select id="log-level-filter" style="width: 100%;">
                                <option value="">All Levels</option>
                                <option value="DEBUG">Debug</option>
                                <option value="INFO">Info</option>
                                <option value="WARNING">Warning</option>
                                <option value="ERROR">Error</option>
                                <option value="CRITICAL">Critical</option>
                                <option value="SECURITY">Security</option>
                            </select>
                        </p>
                        <p>
                            <label for="log-type-filter">Type:</label>
                            <select id="log-type-filter" style="width: 100%;">
                                <option value="">All Types</option>
                                <option value="ajax_employee_delete">Employee Delete</option>
                                <option value="ajax_employee_status_update">Status Update</option>
                                <option value="company_approval">Company Approval</option>
                                <option value="form_submission">Form Submission</option>
                                <option value="user_auth">User Auth</option>
                                <option value="database">Database</option>
                                <option value="security">Security</option>
                            </select>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Recent Logs -->
            <div class="card" style="padding: 15px; margin-top: 20px;">
                <h2>
                    <?php _e('Recent Debug Logs', 'rt-employee-manager'); ?>
                    <span style="float: right; font-size: 12px; color: #666;">
                        Last updated: <span id="last-updated"><?php echo current_time('H:i:s'); ?></span>
                    </span>
                </h2>
                
                <div id="debug-logs-container" style="max-height: 600px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9; font-family: monospace; font-size: 12px;">
                    <?php if (empty($recent_logs)): ?>
                        <div style="text-align: center; color: #666; padding: 20px;">
                            No debug logs available. Enable debugging to start logging.
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_logs as $log): ?>
                            <?php $this->render_log_entry($log); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <style>
            .log-entry {
                margin-bottom: 10px;
                padding: 8px;
                border-left: 4px solid #ddd;
                background: white;
                border-radius: 4px;
            }
            .log-entry.DEBUG { border-left-color: #666; }
            .log-entry.INFO { border-left-color: #2196F3; }
            .log-entry.WARNING { border-left-color: #FF9800; }
            .log-entry.ERROR { border-left-color: #F44336; }
            .log-entry.CRITICAL { border-left-color: #d32f2f; background: #ffebee; }
            .log-entry.SECURITY { border-left-color: #9C27B0; background: #f3e5f5; }
            
            .log-header {
                font-weight: bold;
                margin-bottom: 5px;
            }
            .log-data {
                color: #666;
                font-size: 11px;
                margin-left: 10px;
            }
            .log-timestamp {
                color: #999;
                font-size: 10px;
            }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            var autoRefresh = true;
            var refreshInterval;
            
            // Toggle debug
            $('#toggle-debug').on('click', function() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'rt_debug_action',
                        debug_action: 'toggle_debug',
                        nonce: '<?php echo wp_create_nonce('rt_debug_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#debug-status').text(response.data.enabled ? 'ENABLED' : 'DISABLED');
                            $('#toggle-debug').text(response.data.enabled ? 'Disable Debug' : 'Enable Debug');
                            showNotice(response.data.message, 'success');
                        }
                    }
                });
            });
            
            // Clear logs
            $('#clear-logs').on('click', function() {
                if (confirm('Are you sure you want to clear all debug logs?')) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'rt_debug_action',
                            debug_action: 'clear_logs',
                            nonce: '<?php echo wp_create_nonce('rt_debug_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#debug-logs-container').html('<div style="text-align: center; color: #666; padding: 20px;">All logs cleared.</div>');
                                showNotice(response.data.message, 'success');
                            }
                        }
                    });
                }
            });
            
            // Export logs
            $('#export-logs').on('click', function() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'rt_debug_action',
                        debug_action: 'export_logs',
                        nonce: '<?php echo wp_create_nonce('rt_debug_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var blob = new Blob([JSON.stringify(response.data.logs, null, 2)], {type: 'application/json'});
                            var url = window.URL.createObjectURL(blob);
                            var a = document.createElement('a');
                            a.href = url;
                            a.download = response.data.filename;
                            a.click();
                            window.URL.revokeObjectURL(url);
                            showNotice('Logs exported successfully', 'success');
                        }
                    }
                });
            });
            
            // Refresh logs
            $('#refresh-logs').on('click', function() {
                location.reload();
            });
            
            // Auto-refresh toggle
            $('#auto-refresh').on('change', function() {
                autoRefresh = $(this).is(':checked');
                if (autoRefresh) {
                    startAutoRefresh();
                } else {
                    stopAutoRefresh();
                }
            });
            
            function startAutoRefresh() {
                refreshInterval = setInterval(function() {
                    $('#last-updated').text(new Date().toLocaleTimeString());
                    // Here you could implement AJAX refresh of logs
                }, 10000);
            }
            
            function stopAutoRefresh() {
                if (refreshInterval) {
                    clearInterval(refreshInterval);
                }
            }
            
            function showNotice(message, type) {
                var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
                $('.wrap h1').after($notice);
                setTimeout(function() {
                    $notice.fadeOut();
                }, 3000);
            }
            
            // Start auto-refresh
            if (autoRefresh) {
                startAutoRefresh();
            }
        });
        </script>
        <?php
    }
    
    /**
     * Render individual log entry
     */
    private function render_log_entry($log)
    {
        $level_class = strtoupper($log['level']);
        $timestamp = date('H:i:s', strtotime($log['timestamp']));
        $data_json = !empty($log['data']) ? json_encode($log['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '';
        
        ?>
        <div class="log-entry <?php echo esc_attr($level_class); ?>">
            <div class="log-header">
                [<?php echo esc_html($timestamp); ?>] 
                <span style="color: inherit;"><?php echo esc_html($log['level']); ?></span>: 
                <?php echo esc_html($log['message']); ?>
            </div>
            <?php if ($data_json): ?>
                <div class="log-data">
                    <pre style="margin: 0; white-space: pre-wrap; font-family: monospace; font-size: 11px;"><?php echo esc_html($data_json); ?></pre>
                </div>
            <?php endif; ?>
            <div class="log-timestamp">
                User: <?php echo esc_html($log['user_id'] ?? 'N/A'); ?> | 
                IP: <?php echo esc_html($log['user_ip'] ?? 'N/A'); ?> | 
                Memory: <?php echo esc_html($log['memory_usage'] ?? 'N/A'); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get system information
     */
    private function get_system_info()
    {
        return array(
            'Plugin Version' => RT_EMPLOYEE_MANAGER_VERSION,
            'WordPress Version' => get_bloginfo('version'),
            'PHP Version' => PHP_VERSION,
            'MySQL Version' => $this->get_mysql_version(),
            'Server Software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'WP Debug' => defined('WP_DEBUG') && WP_DEBUG ? 'Enabled' : 'Disabled',
            'WP Debug Log' => defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ? 'Enabled' : 'Disabled',
            'Memory Limit' => ini_get('memory_limit'),
            'Max Execution Time' => ini_get('max_execution_time') . 's',
            'Upload Max Size' => ini_get('upload_max_filesize'),
            'Post Max Size' => ini_get('post_max_size'),
            'Environment' => defined('WP_ENVIRONMENT_TYPE') ? WP_ENVIRONMENT_TYPE : 'Unknown'
        );
    }
    
    /**
     * Get MySQL version
     */
    private function get_mysql_version()
    {
        global $wpdb;
        return $wpdb->get_var("SELECT VERSION()");
    }
    
    /**
     * Get log statistics
     */
    private function get_log_statistics()
    {
        $logs = rt_employee_debug()->get_recent_logs(1000);
        $stats = array(
            'total' => count($logs),
            'errors' => 0,
            'warnings' => 0,
            'security' => 0
        );
        
        foreach ($logs as $log) {
            switch (strtoupper($log['level'])) {
                case 'ERROR':
                case 'CRITICAL':
                    $stats['errors']++;
                    break;
                case 'WARNING':
                    $stats['warnings']++;
                    break;
                case 'SECURITY':
                    $stats['security']++;
                    break;
            }
        }
        
        return $stats;
    }
    
    /**
     * Clear all log files
     */
    private function clear_all_logs()
    {
        $upload_dir = wp_upload_dir();
        $logs_dir = $upload_dir['basedir'] . '/rt-employee-logs';
        
        if (file_exists($logs_dir)) {
            $files = glob($logs_dir . '/rt-employee-debug-*.log*');
            foreach ($files as $file) {
                unlink($file);
            }
        }
        
        // Also clear database logs
        global $wpdb;
        $table_name = $wpdb->prefix . 'rt_employee_logs';
        $wpdb->query("DELETE FROM {$table_name} WHERE employee_id = 0 AND action LIKE 'DEBUG:%' OR action LIKE 'INFO:%' OR action LIKE 'WARNING:%'");
    }
}