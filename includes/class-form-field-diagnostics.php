<?php

/**
 * RT Employee Manager Form Field Diagnostics
 * Tool to help identify and map Gravity Form field IDs
 */

// Security check
if (!defined('ABSPATH')) {
    exit('Direct access forbidden.');
}

class RT_Employee_Manager_Form_Field_Diagnostics
{
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_diagnostics_menu'), 20); // After debug dashboard
        add_action('wp_ajax_rt_analyze_form', array($this, 'analyze_form_ajax'));
        add_action('gform_after_submission', array($this, 'log_form_submission'), 5, 2);
    }
    
    /**
     * Add diagnostics menu
     */
    public function add_diagnostics_menu()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        add_submenu_page(
            'rt-employee-manager',
            __('Form Diagnostics', 'rt-employee-manager'),
            __('Form Diagnostics', 'rt-employee-manager'),
            'manage_options',
            'rt-employee-form-diagnostics',
            array($this, 'render_diagnostics_page')
        );
    }
    
    /**
     * Log every form submission with all field data
     */
    public function log_form_submission($entry, $form)
    {
        rt_employee_debug()->info('Complete Form Submission Data', [
            'form_id' => $form['id'],
            'form_title' => $form['title'],
            'entry_id' => $entry['id'],
            'form_fields' => $this->map_form_fields($form),
            'entry_data' => $this->map_entry_data($entry),
            'field_mapping_suggestions' => $this->suggest_field_mappings($form, $entry)
        ], ['type' => 'form_diagnostics']);
    }
    
    /**
     * Map form fields structure
     */
    private function map_form_fields($form)
    {
        $fields = [];
        
        foreach ($form['fields'] as $field) {
            $field_info = [
                'id' => $field->id,
                'type' => $field->type,
                'label' => $field->label,
                'admin_label' => $field->adminLabel ?? '',
                'css_class' => $field->cssClass ?? '',
                'custom_value' => $field->customValue ?? ''
            ];
            
            // Add subfields for complex fields
            if (isset($field->inputs) && is_array($field->inputs)) {
                $field_info['subfields'] = [];
                foreach ($field->inputs as $input) {
                    $field_info['subfields'][] = [
                        'id' => $input['id'],
                        'label' => $input['label'] ?? ''
                    ];
                }
            }
            
            $fields[] = $field_info;
        }
        
        return $fields;
    }
    
    /**
     * Map entry data
     */
    private function map_entry_data($entry)
    {
        $data = [];
        
        foreach ($entry as $key => $value) {
            if (is_numeric($key) || strpos($key, '.') !== false) {
                $data[$key] = [
                    'value' => $value,
                    'length' => strlen($value),
                    'type' => $this->guess_field_type($value)
                ];
            }
        }
        
        return $data;
    }
    
    /**
     * Suggest field mappings based on content analysis
     */
    private function suggest_field_mappings($form, $entry)
    {
        $suggestions = [];
        
        foreach ($entry as $field_id => $value) {
            if (!is_numeric($field_id) && strpos($field_id, '.') === false) {
                continue;
            }
            
            if (empty($value)) {
                continue;
            }
            
            $field_name = $this->analyze_field_content($value, $field_id, $form);
            if ($field_name) {
                $suggestions[$field_id] = [
                    'suggested_name' => $field_name,
                    'value' => $value,
                    'confidence' => $this->get_confidence_level($field_name, $value)
                ];
            }
        }
        
        return $suggestions;
    }
    
    /**
     * Analyze field content to suggest mapping
     */
    private function analyze_field_content($value, $field_id, $form)
    {
        $value_lower = strtolower($value);
        $field_label = $this->get_field_label($field_id, $form);
        $label_lower = strtolower($field_label);
        
        // Email detection
        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        }
        
        // Phone detection
        if (preg_match('/^[+]?[\d\s\-\(\)]{8,}$/', $value)) {
            return 'telefon';
        }
        
        // Date detection
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) || preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $value)) {
            if (strpos($label_lower, 'geburt') !== false || strpos($label_lower, 'birth') !== false) {
                return 'geburtsdatum';
            }
            if (strpos($label_lower, 'eintritt') !== false || strpos($label_lower, 'start') !== false || strpos($label_lower, 'hire') !== false) {
                return 'eintrittsdatum';
            }
            return 'date_field';
        }
        
        // SVNR detection (10 digits)
        if (preg_match('/^\d{10}$/', preg_replace('/\D/', '', $value))) {
            return 'sozialversicherungsnummer';
        }
        
        // Name detection by label
        if (strpos($label_lower, 'vorname') !== false || strpos($label_lower, 'first') !== false) {
            return 'vorname';
        }
        if (strpos($label_lower, 'nachname') !== false || strpos($label_lower, 'last') !== false || strpos($label_lower, 'surname') !== false) {
            return 'nachname';
        }
        
        // Job/position detection
        if (strpos($label_lower, 'position') !== false || strpos($label_lower, 'job') !== false || strpos($label_lower, 'beruf') !== false) {
            return 'bezeichnung_der_tatigkeit';
        }
        
        // Salary detection
        if (is_numeric($value) && $value > 500 && $value < 10000) {
            if (strpos($label_lower, 'gehalt') !== false || strpos($label_lower, 'salary') !== false || strpos($label_lower, 'lohn') !== false) {
                return 'gehaltlohn';
            }
        }
        
        // Hours detection
        if (is_numeric($value) && $value >= 10 && $value <= 60) {
            if (strpos($label_lower, 'stunden') !== false || strpos($label_lower, 'hours') !== false || strpos($label_lower, 'arbeitszeit') !== false) {
                return 'arbeitszeit_pro_woche';
            }
        }
        
        return null;
    }
    
    /**
     * Get field label from form structure
     */
    private function get_field_label($field_id, $form)
    {
        foreach ($form['fields'] as $field) {
            if ($field->id == $field_id) {
                return $field->label;
            }
            
            // Check subfields
            if (isset($field->inputs) && is_array($field->inputs)) {
                foreach ($field->inputs as $input) {
                    if ($input['id'] == $field_id) {
                        return $input['label'] ?? $field->label;
                    }
                }
            }
        }
        
        return '';
    }
    
    /**
     * Guess field type from value
     */
    private function guess_field_type($value)
    {
        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        }
        if (is_numeric($value)) {
            return 'number';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return 'date';
        }
        if (preg_match('/^[+]?[\d\s\-\(\)]{8,}$/', $value)) {
            return 'phone';
        }
        return 'text';
    }
    
    /**
     * Get confidence level for suggestion
     */
    private function get_confidence_level($field_name, $value)
    {
        switch ($field_name) {
            case 'email':
                return filter_var($value, FILTER_VALIDATE_EMAIL) ? 95 : 50;
            case 'sozialversicherungsnummer':
                return preg_match('/^\d{10}$/', preg_replace('/\D/', '', $value)) ? 90 : 60;
            case 'telefon':
                return preg_match('/^[+]?[\d\s\-\(\)]{8,}$/', $value) ? 80 : 40;
            default:
                return 70;
        }
    }
    
    /**
     * AJAX handler to analyze specific form
     */
    public function analyze_form_ajax()
    {
        check_ajax_referer('rt_form_diagnostics', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $form_id = intval($_POST['form_id']);
        
        if (!class_exists('GFAPI')) {
            wp_send_json_error('Gravity Forms not available');
        }
        
        $form = GFAPI::get_form($form_id);
        if (!$form) {
            wp_send_json_error('Form not found');
        }
        
        $entries = GFAPI::get_entries($form_id, [], null, ['page_size' => 5]);
        
        $analysis = [
            'form_structure' => $this->map_form_fields($form),
            'sample_entries' => [],
            'field_suggestions' => []
        ];
        
        foreach ($entries as $entry) {
            $analysis['sample_entries'][] = [
                'entry_id' => $entry['id'],
                'data' => $this->map_entry_data($entry),
                'suggestions' => $this->suggest_field_mappings($form, $entry)
            ];
        }
        
        wp_send_json_success($analysis);
    }
    
    /**
     * Render diagnostics page
     */
    public function render_diagnostics_page()
    {
        $forms = [];
        
        if (class_exists('GFAPI')) {
            $forms = GFAPI::get_forms();
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('RT Employee Manager - Form Field Diagnostics', 'rt-employee-manager'); ?></h1>
            
            <div class="notice notice-info">
                <p><strong><?php _e('Purpose:', 'rt-employee-manager'); ?></strong> 
                    <?php _e('This tool helps you identify the correct field IDs in your Gravity Forms for employee registration.', 'rt-employee-manager'); ?>
                </p>
            </div>
            
            <?php if (empty($forms)): ?>
                <div class="notice notice-warning">
                    <p><?php _e('No Gravity Forms found. Please create your employee registration form first.', 'rt-employee-manager'); ?></p>
                </div>
            <?php else: ?>
                
                <div class="card" style="padding: 20px; margin: 20px 0;">
                    <h2><?php _e('Analyze Form Fields', 'rt-employee-manager'); ?></h2>
                    
                    <p>
                        <label for="form-select"><?php _e('Select Form:', 'rt-employee-manager'); ?></label>
                        <select id="form-select" style="margin-left: 10px;">
                            <option value=""><?php _e('Choose a form...', 'rt-employee-manager'); ?></option>
                            <?php foreach ($forms as $form): ?>
                                <option value="<?php echo esc_attr($form['id']); ?>">
                                    #<?php echo $form['id']; ?> - <?php echo esc_html($form['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button id="analyze-form" class="button button-primary" style="margin-left: 10px;">
                            <?php _e('Analyze Form', 'rt-employee-manager'); ?>
                        </button>
                    </p>
                </div>
                
                <div id="analysis-results" style="display: none;">
                    <div class="card" style="padding: 20px;">
                        <h2><?php _e('Analysis Results', 'rt-employee-manager'); ?></h2>
                        <div id="results-content"></div>
                    </div>
                </div>
                
                <div class="card" style="padding: 20px; margin: 20px 0;">
                    <h2><?php _e('Recent Form Submissions', 'rt-employee-manager'); ?></h2>
                    <p><?php _e('Recent form submissions are logged automatically. Check the Debug Logs for detailed field mapping information.', 'rt-employee-manager'); ?></p>
                    <p>
                        <a href="<?php echo admin_url('admin.php?page=rt-employee-debug'); ?>" class="button">
                            <?php _e('View Debug Logs', 'rt-employee-manager'); ?>
                        </a>
                    </p>
                </div>
                
                <div class="card" style="padding: 20px; margin: 20px 0;">
                    <h2><?php _e('Current Field Mappings', 'rt-employee-manager'); ?></h2>
                    <p><?php _e('These are the field IDs currently configured in the plugin:', 'rt-employee-manager'); ?></p>
                    
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php _e('Employee Field', 'rt-employee-manager'); ?></th>
                                <th><?php _e('Form Field IDs Tried', 'rt-employee-manager'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td>Vorname</td><td>28, 1.3, 2.3, vorname, first_name, firstname</td></tr>
                            <tr><td>Nachname</td><td>27, 1.6, 2.6, nachname, last_name, lastname</td></tr>
                            <tr><td>SVNR</td><td>53, 3, 4, 5, svnr, sozialversicherungsnummer</td></tr>
                            <tr><td>Email</td><td>26, 6, 7, 8, email, e_mail</td></tr>
                            <tr><td>Telefon</td><td>25, 9, 10, 11, telefon, phone, telephone</td></tr>
                            <tr><td>Geburtsdatum</td><td>29, 12, 13, 14, geburtsdatum, birth_date</td></tr>
                            <tr><td>Eintrittsdatum</td><td>35, 15, 16, 17, eintrittsdatum, entry_date, hire_date</td></tr>
                            <tr><td>Position</td><td>37, 18, 19, 20, position, job_title, bezeichnung_der_tatigkeit</td></tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#analyze-form').on('click', function() {
                var formId = $('#form-select').val();
                
                if (!formId) {
                    alert('Please select a form first');
                    return;
                }
                
                var $button = $(this);
                $button.prop('disabled', true).text('Analyzing...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'rt_analyze_form',
                        form_id: formId,
                        nonce: '<?php echo wp_create_nonce('rt_form_diagnostics'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            displayResults(response.data);
                            $('#analysis-results').show();
                        } else {
                            alert('Analysis failed: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('AJAX error occurred');
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('Analyze Form');
                    }
                });
            });
            
            function displayResults(data) {
                var html = '<h3>Form Structure</h3>';
                html += '<table class="widefat striped"><thead><tr><th>Field ID</th><th>Type</th><th>Label</th><th>Admin Label</th></tr></thead><tbody>';
                
                data.form_structure.forEach(function(field) {
                    html += '<tr>';
                    html += '<td>' + field.id + '</td>';
                    html += '<td>' + field.type + '</td>';
                    html += '<td>' + field.label + '</td>';
                    html += '<td>' + (field.admin_label || '') + '</td>';
                    html += '</tr>';
                    
                    if (field.subfields) {
                        field.subfields.forEach(function(subfield) {
                            html += '<tr style="background: #f9f9f9;">';
                            html += '<td style="padding-left: 20px;">└ ' + subfield.id + '</td>';
                            html += '<td>subfield</td>';
                            html += '<td style="padding-left: 20px;">' + (subfield.label || '') + '</td>';
                            html += '<td></td>';
                            html += '</tr>';
                        });
                    }
                });
                
                html += '</tbody></table>';
                
                if (data.sample_entries.length > 0) {
                    html += '<h3>Sample Entry Analysis</h3>';
                    
                    data.sample_entries.forEach(function(entry, index) {
                        html += '<h4>Entry #' + entry.entry_id + '</h4>';
                        
                        if (Object.keys(entry.suggestions).length > 0) {
                            html += '<table class="widefat striped"><thead><tr><th>Field ID</th><th>Value</th><th>Suggested Mapping</th><th>Confidence</th></tr></thead><tbody>';
                            
                            for (var fieldId in entry.suggestions) {
                                var suggestion = entry.suggestions[fieldId];
                                html += '<tr>';
                                html += '<td>' + fieldId + '</td>';
                                html += '<td>' + suggestion.value + '</td>';
                                html += '<td><strong>' + suggestion.suggested_name + '</strong></td>';
                                html += '<td>' + suggestion.confidence + '%</td>';
                                html += '</tr>';
                            }
                            
                            html += '</tbody></table>';
                        } else {
                            html += '<p>No field mappings suggested for this entry.</p>';
                        }
                    });
                }
                
                $('#results-content').html(html);
            }
        });
        </script>
        
        <style>
        .widefat th, .widefat td {
            padding: 8px 10px;
        }
        .card {
            background: white;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        </style>
        <?php
    }
}