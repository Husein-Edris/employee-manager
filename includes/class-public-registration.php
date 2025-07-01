<?php

if (!defined('ABSPATH')) {
    exit;
}

class RT_Employee_Manager_Public_Registration {
    
    public function __construct() {
        // Hook into Gravity Forms submission for company registration
        add_action('gform_after_submission', array($this, 'handle_gravity_forms_registration'), 10, 2);
        add_action('gform_user_registered', array($this, 'after_company_user_registration'), 10, 4);
    }
    
    /**
     * Handle Gravity Forms company registration submission
     */
    public function handle_gravity_forms_registration($entry, $form) {
        // Debug log all form submissions
        error_log('RT Employee Manager: Form submission detected - Form ID: ' . $form['id'] . ', Title: ' . $form['title']);
        error_log('RT Employee Manager: Entry data: ' . print_r($entry, true));
        error_log('RT Employee Manager: Form fields: ' . print_r($form['fields'], true));
        
        // Check if this is the company registration form
        if (!$this->is_company_registration_form($form)) {
            error_log('RT Employee Manager: Form not recognized as company registration form');
            return;
        }
        
        error_log('RT Employee Manager: Confirmed company registration form');
        
        // Don't process if user was successfully registered (handled by user registration hook)
        if (!empty($entry['created_by'])) {
            error_log('RT Employee Manager: User was created, will be handled by user registration hook');
            return;
        }
        
        // Create pending registration record
        error_log('RT Employee Manager: Creating pending registration record');
        $result = $this->create_pending_registration_from_entry($entry);
        
        if ($result) {
            error_log('RT Employee Manager: Successfully created pending registration with ID: ' . $result);
        } else {
            error_log('RT Employee Manager: Failed to create pending registration');
        }
    }
    
