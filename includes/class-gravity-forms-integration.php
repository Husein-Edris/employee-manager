<?php

if (!defined('ABSPATH')) {
    exit;
}

class RT_Employee_Manager_Gravity_Forms_Integration {
    
    public function __construct() {
        // Field pre-population for employer data
        add_filter('gform_field_value', array($this, 'populate_employer_fields'), 10, 3);
        
        // SVNR validation
        add_filter('gform_field_validation', array($this, 'validate_svnr'), 10, 4);
        
        // Track employee post creation
        add_action('gform_after_submission', array($this, 'track_employee_creation'), 10, 2);
        
        // Post creation hooks
        add_action('gform_post_submission', array($this, 'after_post_creation'), 10, 2);
        
        // Enqueue scripts
        add_action('gform_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Add CSS class to SVNR field
        add_filter('gform_field_css_class', array($this, 'add_svnr_css_class'), 10, 3);
        
        // User registration integration
        add_action('gform_user_registered', array($this, 'after_user_registration'), 10, 4);
    }
    
    /**
     * Pre-populate employer fields with user meta data
     */
    public function populate_employer_fields($value, $field, $name) {
        if (!is_user_logged_in()) {
            return $value;
        }
        
        $user = wp_get_current_user();
        
        // Map field IDs to user meta keys
        $field_mapping = array(
            23 => 'company_name',      // Firmenname
            24 => 'uid_number',        // UID-Nummer
            10 => 'phone',             // Telefonnummer
            '21.1' => 'address_street',    // Street
            '21.3' => 'address_city',      // City
            '21.5' => 'address_postcode',  // ZIP
            '21.6' => 'address_country',   // Country
        );
        
        $field_id = is_array($field) && isset($field->id) ? $field->id : (is_object($field) ? $field->id : null);
        
        if (isset($field_mapping[$field_id])) {
            $meta_value = get_user_meta($user->ID, $field_mapping[$field_id], true);
            return !empty($meta_value) ? $meta_value : $value;
        }
        
        return $value;
    }
    
    /**
     * Validate Austrian Social Security Number (SVNR)
     */
    public function validate_svnr($result, $value, $form, $field) {
        // Check if this is the SVNR field (ID 53) or has svnr-field class
        if ($field->id == 53 || strpos($field->cssClass, 'svnr-field') !== false) {
            
            if (empty($value)) {
                return $result; // Let required field validation handle empty values
            }
            
            // Remove all non-digit characters
            $cleaned = preg_replace('/\D/', '', $value);
            
            // Length validation
            if (strlen($cleaned) !== 10) {
                $result['is_valid'] = false;
                $result['message'] = __('Die Sozialversicherungsnummer muss 10 Ziffern enthalten.', 'rt-employee-manager');
                return $result;
            }
            
            // Checksum validation using Austrian algorithm
            if (!$this->validate_svnr_checksum($cleaned)) {
                $result['is_valid'] = false;
                $result['message'] = __('Ungültige Sozialversicherungsnummer.', 'rt-employee-manager');
                return $result;
            }
            
            // Check for duplicates
            if ($this->svnr_exists($cleaned)) {
                $result['is_valid'] = false;
                $result['message'] = __('Diese Sozialversicherungsnummer ist bereits registriert.', 'rt-employee-manager');
                return $result;
            }
        }
        
        return $result;
    }
    
    /**
     * Validate SVNR checksum using Austrian algorithm
     */
    private function validate_svnr_checksum($svnr) {
        if (strlen($svnr) !== 10) {
            return false;
        }
        
        $digits = str_split($svnr);
        $weights = [3, 7, 9, 5, 8, 4, 2, 1, 6];
        $sum = 0;
        
        for ($i = 0; $i < 9; $i++) {
            $sum += intval($digits[$i]) * $weights[$i];
        }
        
        $checkDigit = ($sum % 11) === 10 ? 0 : $sum % 11;
        
        return $checkDigit === intval($digits[9]);
    }
    
    /**
     * Check if SVNR already exists
     */
    private function svnr_exists($svnr) {
        global $wpdb;
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
             WHERE meta_key = 'sozialversicherungsnummer' 
             AND meta_value = %s",
            $svnr
        ));
        
