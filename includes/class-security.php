<?php

if (!defined('ABSPATH')) {
    exit;
}

class RT_Employee_Manager_Security {
    
    public function __construct() {
        add_action('init', array($this, 'init_security'));
        add_filter('user_has_cap', array($this, 'filter_user_capabilities'), 10, 4);
        add_action('pre_get_posts', array($this, 'restrict_posts_query'));
        add_action('load-edit.php', array($this, 'restrict_admin_access'));
        add_action('wp_ajax_nopriv_rt_employee_action', array($this, 'block_unauthorized_ajax'));
        add_filter('posts_where', array($this, 'restrict_posts_where'), 10, 2);
        add_action('wp_login', array($this, 'log_user_login'), 10, 2);
        add_action('wp_logout', array($this, 'log_user_logout'));
        add_filter('authenticate', array($this, 'check_user_status'), 30, 3);
    }
    
    /**
     * Initialize security measures
     */
    public function init_security() {
        // Remove unnecessary WordPress features for security
        remove_action('wp_head', 'wp_generator');
        remove_action('wp_head', 'wlwmanifest_link');
        remove_action('wp_head', 'rsd_link');
        
        // Add security headers
        add_action('send_headers', array($this, 'add_security_headers'));
        
        // Rate limiting for form submissions
        add_action('gform_pre_submission', array($this, 'rate_limit_submissions'));
        
        // Data sanitization
        add_filter('gform_pre_submission_filter', array($this, 'sanitize_form_data'));
    }
    
