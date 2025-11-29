<?php

/**
 * RT Employee Manager Data Manager
 * Centralized handling of employee data saving and validation
 */

// Security check
if (!defined('ABSPATH')) {
    exit('Direct access forbidden.');
}

class RT_Employee_Manager_Data_Manager
{
    private static $instance = null;
    private $is_saving = false;
    
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct()
    {
        // Priority hooks to ensure data is saved properly
        add_action('save_post_angestellte', array($this, 'save_employee_data'), 5, 2);
        add_action('acf/save_post', array($this, 'handle_acf_save'), 5);
        
        // Debug what's happening during save
        add_action('save_post', array($this, 'debug_save_process'), 1, 2);
        
        // Fix post status issues
        add_action('wp_insert_post_data', array($this, 'fix_post_status'), 10, 2);
        
        // Ensure proper employer assignment
        add_action('transition_post_status', array($this, 'ensure_employer_assignment'), 10, 3);
    }
    
    /**
     * Debug the entire save process
     */
    public function debug_save_process($post_id, $post)
    {
        if ($post->post_type !== 'angestellte') {
            return;
        }
        
        rt_employee_debug()->info('Save Process Debug', [
            'post_id' => $post_id,
            'post_status' => $post->post_status,
            'post_title' => $post->post_title,
            'is_autosave' => wp_is_post_autosave($post_id),
            'is_revision' => wp_is_post_revision($post_id),
            'current_user' => get_current_user_id(),
            'post_data' => $_POST,
            'hook' => current_filter()
        ], ['type' => 'save_debug']);
    }
    
    /**
     * Main employee data save handler
     */
    public function save_employee_data($post_id, $post)
    {
        // Prevent infinite loops
        if ($this->is_saving) {
            return;
        }
        
        // Skip autosaves and revisions
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            rt_employee_debug()->security('Unauthorized attempt to save employee data', [
                'post_id' => $post_id,
                'user_id' => get_current_user_id()
            ]);
            return;
        }
        
        $this->is_saving = true;
        
        rt_employee_debug()->info('Employee Data Save Started', [
            'post_id' => $post_id,
            'post_status' => $post->post_status,
            'user_id' => get_current_user_id()
        ], ['type' => 'employee_save']);
        
