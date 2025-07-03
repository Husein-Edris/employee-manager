<?php

if (!defined('ABSPATH')) {
    exit;
}

class RT_Employee_Manager_ACF_Integration
{

    public function __construct()
    {
        add_action('acf/init', array($this, 'register_field_groups'));
        add_action('acf/save_post', array($this, 'save_post_handler'), 20);
        add_filter('acf/load_value/name=employer_id', array($this, 'load_employer_id'), 10, 3);
        add_filter('acf/update_value/name=employer_id', array($this, 'update_employer_id'), 10, 3);
        add_filter('acf/prepare_field/name=employer_id', array($this, 'prepare_employer_id_field'), 10, 1);
        add_filter('acf/format_value/name=employer_id', array($this, 'format_employer_id_display'), 10, 3);
        
        // Clean up placeholder data on load
        add_action('acf/load_field/name=vorname', array($this, 'clean_placeholder_data'));
        add_action('acf/load_field/name=nachname', array($this, 'clean_placeholder_data'));
        add_action('acf/load_field/name=sozialversicherungsnummer', array($this, 'clean_placeholder_data'));
    }

    /**
     * Register ACF field groups programmatically
     */
    public function register_field_groups()
    {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        // Employee (Angestellte) field group
        acf_add_local_field_group(array(
            'key' => 'group_angestellte_details',
            'title' => __('Angestellte Details', 'rt-employee-manager'),
            'fields' => array(
                array(
                    'key' => 'field_anrede',
                    'label' => __('Anrede', 'rt-employee-manager'),
                    'name' => 'anrede',
                    'type' => 'select',
                    'choices' => array(
                        'Herr' => __('Herr', 'rt-employee-manager'),
                        'Frau' => __('Frau', 'rt-employee-manager'),
                        'Divers' => __('Divers', 'rt-employee-manager')
                    ),
                    'allow_null' => 1,
                    'wrapper' => array('width' => '33.33'),
                ),
                array(
                    'key' => 'field_vorname',
                    'label' => __('Vorname', 'rt-employee-manager'),
                    'name' => 'vorname',
                    'type' => 'text',
                    'required' => 1,
                    'wrapper' => array('width' => '33.33'),
                ),
                array(
                    'key' => 'field_nachname',
                    'label' => __('Nachname', 'rt-employee-manager'),
                    'name' => 'nachname',
                    'type' => 'text',
                    'required' => 1,
                    'wrapper' => array('width' => '33.33'),
                ),
                array(
                    'key' => 'field_svnr',
                    'label' => __('Sozialversicherungsnummer', 'rt-employee-manager'),
                    'name' => 'sozialversicherungsnummer',
                    'type' => 'text',
                    'required' => 1,
                    'instructions' => __('Sozialversicherungsnummer ohne Leerzeichen eingeben', 'rt-employee-manager'),
                    'wrapper' => array('width' => '50'),
                    'maxlength' => 10
                ),
                array(
                    'key' => 'field_geburtsdatum',
                    'label' => __('Geburtsdatum', 'rt-employee-manager'),
                    'name' => 'geburtsdatum',
                    'type' => 'date_picker',
                    'display_format' => 'd.m.Y',
                    'return_format' => 'd.m.Y',
                    'wrapper' => array('width' => '50'),
                ),
                array(
                    'key' => 'field_staatsangehoerigkeit',
                    'label' => __('Staatsangehörigkeit', 'rt-employee-manager'),
                    'name' => 'staatsangehoerigkeit',
                    'type' => 'text',
                    'wrapper' => array('width' => '50'),
                ),
                array(
                    'key' => 'field_email',
                    'label' => __('E-Mail Adresse', 'rt-employee-manager'),
                    'name' => 'email',
                    'type' => 'email',
                    'wrapper' => array('width' => '50'),
                ),
                array(
                    'key' => 'field_adresse_group',
                    'label' => __('Adresse', 'rt-employee-manager'),
                    'name' => 'adresse',
                    'type' => 'group',
                    'layout' => 'block',
                    'sub_fields' => array(
                        array(
                            'key' => 'field_strasse',
                            'label' => __('Straße und Hausnummer', 'rt-employee-manager'),
                            'name' => 'strasse',
                            'type' => 'text',
                            'wrapper' => array('width' => '100'),
                        ),
                        array(
                            'key' => 'field_plz',
                            'label' => __('PLZ', 'rt-employee-manager'),
                            'name' => 'plz',
                            'type' => 'text',
                            'wrapper' => array('width' => '30'),
                        ),
                        array(
                            'key' => 'field_ort',
                            'label' => __('Ort', 'rt-employee-manager'),
                            'name' => 'ort',
                            'type' => 'text',
                            'wrapper' => array('width' => '70'),
                        ),
                    ),
                ),
                array(
                    'key' => 'field_personenstand',
                    'label' => __('Personenstand', 'rt-employee-manager'),
                    'name' => 'personenstand',
                    'type' => 'select',
                    'choices' => array(
                        'Ledig' => __('Ledig', 'rt-employee-manager'),
                        'Verheiratet' => __('Verheiratet', 'rt-employee-manager'),
                        'Geschieden' => __('Geschieden', 'rt-employee-manager'),
                        'Verwitwet' => __('Verwitwet', 'rt-employee-manager')
                    ),
                    'allow_null' => 1,
                    'wrapper' => array('width' => '50'),
                ),
                array(
                    'key' => 'field_eintrittsdatum',
                    'label' => __('Eintrittsdatum', 'rt-employee-manager'),
                    'name' => 'eintrittsdatum',
                    'type' => 'date_picker',
                    'display_format' => 'd.m.Y',
                    'return_format' => 'd.m.Y',
                    'required' => 1,
                    'wrapper' => array('width' => '50'),
                ),
                array(
                    'key' => 'field_bezeichnung_tatigkeit',
                    'label' => __('Bezeichnung der Tätigkeit', 'rt-employee-manager'),
                    'name' => 'bezeichnung_der_tatigkeit',
                    'type' => 'text',
                    'wrapper' => array('width' => '50'),
                ),
                array(
                    'key' => 'field_art_dienstverhaeltnis',
                    'label' => __('Art des Dienstverhältnisses', 'rt-employee-manager'),
                    'name' => 'art_des_dienstverhaltnisses',
                    'type' => 'select',
                    'choices' => array(
                        'Angestellter' => __('Angestellter', 'rt-employee-manager'),
                        'Arbeiter/in' => __('Arbeiter/in', 'rt-employee-manager'),
                        'Lehrling' => __('Lehrling', 'rt-employee-manager')
                    ),
                    'required' => 1,
                    'wrapper' => array('width' => '50'),
                ),
                array(
                    'key' => 'field_arbeitszeit_woche',
                    'label' => __('Arbeitszeit pro Woche', 'rt-employee-manager'),
                    'name' => 'arbeitszeit_pro_woche',
                    'type' => 'number',
                    'min' => 1,
                    'max' => 60,
                    'step' => 0.5,
                    'append' => 'Stunden',
                    'wrapper' => array('width' => '50'),
                ),
                array(
                    'key' => 'field_arbeitstagen',
                    'label' => __('Arbeitstagen', 'rt-employee-manager'),
                    'name' => 'arbeitstagen',
                    'type' => 'checkbox',
                    'choices' => array(
                        'Mo' => __('Montag', 'rt-employee-manager'),
                        'Di' => __('Dienstag', 'rt-employee-manager'),
                        'Mi' => __('Mittwoch', 'rt-employee-manager'),
                        'Do' => __('Donnerstag', 'rt-employee-manager'),
                        'Fr' => __('Freitag', 'rt-employee-manager'),
                        'Sa' => __('Samstag', 'rt-employee-manager'),
                        'So' => __('Sonntag', 'rt-employee-manager')
                    ),
                    'layout' => 'horizontal',
                    'wrapper' => array('width' => '50'),
                ),
                array(
                    'key' => 'field_gehalt_lohn',
                    'label' => __('Gehalt/Lohn', 'rt-employee-manager'),
                    'name' => 'gehaltlohn',
                    'type' => 'number',
                    'min' => 0,
                    'step' => 0.01,
                    'prepend' => '€',
                    'wrapper' => array('width' => '50'),
                ),
                array(
                    'key' => 'field_gehalt_type',
                    'label' => __('Gehalt/Lohn: Brutto/Netto', 'rt-employee-manager'),
                    'name' => 'type',
                    'type' => 'radio',
                    'choices' => array(
                        'Brutto' => __('Brutto', 'rt-employee-manager'),
                        'Netto' => __('Netto', 'rt-employee-manager')
                    ),
                    'layout' => 'horizontal',
                    'wrapper' => array('width' => '50'),
                ),
                array(
                    'key' => 'field_employer_id',
                    'label' => __('Arbeitgeber ID', 'rt-employee-manager'),
                    'name' => 'employer_id',
                    'type' => 'user',
                    'role' => array('kunden', 'administrator'),
                    'return_format' => 'id',
                    'wrapper' => array('width' => '50'),
                ),
                array(
                    'key' => 'field_status',
                    'label' => __('Status', 'rt-employee-manager'),
                    'name' => 'status',
                    'type' => 'select',
                    'choices' => array(
                        'active' => __('Aktiv', 'rt-employee-manager'),
                        'inactive' => __('Inaktiv', 'rt-employee-manager'),
                        'suspended' => __('Gesperrt', 'rt-employee-manager'),
                        'terminated' => __('Gekündigt', 'rt-employee-manager')
                    ),
                    'wrapper' => array('width' => '50'),
                ),
                array(
                    'key' => 'field_anmerkungen',
                    'label' => __('Anmerkungen', 'rt-employee-manager'),
                    'name' => 'anmerkungen',
                    'type' => 'textarea',
                    'rows' => 3,
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'angestellte',
                    ),
                ),
            ),
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'active' => true,
        ));

