<?php

if (!defined('ABSPATH')) {
    exit;
}

class RT_Employee_Manager_Employee_Dashboard {
    
    public function __construct() {
        add_shortcode('employee_dashboard', array($this, 'employee_dashboard_shortcode'));
        add_shortcode('employee_form', array($this, 'employee_form_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('wp_ajax_rt_delete_employee', array($this, 'ajax_delete_employee'));
        add_action('wp_ajax_rt_update_employee_status', array($this, 'ajax_update_employee_status'));
        add_action('wp_ajax_rt_get_employee_data', array($this, 'ajax_get_employee_data'));
    }
    
    /**
     * Employee dashboard shortcode
     */
    public function employee_dashboard_shortcode($atts) {
        $atts = shortcode_atts(array(
            'per_page' => 10,
            'show_search' => 'true',
            'show_filters' => 'true',
            'allow_edit' => 'true',
            'allow_delete' => 'true'
        ), $atts);
        
        if (!is_user_logged_in()) {
            return '<p>' . __('Sie müssen angemeldet sein, um das Dashboard zu nutzen.', 'rt-employee-manager') . '</p>';
        }
        
        $current_user = wp_get_current_user();
        
        // Check if user has permission
        if (!in_array('kunden', $current_user->roles) && !current_user_can('manage_options')) {
            return '<p>' . __('Sie haben keine Berechtigung, dieses Dashboard zu nutzen.', 'rt-employee-manager') . '</p>';
        }
        
        ob_start();
        $this->render_dashboard($atts);
        return ob_get_clean();
    }
    
    /**
     * Render dashboard
     */
    private function render_dashboard($atts) {
        $current_user_id = get_current_user_id();
        $paged = get_query_var('paged') ? get_query_var('paged') : 1;
        $search = isset($_GET['employee_search']) ? sanitize_text_field($_GET['employee_search']) : '';
        $status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : '';
        
        // Build query args
        $args = array(
            'post_type' => 'angestellte',
            'posts_per_page' => $atts['per_page'],
            'paged' => $paged,
            'meta_query' => array(
                array(
                    'key' => 'employer_id',
                    'value' => $current_user_id,
                    'compare' => '='
                )
            )
        );
        
        // Add search
        if (!empty($search)) {
            $args['meta_query'][] = array(
                'relation' => 'OR',
                array(
                    'key' => 'vorname',
                    'value' => $search,
                    'compare' => 'LIKE'
                ),
                array(
                    'key' => 'nachname',
                    'value' => $search,
                    'compare' => 'LIKE'
                )
            );
        }
        
        // Add status filter
        if (!empty($status_filter)) {
            $args['meta_query'][] = array(
                'key' => 'status',
                'value' => $status_filter,
                'compare' => '='
            );
        }
        
        $employees = new WP_Query($args);
        
        // Get statistics
        $stats = $this->get_employee_statistics($current_user_id);
        
        ?>
        <div id="rt-employee-dashboard" class="rt-employee-dashboard">
            <div class="rt-dashboard-header">
                <h2><?php _e('Mitarbeiter Dashboard', 'rt-employee-manager'); ?></h2>
                
                <!-- Statistics -->
                <div class="rt-dashboard-stats">
                    <div class="rt-stat-card">
                        <h3><?php echo $stats['total']; ?></h3>
                        <p><?php _e('Gesamt', 'rt-employee-manager'); ?></p>
                    </div>
                    <div class="rt-stat-card active">
                        <h3><?php echo $stats['active']; ?></h3>
                        <p><?php _e('Aktiv', 'rt-employee-manager'); ?></p>
                    </div>
                    <div class="rt-stat-card inactive">
                        <h3><?php echo $stats['inactive']; ?></h3>
                        <p><?php _e('Inaktiv', 'rt-employee-manager'); ?></p>
                    </div>
                    <div class="rt-stat-card terminated">
                        <h3><?php echo $stats['terminated']; ?></h3>
                        <p><?php _e('Gekündigt', 'rt-employee-manager'); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Search and Filters -->
            <?php if ($atts['show_search'] === 'true' || $atts['show_filters'] === 'true'): ?>
            <div class="rt-dashboard-controls">
                <form method="get" class="rt-employee-search-form">
                    <?php if ($atts['show_search'] === 'true'): ?>
                    <div class="rt-search-field">
                        <input type="text" 
                               name="employee_search" 
                               value="<?php echo esc_attr($search); ?>" 
                               placeholder="<?php _e('Nach Name suchen...', 'rt-employee-manager'); ?>" />
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($atts['show_filters'] === 'true'): ?>
                    <div class="rt-filter-field">
                        <select name="status_filter">
                            <option value=""><?php _e('Alle Status', 'rt-employee-manager'); ?></option>
                            <option value="active" <?php selected($status_filter, 'active'); ?>><?php _e('Aktiv', 'rt-employee-manager'); ?></option>
                            <option value="inactive" <?php selected($status_filter, 'inactive'); ?>><?php _e('Inaktiv', 'rt-employee-manager'); ?></option>
                            <option value="suspended" <?php selected($status_filter, 'suspended'); ?>><?php _e('Gesperrt', 'rt-employee-manager'); ?></option>
                            <option value="terminated" <?php selected($status_filter, 'terminated'); ?>><?php _e('Gekündigt', 'rt-employee-manager'); ?></option>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <button type="submit" class="rt-search-button"><?php _e('Filtern', 'rt-employee-manager'); ?></button>
                    <a href="<?php echo remove_query_arg(array('employee_search', 'status_filter')); ?>" class="rt-reset-button"><?php _e('Zurücksetzen', 'rt-employee-manager'); ?></a>
                </form>
            </div>
            <?php endif; ?>
            
            <!-- Employee List -->
            <div class="rt-employee-list">
                <?php if ($employees->have_posts()): ?>
                    <div class="rt-employee-table-wrapper">
                        <table class="rt-employee-table">
                            <thead>
                                <tr>
                                    <th><?php _e('Name', 'rt-employee-manager'); ?></th>
                                    <th><?php _e('SVNR', 'rt-employee-manager'); ?></th>
                                    <th><?php _e('Position', 'rt-employee-manager'); ?></th>
                                    <th><?php _e('Eintrittsdatum', 'rt-employee-manager'); ?></th>
                                    <th><?php _e('Status', 'rt-employee-manager'); ?></th>
                                    <th><?php _e('Aktionen', 'rt-employee-manager'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($employees->have_posts()): $employees->the_post(); ?>
                                    <?php $this->render_employee_row(get_the_ID(), $atts); ?>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($employees->max_num_pages > 1): ?>
                    <div class="rt-pagination">
                        <?php
                        echo paginate_links(array(
                            'base' => str_replace(999999999, '%#%', esc_url(get_pagenum_link(999999999))),
                            'format' => '?paged=%#%',
                            'current' => max(1, $paged),
                            'total' => $employees->max_num_pages,
                            'prev_text' => __('« Vorherige', 'rt-employee-manager'),
                            'next_text' => __('Nächste »', 'rt-employee-manager'),
                        ));
                        ?>
                    </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="rt-no-employees">
                        <p><?php _e('Keine Mitarbeiter gefunden.', 'rt-employee-manager'); ?></p>
                        <?php
                        $form_id = get_option('rt_employee_manager_employee_form_id', 1);
                        echo do_shortcode('[gravityform id="' . $form_id . '" title="false" description="false"]');
                        ?>
                    </div>
                <?php endif; ?>
                
                <?php wp_reset_postdata(); ?>
            </div>
        </div>
        
        <!-- Edit Modal -->
        <div id="rt-employee-edit-modal" class="rt-modal" style="display: none;">
            <div class="rt-modal-content">
                <span class="rt-modal-close">&times;</span>
                <h3><?php _e('Mitarbeiter bearbeiten', 'rt-employee-manager'); ?></h3>
                <div id="rt-employee-edit-form"></div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render employee table row
     */
    private function render_employee_row($employee_id, $atts) {
        $vorname = get_field('vorname', $employee_id);
        $nachname = get_field('nachname', $employee_id);
        $svnr = get_field('sozialversicherungsnummer', $employee_id);
        $position = get_field('bezeichnung_der_tatigkeit', $employee_id);
        $eintrittsdatum = get_field('eintrittsdatum', $employee_id);
        $status = get_field('status', $employee_id) ?: 'active';
        
        // Format SVNR for display
        $formatted_svnr = '';
        if ($svnr && strlen($svnr) === 10) {
            $formatted_svnr = substr($svnr, 0, 2) . ' ' . 
                             substr($svnr, 2, 4) . ' ' . 
                             substr($svnr, 6, 2) . ' ' . 
                             substr($svnr, 8, 2);
        }
        
        $status_labels = array(
            'active' => __('Aktiv', 'rt-employee-manager'),
            'inactive' => __('Inaktiv', 'rt-employee-manager'),
            'suspended' => __('Gesperrt', 'rt-employee-manager'),
            'terminated' => __('Gekündigt', 'rt-employee-manager')
        );
        ?>
        <tr data-employee-id="<?php echo $employee_id; ?>" class="employee-row status-<?php echo esc_attr($status); ?>">
            <td class="employee-name">
                <strong><?php echo esc_html($vorname . ' ' . $nachname); ?></strong>
            </td>
            <td class="employee-svnr">
                <code><?php echo esc_html($formatted_svnr); ?></code>
            </td>
            <td class="employee-position">
                <?php echo esc_html($position ?: '-'); ?>
            </td>
            <td class="employee-entry-date">
                <?php echo esc_html($eintrittsdatum ?: '-'); ?>
            </td>
            <td class="employee-status">
                <span class="status-badge status-<?php echo esc_attr($status); ?>">
                    <?php echo esc_html($status_labels[$status] ?? $status); ?>
                </span>
            </td>
            <td class="employee-actions">
                <?php if ($atts['allow_edit'] === 'true'): ?>
                <button type="button" 
                        class="rt-btn rt-btn-edit" 
                        data-employee-id="<?php echo $employee_id; ?>"
                        title="<?php _e('Bearbeiten', 'rt-employee-manager'); ?>">
                    📝
                </button>
                <?php endif; ?>
                
                <select class="rt-status-select" data-employee-id="<?php echo $employee_id; ?>">
                    <?php foreach ($status_labels as $value => $label): ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($status, $value); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                
                <?php if ($atts['allow_delete'] === 'true'): ?>
                <button type="button" 
                        class="rt-btn rt-btn-delete" 
                        data-employee-id="<?php echo $employee_id; ?>"
                        data-employee-name="<?php echo esc_attr($vorname . ' ' . $nachname); ?>"
                        title="<?php _e('Löschen', 'rt-employee-manager'); ?>">
                    🗑️
                </button>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }
    
    /**
     * Get employee statistics
     */
    private function get_employee_statistics($user_id) {
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
     * AJAX: Delete employee
     */
    public function ajax_delete_employee() {
        check_ajax_referer('rt_employee_dashboard', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_die(__('Nicht autorisiert', 'rt-employee-manager'));
        }
        
        $employee_id = intval($_POST['employee_id']);
        $current_user_id = get_current_user_id();
        
        // Security check - user can only delete their own employees
        $employer_id = get_post_meta($employee_id, 'employer_id', true);
        
        if ($employer_id != $current_user_id && !current_user_can('manage_options')) {
            wp_die(__('Keine Berechtigung', 'rt-employee-manager'));
        }
        
        if (wp_delete_post($employee_id, true)) {
            wp_send_json_success(array(
                'message' => __('Mitarbeiter erfolgreich gelöscht', 'rt-employee-manager')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Fehler beim Löschen des Mitarbeiters', 'rt-employee-manager')
            ));
        }
    }
    
    /**
     * AJAX: Update employee status
     */
    public function ajax_update_employee_status() {
        check_ajax_referer('rt_employee_dashboard', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_die(__('Nicht autorisiert', 'rt-employee-manager'));
        }
        
        $employee_id = intval($_POST['employee_id']);
        $new_status = sanitize_text_field($_POST['status']);
        $current_user_id = get_current_user_id();
        
        // Security check
        $employer_id = get_post_meta($employee_id, 'employer_id', true);
        
        if ($employer_id != $current_user_id && !current_user_can('manage_options')) {
            wp_die(__('Keine Berechtigung', 'rt-employee-manager'));
        }
        
        // Validate status
        $valid_statuses = array('active', 'inactive', 'suspended', 'terminated');
        if (!in_array($new_status, $valid_statuses)) {
            wp_send_json_error(array(
                'message' => __('Ungültiger Status', 'rt-employee-manager')
            ));
        }
        
        if (update_post_meta($employee_id, 'status', $new_status)) {
            wp_send_json_success(array(
                'message' => __('Status erfolgreich aktualisiert', 'rt-employee-manager'),
                'status' => $new_status
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Fehler beim Aktualisieren des Status', 'rt-employee-manager')
            ));
        }
    }
    
    /**
     * AJAX: Get employee data for editing
     */
    public function ajax_get_employee_data() {
        check_ajax_referer('rt_employee_dashboard', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_die(__('Nicht autorisiert', 'rt-employee-manager'));
        }
        
        $employee_id = intval($_POST['employee_id']);
        $current_user_id = get_current_user_id();
        
        // Security check
        $employer_id = get_post_meta($employee_id, 'employer_id', true);
        
        if ($employer_id != $current_user_id && !current_user_can('manage_options')) {
            wp_die(__('Keine Berechtigung', 'rt-employee-manager'));
        }
        
        // Get all field data
        $fields = get_fields($employee_id);
        
        wp_send_json_success(array(
            'fields' => $fields,
            'post_title' => get_the_title($employee_id)
        ));
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts() {
        if (is_user_logged_in() && (has_shortcode(get_post()->post_content, 'employee_dashboard') || 
            has_shortcode(get_post()->post_content, 'employee_form'))) {
            
            wp_enqueue_script(
                'rt-employee-dashboard',
                RT_EMPLOYEE_MANAGER_PLUGIN_URL . 'assets/js/dashboard.js',
                array('jquery'),
                RT_EMPLOYEE_MANAGER_VERSION,
                true
            );
            
            wp_enqueue_style(
                'rt-employee-dashboard',
                RT_EMPLOYEE_MANAGER_PLUGIN_URL . 'assets/css/dashboard.css',
                array(),
                RT_EMPLOYEE_MANAGER_VERSION
            );
            
            wp_localize_script('rt-employee-dashboard', 'rtEmployeeDashboard', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('rt_employee_dashboard'),
                'strings' => array(
                    'confirmDelete' => __('Sind Sie sicher, dass Sie diesen Mitarbeiter löschen möchten?', 'rt-employee-manager'),
                    'processing' => __('Wird verarbeitet...', 'rt-employee-manager'),
                    'error' => __('Ein Fehler ist aufgetreten', 'rt-employee-manager'),
                    'success' => __('Erfolgreich', 'rt-employee-manager')
                )
            ));
        }
    }
    
    /**
     * Employee form shortcode (for adding new employees)
     */
    public function employee_form_shortcode($atts) {
        $atts = shortcode_atts(array(
            'form_id' => get_option('rt_employee_manager_employee_form_id', 1),
            'title' => 'false',
            'description' => 'false'
        ), $atts);
        
        if (!is_user_logged_in()) {
            return '<p>' . __('Sie müssen angemeldet sein, um das Formular zu nutzen.', 'rt-employee-manager') . '</p>';
        }
        
        return do_shortcode(sprintf(
            '[gravityform id="%d" title="%s" description="%s"]',
            $atts['form_id'],
            $atts['title'],
            $atts['description']
        ));
    }
}