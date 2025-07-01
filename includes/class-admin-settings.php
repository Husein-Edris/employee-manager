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
        add_menu_page(
            __('RT Employee Manager', 'rt-employee-manager'),
            __('Employee Manager', 'rt-employee-manager'),
            'manage_options',
            'rt-employee-manager',
            array($this, 'admin_page'),
            'dashicons-groups',
            26
        );
        
        add_submenu_page(
            'rt-employee-manager',
            __('Einstellungen', 'rt-employee-manager'),
            __('Einstellungen', 'rt-employee-manager'),
            'manage_options',
            'rt-employee-manager-settings',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'rt-employee-manager',
            __('Logs', 'rt-employee-manager'),
            __('Logs', 'rt-employee-manager'),
            'manage_options',
            'rt-employee-manager-logs',
            array($this, 'logs_page')
        );
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
        $total_employees = wp_count_posts('angestellte')->publish;
        $total_clients = wp_count_posts('kunde')->publish;
        
        // Get recent activity
        $recent_employees = get_posts(array(
            'post_type' => 'angestellte',
            'posts_per_page' => 5,
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        ?>
        <div class="wrap">
            <h1><?php _e('RT Employee Manager Dashboard', 'rt-employee-manager'); ?></h1>
            
            <div class="rt-admin-dashboard">
                <!-- Statistics -->
                <div class="rt-admin-stats">
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
                        <h3><?php echo $this->get_active_employees_count(); ?></h3>
                        <p><?php _e('Aktive Mitarbeiter', 'rt-employee-manager'); ?></p>
                    </div>
                    
                    <div class="rt-stat-card">
                        <h3><?php echo $this->get_forms_submissions_today(); ?></h3>
                        <p><?php _e('Anmeldungen heute', 'rt-employee-manager'); ?></p>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="rt-quick-actions">
                    <h2><?php _e('Schnellaktionen', 'rt-employee-manager'); ?></h2>
                    <div class="rt-action-buttons">
                        <a href="<?php echo admin_url('post-new.php?post_type=angestellte'); ?>" class="button button-primary">
                            <?php _e('Neuen Mitarbeiter hinzufügen', 'rt-employee-manager'); ?>
                        </a>
                        <a href="<?php echo admin_url('post-new.php?post_type=kunde'); ?>" class="button button-secondary">
                            <?php _e('Neuen Kunde hinzufügen', 'rt-employee-manager'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=gf_edit_forms'); ?>" class="button button-secondary">
                            <?php _e('Formulare bearbeiten', 'rt-employee-manager'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=rt-employee-manager-settings'); ?>" class="button button-secondary">
                            <?php _e('Einstellungen', 'rt-employee-manager'); ?>
                        </a>
                    </div>
                </div>
                
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
                                                <a href="<?php echo get_edit_post_link($employee->ID); ?>" class="button button-small">
                                                    <?php _e('Bearbeiten', 'rt-employee-manager'); ?>
                                                </a>
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
                
                <!-- System Status -->
                <div class="rt-system-status">
                    <h2><?php _e('Systemstatus', 'rt-employee-manager'); ?></h2>
                    <table class="wp-list-table widefat">
                        <tbody>
                            <tr>
                                <td><?php _e('Gravity Forms', 'rt-employee-manager'); ?></td>
                                <td>
                                    <?php if (class_exists('GFForms')): ?>
                                        <span class="rt-status-ok">✅ <?php _e('Aktiv', 'rt-employee-manager'); ?></span>
                                        <small>(Version: <?php echo GFCommon::$version; ?>)</small>
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
        </style>
        <?php
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
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
}