    /**
     * Add security headers
     */
    public function add_security_headers() {
        if (!headers_sent()) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('X-XSS-Protection: 1; mode=block');
            header('Referrer-Policy: strict-origin-when-cross-origin');
        }
    }
    
    /**
     * Filter user capabilities based on context
     */
    public function filter_user_capabilities($allcaps, $caps, $args, $user) {
        // Restrict client users to only their own employees
        if (isset($user->roles) && in_array('kunden', $user->roles)) {
            
            // If user is trying to edit/delete posts
            if (isset($args[0]) && in_array($args[0], array('edit_post', 'delete_post'))) {
                $post_id = isset($args[2]) ? $args[2] : 0;
                
                if ($post_id && get_post_type($post_id) === 'angestellte') {
                    $employer_id = get_post_meta($post_id, 'employer_id', true);
                    
                    // Only allow if this is their employee
                    if ($employer_id != $user->ID) {
                        $allcaps[$args[0]] = false;
                    }
                }
            }
            
            // Restrict access to other users' client posts
            if (isset($args[0]) && $args[0] === 'edit_others_posts') {
                $allcaps['edit_others_posts'] = false;
            }
        }
        
        return $allcaps;
    }
    
    /**
     * Restrict posts query to user's own employees
     */
    public function restrict_posts_query($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }
        
        $current_user = wp_get_current_user();
        
        // Only restrict for client users
        if (!in_array('kunden', $current_user->roles) || current_user_can('manage_options')) {
            return;
        }
        
        global $pagenow;
        
        // Restrict on employee listing page
        if ($pagenow === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'angestellte') {
            $query->set('meta_query', array(
                array(
                    'key' => 'employer_id',
                    'value' => $current_user->ID,
                    'compare' => '='
                )
            ));
        }
    }
    
    /**
     * Restrict admin access
     */
    public function restrict_admin_access() {
        global $pagenow;
        
        $current_user = wp_get_current_user();
        
        // Block client users from accessing other post types
        if (in_array('kunden', $current_user->roles) && !current_user_can('manage_options')) {
            
            if ($pagenow === 'edit.php') {
                $post_type = isset($_GET['post_type']) ? $_GET['post_type'] : 'post';
                
                // Only allow access to their employee posts
                if (!in_array($post_type, array('angestellte'))) {
                    wp_die(__('Sie haben keine Berechtigung für diese Seite.', 'rt-employee-manager'));
                }
            }
        }
    }
    
    /**
     * Block unauthorized AJAX requests
     */
    public function block_unauthorized_ajax() {
        wp_die(__('Nicht autorisiert', 'rt-employee-manager'), 403);
    }
    
    /**
     * Restrict posts WHERE clause for additional security
     */
    public function restrict_posts_where($where, $query) {
        global $wpdb;
        
        if (!is_admin() || !$query->is_main_query()) {
            return $where;
        }
        
        $current_user = wp_get_current_user();
        
        // Only apply to client users on employee queries
        if (in_array('kunden', $current_user->roles) && !current_user_can('manage_options')) {
            
            if ($query->get('post_type') === 'angestellte') {
                $where .= $wpdb->prepare(
                    " AND {$wpdb->posts}.ID IN (
                        SELECT post_id FROM {$wpdb->postmeta} 
                        WHERE meta_key = 'employer_id' 
                        AND meta_value = %d
                    )",
                    $current_user->ID
                );
            }
        }
        
        return $where;
    }
    
    /**
     * Rate limit form submissions
     */
    public function rate_limit_submissions($form) {
        $ip_address = $this->get_client_ip();
        $cache_key = 'rt_form_rate_limit_' . md5($ip_address);
        
        $submissions = get_transient($cache_key) ?: 0;
        
        // Allow maximum 5 submissions per hour per IP
        if ($submissions >= 5) {
            wp_die(__('Zu viele Versuche. Bitte warten Sie eine Stunde.', 'rt-employee-manager'), 429);
        }
        
        set_transient($cache_key, $submissions + 1, HOUR_IN_SECONDS);
    }
    
    /**
     * Sanitize form data
     */
    public function sanitize_form_data($form) {
        foreach ($form['fields'] as $field) {
            if (isset($_POST['input_' . $field->id])) {
                $value = $_POST['input_' . $field->id];
                
                // Sanitize based on field type
                switch ($field->type) {
                    case 'email':
                        $_POST['input_' . $field->id] = sanitize_email($value);
                        break;
                    case 'text':
                    case 'textarea':
                        $_POST['input_' . $field->id] = sanitize_text_field($value);
                        break;
                    case 'number':
                        $_POST['input_' . $field->id] = floatval($value);
                        break;
                    case 'phone':
                        $_POST['input_' . $field->id] = preg_replace('/[^0-9+\-\s()]/', '', $value);
                        break;
                }
                
                // Check for suspicious patterns
                if ($this->contains_suspicious_content($value)) {
                    wp_die(__('Verdächtiger Inhalt erkannt.', 'rt-employee-manager'), 400);
                }
            }
        }
        
        return $form;
    }
    
    /**
     * Check for suspicious content
     */
    private function contains_suspicious_content($content) {
        $suspicious_patterns = array(
            '/<script[^>]*>.*?<\/script>/is',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/\beval\s*\(/i',
            '/\bdocument\.(write|writeln|cookie)\b/i',
            '/\bwindow\.(location|open)\b/i',
            '/<iframe/i',
            '/<object/i',
            '/<embed/i',
        );
        
        foreach ($suspicious_patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Log user login
     */
    public function log_user_login($user_login, $user) {
        if (in_array('kunden', $user->roles)) {
            $this->log_security_event('user_login', array(
                'user_id' => $user->ID,
                'user_login' => $user_login,
                'ip_address' => $this->get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ));
        }
    }
    
    /**
     * Log user logout
     */
    public function log_user_logout() {
        $current_user = wp_get_current_user();
        
        if (in_array('kunden', $current_user->roles)) {
            $this->log_security_event('user_logout', array(
                'user_id' => $current_user->ID,
                'user_login' => $current_user->user_login,
                'ip_address' => $this->get_client_ip()
            ));
        }
    }
    
    /**
     * Check user account status
     */
    public function check_user_status($user, $username, $password) {
        if (is_wp_error($user)) {
            return $user;
        }
        
        if ($user && isset($user->ID)) {
            $account_status = get_user_meta($user->ID, 'account_status', true);
            
            if ($account_status === 'suspended') {
                return new WP_Error('account_suspended', __('Ihr Konto wurde gesperrt.', 'rt-employee-manager'));
            }
            
            if ($account_status === 'pending') {
                return new WP_Error('account_pending', __('Ihr Konto ist noch nicht aktiviert.', 'rt-employee-manager'));
            }
            
            // Check for too many employees (over limit)
            if (in_array('kunden', $user->roles)) {
                $employee_count = $this->get_user_employee_count($user->ID);
                $limit = get_user_meta($user->ID, 'employee_limit', true) ?: 50;
                
                if ($employee_count > $limit * 1.1) { // 10% buffer
                    $this->log_security_event('account_over_limit', array(
                        'user_id' => $user->ID,
                        'employee_count' => $employee_count,
                        'limit' => $limit
                    ));
                }
            }
        }
        
        return $user;
    }
    
    /**
     * Get user employee count
     */
    private function get_user_employee_count($user_id) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = 'employer_id' 
             AND pm.meta_value = %d
             AND p.post_type = 'angestellte'
             AND p.post_status = 'publish'",
            $user_id
        ));
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_fields = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_fields as $field) {
            if (!empty($_SERVER[$field])) {
                $ip = $_SERVER[$field];
                
                // Handle comma-separated IPs
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Log security events
     */
    private function log_security_event($event_type, $data = array()) {
        if (!get_option('rt_employee_manager_enable_logging')) {
            return;
        }
        
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'rt_employee_logs';
        
        $wpdb->insert(
            $table_name,
            array(
                'employee_id' => 0,
                'action' => $event_type,
                'details' => wp_json_encode($data),
                'user_id' => isset($data['user_id']) ? $data['user_id'] : get_current_user_id(),
                'ip_address' => isset($data['ip_address']) ? $data['ip_address'] : $this->get_client_ip(),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%d', '%s', '%s')
        );
    }
    
    /**
     * Validate SVNR format and checksum
     */
    public function validate_svnr($svnr) {
        // Remove all non-digit characters
        $cleaned = preg_replace('/\D/', '', $svnr);
        
        // Check length
        if (strlen($cleaned) !== 10) {
            return false;
        }
        
        // Check Austrian SVNR checksum
        $digits = str_split($cleaned);
        $weights = [3, 7, 9, 5, 8, 4, 2, 1, 6];
        $sum = 0;
        
        for ($i = 0; $i < 9; $i++) {
            $sum += intval($digits[$i]) * $weights[$i];
        }
        
        $checkDigit = ($sum % 11) === 10 ? 0 : $sum % 11;
        
        return $checkDigit === intval($digits[9]);
    }
    
    /**
     * Encrypt sensitive data
     */
    public function encrypt_data($data) {
        if (!function_exists('openssl_encrypt')) {
            return base64_encode($data); // Fallback
        }
        
        $key = wp_salt('secure_auth');
        $iv = openssl_random_pseudo_bytes(16);
        
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt sensitive data
     */
    public function decrypt_data($encrypted_data) {
        if (!function_exists('openssl_decrypt')) {
            return base64_decode($encrypted_data); // Fallback
        }
        
        $key = wp_salt('secure_auth');
        $data = base64_decode($encrypted_data);
        
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
    
    /**
     * Clean up old logs
     */
    public function cleanup_old_logs() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'rt_employee_logs';
        
        // Delete logs older than 90 days
        $wpdb->query(
            "DELETE FROM {$table_name} 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
        );
    }
    
    /**
     * Get security report
     */
    public function get_security_report($days = 7) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'rt_employee_logs';
        
        $report = $wpdb->get_results($wpdb->prepare(
            "SELECT action, COUNT(*) as count 
             FROM {$table_name} 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY action
             ORDER BY count DESC",
            $days
        ), ARRAY_A);
        
        return $report;
    }
}

// Schedule cleanup task
if (!wp_next_scheduled('rt_employee_manager_cleanup_logs')) {
    wp_schedule_event(time(), 'daily', 'rt_employee_manager_cleanup_logs');
}

add_action('rt_employee_manager_cleanup_logs', function() {
    $security = new RT_Employee_Manager_Security();
    $security->cleanup_old_logs();
});