        return $exists > 0;
    }
    
    /**
     * Track employee post creation
     */
    public function track_employee_creation($entry, $form) {
        // Only track employee form (form ID 1)
        if ($form['id'] != 1) {
            return;
        }
        
        $this->log_debug('Employee form submission received', array(
            'entry_id' => $entry['id'],
            'form_id' => $form['id']
        ));
        
        // Wait a moment for post creation to complete
        wp_schedule_single_event(time() + 5, 'rt_verify_employee_post_creation', array($entry['id']));
    }
    
    /**
     * Verify employee post was created correctly
     */
    public function verify_employee_post_creation($entry_id) {
        $entry = GFAPI::get_entry($entry_id);
        
        if (is_wp_error($entry)) {
            $this->log_error('Failed to retrieve entry', array('entry_id' => $entry_id));
            return;
        }
        
        // Search for created employee post
        $posts = get_posts(array(
            'post_type' => 'angestellte',
            'posts_per_page' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => 'vorname',
                    'value' => rgar($entry, '28'),
                    'compare' => '='
                ),
                array(
                    'key' => 'nachname',
                    'value' => rgar($entry, '27'),
                    'compare' => '='
                ),
                array(
                    'key' => 'sozialversicherungsnummer',
                    'value' => preg_replace('/\D/', '', rgar($entry, '53')),
                    'compare' => '='
                )
            )
        ));
        
        if (empty($posts)) {
            $this->log_error('Employee post not created', array(
                'entry_id' => $entry_id,
                'vorname' => rgar($entry, '28'),
                'nachname' => rgar($entry, '27')
            ));
            
            GFAPI::add_note($entry_id, 0, 'RT Employee Manager', 'ERROR: Employee post was not created.');
        } else {
            $post = $posts[0];
            $this->log_success('Employee post created successfully', array(
                'entry_id' => $entry_id,
                'post_id' => $post->ID
            ));
            
            // Verify all required meta fields
            $this->verify_post_meta($post->ID, $entry);
            
            GFAPI::add_note($entry_id, 0, 'RT Employee Manager', sprintf('SUCCESS: Employee post #%d created.', $post->ID));
        }
    }
    
    /**
     * Verify post meta fields
     */
    private function verify_post_meta($post_id, $entry) {
        $required_fields = array(
            'vorname' => rgar($entry, '28'),
            'nachname' => rgar($entry, '27'),
            'sozialversicherungsnummer' => preg_replace('/\D/', '', rgar($entry, '53')),
            'employer_id' => rgar($entry, 'created_by') ?: get_current_user_id(),
            'status' => 'active'
        );
        
        $missing_fields = array();
        
        foreach ($required_fields as $key => $expected_value) {
            $actual_value = get_post_meta($post_id, $key, true);
            
            if (empty($actual_value) && !empty($expected_value)) {
                $missing_fields[] = $key;
                // Try to fix missing meta
                update_post_meta($post_id, $key, $expected_value);
                $this->log_debug('Fixed missing meta field', array(
                    'post_id' => $post_id,
                    'meta_key' => $key,
                    'meta_value' => $expected_value
                ));
            }
        }
        
        if (!empty($missing_fields)) {
            $this->log_warning('Fixed missing meta fields', array(
                'post_id' => $post_id,
                'missing_fields' => $missing_fields
            ));
        }
    }
    
    /**
     * After post creation hook
     */
    public function after_post_creation($entry, $form) {
        if ($form['id'] == 1) { // Employee form
            $this->send_employee_notification($entry, $form);
        } elseif ($form['id'] == 3) { // Client form
            $this->send_client_notification($entry, $form);
        }
    }
    
    /**
     * After user registration
     */
    public function after_user_registration($user_id, $user_config, $entry, $password) {
        // Save additional user meta from form
        if (!empty($entry)) {
            $this->save_user_meta_from_entry($user_id, $entry);
        }
        
        $this->log_success('User registered successfully', array(
            'user_id' => $user_id,
            'entry_id' => $entry['id']
        ));
    }
    
    /**
     * Save user meta from form entry
     */
    private function save_user_meta_from_entry($user_id, $entry) {
        $meta_mapping = array(
            'company_name' => rgar($entry, '23'),
            'uid_number' => rgar($entry, '24'),
            'phone' => rgar($entry, '10'),
            'address_street' => rgar($entry, '21.1'),
            'address_city' => rgar($entry, '21.3'),
            'address_postcode' => rgar($entry, '21.5'),
            'address_country' => rgar($entry, '21.6')
        );
        
        foreach ($meta_mapping as $meta_key => $value) {
            if (!empty($value)) {
                update_user_meta($user_id, $meta_key, sanitize_text_field($value));
            }
        }
    }
    
    /**
     * Add CSS class to SVNR field
     */
    public function add_svnr_css_class($classes, $field, $form) {
        if ($field->id == 53) { // SVNR field ID
            $classes .= ' svnr-field';
        }
        return $classes;
    }
    
    /**
     * Enqueue scripts for SVNR formatting
     */
    public function enqueue_scripts($form, $is_ajax = false) {
        if ($form['id'] == 1) { // Only for employee form
            wp_enqueue_script(
                'rt-employee-svnr-formatting',
                RT_EMPLOYEE_MANAGER_PLUGIN_URL . 'assets/js/svnr-formatting.js',
                array('jquery'),
                RT_EMPLOYEE_MANAGER_VERSION,
                true
            );
        }
    }
    
    /**
     * Send employee notification
     */
    private function send_employee_notification($entry, $form) {
        if (!get_option('rt_employee_manager_enable_email_notifications')) {
            return;
        }
        
        $admin_email = get_option('rt_employee_manager_admin_email', get_option('admin_email'));
        $employee_name = rgar($entry, '28') . ' ' . rgar($entry, '27');
        
        wp_mail(
            $admin_email,
            sprintf(__('Neuer Mitarbeiter registriert: %s', 'rt-employee-manager'), $employee_name),
            sprintf(__('Ein neuer Mitarbeiter wurde registriert: %s', 'rt-employee-manager'), $employee_name)
        );
    }
    
    /**
     * Send client notification
     */
    private function send_client_notification($entry, $form) {
        if (!get_option('rt_employee_manager_enable_email_notifications')) {
            return;
        }
        
        $admin_email = get_option('rt_employee_manager_admin_email', get_option('admin_email'));
        $company_name = rgar($entry, '23');
        
        wp_mail(
            $admin_email,
            sprintf(__('Neuer Kunde registriert: %s', 'rt-employee-manager'), $company_name),
            sprintf(__('Ein neuer Kunde wurde registriert: %s', 'rt-employee-manager'), $company_name)
        );
    }
    
    /**
     * Logging methods
     */
    private function log_debug($message, $data = array()) {
        if (get_option('rt_employee_manager_enable_logging')) {
            error_log('RT Employee Manager [DEBUG]: ' . $message . ' - ' . print_r($data, true));
        }
    }
    
    private function log_error($message, $data = array()) {
        error_log('RT Employee Manager [ERROR]: ' . $message . ' - ' . print_r($data, true));
    }
    
    private function log_warning($message, $data = array()) {
        error_log('RT Employee Manager [WARNING]: ' . $message . ' - ' . print_r($data, true));
    }
    
    private function log_success($message, $data = array()) {
        if (get_option('rt_employee_manager_enable_logging')) {
            error_log('RT Employee Manager [SUCCESS]: ' . $message . ' - ' . print_r($data, true));
        }
    }
}

// Initialize the scheduled event hook
add_action('rt_verify_employee_post_creation', array(RT_Employee_Manager_Gravity_Forms_Integration::class, 'verify_employee_post_creation'));