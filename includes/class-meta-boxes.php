<?php

if (!defined('ABSPATH')) {
    exit;
}

class RT_Employee_Manager_Meta_Boxes {
    
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'), 10, 2);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'cleanup_placeholder_data'));
        
        // Additional hooks for better save handling
        add_action('transition_post_status', array($this, 'handle_post_status_transition'), 10, 3);
        add_action('wp_insert_post', array($this, 'handle_post_insert'), 10, 2);
    }
    
    /**
     * Add meta boxes
     */
    public function add_meta_boxes() {
        // Employee meta box
        add_meta_box(
            'rt_employee_details',
            __('Mitarbeiterdaten', 'rt-employee-manager'),
            array($this, 'employee_meta_box_callback'),
            'angestellte',
            'normal',
            'high'
        );
        
        // Company meta box
        add_meta_box(
            'rt_company_details',
            __('Unternehmensdaten', 'rt-employee-manager'),
            array($this, 'company_meta_box_callback'),
            'kunde',
            'normal',
            'high'
        );
    }
    
    /**
     * Employee meta box callback
     */
    public function employee_meta_box_callback($post) {
        wp_nonce_field('rt_employee_meta_box', 'rt_employee_meta_box_nonce');
        
        // Get existing values and clean placeholder data
        // Try ACF fields first, then fallback to regular meta
        $anrede = function_exists('get_field') ? get_field('anrede', $post->ID) : get_post_meta($post->ID, 'anrede', true);
        $vorname = function_exists('get_field') ? get_field('vorname', $post->ID) : get_post_meta($post->ID, 'vorname', true);
        $nachname = function_exists('get_field') ? get_field('nachname', $post->ID) : get_post_meta($post->ID, 'nachname', true);
        $svnr = function_exists('get_field') ? get_field('sozialversicherungsnummer', $post->ID) : get_post_meta($post->ID, 'sozialversicherungsnummer', true);
        $geburtsdatum = function_exists('get_field') ? get_field('geburtsdatum', $post->ID) : get_post_meta($post->ID, 'geburtsdatum', true);
        $staatsangehoerigkeit = function_exists('get_field') ? get_field('staatsangehoerigkeit', $post->ID) : get_post_meta($post->ID, 'staatsangehoerigkeit', true);
        $email = function_exists('get_field') ? get_field('email', $post->ID) : get_post_meta($post->ID, 'email', true);
        
        // Clean placeholder data - only remove obviously fake/test data
        $placeholder_patterns = array('test', 'placeholder', 'example', 'dummy', 'sample');
        $obvious_test_values = array('123456789', '0000000000', '1111111111');
        
        // Only clean if it's obviously test data (contains test patterns or obvious fake values)
        $vorname_lower = strtolower($vorname);
        $nachname_lower = strtolower($nachname);
        
        foreach ($placeholder_patterns as $pattern) {
            if (strpos($vorname_lower, $pattern) !== false) $vorname = '';
            if (strpos($nachname_lower, $pattern) !== false) $nachname = '';
        }
        
        // Clean obvious fake SVNR values
        if (in_array($svnr, $obvious_test_values)) $svnr = '';
        
        // Clean obviously fake nationality
        if (in_array(strtolower($staatsangehoerigkeit), array('automatisch', 'gespeicherter', 'test', 'placeholder'))) {
            $staatsangehoerigkeit = '';
        }
        $adresse = function_exists('get_field') ? get_field('adresse', $post->ID) : get_post_meta($post->ID, 'adresse', true);
        $personenstand = function_exists('get_field') ? get_field('personenstand', $post->ID) : get_post_meta($post->ID, 'personenstand', true);
        $eintrittsdatum = function_exists('get_field') ? get_field('eintrittsdatum', $post->ID) : get_post_meta($post->ID, 'eintrittsdatum', true);
        $bezeichnung_der_tatigkeit = function_exists('get_field') ? get_field('bezeichnung_der_tatigkeit', $post->ID) : get_post_meta($post->ID, 'bezeichnung_der_tatigkeit', true);
        $art_des_dienstverhaltnisses = function_exists('get_field') ? get_field('art_des_dienstverhaltnisses', $post->ID) : get_post_meta($post->ID, 'art_des_dienstverhaltnisses', true);
        $arbeitszeit_pro_woche = function_exists('get_field') ? get_field('arbeitszeit_pro_woche', $post->ID) : get_post_meta($post->ID, 'arbeitszeit_pro_woche', true);
        $arbeitstagen = function_exists('get_field') ? get_field('arbeitstagen', $post->ID) : get_post_meta($post->ID, 'arbeitstagen', true);
        $gehaltlohn = function_exists('get_field') ? get_field('gehaltlohn', $post->ID) : get_post_meta($post->ID, 'gehaltlohn', true);
        $type = function_exists('get_field') ? get_field('type', $post->ID) : get_post_meta($post->ID, 'type', true);
        $employer_id = function_exists('get_field') ? get_field('employer_id', $post->ID) : get_post_meta($post->ID, 'employer_id', true);
        $status = function_exists('get_field') ? get_field('status', $post->ID) : get_post_meta($post->ID, 'status', true);
        $status = $status ?: 'active';
        $anmerkungen = function_exists('get_field') ? get_field('anmerkungen', $post->ID) : get_post_meta($post->ID, 'anmerkungen', true);
        
        // Default address structure
        if (!is_array($adresse)) {
            $adresse = array(
                'strasse' => '',
                'plz' => '',
                'ort' => ''
            );
        }
        
        ?>
        <div class="rt-meta-box-container">
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                <div>
                    <label for="anrede"><?php _e('Anrede', 'rt-employee-manager'); ?></label>
                    <select name="anrede" id="anrede" style="width: 100%;">
                        <option value=""><?php _e('Bitte wählen', 'rt-employee-manager'); ?></option>
                        <option value="Herr" <?php selected($anrede, 'Herr'); ?>><?php _e('Herr', 'rt-employee-manager'); ?></option>
                        <option value="Frau" <?php selected($anrede, 'Frau'); ?>><?php _e('Frau', 'rt-employee-manager'); ?></option>
                        <option value="Divers" <?php selected($anrede, 'Divers'); ?>><?php _e('Divers', 'rt-employee-manager'); ?></option>
                    </select>
                </div>
                <div>
                    <label for="vorname"><?php _e('Vorname', 'rt-employee-manager'); ?> *</label>
                    <input type="text" name="vorname" id="vorname" value="<?php echo esc_attr($vorname); ?>" required style="width: 100%;" />
                </div>
                <div>
                    <label for="nachname"><?php _e('Nachname', 'rt-employee-manager'); ?> *</label>
                    <input type="text" name="nachname" id="nachname" value="<?php echo esc_attr($nachname); ?>" required style="width: 100%;" />
                </div>
            </div>
            
            <table class="form-table">
                
                <tr>
                    <td style="width: 50%;">
                        <label for="sozialversicherungsnummer"><?php _e('Sozialversicherungsnummer', 'rt-employee-manager'); ?></label>
                        <input type="text" name="sozialversicherungsnummer" id="sozialversicherungsnummer" 
                               value="<?php echo esc_attr($svnr); ?>" maxlength="10" style="width: 100%;" placeholder="Optional: 10-stellige Nummer" />
                        <p class="description"><?php _e('Optional: 10-stellige Sozialversicherungsnummer ohne Leerzeichen', 'rt-employee-manager'); ?></p>
                    </td>
                    <td style="width: 50%;">
                        <label for="geburtsdatum"><?php _e('Geburtsdatum', 'rt-employee-manager'); ?></label>
                        <input type="date" name="geburtsdatum" id="geburtsdatum" value="<?php echo esc_attr($geburtsdatum); ?>" style="width: 100%;" />
                    </td>
                </tr>
                
                <tr>
                    <td style="width: 50%;">
                        <label for="staatsangehoerigkeit"><?php _e('Staatsangehörigkeit', 'rt-employee-manager'); ?></label>
                        <input type="text" name="staatsangehoerigkeit" id="staatsangehoerigkeit" value="<?php echo esc_attr($staatsangehoerigkeit); ?>" style="width: 100%;" />
                    </td>
                    <td style="width: 50%;">
                        <label for="email"><?php _e('E-Mail-Adresse', 'rt-employee-manager'); ?></label>
                        <input type="email" name="email" id="email" value="<?php echo esc_attr($email); ?>" style="width: 100%;" />
                    </td>
                </tr>
            </table>
            
            <h4><?php _e('Adresse', 'rt-employee-manager'); ?></h4>
            <table class="form-table">
                <tr>
                    <td colspan="3">
                        <label for="adresse_strasse"><?php _e('Straße und Hausnummer', 'rt-employee-manager'); ?></label>
                        <input type="text" name="adresse[strasse]" id="adresse_strasse" 
                               value="<?php echo esc_attr($adresse['strasse']); ?>" style="width: 100%;" />
                    </td>
                </tr>
                <tr>
                    <td style="width: 30%;">
                        <label for="adresse_plz"><?php _e('PLZ', 'rt-employee-manager'); ?></label>
                        <input type="text" name="adresse[plz]" id="adresse_plz" 
                               value="<?php echo esc_attr($adresse['plz']); ?>" style="width: 100%;" />
                    </td>
                    <td style="width: 70%;">
                        <label for="adresse_ort"><?php _e('Ort', 'rt-employee-manager'); ?></label>
                        <input type="text" name="adresse[ort]" id="adresse_ort" 
                               value="<?php echo esc_attr($adresse['ort']); ?>" style="width: 100%;" />
                    </td>
                </tr>
            </table>
            
            <h4><?php _e('Beschäftigungsdaten', 'rt-employee-manager'); ?></h4>
            <table class="form-table">
                <tr>
                    <td style="width: 50%;">
                        <label for="personenstand"><?php _e('Personenstand', 'rt-employee-manager'); ?></label>
                        <select name="personenstand" id="personenstand" style="width: 100%;">
                            <option value=""><?php _e('Bitte wählen', 'rt-employee-manager'); ?></option>
                            <option value="Ledig" <?php selected($personenstand, 'Ledig'); ?>><?php _e('Ledig', 'rt-employee-manager'); ?></option>
                            <option value="Verheiratet" <?php selected($personenstand, 'Verheiratet'); ?>><?php _e('Verheiratet', 'rt-employee-manager'); ?></option>
                            <option value="Geschieden" <?php selected($personenstand, 'Geschieden'); ?>><?php _e('Geschieden', 'rt-employee-manager'); ?></option>
                            <option value="Verwitwet" <?php selected($personenstand, 'Verwitwet'); ?>><?php _e('Verwitwet', 'rt-employee-manager'); ?></option>
                        </select>
                    </td>
                    <td style="width: 50%;">
                        <label for="eintrittsdatum"><?php _e('Eintrittsdatum', 'rt-employee-manager'); ?> *</label>
                        <input type="date" name="eintrittsdatum" id="eintrittsdatum" value="<?php echo esc_attr($eintrittsdatum); ?>" required style="width: 100%;" />
                    </td>
                </tr>
                
                <tr>
                    <td style="width: 50%;">
                        <label for="bezeichnung_der_tatigkeit"><?php _e('Bezeichnung der Tätigkeit', 'rt-employee-manager'); ?></label>
                        <input type="text" name="bezeichnung_der_tatigkeit" id="bezeichnung_der_tatigkeit" 
                               value="<?php echo esc_attr($bezeichnung_der_tatigkeit); ?>" style="width: 100%;" />
                    </td>
                    <td style="width: 50%;">
                        <label for="art_des_dienstverhaltnisses"><?php _e('Art des Dienstverhältnisses', 'rt-employee-manager'); ?> *</label>
                        <select name="art_des_dienstverhaltnisses" id="art_des_dienstverhaltnisses" required style="width: 100%;">
                            <option value=""><?php _e('Bitte wählen', 'rt-employee-manager'); ?></option>
                            <option value="Angestellter" <?php selected($art_des_dienstverhaltnisses, 'Angestellter'); ?>><?php _e('Angestellter', 'rt-employee-manager'); ?></option>
                            <option value="Arbeiter/in" <?php selected($art_des_dienstverhaltnisses, 'Arbeiter/in'); ?>><?php _e('Arbeiter/in', 'rt-employee-manager'); ?></option>
                            <option value="Lehrling" <?php selected($art_des_dienstverhaltnisses, 'Lehrling'); ?>><?php _e('Lehrling', 'rt-employee-manager'); ?></option>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <td style="width: 50%;">
                        <label for="arbeitszeit_pro_woche"><?php _e('Arbeitszeit pro Woche (Stunden)', 'rt-employee-manager'); ?></label>
                        <input type="number" name="arbeitszeit_pro_woche" id="arbeitszeit_pro_woche" 
                               value="<?php echo esc_attr($arbeitszeit_pro_woche); ?>" min="1" max="60" step="0.5" style="width: 100%;" />
                    </td>
                    <td style="width: 50%;">
                        <label for="gehaltlohn"><?php _e('Gehalt/Lohn (€)', 'rt-employee-manager'); ?></label>
                        <input type="number" name="gehaltlohn" id="gehaltlohn" 
                               value="<?php echo esc_attr($gehaltlohn); ?>" min="0" step="0.01" style="width: 100%;" />
                    </td>
                </tr>
                
                <tr>
                    <td style="width: 50%;">
                        <label><?php _e('Gehalt/Lohn: Brutto/Netto', 'rt-employee-manager'); ?></label><br>
                        <label><input type="radio" name="type" value="Brutto" <?php checked($type, 'Brutto'); ?> /> <?php _e('Brutto', 'rt-employee-manager'); ?></label><br>
                        <label><input type="radio" name="type" value="Netto" <?php checked($type, 'Netto'); ?> /> <?php _e('Netto', 'rt-employee-manager'); ?></label>
                    </td>
                    <td style="width: 50%;">
                        <label><?php _e('Arbeitstage', 'rt-employee-manager'); ?></label><br>
                        <?php
                        $selected_days = is_array($arbeitstagen) ? $arbeitstagen : array();
                        $days = array(
                            'Mo' => __('Montag', 'rt-employee-manager'),
                            'Di' => __('Dienstag', 'rt-employee-manager'),
                            'Mi' => __('Mittwoch', 'rt-employee-manager'),
                            'Do' => __('Donnerstag', 'rt-employee-manager'),
                            'Fr' => __('Freitag', 'rt-employee-manager'),
                            'Sa' => __('Samstag', 'rt-employee-manager'),
                            'So' => __('Sonntag', 'rt-employee-manager')
                        );
                        foreach ($days as $key => $label): ?>
                            <label><input type="checkbox" name="arbeitstagen[]" value="<?php echo esc_attr($key); ?>" 
                                          <?php checked(in_array($key, $selected_days)); ?> /> <?php echo esc_html($label); ?></label>
                        <?php endforeach; ?>
                    </td>
                </tr>
                
                <tr>
                    <td style="width: 50%;">
                        <label for="employer_id"><?php _e('Zugehöriges Unternehmen', 'rt-employee-manager'); ?></label>
                        <?php
                        $current_user = wp_get_current_user();
                        $is_kunden = in_array('kunden', $current_user->roles);
                        
                        if ($is_kunden && !current_user_can('manage_options')): ?>
                            <input type="hidden" name="employer_id" value="<?php echo esc_attr($current_user->ID); ?>" />
                            <input type="text" value="<?php echo esc_attr($current_user->display_name); ?>" readonly style="width: 100%;" />
                            <p class="description"><?php _e('Unternehmen kann nicht geändert werden', 'rt-employee-manager'); ?></p>
                        <?php else: ?>
                            <select name="employer_id" id="employer_id" style="width: 100%;">
                                <option value=""><?php _e('Bitte wählen', 'rt-employee-manager'); ?></option>
                                <?php
                                $users = get_users(array('role__in' => array('kunden', 'administrator')));
                                foreach ($users as $user): ?>
                                    <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($employer_id, $user->ID); ?>>
                                        <?php echo esc_html($user->display_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </td>
                    <td style="width: 50%;">
                        <label for="status"><?php _e('Beschäftigungsstatus', 'rt-employee-manager'); ?></label>
                        <select name="status" id="status" style="width: 100%;">
                            <option value="active" <?php selected($status, 'active'); ?>><?php _e('Beschäftigt', 'rt-employee-manager'); ?></option>
                            <option value="inactive" <?php selected($status, 'inactive'); ?>><?php _e('Beurlaubt', 'rt-employee-manager'); ?></option>
                            <option value="suspended" <?php selected($status, 'suspended'); ?>><?php _e('Suspendiert', 'rt-employee-manager'); ?></option>
                            <option value="terminated" <?php selected($status, 'terminated'); ?>><?php _e('Ausgeschieden', 'rt-employee-manager'); ?></option>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <td colspan="2">
                        <label for="anmerkungen"><?php _e('Anmerkungen', 'rt-employee-manager'); ?></label>
                        <textarea name="anmerkungen" id="anmerkungen" rows="3" style="width: 100%;"><?php echo esc_textarea($anmerkungen); ?></textarea>
                    </td>
                </tr>
            </table>
        </div>
        
        <style>
        .rt-meta-box-container label {
            font-weight: 600;
            display: block;
            margin-bottom: 3px;
        }
        .rt-meta-box-container input,
        .rt-meta-box-container select,
        .rt-meta-box-container textarea {
            margin-bottom: 10px;
        }
        .rt-meta-box-container h4 {
            margin: 20px 0 10px 0;
            padding-bottom: 5px;
            border-bottom: 1px solid #ddd;
        }
        .rt-meta-box-container .description {
            font-size: 12px;
            color: #666;
            margin-top: -8px;
            margin-bottom: 10px;
        }
        </style>
        <?php
    }
    
    /**
     * Company meta box callback
     */
    public function company_meta_box_callback($post) {
        wp_nonce_field('rt_company_meta_box', 'rt_company_meta_box_nonce');
        
        // Get existing values
        $company_name = get_post_meta($post->ID, 'company_name', true);
        $uid_number = get_post_meta($post->ID, 'uid_number', true);
        $phone = get_post_meta($post->ID, 'phone', true);
        $email = get_post_meta($post->ID, 'email', true);
        $address = get_post_meta($post->ID, 'address', true);
        $registration_date = get_post_meta($post->ID, 'registration_date', true);
        $form_entry_id = get_post_meta($post->ID, 'form_entry_id', true);
        
        // Sync address data from user meta if missing
        $user_id = get_post_meta($post->ID, 'user_id', true);
        if ($user_id && (empty($address) || !is_array($address))) {
            $this->sync_user_meta_to_company_post($post->ID, $user_id);
            // Re-fetch the address after sync
            $address = get_post_meta($post->ID, 'address', true);
        }
        
        // Default address structure
        if (!is_array($address)) {
            $address = array(
                'street' => '',
                'postcode' => '',
                'city' => '',
                'country' => 'Austria'
            );
        }
        
        ?>
        <div class="rt-meta-box-container">
            <table class="form-table">
                <tr>
                    <td style="width: 50%;">
                        <label for="company_name"><?php _e('Unternehmensname', 'rt-employee-manager'); ?> *</label>
                        <input type="text" name="company_name" id="company_name" value="<?php echo esc_attr($company_name); ?>" required style="width: 100%;" />
                    </td>
                    <td style="width: 50%;">
                        <label for="uid_number"><?php _e('UID-Nummer', 'rt-employee-manager'); ?></label>
                        <input type="text" name="uid_number" id="uid_number" value="<?php echo esc_attr($uid_number); ?>" style="width: 100%;" />
                    </td>
                </tr>
                
                <tr>
                    <td style="width: 50%;">
                        <label for="phone"><?php _e('Telefonnummer', 'rt-employee-manager'); ?></label>
                        <input type="tel" name="phone" id="phone" value="<?php echo esc_attr($phone); ?>" style="width: 100%;" />
                    </td>
                    <td style="width: 50%;">
                        <label for="email"><?php _e('E-Mail', 'rt-employee-manager'); ?></label>
                        <input type="email" name="email" id="email" value="<?php echo esc_attr($email); ?>" style="width: 100%;" />
                    </td>
                </tr>
            </table>
            
            <h4><?php _e('Adresse', 'rt-employee-manager'); ?></h4>
            <table class="form-table">
                <tr>
                    <td colspan="2">
                        <label for="address_street"><?php _e('Straße und Hausnummer', 'rt-employee-manager'); ?></label>
                        <input type="text" name="address[street]" id="address_street" 
                               value="<?php echo esc_attr($address['street']); ?>" style="width: 100%;" />
                    </td>
                </tr>
                <tr>
                    <td style="width: 30%;">
                        <label for="address_postcode"><?php _e('PLZ', 'rt-employee-manager'); ?></label>
                        <input type="text" name="address[postcode]" id="address_postcode" 
                               value="<?php echo esc_attr($address['postcode']); ?>" style="width: 100%;" />
                    </td>
                    <td style="width: 70%;">
                        <label for="address_city"><?php _e('Ort', 'rt-employee-manager'); ?></label>
                        <input type="text" name="address[city]" id="address_city" 
                               value="<?php echo esc_attr($address['city']); ?>" style="width: 100%;" />
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <label for="address_country"><?php _e('Land', 'rt-employee-manager'); ?></label>
                        <input type="text" name="address[country]" id="address_country" 
                               value="<?php echo esc_attr($address['country']); ?>" style="width: 100%;" />
                    </td>
                </tr>
            </table>
            
            <h4><?php _e('Systeminformationen', 'rt-employee-manager'); ?></h4>
            <table class="form-table">
                <tr>
                    <td style="width: 50%;">
                        <label for="registration_date"><?php _e('Registrierungsdatum', 'rt-employee-manager'); ?></label>
                        <input type="datetime-local" name="registration_date" id="registration_date" 
                               value="<?php echo esc_attr($registration_date); ?>" style="width: 100%;" />
                    </td>
                    <td style="width: 50%;">
                        <label for="form_entry_id"><?php _e('Formular Entry ID', 'rt-employee-manager'); ?></label>
                        <input type="number" name="form_entry_id" id="form_entry_id" 
                               value="<?php echo esc_attr($form_entry_id); ?>" readonly style="width: 100%;" />
                        <p class="description"><?php _e('Nur zur Information', 'rt-employee-manager'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
    
    /**
     * Save meta box data
     */
    public function save_meta_boxes($post_id, $post) {
        // Skip auto-saves and revisions
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        
        // Skip if this is not our post type
        if (!in_array($post->post_type, array('angestellte', 'kunde'))) {
            return;
        }
        
        // Debug logging
        if (get_option('rt_employee_manager_enable_logging')) {
            error_log('RT Employee Manager: Saving meta boxes for post ' . $post_id . ', type: ' . $post->post_type);
            error_log('RT Employee Manager: User can edit: ' . (current_user_can('edit_post', $post_id) ? 'yes' : 'no'));
            error_log('RT Employee Manager: Nonce present: ' . (isset($_POST['rt_employee_meta_box_nonce']) || isset($_POST['rt_company_meta_box_nonce']) ? 'yes' : 'no'));
        }
        
        // Handle employee meta box
        if ($post->post_type === 'angestellte') {
            // Check if nonce is present OR if this is a direct backend save
            $has_nonce = isset($_POST['rt_employee_meta_box_nonce']) && wp_verify_nonce($_POST['rt_employee_meta_box_nonce'], 'rt_employee_meta_box');
            $is_backend_save = isset($_POST['post_type']) && $_POST['post_type'] === 'angestellte';
            
            if ($has_nonce || $is_backend_save) {
                if (!current_user_can('edit_post', $post_id)) {
                    return;
                }
                
                $this->save_employee_meta($post_id);
            }
        }
        
        // Handle company meta box
        if ($post->post_type === 'kunde' && isset($_POST['rt_company_meta_box_nonce'])) {
            if (!wp_verify_nonce($_POST['rt_company_meta_box_nonce'], 'rt_company_meta_box')) {
                return;
            }
            
            if (!current_user_can('edit_post', $post_id)) {
                return;
            }
            
            $this->save_company_meta($post_id);
        }
    }
    
    /**
     * Save employee meta data
     */
    private function save_employee_meta($post_id) {
        // Log the save attempt
        if (get_option('rt_employee_manager_enable_logging')) {
            error_log('RT Employee Manager: Saving employee meta for post ' . $post_id);
            error_log('RT Employee Manager: POST data keys: ' . implode(', ', array_keys($_POST)));
        }
        
        $fields = array(
            'anrede', 'vorname', 'nachname', 'sozialversicherungsnummer', 'geburtsdatum',
            'staatsangehoerigkeit', 'email', 'personenstand', 'eintrittsdatum',
            'bezeichnung_der_tatigkeit', 'art_des_dienstverhaltnisses', 'arbeitszeit_pro_woche',
            'gehaltlohn', 'type', 'employer_id', 'status', 'anmerkungen'
        );
        
        foreach ($fields as $field) {
            if (isset($_POST[$field]) && $_POST[$field] !== '') {
                $value = sanitize_text_field($_POST[$field]);
                update_post_meta($post_id, $field, $value);
                
                // Also update ACF field if ACF is available
                if (function_exists('update_field')) {
                    update_field($field, $value, $post_id);
                }
                
                // Debug logging
                if (get_option('rt_employee_manager_enable_logging')) {
                    error_log("RT Employee Manager: Saved meta {$field} = {$value} for post {$post_id}");
                }
            } elseif (isset($_POST[$field]) && $_POST[$field] === '') {
                // Delete empty values to prevent storing empty strings
                delete_post_meta($post_id, $field);
                
                // Also delete ACF field if available
                if (function_exists('delete_field')) {
                    delete_field($field, $post_id);
                }
            }
        }
        
        // Handle address array
        if (isset($_POST['adresse']) && is_array($_POST['adresse'])) {
            $address = array_map('sanitize_text_field', $_POST['adresse']);
            update_post_meta($post_id, 'adresse', $address);
        }
        
        // Handle working days array
        if (isset($_POST['arbeitstagen']) && is_array($_POST['arbeitstagen'])) {
            $days = array_map('sanitize_text_field', $_POST['arbeitstagen']);
            update_post_meta($post_id, 'arbeitstagen', $days);
        } else {
            update_post_meta($post_id, 'arbeitstagen', array());
        }
        
        // Update post title with employee name and ensure it's published
        $vorname_value = isset($_POST['vorname']) ? sanitize_text_field($_POST['vorname']) : '';
        $nachname_value = isset($_POST['nachname']) ? sanitize_text_field($_POST['nachname']) : '';
        
        if (!empty($vorname_value) || !empty($nachname_value)) {
            $title = trim($vorname_value . ' ' . $nachname_value);
            
            if (!empty($title)) {
                // Get current post status
                $current_post = get_post($post_id);
                $post_status = $current_post->post_status;
                
                // Auto-publish if we have data
                if ($post_status === 'auto-draft' || $post_status === 'draft') {
                    $post_status = 'publish';
                }
                
                // If user explicitly clicked "Publish" or "Update"
                if (isset($_POST['publish']) || isset($_POST['save']) || isset($_POST['original_post_status'])) {
                    $post_status = 'publish';
                }
                
                wp_update_post(array(
                    'ID' => $post_id,
                    'post_title' => $title,
                    'post_status' => $post_status
                ));
                
                if (get_option('rt_employee_manager_enable_logging')) {
                    error_log("RT Employee Manager: Updated post title to '{$title}' and status to '{$post_status}' for post {$post_id}");
                }
            }
        }
        
        // Set default status if not set
        if (!get_post_meta($post_id, 'status', true)) {
            update_post_meta($post_id, 'status', 'active');
        }
        
        // Set employer_id logic
        $current_employer_id = get_post_meta($post_id, 'employer_id', true);
        $current_user = wp_get_current_user();
        
        if (empty($current_employer_id) && empty($_POST['employer_id'])) {
            // Auto-assign for kunden users
            if (in_array('kunden', $current_user->roles)) {
                update_post_meta($post_id, 'employer_id', $current_user->ID);
                if (get_option('rt_employee_manager_enable_logging')) {
                    error_log("RT Employee Manager: Auto-assigned employer_id {$current_user->ID} for kunden user");
                }
            } elseif (current_user_can('manage_options')) {
                // For admin users, we need an employer to be selected
                // Get the first available kunden user as fallback
                $kunden_users = get_users(array('role' => 'kunden', 'number' => 1));
                if (!empty($kunden_users)) {
                    update_post_meta($post_id, 'employer_id', $kunden_users[0]->ID);
                    if (get_option('rt_employee_manager_enable_logging')) {
                        error_log("RT Employee Manager: Auto-assigned employer_id {$kunden_users[0]->ID} as fallback for admin");
                    }
                }
            }
        } elseif (!empty($_POST['employer_id']) && is_numeric($_POST['employer_id'])) {
            // Use the posted employer_id if valid
            update_post_meta($post_id, 'employer_id', intval($_POST['employer_id']));
            if (get_option('rt_employee_manager_enable_logging')) {
                error_log("RT Employee Manager: Set employer_id from POST: " . intval($_POST['employer_id']));
            }
        }
        
        // Clear statistics cache
        delete_transient('rt_admin_stats');
        if (!empty($_POST['employer_id'])) {
            delete_transient('rt_user_stats_' . $_POST['employer_id']);
        }
    }
    
    /**
     * Save company meta data
     */
    private function save_company_meta($post_id) {
        $fields = array('company_name', 'uid_number', 'phone', 'email', 'registration_date', 'form_entry_id');
        
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
            }
        }
        
        // Handle address array
        if (isset($_POST['address']) && is_array($_POST['address'])) {
            $address = array_map('sanitize_text_field', $_POST['address']);
            update_post_meta($post_id, 'address', $address);
        }
        
        // Update post title with company name
        if (isset($_POST['company_name'])) {
            wp_update_post(array(
                'ID' => $post_id,
                'post_title' => sanitize_text_field($_POST['company_name'])
            ));
        }
        
        // Set registration date if not set
        if (empty($_POST['registration_date'])) {
            update_post_meta($post_id, 'registration_date', current_time('Y-m-d\TH:i'));
        }
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        global $post_type;
        
        if (in_array($post_type, array('angestellte', 'kunde')) && in_array($hook, array('post.php', 'post-new.php'))) {
            wp_enqueue_script('jquery');
        }
    }
    
    /**
     * Get employee statistics for a user (compatibility with old ACF integration)
     */
    public function get_user_employee_stats($user_id) {
        global $wpdb;
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN COALESCE(pm_status.meta_value, 'active') = 'active' THEN 1 ELSE 0 END) as active,
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
        
        return $stats ?: array('total' => 0, 'active' => 0, 'inactive' => 0, 'terminated' => 0);
    }
    
    /**
     * Cleanup placeholder data from existing posts
     */
    public function cleanup_placeholder_data() {
        // Only run once
        if (get_option('rt_placeholder_data_cleaned')) {
            return;
        }
        
        // Only admins can trigger this
        if (!current_user_can('manage_options')) {
            return;
        }
        
        global $wpdb;
        
        $placeholder_values = array('Max', 'Mustermann', '1234567890', 'Automatisch', 'gespeicherter');
        
        foreach ($placeholder_values as $value) {
            $wpdb->update(
                $wpdb->postmeta,
                array('meta_value' => ''),
                array('meta_value' => $value),
                array('%s'),
                array('%s')
            );
        }
        
        // Mark as cleaned
        update_option('rt_placeholder_data_cleaned', true);
        
        error_log('RT Employee Manager: Cleaned placeholder data from database');
    }
    
    /**
     * Sync user meta data to company post meta
     */
    private function sync_user_meta_to_company_post($post_id, $user_id) {
        // Get address data from user meta
        $address_street = get_user_meta($user_id, 'address_street', true);
        $address_postcode = get_user_meta($user_id, 'address_postcode', true);
        $address_city = get_user_meta($user_id, 'address_city', true);
        $address_country = get_user_meta($user_id, 'address_country', true);
        
        // Create address array for post meta
        if (!empty($address_street) || !empty($address_city) || !empty($address_postcode)) {
            $address_array = array(
                'street' => $address_street,
                'postcode' => $address_postcode,
                'city' => $address_city,
                'country' => $address_country
            );
            update_post_meta($post_id, 'address', $address_array);
        }
        
        // Also sync other company data if missing
        $company_data_mapping = array(
            'company_name' => 'company_name',
            'uid_number' => 'uid_number',
            'phone' => 'phone'
        );
        
        foreach ($company_data_mapping as $post_meta_key => $user_meta_key) {
            $existing_value = get_post_meta($post_id, $post_meta_key, true);
            if (empty($existing_value)) {
                $user_value = get_user_meta($user_id, $user_meta_key, true);
                if (!empty($user_value)) {
                    update_post_meta($post_id, $post_meta_key, $user_value);
                }
            }
        }
    }
    
    /**
     * Handle post status transitions for better save handling
     */
    public function handle_post_status_transition($new_status, $old_status, $post) {
        if ($post->post_type !== 'angestellte') {
            return;
        }
        
        if (get_option('rt_employee_manager_enable_logging')) {
            error_log("RT Employee Manager: Post status transition from '{$old_status}' to '{$new_status}' for post {$post->ID}");
        }
        
        // If transitioning to published, ensure meta data is saved
        if ($new_status === 'publish' && $old_status !== 'publish') {
            $this->ensure_employee_meta_on_publish($post->ID);
        }
    }
    
    /**
     * Handle post insert for new posts
     */
    public function handle_post_insert($post_id, $post) {
        if ($post->post_type !== 'angestellte') {
            return;
        }
        
        // For new posts, ensure defaults are set
        if ($post->post_status === 'auto-draft') {
            return; // Skip auto-drafts
        }
        
        $this->ensure_employee_defaults($post_id);
    }
    
    /**
     * Ensure employee meta is properly saved when publishing
     */
    private function ensure_employee_meta_on_publish($post_id) {
        // Check if we have basic meta data
        $vorname = get_post_meta($post_id, 'vorname', true);
        $nachname = get_post_meta($post_id, 'nachname', true);
        $employer_id = get_post_meta($post_id, 'employer_id', true);
        
        // Set defaults if missing
        if (empty($employer_id)) {
            $current_user = wp_get_current_user();
            if (in_array('kunden', $current_user->roles)) {
                update_post_meta($post_id, 'employer_id', $current_user->ID);
            } elseif (current_user_can('manage_options')) {
                // Get first kunden user as fallback
                $kunden_users = get_users(array('role' => 'kunden', 'number' => 1));
                if (!empty($kunden_users)) {
                    update_post_meta($post_id, 'employer_id', $kunden_users[0]->ID);
                }
            }
        }
        
        if (empty(get_post_meta($post_id, 'status', true))) {
            update_post_meta($post_id, 'status', 'active');
        }
        
        if (get_option('rt_employee_manager_enable_logging')) {
            error_log("RT Employee Manager: Ensured meta data for published post {$post_id}");
        }
    }
    
    /**
     * Ensure employee defaults are set
     */
    private function ensure_employee_defaults($post_id) {
        if (empty(get_post_meta($post_id, 'status', true))) {
            update_post_meta($post_id, 'status', 'active');
        }
        
        if (empty(get_post_meta($post_id, 'employer_id', true))) {
            $current_user = wp_get_current_user();
            if (in_array('kunden', $current_user->roles)) {
                update_post_meta($post_id, 'employer_id', $current_user->ID);
            }
        }
    }
}