        // Client (Kunde) field group
        acf_add_local_field_group(array(
            'key' => 'group_kunden_details',
            'title' => __('Kunden Details', 'rt-employee-manager'),
            'fields' => array(
                array(
                    'key' => 'field_company_name',
                    'label' => __('Firmenname', 'rt-employee-manager'),
                    'name' => 'company_name',
                    'type' => 'text',
                    'required' => 1,
                    'wrapper' => array('width' => '50'),
                ),
                array(
                    'key' => 'field_uid_number',
                    'label' => __('UID-Nummer', 'rt-employee-manager'),
                    'name' => 'uid_number',
                    'type' => 'text',
                    'wrapper' => array('width' => '50'),
                ),
                array(
                    'key' => 'field_company_phone',
                    'label' => __('Telefonnummer', 'rt-employee-manager'),
                    'name' => 'phone',
                    'type' => 'text',
                    'wrapper' => array('width' => '50'),
                ),
                array(
                    'key' => 'field_company_email',
                    'label' => __('E-Mail', 'rt-employee-manager'),
                    'name' => 'email',
                    'type' => 'email',
                    'wrapper' => array('width' => '50'),
                ),
                array(
                    'key' => 'field_company_address_group',
                    'label' => __('Adresse', 'rt-employee-manager'),
                    'name' => 'address',
                    'type' => 'group',
                    'layout' => 'block',
                    'sub_fields' => array(
                        array(
                            'key' => 'field_company_street',
                            'label' => __('Straße und Hausnummer', 'rt-employee-manager'),
                            'name' => 'street',
                            'type' => 'text',
                            'wrapper' => array('width' => '100'),
                        ),
                        array(
                            'key' => 'field_company_postcode',
                            'label' => __('PLZ', 'rt-employee-manager'),
                            'name' => 'postcode',
                            'type' => 'text',
                            'wrapper' => array('width' => '30'),
                        ),
                        array(
                            'key' => 'field_company_city',
                            'label' => __('Ort', 'rt-employee-manager'),
                            'name' => 'city',
                            'type' => 'text',
                            'wrapper' => array('width' => '70'),
                        ),
                        array(
                            'key' => 'field_company_country',
                            'label' => __('Land', 'rt-employee-manager'),
                            'name' => 'country',
                            'type' => 'text',
                            'wrapper' => array('width' => '100'),
                        ),
                    ),
                ),
                array(
                    'key' => 'field_registration_date',
                    'label' => __('Registrierungsdatum', 'rt-employee-manager'),
                    'name' => 'registration_date',
                    'type' => 'date_time_picker',
                    'display_format' => 'd.m.Y H:i',
                    'return_format' => 'd.m.Y H:i',
                    'wrapper' => array('width' => '50'),
                ),
                array(
                    'key' => 'field_form_entry_id',
                    'label' => __('Formular Entry ID', 'rt-employee-manager'),
                    'name' => 'form_entry_id',
                    'type' => 'number',
                    'readonly' => 1,
                    'wrapper' => array('width' => '50'),
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'kunde',
                    ),
                ),
            ),
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'active' => true,
        ));
    }

    /**
     * Handle post save for additional processing
     */
    public function save_post_handler($post_id)
    {
        // Skip if it's not our post types
        if (!in_array(get_post_type($post_id), array('angestellte', 'kunde'))) {
            return;
        }

        // Skip revisions and autosaves
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        if (get_post_type($post_id) === 'angestellte') {
            $this->process_employee_save($post_id);
        } elseif (get_post_type($post_id) === 'kunde') {
            $this->process_client_save($post_id);
        }
    }

    /**
     * Process employee post save
     */
    private function process_employee_save($post_id)
    {
        // Update post title with employee name
        $vorname = get_field('vorname', $post_id);
        $nachname = get_field('nachname', $post_id);

        if ($vorname && $nachname) {
            $title = $vorname . ' ' . $nachname;
            wp_update_post(array(
                'ID' => $post_id,
                'post_title' => $title
            ));
        }

        // Validate and format SVNR
        $svnr = get_field('sozialversicherungsnummer', $post_id);
        if ($svnr) {
            $cleaned_svnr = preg_replace('/\D/', '', $svnr);
            if (strlen($cleaned_svnr) === 10) {
                update_field('sozialversicherungsnummer', $cleaned_svnr, $post_id);
            }
        }

        // Set employer ID if not set
        $employer_id = get_field('employer_id', $post_id);
        if (empty($employer_id) && is_user_logged_in()) {
            update_field('employer_id', get_current_user_id(), $post_id);
        }

        // Log the save
        $this->log_employee_update($post_id);
    }

    /**
     * Process client post save
     */
    private function process_client_save($post_id)
    {
        // Update post title with company name
        $company_name = get_field('company_name', $post_id);

        if ($company_name) {
            wp_update_post(array(
                'ID' => $post_id,
                'post_title' => $company_name
            ));
        }

        // Set registration date if not set
        $registration_date = get_field('registration_date', $post_id);
        if (empty($registration_date)) {
            update_field('registration_date', current_time('d.m.Y H:i'), $post_id);
        }
    }

    /**
     * Load employer ID default value
     */
    public function load_employer_id($value, $post_id, $field)
    {
        if (empty($value) && is_user_logged_in()) {
            return get_current_user_id();
        }
        return $value;
    }

    /**
     * Update employer ID with current user if empty
     */
    public function update_employer_id($value, $post_id, $field)
    {
        if (empty($value) && is_user_logged_in()) {
            return get_current_user_id();
        }
        return $value;
    }

    /**
     * Log employee update
     */
    private function log_employee_update($post_id)
    {
        if (get_option('rt_employee_manager_enable_logging')) {
            global $wpdb;

            $table_name = $wpdb->prefix . 'rt_employee_logs';

            $wpdb->insert(
                $table_name,
                array(
                    'employee_id' => $post_id,
                    'action' => 'updated',
                    'details' => 'Employee record updated via ACF',
                    'user_id' => get_current_user_id(),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%d', '%s', '%s')
            );
        }
    }

    /**
     * Get employee statistics for a user
     */
    public function get_user_employee_stats($user_id)
    {
        global $wpdb;

        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN pm_status.meta_value = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN pm_status.meta_value = 'inactive' THEN 1 ELSE 0 END) as inactive,
                SUM(CASE WHEN pm_status.meta_value = 'terminated' THEN 1 ELSE 0 END) as terminated
             FROM {$wpdb->postmeta} pm_employer
             INNER JOIN {$wpdb->posts} p ON pm_employer.post_id = p.ID
             LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = 'status'
             WHERE pm_employer.meta_key = 'employer_id' 
             AND pm_employer.meta_value = %d
             AND p.post_type = 'angestellte'
             AND p.post_status = 'publish'",
            $user_id
        ), ARRAY_A);

        return $stats;
    }

    /**
     * Prepare employer_id field - make it read-only for kunden users
     */
    public function prepare_employer_id_field($field)
    {
        $current_user = wp_get_current_user();
        
        if (in_array('kunden', $current_user->roles) && !current_user_can('manage_options')) {
            $field['readonly'] = 1;
            $field['disabled'] = 1;
            $field['instructions'] = __('Arbeitgeber kann nicht geändert werden', 'rt-employee-manager');
            
            // Set display value for the current user
            $field['value'] = $current_user->ID;
            $field['formatted_value'] = $current_user->display_name;
        }
        
        return $field;
    }

    /**
     * Format employer_id field display
     */
    public function format_employer_id_display($value, $post_id, $field)
    {
        if (empty($value)) {
            return $value;
        }

        $user = get_userdata($value);
        if ($user) {
            return $user->display_name;
        }

        return $value;
    }

    /**
     * Clean placeholder data from fields
     */
    public function clean_placeholder_data($field)
    {
        global $post;
        
        if (!$post || $post->post_type !== 'angestellte') {
            return $field;
        }
        
        $placeholder_values = array(
            'Max', 'Mustermann', '1234567890', 'Automatischt', 'gespeicherter'
        );
        
        $current_value = get_field($field['name'], $post->ID);
        
        if (in_array($current_value, $placeholder_values)) {
            // Clear the placeholder value
            update_field($field['name'], '', $post->ID);
            $field['value'] = '';
        }
        
        return $field;
    }

}