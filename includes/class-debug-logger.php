<?php

/**
 * RT Employee Manager Debug Logger
 * Comprehensive debugging and tracking system
 */

// Security check
if (!defined('ABSPATH')) {
    exit('Direct access forbidden.');
}

class RT_Employee_Manager_Debug_Logger
{
    private static $instance = null;
    private $log_file;
    private $debug_enabled;
    private $log_retention_days = 7;
    private $max_log_size = 10485760; // 10MB
    
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct()
    {
        $this->init_logger();
    }
    
    private function init_logger()
    {
        // Enable debugging if WP_DEBUG is on OR if explicitly enabled
        $this->debug_enabled = (defined('WP_DEBUG') && WP_DEBUG) || get_option('rt_employee_debug_enabled', false);
        
        // Create logs directory if it doesn't exist
        $upload_dir = wp_upload_dir();
        $logs_dir = $upload_dir['basedir'] . '/rt-employee-logs';
        
        if (!file_exists($logs_dir)) {
            wp_mkdir_p($logs_dir);
            // Add .htaccess to protect log files
            file_put_contents($logs_dir . '/.htaccess', "Order deny,allow\nDeny from all");
        }
        
        // Set log file path with date rotation
        $this->log_file = $logs_dir . '/rt-employee-debug-' . date('Y-m-d') . '.log';
        
        // Setup hooks
        $this->setup_hooks();
        
        // Log initialization
        $this->log('SYSTEM', 'Debug logger initialized', [
            'debug_enabled' => $this->debug_enabled,
            'log_file' => basename($this->log_file),
            'user_id' => get_current_user_id(),
            'user_ip' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
    }
    
    private function setup_hooks()
    {
        // Track all AJAX requests
        add_action('wp_ajax_nopriv_init', array($this, 'log_ajax_start'));
        add_action('wp_ajax_init', array($this, 'log_ajax_start'));
        
        // Track form submissions
        add_action('gform_pre_submission', array($this, 'log_gravity_form_submission'), 5, 1);
        add_action('gform_post_submission', array($this, 'log_gravity_form_completion'), 15, 2);
        
        // Track database operations
        add_filter('query', array($this, 'log_database_queries'));
        
        // Track user actions
        add_action('wp_login', array($this, 'log_user_login'), 10, 2);
        add_action('wp_logout', array($this, 'log_user_logout'));
        
        // Track post operations
        add_action('save_post', array($this, 'log_post_save'), 10, 3);
        add_action('delete_post', array($this, 'log_post_delete'));
        
        // Track admin page loads
        add_action('admin_init', array($this, 'log_admin_page_access'));
        
        // Track errors
        add_action('wp_die_handler', array($this, 'log_wp_die'));
        
        // Cleanup old logs
        add_action('wp_scheduled_delete', array($this, 'cleanup_old_logs'));
    }
    
    /**
     * Main logging function
     */
    public function log($level, $message, $data = array(), $context = array())
    {
        if (!$this->debug_enabled) {
            return;
        }
        
        // Rotate log file if too large
        if (file_exists($this->log_file) && filesize($this->log_file) > $this->max_log_size) {
            $this->rotate_log_file();
        }
        
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'microtime' => microtime(true),
            'level' => strtoupper($level),
            'message' => $message,
            'user_id' => get_current_user_id(),
            'user_ip' => $this->get_client_ip(),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'http_method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 200),
            'memory_usage' => $this->format_bytes(memory_get_usage(true)),
            'peak_memory' => $this->format_bytes(memory_get_peak_usage(true)),
            'data' => $data,
            'context' => $context,
            'backtrace' => $this->get_filtered_backtrace()
        );
        
        // Format log entry as JSON for easier parsing
        $log_line = json_encode($log_entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        
        // Write to file
        file_put_contents($this->log_file, $log_line, FILE_APPEND | LOCK_EX);
        
        // Also log to WordPress debug.log if WP_DEBUG_LOG is enabled
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log("RT_EMPLOYEE[{$level}]: {$message} " . json_encode($data));
        }
        
        // Log to database for critical events
        if (in_array($level, ['ERROR', 'CRITICAL', 'SECURITY'])) {
            $this->log_to_database($log_entry);
        }
    }
    
    /**
     * Convenience methods for different log levels
     */
    public function debug($message, $data = array(), $context = array())
    {
        $this->log('DEBUG', $message, $data, $context);
    }
    
    public function info($message, $data = array(), $context = array())
    {
        $this->log('INFO', $message, $data, $context);
    }
    
    public function warning($message, $data = array(), $context = array())
    {
        $this->log('WARNING', $message, $data, $context);
    }
    
    public function error($message, $data = array(), $context = array())
    {
        $this->log('ERROR', $message, $data, $context);
    }
    
    public function critical($message, $data = array(), $context = array())
    {
        $this->log('CRITICAL', $message, $data, $context);
    }
    
    public function security($message, $data = array(), $context = array())
    {
        $this->log('SECURITY', $message, $data, $context);
    }
    
    /**
     * Track AJAX requests
     */
    public function log_ajax_start()
    {
        $action = $_POST['action'] ?? $_GET['action'] ?? 'unknown';
        
        $this->info('AJAX Request Started', [
            'action' => $action,
            'post_data' => $this->sanitize_sensitive_data($_POST),
            'get_data' => $_GET,
            'referer' => wp_get_referer()
        ], ['type' => 'ajax']);
    }
    
    /**
     * Track Gravity Form submissions
     */
    public function log_gravity_form_submission($form)
    {
        $this->info('Gravity Form Submission Started', [
            'form_id' => $form['id'],
            'form_title' => $form['title'],
            'field_count' => count($form['fields']),
            'submitted_data' => $this->sanitize_sensitive_data($_POST)
        ], ['type' => 'form_submission']);
    }
    
    public function log_gravity_form_completion($entry, $form)
    {
        $this->info('Gravity Form Submission Completed', [
            'form_id' => $form['id'],
            'form_title' => $form['title'],
            'entry_id' => $entry['id'],
            'entry_status' => $entry['status'],
            'created_by' => $entry['created_by']
        ], ['type' => 'form_completion']);
    }
    
    /**
     * Track database queries (only for RT Employee Manager queries)
     */
    public function log_database_queries($query)
    {
        // Only log our plugin's queries
        if (strpos($query, 'rt_employee') !== false || 
            strpos($query, 'angestellte') !== false || 
            strpos($query, 'kunde') !== false) {
            
            $this->debug('Database Query', [
                'query' => $query,
                'execution_time' => $this->get_query_execution_time($query)
            ], ['type' => 'database']);
        }
        
        return $query;
    }
    
    /**
     * Track user login/logout
     */
    public function log_user_login($user_login, $user)
    {
        $this->info('User Login', [
            'user_id' => $user->ID,
            'user_login' => $user_login,
            'user_email' => $user->user_email,
            'user_roles' => $user->roles
        ], ['type' => 'user_auth']);
    }
    
    public function log_user_logout()
    {
        $this->info('User Logout', [
            'user_id' => get_current_user_id()
        ], ['type' => 'user_auth']);
    }
    
    /**
     * Track post operations
     */
    public function log_post_save($post_id, $post, $update)
    {
        // Only log our custom post types
        if (!in_array($post->post_type, ['angestellte', 'kunde'])) {
            return;
        }
        
        $this->info($update ? 'Post Updated' : 'Post Created', [
            'post_id' => $post_id,
            'post_type' => $post->post_type,
            'post_status' => $post->post_status,
            'post_author' => $post->post_author,
            'is_update' => $update,
            'meta_data' => $this->get_post_meta_for_logging($post_id)
        ], ['type' => 'post_operation']);
    }
    
    public function log_post_delete($post_id)
    {
        $post = get_post($post_id);
        
        if ($post && in_array($post->post_type, ['angestellte', 'kunde'])) {
            $this->warning('Post Deleted', [
                'post_id' => $post_id,
                'post_type' => $post->post_type,
                'post_title' => $post->post_title,
                'post_author' => $post->post_author
            ], ['type' => 'post_operation']);
        }
    }
    
    /**
     * Track admin page access
     */
    public function log_admin_page_access()
    {
        if (!is_admin()) {
            return;
        }
        
        $screen = get_current_screen();
        $page = $_GET['page'] ?? '';
        
        // Only log RT Employee Manager related pages
        if (strpos($page, 'rt-employee') !== false || 
            ($screen && (strpos($screen->id, 'angestellte') !== false || strpos($screen->id, 'kunde') !== false))) {
            
            $this->debug('Admin Page Access', [
                'screen_id' => $screen ? $screen->id : 'unknown',
                'page' => $page,
                'post_type' => $screen ? $screen->post_type : null,
                'action' => $_GET['action'] ?? null
            ], ['type' => 'admin_access']);
        }
    }
    
    /**
     * Track WordPress errors
     */
    public function log_wp_die($handler)
    {
        $this->error('WordPress Die Called', [
            'handler' => $handler,
            'backtrace' => $this->get_filtered_backtrace(5)
        ], ['type' => 'error']);
        
        return $handler;
    }
    
    /**
     * Helper methods
     */
    private function get_client_ip()
    {
        $ip_headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];
        
        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    private function sanitize_sensitive_data($data)
    {
        $sensitive_keys = [
            'password', 'pwd', 'pass', 'token', 'secret', 'key', 'auth',
            'sozialversicherungsnummer', 'svnr', 'ssn', 'credit_card', 'cc'
        ];
        
        $sanitized = $data;
        
        array_walk_recursive($sanitized, function(&$value, $key) use ($sensitive_keys) {
            foreach ($sensitive_keys as $sensitive_key) {
                if (stripos($key, $sensitive_key) !== false) {
                    $value = '[REDACTED]';
                    break;
                }
            }
        });
        
        return $sanitized;
    }
    
    private function get_filtered_backtrace($limit = 10)
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $limit);
        
