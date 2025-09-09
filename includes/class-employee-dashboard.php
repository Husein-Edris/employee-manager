<?php

if (!defined('ABSPATH')) {
    exit;
}

class RT_Employee_Manager_Employee_Dashboard
{

    public function __construct()
    {
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
    public function employee_dashboard_shortcode($atts)
    {
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
        $user_roles = $current_user->roles;
        $has_kunden_role = in_array('kunden', $user_roles);
        $has_admin_cap = current_user_can('manage_options');

        if (!$has_kunden_role && !$has_admin_cap) {
            return '<p>' . __('Sie haben keine Berechtigung, dieses Dashboard zu nutzen.', 'rt-employee-manager') . '</p>';
        }

        ob_start();
        $this->render_dashboard($atts);
        return ob_get_clean();
    }

    /**
     * Render dashboard
     */
    private function render_dashboard($atts)
    {
        $current_user_id = get_current_user_id();
        $paged = get_query_var('paged') ? get_query_var('paged') : 1;
        $search = isset($_GET['employee_search']) ? sanitize_text_field($_GET['employee_search']) : '';
        $status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : '';

        // Build query args - show all employees for presentation
        $args = array(
            'post_type' => 'angestellte',
            'posts_per_page' => -1, // Show all employees, no pagination limit
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

        // Debug logging for the query
        if (get_option('rt_employee_manager_enable_logging') && $employees->have_posts()) {
            error_log("RT Employee Manager: Found " . $employees->found_posts . " employee posts for user " . $current_user_id);
            while ($employees->have_posts()) {
                $employees->the_post();
                $post_status = get_post_status();
                $post_title = get_the_title();
                $employer_id = get_post_meta(get_the_ID(), 'employer_id', true);
                error_log("RT Employee Manager: Post ID=" . get_the_ID() . ", Title='$post_title', Status='$post_status', Employer='$employer_id'");
            }
            wp_reset_postdata();
        }

        // Get statistics
        $stats = $this->get_employee_statistics($current_user_id);

?>
<div id="rt-employee-dashboard" class="rt-employee-dashboard">
    <div class="rt-dashboard-header">
        <div class="rt-dashboard-title-section">
            <div class="rt-dashboard-actions">
                <a href="/anmeldung-neue-r-dienstnehmer-in/"
                    class="elementor-button elementor-button-link elementor-size-sm add-employee-btn">
                    <?php _e('Neuen Mitarbeiter hinzufügen', 'rt-employee-manager'); ?>
                </a>
            </div>
        </div>

        <!-- Statistics -->
        <div class="rt-dashboard-stats">
            <div class="rt-stat-card">
                <h3><?php echo intval($stats['total']); ?></h3>
                <p><?php _e('Gesamt', 'rt-employee-manager'); ?></p>
            </div>
            <div class="rt-stat-card active">
                <h3><?php echo intval($stats['active']); ?></h3>
                <p><?php _e('Beschäftigt', 'rt-employee-manager'); ?></p>
            </div>
            <div class="rt-stat-card inactive">
                <h3><?php echo intval($stats['inactive']); ?></h3>
                <p><?php _e('Beurlaubt', 'rt-employee-manager'); ?></p>
            </div>
            <div class="rt-stat-card terminated">
                <h3><?php echo intval($stats['terminated']); ?></h3>
                <p><?php _e('Ausgeschieden', 'rt-employee-manager'); ?></p>
            </div>
        </div>
    </div>

    <!-- Search and Filters -->
    <?php if ($atts['show_search'] === 'true' || $atts['show_filters'] === 'true'): ?>
    <div class="rt-dashboard-controls">
        <form method="get" class="rt-employee-search-form">
            <?php if ($atts['show_search'] === 'true'): ?>
            <div class="rt-search-field">
                <input type="text" name="employee_search" value="<?php echo esc_attr($search); ?>"
                    placeholder="<?php _e('Nach Name suchen...', 'rt-employee-manager'); ?>" />
            </div>
            <?php endif; ?>

            <?php if ($atts['show_filters'] === 'true'): ?>
            <div class="rt-filter-field">
                <select name="status_filter">
                    <option value=""><?php _e('Alle Status', 'rt-employee-manager'); ?></option>
                    <option value="active" <?php selected($status_filter, 'active'); ?>>
                        <?php _e('Beschäftigt', 'rt-employee-manager'); ?></option>
                    <option value="inactive" <?php selected($status_filter, 'inactive'); ?>>
                        <?php _e('Beurlaubt', 'rt-employee-manager'); ?></option>
                    <option value="suspended" <?php selected($status_filter, 'suspended'); ?>>
                        <?php _e('Suspendiert', 'rt-employee-manager'); ?></option>
                    <option value="terminated" <?php selected($status_filter, 'terminated'); ?>>
                        <?php _e('Ausgeschieden', 'rt-employee-manager'); ?></option>
                </select>
            </div>
            <?php endif; ?>

            <button type="submit" class="rt-search-button"><?php _e('Filtern', 'rt-employee-manager'); ?></button>
            <a href="<?php echo remove_query_arg(array('employee_search', 'status_filter')); ?>"
                class="rt-reset-button"><?php _e('Zurücksetzen', 'rt-employee-manager'); ?></a>
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
            <div class="rt-no-employees-content">
                <div class="rt-no-employees-icon">
                    <span class="dashicons dashicons-groups" style="font-size: 48px; color: #ccc;"></span>
                </div>
                <h3><?php _e('Noch keine Mitarbeiter vorhanden', 'rt-employee-manager'); ?></h3>
                <p><?php _e('Fügen Sie Ihren ersten Mitarbeiter hinzu, um mit der Verwaltung zu beginnen.', 'rt-employee-manager'); ?>
                </p>
                <div class="rt-add-employee-section">
                    <a href="/anmeldung-neue-r-dienstnehmer-in/" class="button button-primary button-large">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php _e('Ersten Mitarbeiter hinzufügen', 'rt-employee-manager'); ?>
                    </a>
                </div>
            </div>
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
    private function render_employee_row($employee_id, $atts)
    {
        // Get meta data with enhanced fallback checking
        $vorname = $this->get_field_value($employee_id, 'vorname');
        $nachname = $this->get_field_value($employee_id, 'nachname');
        $svnr = $this->get_field_value($employee_id, 'sozialversicherungsnummer');
        $position = $this->get_field_value($employee_id, 'bezeichnung_der_tatigkeit');
        $eintrittsdatum = $this->get_field_value($employee_id, 'eintrittsdatum');
        $status = $this->get_field_value($employee_id, 'status') ?: 'active';

        // Debug logging for troubleshooting empty rows
        if (get_option('rt_employee_manager_enable_logging')) {
            $all_meta = get_post_meta($employee_id);
            error_log("RT Employee Manager: Rendering row for post $employee_id");
            error_log("RT Employee Manager: vorname='$vorname', nachname='$nachname', position='$position', eintrittsdatum='$eintrittsdatum'");
            error_log("RT Employee Manager: All meta keys: " . implode(', ', array_keys($all_meta)));
        }

        // Format SVNR for display
        $formatted_svnr = '';
        if ($svnr && strlen($svnr) === 10) {
            $formatted_svnr = substr($svnr, 0, 2) . ' ' .
                substr($svnr, 2, 4) . ' ' .
                substr($svnr, 6, 2) . ' ' .
                substr($svnr, 8, 2);
        }

        $status_labels = array(
            'active' => __('Beschäftigt', 'rt-employee-manager'),
            'inactive' => __('Beurlaubt', 'rt-employee-manager'),
            'suspended' => __('Suspendiert', 'rt-employee-manager'),
            'terminated' => __('Ausgeschieden', 'rt-employee-manager')
        );
    ?>
<tr data-employee-id="<?php echo esc_attr($employee_id); ?>"
    class="employee-row status-<?php echo esc_attr($status); ?>">
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
        <!-- Only show WordPress admin edit link for backend editing -->
        <?php if (current_user_can('edit_post', $employee_id)): ?>
        <a href="<?php echo get_edit_post_link($employee_id); ?>" class="button button-small"
            title="<?php _e('Im Backend bearbeiten', 'rt-employee-manager'); ?>" target="_blank">
            <?php _e('Bearbeiten', 'rt-employee-manager'); ?>
        </a>
        <?php endif; ?>

        <!-- Status selector remains for quick status changes -->
        <select class="rt-status-select" data-employee-id="<?php echo esc_attr($employee_id); ?>">
            <?php foreach ($status_labels as $value => $label): ?>
            <option value="<?php echo esc_attr($value); ?>" <?php selected($status, $value); ?>>
                <?php echo esc_html($label); ?>
            </option>
            <?php endforeach; ?>
        </select>
    </td>
</tr>
<?php
    }

    /**
     * Get employee statistics
     */
    private function get_employee_statistics($user_id)
    {
        global $wpdb;

        // Clear any relevant caches first
        wp_cache_delete("user_meta_$user_id", 'user_meta');
        wp_cache_delete("employee_stats_$user_id", 'rt_employee_manager');

        // Try cached version first
        $cache_key = "employee_stats_$user_id";
        $cached_stats = wp_cache_get($cache_key, 'rt_employee_manager');

        if ($cached_stats !== false) {
            return $cached_stats;
        }

        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN COALESCE(pm_status.meta_value, 'active') = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN pm_status.meta_value = 'inactive' THEN 1 ELSE 0 END) as inactive,
                SUM(CASE WHEN pm_status.meta_value = 'terminated' THEN 1 ELSE 0 END) as term_count
             FROM {$wpdb->postmeta} pm_employer
             INNER JOIN {$wpdb->posts} p ON pm_employer.post_id = p.ID
             LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = 'status'
             WHERE pm_employer.meta_key = 'employer_id' 
             AND pm_employer.meta_value = %d
             AND p.post_type = 'angestellte'
             AND p.post_status = 'publish'",
            $user_id
        ), ARRAY_A);

        $result = $stats ? array(
            'total' => $stats['total'],
            'active' => $stats['active'], 
            'inactive' => $stats['inactive'],
            'terminated' => $stats['term_count']
        ) : array('total' => 0, 'active' => 0, 'inactive' => 0, 'terminated' => 0);

        // Cache for 5 minutes
        wp_cache_set($cache_key, $result, 'rt_employee_manager', 300);

        return $result;
    }

