<?php

if (!defined('ABSPATH')) {
    exit;
}

class RT_Employee_Manager_Gravity_Forms_Integration {
    
    public function __construct() {
        // Field pre-population for employer data
        add_filter('gform_field_value', array($this, 'populate_employer_fields'), 10, 3);
        
        // SVNR validation - currently disabled
        // add_filter('gform_field_validation', array($this, 'validate_svnr'), 10, 4);
        
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
        
        // Manual user approval workflow
        add_action('wp_ajax_approve_company_registration', array($this, 'handle_company_approval'));
        add_action('wp_ajax_reject_company_registration', array($this, 'handle_company_rejection'));
        
        // Password reset improvements for kunden users
        add_filter('retrieve_password_message', array($this, 'custom_password_reset_message'), 10, 4);
        add_filter('wp_mail_from_name', array($this, 'custom_mail_from_name'));
        add_action('password_reset', array($this, 'after_password_reset'), 10, 2);
        
        // German translations for Gravity Forms
        add_filter('gform_pre_render', array($this, 'translate_form_labels'));
        add_filter('gform_validation_message', array($this, 'translate_validation_messages'), 10, 2);
        add_filter('gform_field_validation', array($this, 'translate_field_validation_messages'), 10, 4);
        
        // Frontend user interface enhancements
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('wp_footer', array($this, 'add_frontend_user_interface'));
        
        // Admin interface modifications
        add_filter('admin_url', array($this, 'redirect_backend_employee_creation'), 10, 2);
        
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
                )
                // SVNR check removed - no longer required
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
            'employer_id' => rgar($entry, 'created_by') ?: get_current_user_id(),
            'status' => 'active'
        );
        
        // Add SVNR if provided (optional now)
        $svnr = preg_replace('/\D/', '', rgar($entry, '53'));
        if (!empty($svnr)) {
            $required_fields['sozialversicherungsnummer'] = $svnr;
        }
        
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
    
    /**
     * Handle company approval via AJAX
     */
    public function handle_company_approval() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $user_id = intval($_POST['user_id']);
        $user = get_user_by('ID', $user_id);
        
        if (!$user) {
            wp_die('User not found');
        }
        
        // Update user status to active
        update_user_meta($user_id, 'account_status', 'active');
        
        // Send approval email with login instructions
        $this->send_approval_email($user);
        
        wp_send_json_success('Company approved and email sent');
    }
    
    /**
     * Handle company rejection via AJAX
     */
    public function handle_company_rejection() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $user_id = intval($_POST['user_id']);
        $reason = sanitize_textarea_field($_POST['reason']);
        
        $user = get_user_by('ID', $user_id);
        
        if (!$user) {
            wp_die('User not found');
        }
        
        // Update user status to rejected
        update_user_meta($user_id, 'account_status', 'rejected');
        update_user_meta($user_id, 'rejection_reason', $reason);
        
        // Send rejection email
        $this->send_rejection_email($user, $reason);
        
        wp_send_json_success('Company rejected and email sent');
    }
    
    /**
     * Send approval email with login instructions
     */
    private function send_approval_email($user) {
        $company_name = get_user_meta($user->ID, 'company_name', true);
        $login_url = wp_login_url();
        $dashboard_url = admin_url('admin.php?page=rt-employee-manager');
        
        // Generate password reset link
        $reset_key = get_password_reset_key($user);
        if (!is_wp_error($reset_key)) {
            $reset_url = network_site_url("wp-login.php?action=rp&key=$reset_key&login=" . rawurlencode($user->user_login), 'login');
        } else {
            $reset_url = wp_lostpassword_url();
        }
        
        $subject = 'Ihr Unternehmenszugang wurde genehmigt - RT Employee Manager';
        
        $message = sprintf('
Hallo %s,

Ihr Unternehmenszugang für "%s" wurde erfolgreich genehmigt!

Sie können sich jetzt in unser System einloggen:

**Ihre Zugangsdaten:**
Benutzername: %s
E-Mail: %s

**Login-Link:** %s

**Passwort setzen:** 
Falls Sie Ihr Passwort noch nicht gesetzt haben oder es vergessen haben, können Sie es hier zurücksetzen:
%s

**Nach dem Login können Sie:**
- Neue Mitarbeiter registrieren
- Mitarbeiterdaten verwalten
- Ihr Unternehmensprofil aktualisieren

**Direktlink zur Mitarbeiterverwaltung:** %s

Bei Fragen stehen wir Ihnen gerne zur Verfügung.

Mit freundlichen Grüßen
Ihr RT Team

---
Diese E-Mail wurde automatisch generiert.
',
            $company_name ?: $user->display_name,
            $company_name ?: 'Ihr Unternehmen',
            $user->user_login,
            $user->user_email,
            $login_url,
            $reset_url,
            $dashboard_url
        );
        
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        
        wp_mail($user->user_email, $subject, $message, $headers);
        
        // Also notify admin
        $admin_email = get_option('rt_employee_manager_admin_email', get_option('admin_email'));
        if ($admin_email !== $user->user_email) {
            wp_mail($admin_email, 'Unternehmen genehmigt: ' . ($company_name ?: $user->display_name), 
                sprintf('Das Unternehmen "%s" (%s) wurde genehmigt und benachrichtigt.', 
                $company_name ?: $user->display_name, $user->user_email));
        }
        
        $this->log_success('Approval email sent', array(
            'user_id' => $user->ID,
            'company_name' => $company_name,
            'email' => $user->user_email
        ));
    }
    
    /**
     * Send rejection email
     */
    private function send_rejection_email($user, $reason) {
        $company_name = get_user_meta($user->ID, 'company_name', true);
        
        $subject = 'Ihr Registrierungsantrag - RT Employee Manager';
        
        $message = sprintf('
Hallo %s,

leider können wir Ihren Registrierungsantrag für "%s" derzeit nicht genehmigen.

**Grund der Ablehnung:**
%s

Falls Sie Fragen haben oder zusätzliche Informationen bereitstellen möchten, können Sie sich gerne an uns wenden.

Mit freundlichen Grüßen
Ihr RT Team

---
Diese E-Mail wurde automatisch generiert.
',
            $company_name ?: $user->display_name,
            $company_name ?: 'Ihr Unternehmen',
            $reason ?: 'Keine spezifischen Informationen verfügbar.'
        );
        
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        
        wp_mail($user->user_email, $subject, $message, $headers);
        
        $this->log_success('Rejection email sent', array(
            'user_id' => $user->ID,
            'company_name' => $company_name,
            'email' => $user->user_email,
            'reason' => $reason
        ));
    }
    
    /**
     * Custom password reset message for better user experience
     */
    public function custom_password_reset_message($message, $key, $user_login, $user_data) {
        // Check if this is a kunden user
        $user = get_user_by('login', $user_login);
        if ($user && in_array('kunden', $user->roles)) {
            $company_name = get_user_meta($user->ID, 'company_name', true);
            $reset_url = network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user_login), 'login');
            $dashboard_url = admin_url('admin.php?page=rt-employee-manager');
            
            $message = sprintf('
Hallo %s,

Sie haben eine Passwort-Zurücksetzung für Ihr RT Employee Manager Konto angefordert.

**Unternehmen:** %s
**Benutzername:** %s
**E-Mail:** %s

Klicken Sie auf den folgenden Link, um Ihr Passwort zurückzusetzen:
%s

Nach der Passwort-Zurücksetzung können Sie sich hier einloggen:
%s

**Direkt zur Mitarbeiterverwaltung:** %s

Falls Sie diese Anfrage nicht gestellt haben, können Sie diese E-Mail ignorieren.

Mit freundlichen Grüßen
Ihr RT Team

---
Diese E-Mail wurde automatisch generiert.
',
                $company_name ?: $user->display_name,
                $company_name ?: 'Nicht angegeben',
                $user_login,
                $user->user_email,
                $reset_url,
                wp_login_url(),
                $dashboard_url
            );
        }
        
        return $message;
    }
    
    /**
     * Custom mail from name
     */
    public function custom_mail_from_name($name) {
        return 'RT Employee Manager';
    }
    
    /**
     * After password reset - log and possibly send confirmation
     */
    public function after_password_reset($user, $new_pass) {
        if (in_array('kunden', $user->roles)) {
            $this->log_success('Password reset completed', array(
                'user_id' => $user->ID,
                'company_name' => get_user_meta($user->ID, 'company_name', true),
                'email' => $user->user_email
            ));
            
            // Optionally send confirmation email
            if (get_option('rt_employee_manager_enable_email_notifications')) {
                $company_name = get_user_meta($user->ID, 'company_name', true);
                $dashboard_url = admin_url('admin.php?page=rt-employee-manager');
                
                $subject = 'Passwort erfolgreich zurückgesetzt - RT Employee Manager';
                $message = sprintf('
Hallo %s,

Ihr Passwort für das RT Employee Manager System wurde erfolgreich zurückgesetzt.

Sie können sich jetzt mit Ihrem neuen Passwort einloggen:
%s

**Direktlink zur Mitarbeiterverwaltung:** %s

Falls Sie diese Änderung nicht vorgenommen haben, kontaktieren Sie uns bitte umgehend.

Mit freundlichen Grüßen
Ihr RT Team
',
                    $company_name ?: $user->display_name,
                    wp_login_url(),
                    $dashboard_url
                );
                
                wp_mail($user->user_email, $subject, $message, array('Content-Type: text/plain; charset=UTF-8'));
            }
        }
    }
    
    /**
     * Translate Gravity Forms labels to German
     */
    public function translate_form_labels($form) {
        // Translation mappings for common form elements
        $translations = array(
            'Step 1 of 2' => 'Schritt 1 von 2',
            'Step 2 of 2' => 'Schritt 2 von 2',
            'Step 1 of 3' => 'Schritt 1 von 3',
            'Step 2 of 3' => 'Schritt 2 von 3',
            'Step 3 of 3' => 'Schritt 3 von 3',
            'Next' => 'Weiter',
            'Previous' => 'Zurück',
            'Submit' => 'Senden',
            'Save and Continue Later' => 'Speichern und später fortsetzen',
            'Required field' => 'Pflichtfeld',
            'This field is required' => 'Dieses Feld ist erforderlich',
            'Please enter a valid email address' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein',
            'Please enter a valid phone number' => 'Bitte geben Sie eine gültige Telefonnummer ein',
            'Please enter a valid date' => 'Bitte geben Sie ein gültiges Datum ein',
            'File upload' => 'Datei hochladen',
            'Choose Files' => 'Dateien auswählen',
            'Maximum file size exceeded' => 'Maximale Dateigröße überschritten',
            'Acceptable file types' => 'Zulässige Dateitypen',
            'Drop files here or' => 'Dateien hier ablegen oder',
            'select files' => 'Dateien auswählen'
        );
        
        // Translate page names (multi-page forms)
        if (isset($form['pagination']) && $form['pagination']) {
            if (isset($form['pagination']['pages']) && is_array($form['pagination']['pages'])) {
                foreach ($form['pagination']['pages'] as $key => $page) {
                    if (isset($translations[$page])) {
                        $form['pagination']['pages'][$key] = $translations[$page];
                    }
                }
            }
            
            // Translate next/previous button text
            if (isset($form['pagination']['nextText']) && isset($translations[$form['pagination']['nextText']])) {
                $form['pagination']['nextText'] = $translations[$form['pagination']['nextText']];
            }
            if (isset($form['pagination']['previousText']) && isset($translations[$form['pagination']['previousText']])) {
                $form['pagination']['previousText'] = $translations[$form['pagination']['previousText']];
            }
        }
        
        // Translate button text
        if (isset($form['button']['text']) && isset($translations[$form['button']['text']])) {
            $form['button']['text'] = $translations[$form['button']['text']];
        }
        
        // Translate field labels and descriptions
        foreach ($form['fields'] as &$field) {
            // Translate common field labels
            if (isset($field->label) && isset($translations[$field->label])) {
                $field->label = $translations[$field->label];
            }
            
            // Translate field descriptions
            if (isset($field->description) && isset($translations[$field->description])) {
                $field->description = $translations[$field->description];
            }
            
            // Translate field placeholders
            if (isset($field->placeholder) && isset($translations[$field->placeholder])) {
                $field->placeholder = $translations[$field->placeholder];
            }
            
            // Translate validation messages
            if (isset($field->errorMessage) && isset($translations[$field->errorMessage])) {
                $field->errorMessage = $translations[$field->errorMessage];
            }
            
            // Translate choice labels for select/radio/checkbox fields
            if (isset($field->choices) && is_array($field->choices)) {
                foreach ($field->choices as &$choice) {
                    if (isset($choice['text']) && isset($translations[$choice['text']])) {
                        $choice['text'] = $translations[$choice['text']];
                    }
                }
            }
        }
        
        return $form;
    }
    
    /**
     * Translate validation messages
     */
    public function translate_validation_messages($message, $form) {
        $translations = array(
            'There was a problem with your submission' => 'Es gab ein Problem mit Ihrer Übermittlung',
            'Please review the fields below' => 'Bitte überprüfen Sie die unten stehenden Felder',
            'Errors have been highlighted below' => 'Fehler wurden unten hervorgehoben',
            'Please fix the errors below and try again' => 'Bitte beheben Sie die unten stehenden Fehler und versuchen Sie es erneut'
        );
        
        foreach ($translations as $english => $german) {
            $message = str_replace($english, $german, $message);
        }
        
        return $message;
    }
    
    /**
     * Translate individual field validation messages
     */
    public function translate_field_validation_messages($result, $value, $form, $field) {
        if (!$result['is_valid'] && isset($result['message'])) {
            $translations = array(
                'This field is required.' => 'Dieses Feld ist erforderlich.',
                'Please enter a valid email address.' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
                'Please enter a valid phone number.' => 'Bitte geben Sie eine gültige Telefonnummer ein.',
                'Please enter a valid date.' => 'Bitte geben Sie ein gültiges Datum ein.',
                'Please enter a valid number.' => 'Bitte geben Sie eine gültige Zahl ein.',
                'Please enter a valid URL.' => 'Bitte geben Sie eine gültige URL ein.',
                'The passwords do not match.' => 'Die Passwörter stimmen nicht überein.',
                'Password is too short.' => 'Das Passwort ist zu kurz.',
                'Please select an option.' => 'Bitte wählen Sie eine Option aus.',
                'Please upload a file.' => 'Bitte laden Sie eine Datei hoch.',
                'File type not allowed.' => 'Dateityp nicht erlaubt.',
                'File size too large.' => 'Datei zu groß.'
            );
            
            foreach ($translations as $english => $german) {
                $result['message'] = str_replace($english, $german, $result['message']);
            }
        }
        
        return $result;
    }
    
    /**
     * Enqueue frontend scripts for user interface
     */
    public function enqueue_frontend_scripts() {
        if (!is_admin()) {
            wp_enqueue_script('jquery');
        }
    }
    
    /**
     * Add frontend user interface modifications
     */
    public function add_frontend_user_interface() {
        if (is_admin()) {
            return;
        }
        
        $current_user = wp_get_current_user();
        $is_logged_in = is_user_logged_in();
        $is_kunden = $is_logged_in && in_array('kunden', $current_user->roles);
        
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            <?php if ($is_kunden): ?>
            // Hide login button for logged-in kunden users
            $('.elementor-element-de74848').hide();
            
            // Show portal button if it has the class 'rt-portal-button' and is hidden
            $('.rt-portal-button').show();
            
            // Replace login button with portal button
            var portalButton = $('.rt-portal-button');
            if (portalButton.length === 0) {
                // Create portal button if it doesn't exist
                var loginButton = $('.elementor-element-de74848');
                if (loginButton.length > 0) {
                    var portalHtml = '<div class="elementor-element elementor-element-portal rt-portal-button elementor-widget elementor-widget-button" style="display: block;">' +
                        '<div class="elementor-widget-container">' +
                        '<div class="elementor-button-wrapper">' +
                        '<a class="elementor-button elementor-button-link elementor-size-sm" href="<?php echo admin_url('admin.php?page=rt-employee-manager'); ?>">' +
                        '<span class="elementor-button-content-wrapper">' +
                        '<span class="elementor-button-text">Mitarbeiter Portal</span>' +
                        '</span>' +
                        '</a>' +
                        '</div>' +
                        '</div>' +
                        '</div>';
                    
                    loginButton.after(portalHtml);
                }
            }
            
            // Add user info display
            var userInfo = '<div class="rt-user-info" style="position: fixed; top: 20px; right: 20px; background: #fff; padding: 10px; border-radius: 5px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); z-index: 9999; max-width: 200px;">' +
                '<div style="margin-bottom: 5px;"><strong>Willkommen:</strong></div>' +
                '<div style="margin-bottom: 5px; font-size: 12px;"><?php echo esc_js($current_user->display_name); ?></div>' +
                <?php 
                $company_name = get_user_meta($current_user->ID, 'company_name', true);
                if ($company_name): 
                ?>
                '<div style="margin-bottom: 5px; font-size: 11px; color: #666;"><?php echo esc_js($company_name); ?></div>' +
                <?php endif; ?>
                '<div style="text-align: center; margin-top: 10px;">' +
                '<a href="<?php echo admin_url('admin.php?page=rt-employee-manager'); ?>" style="text-decoration: none; background: #0073aa; color: white; padding: 5px 10px; border-radius: 3px; font-size: 11px;">Portal</a> ' +
                '<a href="<?php echo wp_logout_url(home_url()); ?>" style="text-decoration: none; background: #dc3232; color: white; padding: 5px 10px; border-radius: 3px; font-size: 11px;">Logout</a>' +
                '</div>' +
                '</div>';
            
            $('body').append(userInfo);
            
            // Make user info dismissible
            $('.rt-user-info').click(function(e) {
                e.preventDefault();
                $(this).fadeOut();
            });
            
            <?php endif; ?>
            
            <?php if ($is_logged_in && !$is_kunden): ?>
            // For other logged-in users (admin, etc.), just hide the login button
            $('.elementor-element-de74848').hide();
            <?php endif; ?>
        });
        </script>
        
        <style>
        /* Styles for portal button */
        .rt-portal-button {
            display: none; /* Hidden by default, shown by JS for kunden users */
        }
        
        .rt-portal-button .elementor-button {
            background: #28a745 !important;
            color: white !important;
        }
        
        .rt-portal-button .elementor-button:hover {
            background: #218838 !important;
        }
        
        /* User info styles */
        .rt-user-info {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            cursor: pointer;
        }
        
        .rt-user-info:hover {
            opacity: 0.9;
        }
        
        /* Mobile responsiveness */
        @media (max-width: 768px) {
            .rt-user-info {
                top: 10px;
                right: 10px;
                max-width: 150px;
                font-size: 11px;
            }
        }
        </style>
        <?php
    }
    
    /**
     * Redirect backend employee creation to frontend form
     */
    public function redirect_backend_employee_creation($url, $path) {
        if (strpos($path, 'post-new.php?post_type=angestellte') !== false) {
            // Redirect to frontend employee registration form
            return 'https://rt-buchhaltung.at/anmeldung-neue-r-dienstnehmer-in/';
        }
        
        return $url;
    }
}

// Initialize the scheduled event hook
add_action('rt_verify_employee_post_creation', array(RT_Employee_Manager_Gravity_Forms_Integration::class, 'verify_employee_post_creation'));