<?php

if (!defined('ABSPATH')) {
    exit;
}

class RT_Employee_Manager_User_Fields {
    
    public function __construct() {
        add_action('show_user_profile', array($this, 'add_custom_user_fields'));
        add_action('edit_user_profile', array($this, 'add_custom_user_fields'));
        add_action('personal_options_update', array($this, 'save_custom_user_fields'));
        add_action('edit_user_profile_update', array($this, 'save_custom_user_fields'));
        
        // Add fields to user registration form if needed
        add_action('user_new_form', array($this, 'add_custom_user_fields_registration'));
        add_action('user_register', array($this, 'save_custom_user_fields_registration'));
    }
    
    /**
     * Add custom fields to user profile
     */
    public function add_custom_user_fields($user) {
        // Security check
        if (!current_user_can('edit_user', $user->ID)) {
            return;
        }
        
        wp_nonce_field('rt_employee_manager_user_fields', 'rt_employee_manager_user_fields_nonce');
        ?>
        <h3><?php _e('Firmendaten', 'rt-employee-manager'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="company_name"><?php _e('Firmenname', 'rt-employee-manager'); ?></label></th>
                <td>
                    <input type="text" 
                           name="company_name" 
                           id="company_name" 
                           value="<?php echo esc_attr(get_user_meta($user->ID, 'company_name', true)); ?>" 
                           class="regular-text" 
                           maxlength="255" />
                    <p class="description"><?php _e('Name des Unternehmens', 'rt-employee-manager'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="uid_number"><?php _e('UID-Nummer', 'rt-employee-manager'); ?></label></th>
                <td>
                    <input type="text" 
                           name="uid_number" 
                           id="uid_number" 
                           value="<?php echo esc_attr(get_user_meta($user->ID, 'uid_number', true)); ?>" 
                           class="regular-text" 
                           maxlength="20" />
                    <p class="description"><?php _e('Umsatzsteuer-Identifikationsnummer', 'rt-employee-manager'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="phone"><?php _e('Telefonnummer', 'rt-employee-manager'); ?></label></th>
                <td>
                    <input type="tel" 
                           name="phone" 
                           id="phone" 
                           value="<?php echo esc_attr(get_user_meta($user->ID, 'phone', true)); ?>" 
                           class="regular-text" 
                           maxlength="20" />
                    <p class="description"><?php _e('Geschäftliche Telefonnummer', 'rt-employee-manager'); ?></p>
                </td>
            </tr>
        </table>

        <h3><?php _e('Adresse', 'rt-employee-manager'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="address_street"><?php _e('Straße und Hausnummer', 'rt-employee-manager'); ?></label></th>
                <td>
                    <input type="text" 
                           name="address_street" 
                           id="address_street" 
                           value="<?php echo esc_attr(get_user_meta($user->ID, 'address_street', true)); ?>" 
                           class="regular-text" 
                           maxlength="255" />
                </td>
            </tr>
            <tr>
                <th><label for="address_postcode"><?php _e('PLZ', 'rt-employee-manager'); ?></label></th>
                <td>
                    <input type="text" 
                           name="address_postcode" 
                           id="address_postcode" 
                           value="<?php echo esc_attr(get_user_meta($user->ID, 'address_postcode', true)); ?>" 
                           class="regular-text" 
                           maxlength="10" />
                </td>
            </tr>
            <tr>
                <th><label for="address_city"><?php _e('Ort', 'rt-employee-manager'); ?></label></th>
                <td>
                    <input type="text" 
                           name="address_city" 
                           id="address_city" 
                           value="<?php echo esc_attr(get_user_meta($user->ID, 'address_city', true)); ?>" 
                           class="regular-text" 
                           maxlength="100" />
                </td>
            </tr>
            <tr>
                <th><label for="address_country"><?php _e('Land', 'rt-employee-manager'); ?></label></th>
                <td>
                    <select name="address_country" id="address_country" class="regular-text">
                        <?php
                        $current_country = get_user_meta($user->ID, 'address_country', true);
                        $countries = $this->get_countries();
                        foreach ($countries as $code => $name) {
                            printf(
                                '<option value="%s"%s>%s</option>',
                                esc_attr($code),
                                selected($current_country, $code, false),
                                esc_html($name)
                            );
                        }
                        ?>
                    </select>
                </td>
            </tr>
        </table>

        <?php if (current_user_can('manage_options')): ?>
        <h3><?php _e('Employee Manager Einstellungen', 'rt-employee-manager'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="employee_limit"><?php _e('Maximale Anzahl Mitarbeiter', 'rt-employee-manager'); ?></label></th>
                <td>
                    <input type="number" 
                           name="employee_limit" 
                           id="employee_limit" 
                           value="<?php echo esc_attr(get_user_meta($user->ID, 'employee_limit', true) ?: '50'); ?>" 
                           class="regular-text" 
                           min="1" 
                           max="1000" />
                    <p class="description"><?php _e('Maximale Anzahl von Mitarbeitern, die dieser Kunde registrieren kann', 'rt-employee-manager'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="account_status"><?php _e('Kontostatus', 'rt-employee-manager'); ?></label></th>
                <td>
                    <select name="account_status" id="account_status">
                        <?php
                        $current_status = get_user_meta($user->ID, 'account_status', true) ?: 'active';
                        $statuses = array(
                            'active' => __('Aktiv', 'rt-employee-manager'),
                            'suspended' => __('Gesperrt', 'rt-employee-manager'),
                            'pending' => __('Ausstehend', 'rt-employee-manager')
                        );
                        foreach ($statuses as $value => $label) {
                            printf(
                                '<option value="%s"%s>%s</option>',
                                esc_attr($value),
                                selected($current_status, $value, false),
                                esc_html($label)
                            );
                        }
                        ?>
                    </select>
                </td>
            </tr>
        </table>
        <?php endif; ?>
        
        <style>
        .rt-employee-manager-fields {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
        }
        </style>
        <?php
    }
    
    /**
     * Add custom fields to user registration form (admin)
     */
    public function add_custom_user_fields_registration($type) {
        if ($type !== 'add-new-user') {
            return;
        }
        
        wp_nonce_field('rt_employee_manager_user_fields', 'rt_employee_manager_user_fields_nonce');
        ?>
        <h3><?php _e('Firmendaten', 'rt-employee-manager'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="company_name"><?php _e('Firmenname', 'rt-employee-manager'); ?></label></th>
                <td><input type="text" name="company_name" id="company_name" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="uid_number"><?php _e('UID-Nummer', 'rt-employee-manager'); ?></label></th>
                <td><input type="text" name="uid_number" id="uid_number" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="phone"><?php _e('Telefonnummer', 'rt-employee-manager'); ?></label></th>
                <td><input type="tel" name="phone" id="phone" class="regular-text" /></td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Save custom user fields
     */
    public function save_custom_user_fields($user_id) {
        // Security checks
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }
        
        if (!isset($_POST['rt_employee_manager_user_fields_nonce']) || 
            !wp_verify_nonce($_POST['rt_employee_manager_user_fields_nonce'], 'rt_employee_manager_user_fields')) {
            return false;
        }
        
        // Define fields to save
        $fields = array(
            'company_name' => 'sanitize_text_field',
            'uid_number' => 'sanitize_text_field',
            'phone' => 'sanitize_text_field',
            'address_street' => 'sanitize_text_field',
            'address_postcode' => 'sanitize_text_field',
            'address_city' => 'sanitize_text_field',
            'address_country' => 'sanitize_text_field'
        );
        
        // Admin-only fields
        if (current_user_can('manage_options')) {
            $fields['employee_limit'] = 'absint';
            $fields['account_status'] = 'sanitize_text_field';
        }
        
        // Save each field
        foreach ($fields as $field => $sanitize_callback) {
            if (isset($_POST[$field])) {
                $value = call_user_func($sanitize_callback, $_POST[$field]);
                update_user_meta($user_id, $field, $value);
            }
        }
        
        // Log the update
        $this->log_user_update($user_id);
    }
    
    /**
     * Save custom user fields during registration
     */
    public function save_custom_user_fields_registration($user_id) {
        $this->save_custom_user_fields($user_id);
    }
    
    /**
     * Get list of countries
     */
    private function get_countries() {
        return array(
            'AT' => __('Österreich', 'rt-employee-manager'),
            'DE' => __('Deutschland', 'rt-employee-manager'),
            'CH' => __('Schweiz', 'rt-employee-manager'),
            'IT' => __('Italien', 'rt-employee-manager'),
            'FR' => __('Frankreich', 'rt-employee-manager'),
            'HU' => __('Ungarn', 'rt-employee-manager'),
            'SI' => __('Slowenien', 'rt-employee-manager'),
            'CZ' => __('Tschechien', 'rt-employee-manager'),
            'SK' => __('Slowakei', 'rt-employee-manager'),
            'HR' => __('Kroatien', 'rt-employee-manager'),
            'PL' => __('Polen', 'rt-employee-manager'),
            'NL' => __('Niederlande', 'rt-employee-manager'),
            'BE' => __('Belgien', 'rt-employee-manager'),
            'LU' => __('Luxemburg', 'rt-employee-manager'),
            'ES' => __('Spanien', 'rt-employee-manager'),
            'PT' => __('Portugal', 'rt-employee-manager'),
            'OTHER' => __('Andere', 'rt-employee-manager')
        );
    }
    
    /**
     * Get user's employee count
     */
    public function get_user_employee_count($user_id) {
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
     * Check if user can add more employees
     */
    public function can_user_add_employee($user_id) {
        $current_count = $this->get_user_employee_count($user_id);
        $limit = get_user_meta($user_id, 'employee_limit', true) ?: 50;
        
        return $current_count < $limit;
    }
    
    /**
     * Log user update
     */
    private function log_user_update($user_id) {
        if (get_option('rt_employee_manager_enable_logging')) {
            $user = get_user_by('ID', $user_id);
            error_log(sprintf(
                'RT Employee Manager: User profile updated - User ID: %d, Username: %s, Updated by: %d',
                $user_id,
                $user->user_login,
                get_current_user_id()
            ));
        }
    }
    
    /**
     * Get user's company information
     */
    public function get_user_company_info($user_id) {
        return array(
            'company_name' => get_user_meta($user_id, 'company_name', true),
            'uid_number' => get_user_meta($user_id, 'uid_number', true),
            'phone' => get_user_meta($user_id, 'phone', true),
            'address' => array(
                'street' => get_user_meta($user_id, 'address_street', true),
                'postcode' => get_user_meta($user_id, 'address_postcode', true),
                'city' => get_user_meta($user_id, 'address_city', true),
                'country' => get_user_meta($user_id, 'address_country', true)
            ),
            'employee_limit' => get_user_meta($user_id, 'employee_limit', true) ?: 50,
            'account_status' => get_user_meta($user_id, 'account_status', true) ?: 'active'
        );
    }
}