    /**
     * AJAX: Delete employee
     */
    public function ajax_delete_employee()
    {
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
            // Clear statistics cache
            wp_cache_delete("employee_stats_$current_user_id", 'rt_employee_manager');
            
            // Get updated statistics
            $updated_stats = $this->get_employee_statistics($current_user_id);
            
            wp_send_json_success(array(
                'message' => __('Mitarbeiter erfolgreich gelöscht', 'rt-employee-manager'),
                'stats' => $updated_stats
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
    public function ajax_update_employee_status()
    {
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
            // Clear statistics cache for the employer
            wp_cache_delete("employee_stats_$current_user_id", 'rt_employee_manager');

            // Also update ACF field if it exists
            if (function_exists('update_field')) {
                update_field('status', $new_status, $employee_id);
            }

            // Clear post meta cache
            wp_cache_delete($employee_id, 'post_meta');
            clean_post_cache($employee_id);

            // Get updated statistics
            $updated_stats = $this->get_employee_statistics($current_user_id);
            
            wp_send_json_success(array(
                'message' => __('Status erfolgreich aktualisiert', 'rt-employee-manager'),
                'status' => $new_status,
                'stats' => $updated_stats
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
    public function ajax_get_employee_data()
    {
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

        // Get all meta data
        $fields = get_post_meta($employee_id);

        // Clean up the data (remove arrays and keep only single values)
        $clean_fields = array();
        foreach ($fields as $key => $value) {
            $clean_fields[$key] = is_array($value) && count($value) === 1 ? $value[0] : $value;
        }

        wp_send_json_success(array(
            'fields' => $clean_fields,
            'post_title' => get_the_title($employee_id)
        ));
    }

    /**
     * Get field value with fallback support for ACF and post meta
     */
    private function get_field_value($post_id, $field_name)
    {
        $value = '';

        // Try ACF first if available
        if (function_exists('get_field')) {
            $value = get_field($field_name, $post_id);
        }

        // Fallback to post meta if ACF returns empty
        if (empty($value)) {
            $value = get_post_meta($post_id, $field_name, true);
        }

        // Additional fallback - check for alternative field names that might have been used
        if (empty($value)) {
            $alternative_fields = array(
                'vorname' => array('first_name', 'firstname', 'Vorname'),
                'nachname' => array('last_name', 'lastname', 'Nachname'),
                'sozialversicherungsnummer' => array('svnr', 'social_security_number'),
                'eintrittsdatum' => array('entry_date', 'hire_date', 'start_date', 'Eintrittsdatum'),
                'bezeichnung_der_tatigkeit' => array('job_title', 'position', 'title', 'Position')
            );

            if (isset($alternative_fields[$field_name])) {
                foreach ($alternative_fields[$field_name] as $alt_field) {
                    $value = get_post_meta($post_id, $alt_field, true);
                    if (!empty($value)) {
                        break;
                    }
                }
            }
        }

        return $value;
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts()
    {
        $current_post = get_post();
        if (
            is_user_logged_in() && $current_post &&
            (has_shortcode($current_post->post_content, 'employee_dashboard') ||
                has_shortcode($current_post->post_content, 'employee_form'))
        ) {

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
    public function employee_form_shortcode($atts)
    {
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