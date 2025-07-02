<?php

if (!defined('ABSPATH')) {
    exit;
}

class RT_Employee_Manager_Admin_Settings {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu page - accessible to both admins and kunden
        $main_page = add_menu_page(
            __('RT Employee Manager', 'rt-employee-manager'),
            __('Employee Manager', 'rt-employee-manager'),
            'read',  // Allow kunden access
            'rt-employee-manager',
            array($this, 'admin_page'),
            'dashicons-groups',
            26
        );
        
        // Settings submenu - admin only
        if (current_user_can('manage_options')) {
            add_submenu_page(
                'rt-employee-manager',
                __('Einstellungen', 'rt-employee-manager'),
                __('Einstellungen', 'rt-employee-manager'),
                'manage_options',
                'rt-employee-manager-settings',
                array($this, 'settings_page')
            );
            
            // Logs submenu - admin only
            add_submenu_page(
                'rt-employee-manager',
                __('Logs', 'rt-employee-manager'),
                __('Logs', 'rt-employee-manager'),
                'manage_options',
                'rt-employee-manager-logs',
                array($this, 'logs_page')
            );
        }
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('rt_employee_manager_settings', 'rt_employee_manager_enable_email_notifications');
        register_setting('rt_employee_manager_settings', 'rt_employee_manager_admin_email');
        register_setting('rt_employee_manager_settings', 'rt_employee_manager_employee_form_id');
        register_setting('rt_employee_manager_settings', 'rt_employee_manager_client_form_id');
        register_setting('rt_employee_manager_settings', 'rt_employee_manager_enable_logging');
        register_setting('rt_employee_manager_settings', 'rt_employee_manager_enable_svnr_validation');
        register_setting('rt_employee_manager_settings', 'rt_employee_manager_max_employees_per_client');
        register_setting('rt_employee_manager_settings', 'rt_employee_manager_enable_frontend_editing');
    }
    
    /**
     * Main admin page
     */
    public function admin_page() {
        // Check user permissions
        if (!current_user_can('read')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        // Debug: Fix missing metadata for test employee post
        $this->fix_test_employee_metadata();
        
        $current_user = wp_get_current_user();
        $is_admin = current_user_can('manage_options');
        $is_kunden = in_array('kunden', $current_user->roles);
        
        if ($is_admin) {
            // Admin sees all data
            $total_employees = wp_count_posts('angestellte')->publish;
            $total_clients = wp_count_posts('kunde')->publish;
        } else {
            // Kunden users see only their own employees
            $args = array(
                'post_type' => 'angestellte',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'meta_query' => array(
                    array(
                        'key' => 'employer_id',
                        'value' => $current_user->ID,
                        'compare' => '='
                    )
                )
            );
            $user_employees = get_posts($args);
            $total_employees = count($user_employees);
            $total_clients = 1; // The current user's company
        }
        
        // Get recent activity
        $recent_args = array(
            'post_type' => 'angestellte',
            'post_status' => 'publish',
            'posts_per_page' => 5,
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        // If not admin, only show user's employees
        if (!$is_admin) {
            $recent_args['meta_query'] = array(
                array(
                    'key' => 'employer_id',
                    'value' => $current_user->ID,
                    'compare' => '='
                )
            );
        }
        
        $recent_employees = get_posts($recent_args);
        
        ?>
        <div class="wrap">
            <h1><?php _e('RT Employee Manager Dashboard', 'rt-employee-manager'); ?></h1>
            
            <div class="rt-admin-dashboard">
                <!-- Statistics -->
                <div class="rt-admin-stats">
                    <?php if ($is_admin): ?>
                        <!-- Admin sees all stats -->
                        <div class="rt-stat-card">
                            <h3><?php echo number_format($total_employees); ?></h3>
                            <p><?php _e('Mitarbeiter gesamt', 'rt-employee-manager'); ?></p>
                            <a href="<?php echo admin_url('edit.php?post_type=angestellte'); ?>" class="rt-stat-link">
                                <?php _e('Alle anzeigen', 'rt-employee-manager'); ?>
                            </a>
                        </div>
                        
                        <div class="rt-stat-card">
                            <h3><?php echo number_format($total_clients); ?></h3>
                            <p><?php _e('Kunden gesamt', 'rt-employee-manager'); ?></p>
                            <a href="<?php echo admin_url('edit.php?post_type=kunde'); ?>" class="rt-stat-link">
                                <?php _e('Alle anzeigen', 'rt-employee-manager'); ?>
                            </a>
                        </div>
                        
                        <div class="rt-stat-card">
                            <h3><?php echo intval($this->get_active_employees_count()); ?></h3>
                            <p><?php _e('Aktive Mitarbeiter', 'rt-employee-manager'); ?></p>
                        </div>
                        
                        <div class="rt-stat-card">
                            <h3><?php echo intval($this->get_forms_submissions_today()); ?></h3>
                            <p><?php _e('Anmeldungen heute', 'rt-employee-manager'); ?></p>
                        </div>
                    <?php else: ?>
                        <!-- Kunden see only their employee stats -->
                        <div class="rt-stat-card">
                            <h3><?php echo number_format($total_employees); ?></h3>
                            <p><?php _e('Meine Mitarbeiter gesamt', 'rt-employee-manager'); ?></p>
                            <?php if (current_user_can('edit_employees')): ?>
                                <a href="<?php echo admin_url('edit.php?post_type=angestellte'); ?>" class="rt-stat-link">
                                    <?php _e('Alle anzeigen', 'rt-employee-manager'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <div class="rt-stat-card">
                            <h3><?php echo $this->get_user_active_employees_count($current_user->ID); ?></h3>
                            <p><?php _e('Aktive Mitarbeiter', 'rt-employee-manager'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Quick Actions -->
                <div class="rt-quick-actions">
                    <h2><?php _e('Schnellaktionen', 'rt-employee-manager'); ?></h2>
                    <div class="rt-action-buttons">
                        <?php if (current_user_can('create_employees')): ?>
                            <a href="<?php echo admin_url('post-new.php?post_type=angestellte'); ?>" class="button button-primary">
                                <?php _e('Neuen Mitarbeiter hinzufügen', 'rt-employee-manager'); ?>
                            </a>
                        <?php endif; ?>
                        <?php if ($is_admin): ?>
                            <a href="<?php echo admin_url('post-new.php?post_type=kunde'); ?>" class="button button-secondary">
                                <?php _e('Neuen Kunde hinzufügen', 'rt-employee-manager'); ?>
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=gf_edit_forms'); ?>" class="button button-secondary">
                                <?php _e('Formulare bearbeiten', 'rt-employee-manager'); ?>
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=rt-employee-manager-settings'); ?>" class="button button-secondary">
                                <?php _e('Einstellungen', 'rt-employee-manager'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Pending Registrations (Admin Only) -->
                <?php if ($is_admin): ?>
                    <?php $pending_registrations = $this->get_pending_registrations(); ?>
                    <?php if (!empty($pending_registrations)): ?>
                        <div class="rt-pending-registrations">
                            <h2>
                                <?php _e('Wartende Registrierungen', 'rt-employee-manager'); ?>
                                <span class="count">(<?php echo count($pending_registrations); ?>)</span>
                            </h2>
                            <div class="rt-registration-list">
                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th><?php _e('Firma', 'rt-employee-manager'); ?></th>
                                            <th><?php _e('Kontakt', 'rt-employee-manager'); ?></th>
                                            <th><?php _e('E-Mail', 'rt-employee-manager'); ?></th>
                                            <th><?php _e('Eingereicht', 'rt-employee-manager'); ?></th>
                                            <th><?php _e('Aktionen', 'rt-employee-manager'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($pending_registrations, 0, 5) as $registration): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo esc_html($registration->company_name); ?></strong>
                                                    <?php if ($registration->uid_number): ?>
                                                        <br><small>UID: <?php echo esc_html($registration->uid_number); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo esc_html($registration->contact_first_name . ' ' . $registration->contact_last_name); ?>
                                                </td>
                                                <td>
                                                    <?php echo esc_html($registration->company_email); ?>
                                                </td>
                                                <td>
                                                    <?php echo wp_date(get_option('date_format'), strtotime($registration->submitted_at)); ?>
                                                    <br><small><?php echo human_time_diff(strtotime($registration->submitted_at)); ?> ago</small>
                                                </td>
                                                <td>
                                                    <a href="<?php echo admin_url('admin.php?page=rt-employee-manager-registrations'); ?>" class="button button-small">
                                                        <?php _e('Prüfen', 'rt-employee-manager'); ?>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                
                                <?php if (count($pending_registrations) > 5): ?>
                                    <p class="rt-view-all">
                                        <a href="<?php echo admin_url('admin.php?page=rt-employee-manager-registrations'); ?>" class="button">
                                            <?php printf(__('Alle %d Registrierungen anzeigen', 'rt-employee-manager'), count($pending_registrations)); ?>
                                        </a>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <!-- Recent Activity -->
                <div class="rt-recent-activity">
                    <h2><?php _e('Neueste Mitarbeiter', 'rt-employee-manager'); ?></h2>
                    <div class="rt-activity-list">
                        <?php if (!empty($recent_employees)): ?>
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th><?php _e('Name', 'rt-employee-manager'); ?></th>
                                        <th><?php _e('Arbeitgeber', 'rt-employee-manager'); ?></th>
                                        <th><?php _e('Status', 'rt-employee-manager'); ?></th>
                                        <th><?php _e('Registriert', 'rt-employee-manager'); ?></th>
                                        <th><?php _e('Aktionen', 'rt-employee-manager'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_employees as $employee): ?>
                                        <?php
                                        $vorname = get_post_meta($employee->ID, 'vorname', true);
                                        $nachname = get_post_meta($employee->ID, 'nachname', true);
                                        $employer_id = get_post_meta($employee->ID, 'employer_id', true);
                                        $status = get_post_meta($employee->ID, 'status', true) ?: 'active';
                                        $employer = $employer_id ? get_user_by('ID', $employer_id) : null;
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo esc_html($vorname . ' ' . $nachname); ?></strong>
                                            </td>
                                            <td>
                                                <?php if ($employer): ?>
                                                    <?php echo esc_html($employer->display_name); ?>
                                                    <br><small><?php echo esc_html(get_user_meta($employer_id, 'company_name', true)); ?></small>
                                                <?php else: ?>
                                                    <em><?php _e('Unbekannt', 'rt-employee-manager'); ?></em>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="rt-status-badge status-<?php echo esc_attr($status); ?>">
                                                    <?php echo esc_html(ucfirst($status)); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo get_the_date('d.m.Y H:i', $employee->ID); ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $edit_link = get_edit_post_link($employee->ID);
                                                if ($edit_link && current_user_can('edit_post', $employee->ID)): 
                                                ?>
                                                    <a href="<?php echo esc_url($edit_link); ?>" class="button button-small">
                                                        <?php _e('Bearbeiten', 'rt-employee-manager'); ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="button button-small button-disabled">
                                                        <?php _e('Keine Berechtigung', 'rt-employee-manager'); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p><?php _e('Keine Mitarbeiter vorhanden.', 'rt-employee-manager'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($is_admin): ?>
                <!-- System Status (Admin only) -->
                <div class="rt-system-status">
                    <h2><?php _e('Systemstatus', 'rt-employee-manager'); ?></h2>
                    <table class="wp-list-table widefat">
                        <tbody>
                            <tr>
                                <td><?php _e('Gravity Forms', 'rt-employee-manager'); ?></td>
                                <td>
                                    <?php if (class_exists('GFForms')): ?>
                                        <span class="rt-status-ok">✅ <?php _e('Aktiv', 'rt-employee-manager'); ?></span>
                                        <small>(Version: <?php echo esc_html(GFCommon::$version); ?>)</small>
                                    <?php else: ?>
                                        <span class="rt-status-error">❌ <?php _e('Nicht installiert', 'rt-employee-manager'); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><?php _e('Advanced Custom Fields', 'rt-employee-manager'); ?></td>
                                <td>
                                    <?php if (function_exists('acf')): ?>
                                        <span class="rt-status-ok">✅ <?php _e('Aktiv', 'rt-employee-manager'); ?></span>
                                        <small>(Version: <?php echo acf()->version; ?>)</small>
                                    <?php else: ?>
                                        <span class="rt-status-error">❌ <?php _e('Nicht installiert', 'rt-employee-manager'); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><?php _e('Advanced Post Creation', 'rt-employee-manager'); ?></td>
                                <td>
                                    <?php if (class_exists('GF_Advanced_Post_Creation')): ?>
                                        <span class="rt-status-ok">✅ <?php _e('Aktiv', 'rt-employee-manager'); ?></span>
                                    <?php else: ?>
                                        <span class="rt-status-error">❌ <?php _e('Nicht installiert', 'rt-employee-manager'); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><?php _e('Database Tables', 'rt-employee-manager'); ?></td>
                                <td>
                                    <?php if ($this->check_database_tables()): ?>
                                        <span class="rt-status-ok">✅ <?php _e('OK', 'rt-employee-manager'); ?></span>
                                    <?php else: ?>
                                        <span class="rt-status-error">❌ <?php _e('Fehler', 'rt-employee-manager'); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
        .rt-admin-dashboard {
            display: grid;
            gap: 20px;
            margin-top: 20px;
        }
        
        .rt-admin-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .rt-stat-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        
        .rt-stat-card h3 {
            font-size: 2.5em;
            margin: 0 0 10px 0;
            color: #0073aa;
        }
        
        .rt-stat-card p {
            margin: 0 0 10px 0;
            color: #666;
        }
        
        .rt-stat-link {
            text-decoration: none;
            color: #0073aa;
            font-size: 0.9em;
        }
        
        .rt-action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .rt-status-badge {
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 0.8em;
            color: white;
        }
        
        .rt-status-badge.status-active {
            background: #46b450;
        }
        
        .rt-status-badge.status-inactive {
            background: #ffb900;
        }
        
        .rt-status-badge.status-terminated {
            background: #dc3232;
        }
        
        .rt-status-ok {
            color: #46b450;
        }
        
        .rt-status-error {
            color: #dc3232;
        }
        
        .rt-pending-registrations {
            margin: 20px 0;
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            padding: 20px;
        }
        
        .rt-pending-registrations h2 {
            margin: 0 0 15px 0;
            color: #1d2327;
        }
        
        .rt-pending-registrations .count {
            background: #d63638;
            color: white;
            border-radius: 10px;
            padding: 2px 8px;
            font-size: 0.8em;
            font-weight: normal;
        }
        
        .rt-view-all {
            text-align: center;
            margin: 15px 0 0 0;
        }
        </style>
        <?php
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        if (isset($_POST['submit'])) {
            check_admin_referer('rt_employee_manager_settings');
            
            // Save settings
            $settings = array(
                'rt_employee_manager_enable_email_notifications',
                'rt_employee_manager_admin_email',
                'rt_employee_manager_employee_form_id',
                'rt_employee_manager_client_form_id',
                'rt_employee_manager_enable_logging',
                'rt_employee_manager_enable_svnr_validation',
                'rt_employee_manager_max_employees_per_client',
                'rt_employee_manager_enable_frontend_editing'
            );
            
            foreach ($settings as $setting) {
                if (isset($_POST[$setting])) {
                    update_option($setting, sanitize_text_field($_POST[$setting]));
                }
            }
            
            echo '<div class="notice notice-success"><p>' . __('Einstellungen gespeichert.', 'rt-employee-manager') . '</p></div>';
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('RT Employee Manager Einstellungen', 'rt-employee-manager'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('rt_employee_manager_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('E-Mail Benachrichtigungen', 'rt-employee-manager'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="rt_employee_manager_enable_email_notifications" value="1" 
                                       <?php checked(get_option('rt_employee_manager_enable_email_notifications'), '1'); ?> />
                                <?php _e('E-Mail Benachrichtigungen aktivieren', 'rt-employee-manager'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Admin E-Mail', 'rt-employee-manager'); ?></th>
                        <td>
                            <input type="email" name="rt_employee_manager_admin_email" 
                                   value="<?php echo esc_attr(get_option('rt_employee_manager_admin_email', get_option('admin_email'))); ?>" 
                                   class="regular-text" />
                            <p class="description"><?php _e('E-Mail Adresse für Benachrichtigungen', 'rt-employee-manager'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Mitarbeiter Formular ID', 'rt-employee-manager'); ?></th>
                        <td>
                            <input type="number" name="rt_employee_manager_employee_form_id" 
                                   value="<?php echo esc_attr(get_option('rt_employee_manager_employee_form_id', '1')); ?>" 
                                   class="small-text" min="1" />
                            <p class="description"><?php _e('Gravity Forms ID für Mitarbeiter Anmeldung', 'rt-employee-manager'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Kunden Formular ID', 'rt-employee-manager'); ?></th>
                        <td>
                            <input type="number" name="rt_employee_manager_client_form_id" 
                                   value="<?php echo esc_attr(get_option('rt_employee_manager_client_form_id', '3')); ?>" 
                                   class="small-text" min="1" />
                            <p class="description"><?php _e('Gravity Forms ID für Kunden Registrierung', 'rt-employee-manager'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Logging', 'rt-employee-manager'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="rt_employee_manager_enable_logging" value="1" 
                                       <?php checked(get_option('rt_employee_manager_enable_logging'), '1'); ?> />
                                <?php _e('Debug Logging aktivieren', 'rt-employee-manager'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('SVNR Validierung', 'rt-employee-manager'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="rt_employee_manager_enable_svnr_validation" value="1" 
                                       <?php checked(get_option('rt_employee_manager_enable_svnr_validation'), '1'); ?> />
                                <?php _e('Österreichische SVNR Validierung aktivieren', 'rt-employee-manager'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Max. Mitarbeiter pro Kunde', 'rt-employee-manager'); ?></th>
                        <td>
                            <input type="number" name="rt_employee_manager_max_employees_per_client" 
                                   value="<?php echo esc_attr(get_option('rt_employee_manager_max_employees_per_client', '50')); ?>" 
                                   class="small-text" min="1" max="1000" />
                            <p class="description"><?php _e('Standard Maximum für neue Kunden', 'rt-employee-manager'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Frontend Bearbeitung', 'rt-employee-manager'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="rt_employee_manager_enable_frontend_editing" value="1" 
                                       <?php checked(get_option('rt_employee_manager_enable_frontend_editing'), '1'); ?> />
                                <?php _e('Frontend Bearbeitung für Kunden aktivieren', 'rt-employee-manager'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Logs page
     */
    public function logs_page() {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'rt_employee_logs';
        $per_page = 20;
        $paged = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
        $offset = ($paged - 1) * $per_page;
        
        // Get logs
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));
        
        $total_logs = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        $total_pages = ceil($total_logs / $per_page);
        
        ?>
        <div class="wrap">
            <h1><?php _e('RT Employee Manager Logs', 'rt-employee-manager'); ?></h1>
            
            <?php if (!empty($logs)): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Datum', 'rt-employee-manager'); ?></th>
                            <th><?php _e('Mitarbeiter', 'rt-employee-manager'); ?></th>
                            <th><?php _e('Aktion', 'rt-employee-manager'); ?></th>
                            <th><?php _e('Details', 'rt-employee-manager'); ?></th>
                            <th><?php _e('Benutzer', 'rt-employee-manager'); ?></th>
                            <th><?php _e('IP', 'rt-employee-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo esc_html($log->created_at); ?></td>
                                <td>
                                    <?php if ($log->employee_id): ?>
                                        <a href="<?php echo get_edit_post_link($log->employee_id); ?>">
                                            <?php echo esc_html(get_the_title($log->employee_id)); ?>
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($log->action); ?></td>
                                <td><?php echo esc_html($log->details); ?></td>
                                <td>
                                    <?php if ($log->user_id): ?>
                                        <?php $user = get_user_by('ID', $log->user_id); ?>
                                        <?php echo $user ? esc_html($user->display_name) : $log->user_id; ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($log->ip_address); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if ($total_pages > 1): ?>
                    <div class="tablenav">
                        <div class="tablenav-pages">
                            <?php
                            echo paginate_links(array(
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => __('&laquo;'),
                                'next_text' => __('&raquo;'),
                                'total' => $total_pages,
                                'current' => $paged
                            ));
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <p><?php _e('Keine Logs vorhanden.', 'rt-employee-manager'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Get active employees count
     */
    private function get_active_employees_count() {
        global $wpdb;
        
        return $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = 'status' 
             AND pm.meta_value = 'active'
             AND p.post_type = 'angestellte'
             AND p.post_status = 'publish'"
        ) ?: 0;
    }
    
    /**
     * Get user-specific active employees count
     */
    private function get_user_active_employees_count($user_id) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm_status
             INNER JOIN {$wpdb->posts} p ON pm_status.post_id = p.ID
             INNER JOIN {$wpdb->postmeta} pm_employer ON p.ID = pm_employer.post_id
             WHERE pm_status.meta_key = 'status' 
             AND pm_status.meta_value = 'active'
             AND pm_employer.meta_key = 'employer_id'
             AND pm_employer.meta_value = %d
             AND p.post_type = 'angestellte'
             AND p.post_status = 'publish'",
            $user_id
        )) ?: 0;
    }
    
    /**
     * Get form submissions today
     */
    private function get_forms_submissions_today() {
        if (!class_exists('GFAPI')) {
            return 0;
        }
        
        $today = date('Y-m-d');
        $employee_form_id = get_option('rt_employee_manager_employee_form_id', 1);
        $client_form_id = get_option('rt_employee_manager_client_form_id', 3);
        
        $search_criteria = array(
            'start_date' => $today,
            'end_date' => $today
        );
        
        $employee_entries = GFAPI::get_entries($employee_form_id, $search_criteria);
        $client_entries = GFAPI::get_entries($client_form_id, $search_criteria);
        
        return count($employee_entries) + count($client_entries);
    }
    
    /**
     * Check database tables
     */
    private function check_database_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'rt_employee_logs';
        return $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'rt-employee-manager') !== false) {
            wp_enqueue_style('rt-employee-manager-admin', RT_EMPLOYEE_MANAGER_PLUGIN_URL . 'assets/css/admin.css', array(), RT_EMPLOYEE_MANAGER_VERSION);
        }
    }
    
    /**
     * Temporary function to fix test employee metadata
     */
    private function fix_test_employee_metadata() {
        // Fix missing kunde posts first
        $this->fix_missing_kunde_posts();
        
        // Only run once - check if already fixed
        if (get_option('rt_test_employee_fixed')) {
            return;
        }
        
        // Find the test employee post
        $posts = get_posts(array(
            'post_type' => 'angestellte',
            'post_status' => 'publish',
            'posts_per_page' => -1
        ));
        
        foreach ($posts as $post) {
            // Check if metadata is missing
            $vorname = get_post_meta($post->ID, 'vorname', true);
            $employer_id = get_post_meta($post->ID, 'employer_id', true);
            
            if (empty($vorname) || empty($employer_id)) {
                // Find a kunden user to assign as employer
                $kunden_users = get_users(array('role' => 'kunden'));
                if (!empty($kunden_users)) {
                    $kunden_user = $kunden_users[0];
                    
                    // Set missing metadata based on post title
                    $title_parts = explode(' ', $post->post_title);
                    $first_name = isset($title_parts[0]) ? $title_parts[0] : 'Max';
                    $last_name = isset($title_parts[1]) ? $title_parts[1] : 'Mustermann';
                    
                    update_post_meta($post->ID, 'vorname', $first_name);
                    update_post_meta($post->ID, 'nachname', $last_name);
                    update_post_meta($post->ID, 'employer_id', $kunden_user->ID);
                    update_post_meta($post->ID, 'status', 'active');
                    update_post_meta($post->ID, 'sozialversicherungsnummer', '1234567890');
                    update_post_meta($post->ID, 'eintrittsdatum', date('d.m.Y'));
                    
                    error_log('RT Employee Manager: Fixed metadata for employee post #' . $post->ID);
                }
            }
        }
        
        // Mark as fixed
        update_option('rt_test_employee_fixed', true);
    }
    
    /**
     * Fix missing kunde CPT posts for existing users
     */
    private function fix_missing_kunde_posts() {
        // Only run once - check if already fixed
        if (get_option('rt_missing_kunde_posts_fixed')) {
            return;
        }
        
        // Get all kunden users
        $kunden_users = get_users(array(
            'role' => 'kunden',
            'fields' => 'all'
        ));
        
        $created_count = 0;
        
        foreach ($kunden_users as $user) {
            // Check if user already has a kunde post
            $existing_post_id = get_user_meta($user->ID, 'kunde_post_id', true);
            
            if (empty($existing_post_id) || !get_post($existing_post_id)) {
                // Create kunde post for this user
                $company_name = get_user_meta($user->ID, 'company_name', true);
                
                if (empty($company_name)) {
                    $company_name = $user->display_name . ' Company'; // Fallback
                }
                
                $post_data = array(
                    'post_title' => sanitize_text_field($company_name),
                    'post_type' => 'kunde',
                    'post_status' => 'publish',
                    'post_author' => $user->ID,
                    'meta_input' => array(
                        'company_name' => sanitize_text_field($company_name),
                        'uid_number' => get_user_meta($user->ID, 'uid_number', true),
                        'phone' => get_user_meta($user->ID, 'phone', true),
                        'email' => $user->user_email,
                        'registration_date' => get_date_from_gmt($user->user_registered, 'd.m.Y H:i'),
                        'user_id' => $user->ID,
                    )
                );
                
                // Add address data if available
                $address_data = array(
                    'street' => get_user_meta($user->ID, 'address_street', true),
                    'postcode' => get_user_meta($user->ID, 'address_postcode', true),
                    'city' => get_user_meta($user->ID, 'address_city', true),
                    'country' => get_user_meta($user->ID, 'address_country', true) ?: 'Austria'
                );
                
                // Only add address if we have some data
                if (!empty($address_data['street']) || !empty($address_data['city'])) {
                    $post_data['meta_input']['address'] = $address_data;
                }
                
                $post_id = wp_insert_post($post_data);
                
                if (!is_wp_error($post_id)) {
                    // Store the kunde post ID in user meta
                    update_user_meta($user->ID, 'kunde_post_id', $post_id);
                    $created_count++;
                    
                    error_log('RT Employee Manager: Created kunde post #' . $post_id . ' for user #' . $user->ID);
                }
            }
        }
        
        if ($created_count > 0) {
            error_log('RT Employee Manager: Created ' . $created_count . ' missing kunde posts');
        }
        
        // Mark as fixed
        update_option('rt_missing_kunde_posts_fixed', true);
    }
    
    /**
     * Get pending registrations for admin dashboard
     */
    private function get_pending_registrations() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'rt_pending_registrations';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return array();
        }
        
        return $wpdb->get_results(
            "SELECT * FROM {$table_name} WHERE status = 'pending' ORDER BY submitted_at DESC LIMIT 10"
        );
    }
}