        try {
            // Process the save
            $this->process_employee_save($post_id, $post);
            
            rt_employee_debug()->info('Employee Data Save Completed Successfully', [
                'post_id' => $post_id
            ]);
            
        } catch (Exception $e) {
            rt_employee_debug()->error('Employee Data Save Failed', [
                'post_id' => $post_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        } finally {
            $this->is_saving = false;
        }
    }
    
    /**
     * Process actual employee save
     */
    private function process_employee_save($post_id, $post)
    {
        // 1. Handle basic employee data
        $this->save_basic_employee_data($post_id);
        
        // 2. Update post title
        $this->update_employee_title($post_id);
        
        // 3. Ensure proper status
        $this->ensure_proper_status($post_id);
        
        // 4. Set employer relationship
        $this->ensure_employer_assignment($post_id);
        
        // 5. Validate and clean data
        $this->validate_and_clean_data($post_id);
        
        // 6. Clear caches
        $this->clear_related_caches($post_id);
        
        // 7. Log the save
        $this->log_employee_save($post_id);
    }
    
    /**
     * Save basic employee data from form submission
     */
    private function save_basic_employee_data($post_id)
    {
        $fields_to_save = [
            'vorname' => 'sanitize_text_field',
            'nachname' => 'sanitize_text_field',
            'sozialversicherungsnummer' => array($this, 'sanitize_svnr'),
            'email' => 'sanitize_email',
            'telefon' => 'sanitize_text_field',
            'geburtsdatum' => array($this, 'sanitize_date'),
            'eintrittsdatum' => array($this, 'sanitize_date'),
            'bezeichnung_der_tatigkeit' => 'sanitize_text_field',
            'abteilung' => 'sanitize_text_field',
            'gehaltlohn' => array($this, 'sanitize_currency'),
            'art_des_dienstverhaltnisses' => 'sanitize_text_field',
            'staatsangehoerigkeit' => 'sanitize_text_field',
            'personenstand' => 'sanitize_text_field',
            'arbeitszeit_pro_woche' => array($this, 'sanitize_hours'),
            'anmerkungen' => 'sanitize_textarea_field'
        ];
        
        $saved_fields = [];
        
        foreach ($fields_to_save as $field_name => $sanitize_function) {
            $value = '';
            
            // Get value from different sources
            if (isset($_POST[$field_name])) {
                $value = $_POST[$field_name];
            } elseif (isset($_POST['acf']) && isset($_POST['acf']['field_' . $field_name])) {
                $value = $_POST['acf']['field_' . $field_name];
            }
            
            // Skip empty values
            if (empty($value)) {
                continue;
            }
            
            // Sanitize the value
            if (is_callable($sanitize_function)) {
                $clean_value = call_user_func($sanitize_function, $value);
            } else {
                $clean_value = sanitize_text_field($value);
            }
            
            if (!empty($clean_value)) {
                // Save both as post meta and ACF field
                update_post_meta($post_id, $field_name, $clean_value);
                
                if (function_exists('update_field')) {
                    update_field($field_name, $clean_value, $post_id);
                }
                
                $saved_fields[$field_name] = $clean_value;
                
                rt_employee_debug()->debug('Saved employee field', [
                    'post_id' => $post_id,
                    'field' => $field_name,
                    'value' => $clean_value
                ]);
            }
        }
        
        rt_employee_debug()->info('Basic employee data saved', [
            'post_id' => $post_id,
            'fields_saved' => array_keys($saved_fields),
            'field_count' => count($saved_fields)
        ]);
        
        return $saved_fields;
    }
    
    /**
     * Update employee post title
     */
    private function update_employee_title($post_id)
    {
        $vorname = $this->get_employee_field($post_id, 'vorname');
        $nachname = $this->get_employee_field($post_id, 'nachname');
        
        if ($vorname && $nachname) {
            $title = $vorname . ' ' . $nachname;
            
            // Prevent infinite loop by removing our hook temporarily
            remove_action('save_post_angestellte', array($this, 'save_employee_data'), 5);
            
            wp_update_post([
                'ID' => $post_id,
                'post_title' => $title
            ]);
            
            // Re-add our hook
            add_action('save_post_angestellte', array($this, 'save_employee_data'), 5, 2);
            
            rt_employee_debug()->info('Employee title updated', [
                'post_id' => $post_id,
                'title' => $title
            ]);
        }
    }
    
    /**
     * Ensure proper post status
     */
    private function ensure_proper_status($post_id)
    {
        $post = get_post($post_id);
        $current_status = $post->post_status;
        
        // If this is an auto-draft or draft with complete data, publish it
        if (in_array($current_status, ['auto-draft', 'draft'])) {
            $vorname = $this->get_employee_field($post_id, 'vorname');
            $nachname = $this->get_employee_field($post_id, 'nachname');
            
            if ($vorname && $nachname) {
                // Remove our hook to prevent infinite loop
                remove_action('save_post_angestellte', array($this, 'save_employee_data'), 5);
                
                wp_update_post([
                    'ID' => $post_id,
                    'post_status' => 'publish'
                ]);
                
                // Re-add our hook
                add_action('save_post_angestellte', array($this, 'save_employee_data'), 5, 2);
                
                rt_employee_debug()->info('Employee post published', [
                    'post_id' => $post_id,
                    'old_status' => $current_status,
                    'new_status' => 'publish'
                ]);
            }
        }
    }
    
    /**
     * Ensure employer assignment
     */
    public function ensure_employer_assignment($post_id, $new_status = null, $old_status = null)
    {
        // Handle both direct calls and transition_post_status hook
        if (is_string($post_id)) {
            // Called from transition_post_status hook
            $post_id = func_get_arg(2); // Third argument is post_id
            $post = get_post($post_id);
        } else {
            $post = get_post($post_id);
        }
        
        if (!$post || $post->post_type !== 'angestellte') {
            return;
        }
        
        $employer_id = $this->get_employee_field($post_id, 'employer_id');
        
        if (empty($employer_id)) {
            $current_user_id = get_current_user_id();
            
            if ($current_user_id) {
                update_post_meta($post_id, 'employer_id', $current_user_id);
                
                if (function_exists('update_field')) {
                    update_field('employer_id', $current_user_id, $post_id);
                }
                
                rt_employee_debug()->info('Employer ID assigned', [
                    'post_id' => $post_id,
                    'employer_id' => $current_user_id
                ]);
            }
        }
        
        // Also ensure status is set
        $status = $this->get_employee_field($post_id, 'status');
        if (empty($status)) {
            update_post_meta($post_id, 'status', 'active');
            
            if (function_exists('update_field')) {
                update_field('status', 'active', $post_id);
            }
        }
    }
    
    /**
     * Validate and clean employee data
     */
    private function validate_and_clean_data($post_id)
    {
        // Clean SVNR
        $svnr = $this->get_employee_field($post_id, 'sozialversicherungsnummer');
        if ($svnr) {
            $clean_svnr = $this->sanitize_svnr($svnr);
            if ($clean_svnr !== $svnr) {
                update_post_meta($post_id, 'sozialversicherungsnummer', $clean_svnr);
                if (function_exists('update_field')) {
                    update_field('sozialversicherungsnummer', $clean_svnr, $post_id);
                }
            }
        }
        
        // Validate dates
        $dates = ['geburtsdatum', 'eintrittsdatum'];
        foreach ($dates as $date_field) {
            $date_value = $this->get_employee_field($post_id, $date_field);
            if ($date_value) {
                $clean_date = $this->sanitize_date($date_value);
                if ($clean_date !== $date_value) {
                    update_post_meta($post_id, $date_field, $clean_date);
                    if (function_exists('update_field')) {
                        update_field($date_field, $clean_date, $post_id);
                    }
                }
            }
        }
    }
    
    /**
     * Clear related caches
     */
    private function clear_related_caches($post_id)
    {
        $employer_id = $this->get_employee_field($post_id, 'employer_id');
        
        if ($employer_id) {
            wp_cache_delete("employee_stats_$employer_id", 'rt_employee_manager');
            clean_user_cache($employer_id);
        }
        
        wp_cache_delete($post_id, 'post_meta');
        clean_post_cache($post_id);
    }
    
    /**
     * Log employee save activity
     */
    private function log_employee_save($post_id)
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'rt_employee_logs';
        
        $employee_name = $this->get_employee_field($post_id, 'vorname') . ' ' . $this->get_employee_field($post_id, 'nachname');
        
        $wpdb->insert(
            $table_name,
            [
                'employee_id' => $post_id,
                'action' => 'employee_data_saved',
                'details' => "Employee data saved/updated: $employee_name",
                'user_id' => get_current_user_id(),
                'ip_address' => $this->get_client_ip(),
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%d', '%s', '%s']
        );
    }
    
    /**
     * Handle ACF save hook
     */
    public function handle_acf_save($post_id)
    {
        $post = get_post($post_id);
        
        if (!$post || $post->post_type !== 'angestellte') {
            return;
        }
        
        // Skip if we're already processing this save
        if ($this->is_saving) {
            return;
        }
        
        rt_employee_debug()->info('ACF Save Hook Triggered', [
            'post_id' => $post_id,
            'post_status' => $post->post_status
        ]);
        
        // Let our main save handler take care of everything
        $this->save_employee_data($post_id, $post);
    }
    
    /**
     * Fix post status during insert
     */
    public function fix_post_status($data, $postarr)
    {
        if ($data['post_type'] !== 'angestellte') {
            return $data;
        }
        
        // Don't auto-publish empty posts
        if (empty($data['post_title']) || $data['post_title'] === 'Auto Draft') {
            $data['post_status'] = 'draft';
        }
        
        return $data;
    }
    
    // Utility methods
    
    /**
     * Get employee field value (try both ACF and post meta)
     */
    private function get_employee_field($post_id, $field_name)
    {
        // Try ACF first
        if (function_exists('get_field')) {
            $value = get_field($field_name, $post_id);
            if (!empty($value)) {
                return $value;
            }
        }
        
        // Fall back to post meta
        return get_post_meta($post_id, $field_name, true);
    }
    
    /**
     * Sanitize SVNR
     */
    private function sanitize_svnr($svnr)
    {
        $clean = preg_replace('/\D/', '', $svnr);
        if (strlen($clean) === 10) {
            return $clean;
        }
        return '';
    }
    
    /**
     * Sanitize date
     */
    private function sanitize_date($date)
    {
        $timestamp = strtotime($date);
        if ($timestamp) {
            return date('Y-m-d', $timestamp);
        }
        return '';
    }
    
    /**
     * Sanitize currency
     */
    private function sanitize_currency($amount)
    {
        $clean = preg_replace('/[^\d.,]/', '', $amount);
        $clean = str_replace(',', '.', $clean);
        return floatval($clean);
    }
    
    /**
     * Sanitize hours
     */
    private function sanitize_hours($hours)
    {
        return floatval($hours);
    }
    
    /**
     * Get client IP
     */
    private function get_client_ip()
    {
        $ip_headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}