        // Filter out this class from backtrace
        $filtered = array_filter($backtrace, function($trace) {
            return !isset($trace['class']) || $trace['class'] !== __CLASS__;
        });
        
        // Return simplified backtrace
        return array_map(function($trace) {
            return [
                'file' => isset($trace['file']) ? basename($trace['file']) : 'unknown',
                'line' => $trace['line'] ?? 'unknown',
                'function' => $trace['function'] ?? 'unknown',
                'class' => $trace['class'] ?? null
            ];
        }, array_slice($filtered, 0, 5));
    }
    
    private function format_bytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    private function get_query_execution_time($query)
    {
        $start_time = microtime(true);
        // This is a simplified approach - in production you'd want more sophisticated query timing
        return round((microtime(true) - $start_time) * 1000, 2) . 'ms';
    }
    
    private function get_post_meta_for_logging($post_id)
    {
        $meta = get_post_meta($post_id);
        
        // Only include RT Employee Manager related meta
        $filtered_meta = array();
        $relevant_keys = [
            'vorname', 'nachname', 'status', 'employer_id', 'eintrittsdatum',
            'bezeichnung_der_tatigkeit', 'art_des_dienstverhaltnisses'
        ];
        
        foreach ($relevant_keys as $key) {
            if (isset($meta[$key])) {
                $filtered_meta[$key] = $meta[$key][0] ?? null;
            }
        }
        
        return $filtered_meta;
    }
    
    private function rotate_log_file()
    {
        if (file_exists($this->log_file)) {
            $rotated_file = $this->log_file . '.old';
            rename($this->log_file, $rotated_file);
        }
    }
    
    private function log_to_database($log_entry)
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'rt_employee_logs';
        
        $wpdb->insert(
            $table_name,
            array(
                'employee_id' => 0, // System log
                'action' => $log_entry['level'] . ': ' . $log_entry['message'],
                'details' => json_encode([
                    'data' => $log_entry['data'],
                    'context' => $log_entry['context'],
                    'memory_usage' => $log_entry['memory_usage']
                ]),
                'user_id' => $log_entry['user_id'],
                'ip_address' => $log_entry['user_ip'],
                'created_at' => $log_entry['timestamp']
            ),
            array('%d', '%s', '%s', '%d', '%s', '%s')
        );
    }
    
    public function cleanup_old_logs()
    {
        $upload_dir = wp_upload_dir();
        $logs_dir = $upload_dir['basedir'] . '/rt-employee-logs';
        
        if (!file_exists($logs_dir)) {
            return;
        }
        
        $files = glob($logs_dir . '/rt-employee-debug-*.log');
        $cutoff_time = strtotime("-{$this->log_retention_days} days");
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_time) {
                unlink($file);
            }
        }
    }
    
    /**
     * Get recent logs for admin display
     */
    public function get_recent_logs($limit = 100, $level = null)
    {
        if (!file_exists($this->log_file)) {
            return array();
        }
        
        $lines = file($this->log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $logs = array();
        
        // Get last N lines
        $lines = array_slice($lines, -$limit);
        
        foreach ($lines as $line) {
            $log_entry = json_decode($line, true);
            
            if ($log_entry && (!$level || $log_entry['level'] === strtoupper($level))) {
                $logs[] = $log_entry;
            }
        }
        
        return array_reverse($logs); // Most recent first
    }
}

// Initialize the debug logger
function rt_employee_debug()
{
    return RT_Employee_Manager_Debug_Logger::get_instance();
}