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
        
        // Fix rate limiting for registration forms
        add_filter('gform_form_limit_exceeded', array($this, 'bypass_rate_limiting_for_registration'), 10, 5);
        add_filter('gform_entry_limit_exceeded_message', array($this, 'custom_rate_limit_message'), 10, 4);
        
        // Enqueue scripts
        add_action('gform_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Add CSS class to SVNR field
        add_filter('gform_field_css_class', array($this, 'add_svnr_css_class'), 10, 3);
        
        // User registration integration
        add_action('gform_user_registered', array($this, 'after_user_registration'), 10, 4);
        
        // Advanced Post Creation integration
        add_action('gform_advancedpostcreation_post_after_creation', array($this, 'after_employee_post_creation'), 10, 4);
        
        // Alternative hook for when APC creates posts
        add_action('gform_after_submission', array($this, 'ensure_employee_post_data'), 20, 2);
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
            // Skip validation in local development environment
            if (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'local') {
                // In local development, only check basic format
                if (get_option('rt_employee_manager_enable_logging')) {
                    error_log('RT Employee Manager: SVNR validation skipped in local environment: ' . $cleaned);
                }
            } else {
                // Full validation in production
                if (!$this->validate_svnr_checksum($cleaned)) {
                    $result['is_valid'] = false;
                    $result['message'] = __('Ungültige Sozialversicherungsnummer. Format: 10 Ziffern (z.B. 1237010180)', 'rt-employee-manager');
                    
                    // Debug logging
                    if (get_option('rt_employee_manager_enable_logging')) {
                        error_log('RT Employee Manager: SVNR validation failed for: ' . $cleaned);
                    }
                    
                    return $result;
                }
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
            
            // Create corresponding kunde CPT post
            $this->create_kunde_post_from_registration($user_id, $entry);
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
     * Create kunde CPT post from user registration
     */
    private function create_kunde_post_from_registration($user_id, $entry) {
        $company_name = rgar($entry, '23');
        
        if (empty($company_name)) {
            $this->log_error('Cannot create kunde post - missing company name', array(
                'user_id' => $user_id,
                'entry_id' => $entry['id']
            ));
            return false;
        }
        
        // Create the kunde post
        $post_data = array(
            'post_title' => sanitize_text_field($company_name),
            'post_type' => 'kunde',
            'post_status' => 'publish',
            'post_author' => $user_id,
            'meta_input' => array(
                'company_name' => sanitize_text_field($company_name),
                'uid_number' => sanitize_text_field(rgar($entry, '24')),
                'phone' => sanitize_text_field(rgar($entry, '10')),
                'email' => sanitize_email(rgar($entry, '11')), // assuming field 11 is email
                'registration_date' => current_time('d.m.Y H:i'),
                'form_entry_id' => $entry['id'],
                'user_id' => $user_id, // Link back to the WordPress user
            )
        );
        
        // Add address data as a group
        $address_data = array(
            'street' => sanitize_text_field(rgar($entry, '21.1')),
            'postcode' => sanitize_text_field(rgar($entry, '21.5')),
            'city' => sanitize_text_field(rgar($entry, '21.3')),
            'country' => sanitize_text_field(rgar($entry, '21.6')) ?: 'Austria'
        );
        
        // Only add address if we have at least street or city
        if (!empty($address_data['street']) || !empty($address_data['city'])) {
            $post_data['meta_input']['address'] = $address_data;
        }
        
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            $this->log_error('Failed to create kunde post', array(
                'user_id' => $user_id,
                'entry_id' => $entry['id'],
                'error' => $post_id->get_error_message()
            ));
            return false;
        }
        
        // Store the kunde post ID in user meta for easy reference
        update_user_meta($user_id, 'kunde_post_id', $post_id);
        
        $this->log_success('Created kunde post successfully', array(
            'user_id' => $user_id,
            'post_id' => $post_id,
            'entry_id' => $entry['id'],
            'company_name' => $company_name
        ));
        
        return $post_id;
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
            sprintf(__('Neues Unternehmen registriert: %s', 'rt-employee-manager'), $company_name),
            sprintf(__('Ein neues Unternehmen wurde registriert: %s', 'rt-employee-manager'), $company_name)
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
    
    /**
     * Handle employee post after Advanced Post Creation creates it
     */
    public function after_employee_post_creation($post_id, $feed, $entry, $form) {
        // Only handle employee form
        if ($form['id'] != 1) {
            return;
        }
        
        $this->log_debug('Advanced Post Creation created employee post', array(
            'post_id' => $post_id,
            'entry_id' => $entry['id']
        ));
        
        // Ensure post is published
        wp_update_post(array(
            'ID' => $post_id,
            'post_status' => 'publish'
        ));
        
        // Save additional employee data
        $this->save_employee_post_data($post_id, $entry);
        
        $this->log_success('Employee post updated after APC creation', array(
            'post_id' => $post_id,
            'entry_id' => $entry['id']
        ));
    }
    
    /**
     * Ensure employee post data is saved (alternative method)
     */
    public function ensure_employee_post_data($entry, $form) {
        // Only handle employee form
        if ($form['id'] != 1) {
            return;
        }
        
        // Look for recently created employee posts that might need updating
        $recent_posts = get_posts(array(
            'post_type' => 'angestellte',
            'posts_per_page' => 5,
            'orderby' => 'date',
            'order' => 'DESC',
            'date_query' => array(
                'after' => '5 minutes ago'
            )
        ));
        
        foreach ($recent_posts as $post) {
            // Check if this post needs data (has minimal meta)
            $existing_meta = get_post_meta($post->ID);
            $has_employee_data = isset($existing_meta['first_name']) || 
                               isset($existing_meta['last_name']) || 
                               isset($existing_meta['svnr']);
            
            if (!$has_employee_data) {
                $this->log_debug('Found employee post without data, updating', array(
                    'post_id' => $post->ID,
                    'entry_id' => $entry['id']
                ));
                
                // Update post status
                if ($post->post_status === 'draft') {
                    wp_update_post(array(
                        'ID' => $post->ID,
                        'post_status' => 'publish'
                    ));
                }
                
                // Save employee data
                $this->save_employee_post_data($post->ID, $entry);
                break; // Only update the first matching post
            }
        }
    }
    
    /**
     * Save employee data to post meta
     */
    private function save_employee_post_data($post_id, $entry) {
        // Get current user (employer)
        $employer_id = is_user_logged_in() ? get_current_user_id() : null;
        
        // Field mapping for employee data (using German field names to match meta boxes)
        $field_mapping = array(
            'vorname' => rgar($entry, '28'),         // First name (Vorname)
            'nachname' => rgar($entry, '27'),       // Last name (Nachname)
            'sozialversicherungsnummer' => rgar($entry, '53'), // SVNR
            'email' => rgar($entry, '26'),           // Email
            'telefon' => rgar($entry, '25'),         // Phone
            'geburtsdatum' => rgar($entry, '29'),    // Birth date
            'eintrittsdatum' => rgar($entry, '30'),  // Hire date
            'bezeichnung_der_tatigkeit' => rgar($entry, '31'), // Position
            'abteilung' => rgar($entry, '32'),       // Department
            'gehaltlohn' => rgar($entry, '33'),      // Salary
            'art_des_dienstverhaltnisses' => rgar($entry, '34'), // Employment type
            'staatsangehoerigkeit' => rgar($entry, '38'), // Nationality
            'personenstand' => rgar($entry, '39'),   // Marital status
            'arbeitszeit_pro_woche' => rgar($entry, '40'), // Working hours per week
            'anmerkungen' => rgar($entry, '37')      // Notes
        );
        
        // Handle address as array structure (as expected by meta box)
        $address_street = rgar($entry, '35.1');
        $address_city = rgar($entry, '35.3');
        $address_postcode = rgar($entry, '35.5');
        $address_country = rgar($entry, '35.6');
        
        if (!empty($address_street) || !empty($address_city) || !empty($address_postcode)) {
            $address_array = array(
                'strasse' => $address_street,
                'plz' => $address_postcode,
                'ort' => $address_city,
                'land' => $address_country
            );
            update_post_meta($post_id, 'adresse', $address_array);
        }
        
        // Save employer relationship
        if ($employer_id) {
            update_post_meta($post_id, 'employer_id', $employer_id);
        }
        
        // Set default status
        update_post_meta($post_id, 'status', 'active');
        
        // Save all employee data
        foreach ($field_mapping as $meta_key => $value) {
            if (!empty($value)) {
                update_post_meta($post_id, $meta_key, sanitize_text_field($value));
            }
        }
        
        // Update post title with employee name
        $vorname = rgar($entry, '28');
        $nachname = rgar($entry, '27');
        if (!empty($vorname) || !empty($nachname)) {
            $full_name = trim($vorname . ' ' . $nachname);
            wp_update_post(array(
                'ID' => $post_id,
                'post_title' => $full_name
            ));
        }
        
        $this->log_success('Employee post data saved', array(
            'post_id' => $post_id,
            'employer_id' => $employer_id,
            'employee_name' => $vorname . ' ' . $nachname
        ));
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
    
    /**
     * Bypass rate limiting for registration forms
     */
    public function bypass_rate_limiting_for_registration($is_limit_exceeded, $form, $entry, $entry_limit, $range) {
        // Get registration form ID from settings
        $registration_form_id = get_option('rt_employee_manager_registration_form_id', '3');
        
        // Allow unlimited submissions for registration form
        if ($form['id'] == $registration_form_id) {
            return false; // No limit exceeded
        }
        
        return $is_limit_exceeded;
    }
    
    /**
     * Custom rate limit message for other forms
     */
    public function custom_rate_limit_message($message, $form, $entry_limit, $range) {
        $registration_form_id = get_option('rt_employee_manager_registration_form_id', '3');
        
        // Don't show message for registration form
        if ($form['id'] == $registration_form_id) {
            return '';
        }
        
        return __('Zu viele Versuche. Bitte warten Sie eine Stunde oder kontaktieren Sie den Administrator.', 'rt-employee-manager');
    }
}

// Initialize the scheduled event hook
add_action('rt_verify_employee_post_creation', array(RT_Employee_Manager_Gravity_Forms_Integration::class, 'verify_employee_post_creation'));