    /**
     * Check if this is a company registration form
     */
    private function is_company_registration_form($form) {
        // Check by form title or specific field presence
        if (strpos(strtolower($form['title']), 'firmen') !== false || 
            strpos(strtolower($form['title']), 'company') !== false ||
            strpos(strtolower($form['title']), 'registrierung') !== false) {
            return true;
        }
        
        // Alternative: Check by form ID (adjust as needed)
        $registration_form_id = get_option('rt_employee_manager_registration_form_id', 3);
        if ($form['id'] == $registration_form_id) {
            return true;
        }
        
        // Check for specific company fields
        foreach ($form['fields'] as $field) {
            if (strpos(strtolower($field->label), 'firmenname') !== false ||
                strpos(strtolower($field->label), 'company name') !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Create pending registration from Gravity Forms entry
     */
    private function create_pending_registration_from_entry($entry) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'rt_pending_registrations';
        
        // Debug: Log all entry field values to see what's available
        error_log('RT Employee Manager: Mapping entry fields:');
        foreach ($entry as $key => $value) {
            if (is_numeric($key)) {
                error_log("Field {$key}: " . $value);
            }
        }
        
        // Map Gravity Forms fields to our database structure
        // Note: We'll use a more flexible mapping approach
        $registration_data = array(
            'status' => 'pending',
            'submitted_at' => current_time('mysql'),
            'ip_address' => rgar($entry, 'ip'),
            'user_agent' => rgar($entry, 'user_agent'),
            'gravity_form_entry_id' => $entry['id']
        );
        
        // Try to find company name field by looking for common patterns
        foreach ($entry as $field_id => $value) {
            if (is_numeric($field_id) && !empty($value)) {
                // Get field label from form to help identify fields
                $form_id = $entry['form_id'];
                $form = GFAPI::get_form($form_id);
                $field_label = '';
                
                foreach ($form['fields'] as $field) {
                    if ($field->id == $field_id) {
                        $field_label = strtolower($field->label);
                        break;
                    }
                }
                
                error_log("Field {$field_id} (Label: {$field_label}): {$value}");
                
                // Map fields based on labels
                if (strpos($field_label, 'firma') !== false || strpos($field_label, 'company') !== false) {
                    $registration_data['company_name'] = sanitize_text_field($value);
                } elseif (strpos($field_label, 'email') !== false || strpos($field_label, 'e-mail') !== false) {
                    $registration_data['company_email'] = sanitize_email($value);
                    $registration_data['contact_email'] = sanitize_email($value);
                } elseif (strpos($field_label, 'phone') !== false || strpos($field_label, 'telefon') !== false) {
                    $registration_data['company_phone'] = sanitize_text_field($value);
                } elseif (strpos($field_label, 'uid') !== false) {
                    $registration_data['uid_number'] = sanitize_text_field($value);
                } elseif (strpos($field_label, 'vorname') !== false || strpos($field_label, 'first') !== false) {
                    $registration_data['contact_first_name'] = sanitize_text_field($value);
                } elseif (strpos($field_label, 'nachname') !== false || strpos($field_label, 'last') !== false) {
                    $registration_data['contact_last_name'] = sanitize_text_field($value);
                } elseif (strpos($field_label, 'straße') !== false || strpos($field_label, 'street') !== false) {
                    $registration_data['company_street'] = sanitize_text_field($value);
                } elseif (strpos($field_label, 'plz') !== false || strpos($field_label, 'postcode') !== false || strpos($field_label, 'zip') !== false) {
                    $registration_data['company_postcode'] = sanitize_text_field($value);
                } elseif (strpos($field_label, 'stadt') !== false || strpos($field_label, 'city') !== false || strpos($field_label, 'ort') !== false) {
                    $registration_data['company_city'] = sanitize_text_field($value);
                } elseif (strpos($field_label, 'land') !== false || strpos($field_label, 'country') !== false) {
                    $registration_data['company_country'] = sanitize_text_field($value);
                }
            }
        }
        
        // Ensure we have required fields
        if (empty($registration_data['company_name'])) {
            error_log('RT Employee Manager: No company name found in entry');
            return false;
        }
        
        if (empty($registration_data['company_email'])) {
            error_log('RT Employee Manager: No company email found in entry');
            return false;
        }
        
        // Set defaults for missing contact info
        if (empty($registration_data['contact_first_name'])) {
            $registration_data['contact_first_name'] = 'Unknown';
        }
        if (empty($registration_data['contact_last_name'])) {
            $registration_data['contact_last_name'] = 'User';
        }
        if (empty($registration_data['company_country'])) {
            $registration_data['company_country'] = 'Austria';
        }
        
        error_log('RT Employee Manager: Final registration data: ' . print_r($registration_data, true));
        
        $result = $wpdb->insert($table_name, $registration_data);
        
        if ($result !== false) {
            $registration_id = $wpdb->insert_id;
            
            // Notify admin about new registration
            $this->notify_admin_new_registration($registration_id);
            
            return $registration_id;
        } else {
            error_log('RT Employee Manager: Database insert failed: ' . $wpdb->last_error);
        }
        
        return false;
    }
    
    /**
     * Handle company user registration (when using Gravity Forms User Registration)
     */
    public function after_company_user_registration($user_id, $user_config, $entry, $password) {
        // Check if this is from company registration form
        $form = GFAPI::get_form($entry['form_id']);
        
        if (!$this->is_company_registration_form($form)) {
            return;
        }
        
        // Create pending registration (will be auto-approved since user was created)
        $registration_id = $this->create_pending_registration_from_entry($entry);
        
        if ($registration_id) {
            // Auto-approve since Gravity Forms already created the user
            $this->auto_approve_registration($registration_id, $user_id);
        }
    }
    
    /**
     * Auto-approve registration when user is created via Gravity Forms
     */
    private function auto_approve_registration($registration_id, $user_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'rt_pending_registrations';
        
        // Update registration status
        $wpdb->update(
            $table_name,
            array(
                'status' => 'approved',
                'approved_at' => current_time('mysql'),
                'approved_by' => 1 // System auto-approval
            ),
            array('id' => $registration_id)
        );
        
        // Create kunde post if it doesn't exist
        $kunde_post_id = get_user_meta($user_id, 'kunde_post_id', true);
        if (!$kunde_post_id) {
            $registration = $this->get_registration_by_id($registration_id);
            if ($registration) {
                $this->create_kunde_post_for_user($user_id, $registration);
            }
        }
    }
    
    /**
     * Create kunde post for approved user
     */
    private function create_kunde_post_for_user($user_id, $registration) {
        $post_data = array(
            'post_title' => $registration->company_name,
            'post_type' => 'kunde',
            'post_status' => 'publish',
            'post_author' => $user_id,
            'meta_input' => array(
                'company_name' => $registration->company_name,
                'uid_number' => $registration->uid_number,
                'phone' => $registration->company_phone,
                'email' => $registration->company_email,
                'registration_date' => current_time('d.m.Y H:i'),
                'user_id' => $user_id,
                'approved_from_registration' => $registration->id
            )
        );
        
        $post_id = wp_insert_post($post_data);
        
        if (!is_wp_error($post_id)) {
            // Link user to kunde post
            update_user_meta($user_id, 'kunde_post_id', $post_id);
            return $post_id;
        }
        
        return false;
    }
    
    /**
     * Get registration by ID
     */
    private function get_registration_by_id($id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'rt_pending_registrations';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Notify admin about new registration
     */
    private function notify_admin_new_registration($registration_id) {
        $admin_email = get_option('rt_employee_manager_admin_email', get_option('admin_email'));
        $registration = $this->get_registration_by_id($registration_id);
        
        if (!$registration) {
            return false;
        }
        
        $subject = sprintf(__('[%s] Neue Firmen-Registrierung', 'rt-employee-manager'), get_bloginfo('name'));
        
        $message = sprintf(__('
Eine neue Firma hat sich registriert und wartet auf Genehmigung:

Firmenname: %s
E-Mail: %s
Kontaktperson: %s %s
Persönliche E-Mail: %s

Genehmigen Sie die Registrierung in Ihrem Admin-Bereich:
%s

---
Diese E-Mail wurde automatisch generiert.
', 'rt-employee-manager'), 
            $registration->company_name,
            $registration->company_email,
            $registration->contact_first_name,
            $registration->contact_last_name,
            $registration->contact_email,
            admin_url('admin.php?page=rt-employee-manager-dashboard')
        );
        
        return wp_mail($admin_email, $subject, $message);
    }
}