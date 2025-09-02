<?php
/**
 * Plugin Name: RT Employee Manager
 * Plugin URI: https://edrishusein.com
 * Description: Professional employee management system with Gravity Forms integration, native WordPress meta boxes, and Austrian SVNR validation
 * Version: 1.0.0
 * Author: Edris Husein
 * Text Domain: rt-employee-manager
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 */

// Security check
if (!defined('ABSPATH')) {
    exit('Direct access forbidden.');
}

// Define plugin constants
define('RT_EMPLOYEE_MANAGER_VERSION', '1.0.0');
define('RT_EMPLOYEE_MANAGER_PLUGIN_FILE', __FILE__);
define('RT_EMPLOYEE_MANAGER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RT_EMPLOYEE_MANAGER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RT_EMPLOYEE_MANAGER_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Main plugin class
class RT_Employee_Manager {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'load_plugin'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Fix for Admin Site Enhancements plugin menu array corruption
        add_action('admin_menu', array($this, 'fix_menu_arrays'), 1);
        add_action('admin_init', array($this, 'fix_menu_arrays'), 1);
        add_action('current_screen', array($this, 'fix_menu_arrays'), 1);
        add_action('wp_before_admin_bar_render', array($this, 'fix_menu_arrays'), 1);
        
        // Clean up admin menu for kunden users
        add_action('admin_menu', array($this, 'customize_admin_menu_for_kunden'), 999);
        
        // Add admin notice if plugin was recently deactivated
        add_action('admin_notices', array($this, 'deactivation_notice'));
        
        // Production safety checks
        add_action('admin_notices', array($this, 'production_safety_notices'));
        
        // Hide WordPress admin notices for kunden users
        add_action('admin_notices', array($this, 'hide_wp_admin_notices'), 1);
        
        // Force menu refresh on every admin load to ensure proper translations
        add_action('admin_menu', array($this, 'force_menu_translation_refresh'), 999999);
        
        // Development environment features
        if (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'local') {
            // Disable Gravity Forms rate limiting for development only
            add_filter('gform_entry_limit_exceeded_message', '__return_false');
            add_filter('gform_form_limit_exceeded', '__return_false');
            add_filter('gform_enable_duplicate_prevention', '__return_false');
            add_filter('gform_duplicate_message', '__return_false');
        }
    }
    
    public function load_plugin() {
        // Only load if plugin is actually active
        if (!$this->is_plugin_really_active()) {
            return;
        }
        
        // Check dependencies
        if (!$this->check_dependencies()) {
            add_action('admin_notices', array($this, 'dependency_notice'));
            return;
        }
        
        // Load plugin components
        $this->load_includes();
        $this->init_components();
        
        // Load text domain
        load_plugin_textdomain('rt-employee-manager', false, dirname(RT_EMPLOYEE_MANAGER_PLUGIN_BASENAME) . '/languages');
    }
    
    /**
     * Check if plugin is really active (not just file loaded)
     */
    private function is_plugin_really_active() {
        // Check if we're in admin and the function exists
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        
        return is_plugin_active(RT_EMPLOYEE_MANAGER_PLUGIN_BASENAME);
    }
    
    private function check_dependencies() {
        // Check for Gravity Forms
        if (!class_exists('GFForms')) {
            return false;
        }
        
        // Check for Advanced Post Creation
        if (!class_exists('GF_Advanced_Post_Creation')) {
            return false;
        }
        
        return true;
    }
    
    public function dependency_notice() {
        $missing = array();
        
        if (!class_exists('GFForms')) {
            $missing[] = 'Gravity Forms';
        }
        
        if (!class_exists('GF_Advanced_Post_Creation')) {
            $missing[] = 'Gravity Forms Advanced Post Creation';
        }
        
        if (!empty($missing)) {
            echo '<div class="notice notice-error rt-employee-error"><p>';
            echo sprintf(
                __('RT Mitarbeiterverwaltung benötigt die folgenden Plugins: %s', 'rt-employee-manager'),
                esc_html(implode(', ', $missing))
            );
            echo '</p></div>';
        }
    }
    
    private function load_includes() {
        require_once RT_EMPLOYEE_MANAGER_PLUGIN_DIR . 'includes/class-custom-post-types.php';
        require_once RT_EMPLOYEE_MANAGER_PLUGIN_DIR . 'includes/class-gravity-forms-integration.php';
        require_once RT_EMPLOYEE_MANAGER_PLUGIN_DIR . 'includes/class-user-fields.php';
        require_once RT_EMPLOYEE_MANAGER_PLUGIN_DIR . 'includes/class-meta-boxes.php';
        require_once RT_EMPLOYEE_MANAGER_PLUGIN_DIR . 'includes/class-employee-dashboard.php';
        require_once RT_EMPLOYEE_MANAGER_PLUGIN_DIR . 'includes/class-admin-settings.php';
        require_once RT_EMPLOYEE_MANAGER_PLUGIN_DIR . 'includes/class-security.php';
        require_once RT_EMPLOYEE_MANAGER_PLUGIN_DIR . 'includes/class-public-registration.php';
        require_once RT_EMPLOYEE_MANAGER_PLUGIN_DIR . 'includes/class-registration-admin.php';
        require_once RT_EMPLOYEE_MANAGER_PLUGIN_DIR . 'includes/class-login-redirect.php';
    }
    
    private function init_components() {
        new RT_Employee_Manager_Custom_Post_Types();
        new RT_Employee_Manager_Gravity_Forms_Integration();
        new RT_Employee_Manager_User_Fields();
        new RT_Employee_Manager_Meta_Boxes();
        new RT_Employee_Manager_Employee_Dashboard();
        $GLOBALS['rt_employee_manager_admin_settings'] = new RT_Employee_Manager_Admin_Settings();
        new RT_Employee_Manager_Security();
        new RT_Employee_Manager_Public_Registration();
        new RT_Employee_Manager_Registration_Admin();
        new RT_Employee_Manager_Login_Redirect();
    }
    
    public function activate() {
        // Load includes first
        $this->load_includes();
        
        // Create custom post types
        RT_Employee_Manager_Custom_Post_Types::register_post_types();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Create database tables if needed
        $this->create_tables();
        
        // Add database indexes for performance
        $this->add_performance_indexes();
        
        // Set default options
        $this->set_default_options();
    }
    
    public function deactivate() {
        // Set a flag to indicate plugin was deactivated
        update_option('rt_employee_manager_deactivated', time());
        
        // Unregister custom post types by flushing rewrite rules
        flush_rewrite_rules();
        
        // Clear any cached data
        wp_cache_flush();
        
        // Clear custom post type queries from cache
        wp_cache_delete('rt_employee_manager_post_types', 'options');
        
        // Remove custom capabilities from users (optional - keeps data intact)
        $this->cleanup_capabilities_on_deactivation();
        
        // Clear any temporary options we set
        delete_option('rt_test_employee_fixed');
        delete_option('rt_missing_kunde_posts_fixed');
        
        // Force WordPress to rebuild the admin menu on next load
        wp_cache_delete('admin_menu_', 'options');
        
        // Clear all menu-related caches
        global $menu, $submenu;
        $menu = null;
        $submenu = null;
    }
    
    /**
     * Clean up capabilities when deactivating (optional)
     */
    private function cleanup_capabilities_on_deactivation() {
        // Note: We keep user roles and data intact, just remove our custom capabilities
        // This is optional and can be commented out if you want to keep capabilities
        
        $capabilities_to_remove = array(
            'create_employees', 'edit_employees', 'edit_others_employees', 'publish_employees',
            'read_employee', 'read_private_employees', 'delete_employees', 'delete_others_employees',
            'delete_private_employees', 'delete_published_employees', 'edit_private_employees',
            'edit_published_employees', 'create_clients', 'edit_clients', 'edit_others_clients',
            'publish_clients', 'read_client', 'read_private_clients', 'delete_clients',
            'delete_others_clients', 'delete_private_clients', 'delete_published_clients',
            'edit_private_clients', 'edit_published_clients'
        );
        
        // Remove from administrator role
        $admin_role = get_role('administrator');
        if ($admin_role) {
            foreach ($capabilities_to_remove as $cap) {
                $admin_role->remove_cap($cap);
            }
        }
        
        // Remove from kunden role
        $kunden_role = get_role('kunden');
        if ($kunden_role) {
            foreach ($capabilities_to_remove as $cap) {
                $kunden_role->remove_cap($cap);
            }
        }
    }
    
    /**
     * Show notice after deactivation
     */
    public function deactivation_notice() {
        $deactivated_time = get_option('rt_employee_manager_deactivated');
        
        // Show notice for 5 minutes after deactivation
        if ($deactivated_time && (time() - $deactivated_time) < 300) {
            echo '<div class="notice notice-info is-dismissible rt-employee-info">';
            echo '<p><strong>RT Mitarbeiterverwaltung:</strong> ';
            echo __('Plugin wurde deaktiviert. Benutzerdefinierte Inhaltstypen (Mitarbeiter, Unternehmen) und verwandte Funktionen sind jetzt deaktiviert. Sie müssen möglicherweise die Seite aktualisieren, um Änderungen zu sehen.', 'rt-employee-manager');
            echo '</p></div>';
            
            // Clear the flag after showing the notice
            delete_option('rt_employee_manager_deactivated');
        }
    }
    
    /**
     * Production safety notices - warn about potential issues
     */
    public function production_safety_notices() {
        // Only show to administrators
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $warnings = array();
        
        // Check if WP_DEBUG is enabled in production
        if (defined('WP_DEBUG') && WP_DEBUG && (!defined('WP_ENVIRONMENT_TYPE') || WP_ENVIRONMENT_TYPE !== 'local')) {
            $warnings[] = __('WP_DEBUG ist auf einer Produktionsseite aktiviert. Erwägen Sie, es aus Sicherheitsgründen zu deaktivieren.', 'rt-employee-manager');
        }
        
        // Check if error logging is exposed
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG && ini_get('log_errors')) {
            $log_file = WP_CONTENT_DIR . '/debug.log';
            if (file_exists($log_file) && is_readable($log_file) && filesize($log_file) > 1024 * 1024) { // > 1MB
                $warnings[] = __('Debug-Log-Datei ist groß (>1MB). Erwägen Sie, sie zu löschen oder die Debug-Protokollierung zu deaktivieren.', 'rt-employee-manager');
            }
        }
        
        // Check if admin email is set properly
        $admin_email = get_option('admin_email');
        if (empty($admin_email) || $admin_email === 'admin@example.com' || strpos($admin_email, 'changeme') !== false) {
            $warnings[] = __('Administrator-E-Mail ist nicht richtig konfiguriert. Registrierungsbenachrichtigungen funktionieren möglicherweise nicht.', 'rt-employee-manager');
        }
        
        // Check if required plugins are active
        if (!class_exists('GFForms')) {
            $warnings[] = __('Gravity Forms ist nicht aktiv. Mitarbeiterregistrierung funktioniert nicht.', 'rt-employee-manager');
        }
        
        if (!class_exists('GF_Advanced_Post_Creation')) {
            $warnings[] = __('Gravity Forms Advanced Post Creation ist nicht aktiv. Einige Funktionen funktionieren möglicherweise nicht.', 'rt-employee-manager');
        }
        
        // Check file permissions
        $upload_dir = wp_upload_dir();
        if (!wp_is_writable($upload_dir['basedir'])) {
            $warnings[] = __('Upload-Verzeichnis ist nicht beschreibbar. E-Mail-Protokollierung und Datei-Uploads können fehlschlagen.', 'rt-employee-manager');
        }
        
        // Display warnings if any
        if (!empty($warnings)) {
            echo '<div class="notice notice-warning rt-employee-warning">';
            echo '<p><strong>' . __('RT Mitarbeiterverwaltung - Produktionswarnungen:', 'rt-employee-manager') . '</strong></p>';
            echo '<ul>';
            foreach ($warnings as $warning) {
                echo '<li>' . esc_html($warning) . '</li>';
            }
            echo '</ul>';
            echo '<p><em>' . __('Dies sind Empfehlungen für optimale Sicherheit und Funktionalität.', 'rt-employee-manager') . '</em></p>';
            echo '</div>';
        }
    }
    
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Employee logs table
        $table_name = $wpdb->prefix . 'rt_employee_logs';
        
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            employee_id int(11) NOT NULL,
            action varchar(50) NOT NULL,
            details text,
            user_id int(11) NOT NULL,
            ip_address varchar(45),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY employee_id (employee_id),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Pending registrations table
        $table_name = $wpdb->prefix . 'rt_pending_registrations';
        
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            company_name varchar(255) NOT NULL,
            company_email varchar(255) NOT NULL,
            company_phone varchar(50),
            uid_number varchar(50),
            company_street varchar(255),
            company_postcode varchar(20),
            company_city varchar(100),
            company_country varchar(100),
            contact_first_name varchar(100) NOT NULL,
            contact_last_name varchar(100) NOT NULL,
            contact_email varchar(255) NOT NULL,
            status varchar(20) DEFAULT 'pending',
            submitted_at datetime DEFAULT CURRENT_TIMESTAMP,
            approved_at datetime,
            approved_by int(11),
            rejection_reason text,
            ip_address varchar(45),
            user_agent text,
            gravity_form_entry_id int(11),
            created_user_id int(11),
            PRIMARY KEY (id),
            KEY status (status),
            KEY company_email (company_email),
            KEY contact_email (contact_email),
            KEY submitted_at (submitted_at),
            KEY gravity_form_entry_id (gravity_form_entry_id),
            KEY created_user_id (created_user_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Add database indexes for better performance
     */
    private function add_performance_indexes() {
        global $wpdb;
        
        // Only add indexes once
        if (get_option('rt_employee_manager_indexes_added')) {
            return;
        }
        
        // Add index for employer_id lookups
        $wpdb->query("CREATE INDEX IF NOT EXISTS idx_rt_employer_id ON {$wpdb->postmeta} (meta_key, meta_value) WHERE meta_key = 'employer_id'");
        
        // Add index for status lookups
        $wpdb->query("CREATE INDEX IF NOT EXISTS idx_rt_status ON {$wpdb->postmeta} (meta_key, meta_value) WHERE meta_key = 'status'");
        
        // Add composite index for employee queries
        $wpdb->query("CREATE INDEX IF NOT EXISTS idx_rt_employee_lookup ON {$wpdb->postmeta} (post_id, meta_key, meta_value)");
        
        // Mark indexes as added
        update_option('rt_employee_manager_indexes_added', true);
    }
    
    private function set_default_options() {
        $default_options = array(
            'enable_email_notifications' => '1',
            'admin_email' => get_option('admin_email'),
            'employee_form_id' => '1',
            'client_form_id' => '3',
            'registration_form_id' => '3', // Form ID for company registration at /firmen-registrierung/
            'enable_logging' => '1',
            'enable_svnr_validation' => '1'
        );
        
        foreach ($default_options as $key => $value) {
            if (!get_option('rt_employee_manager_' . $key)) {
                update_option('rt_employee_manager_' . $key, $value);
            }
        }
    }
    
    
    /**
     * Fix WordPress menu arrays that get corrupted by Admin Site Enhancements plugin
     * This ensures $menu and $submenu globals exist and are properly structured
     */
    public function fix_menu_arrays() {
        global $menu, $submenu, $_wp_submenu_nopriv, $_wp_menu_nopriv;
        
        // Only run in admin area
        if (!is_admin()) {
            return;
        }
        
        // Force re-initialize menu arrays to prevent corruption
        if (!is_array($menu)) {
            $menu = array();
        }
        
        if (!is_array($submenu)) {
            $submenu = array();
        }
        
        if (!is_array($_wp_submenu_nopriv)) {
            $_wp_submenu_nopriv = array();
        }
        
        if (!is_array($_wp_menu_nopriv)) {
            $_wp_menu_nopriv = array();
        }
        
        // More aggressive fix - rebuild corrupted menu items
        $fixed_menu = array();
        foreach ($menu as $key => $menu_item) {
            if (!is_array($menu_item)) {
                continue; // Skip corrupted items
            }
            
            // Ensure menu item has all required keys with proper defaults
            $fixed_item = array(
                0 => isset($menu_item[0]) ? $menu_item[0] : '', // Menu title
                1 => isset($menu_item[1]) ? $menu_item[1] : 'read', // Capability
                2 => isset($menu_item[2]) && !empty($menu_item[2]) ? $menu_item[2] : 'admin.php', // Menu slug/file
                3 => isset($menu_item[3]) ? $menu_item[3] : '', // Page title
                4 => isset($menu_item[4]) ? $menu_item[4] : 'menu-top', // CSS classes
                5 => isset($menu_item[5]) ? $menu_item[5] : '', // Hookname
                6 => isset($menu_item[6]) ? $menu_item[6] : '' // Icon URL
            );
            
            $fixed_menu[$key] = $fixed_item;
        }
        $menu = $fixed_menu;
        
        // Fix submenu items
        $fixed_submenu = array();
        foreach ($submenu as $parent => $sub_items) {
            if (!is_array($sub_items)) {
                continue;
            }
            
            $fixed_sub_items = array();
            foreach ($sub_items as $sub_key => $sub_item) {
                if (!is_array($sub_item)) {
                    continue;
                }
                
                // Ensure submenu item has required keys
                $fixed_sub_item = array(
                    0 => isset($sub_item[0]) ? $sub_item[0] : '', // Menu title
                    1 => isset($sub_item[1]) ? $sub_item[1] : 'read', // Capability
                    2 => isset($sub_item[2]) && !empty($sub_item[2]) ? $sub_item[2] : 'admin.php' // Menu slug/file
                );
                
                $fixed_sub_items[$sub_key] = $fixed_sub_item;
            }
            
            if (!empty($fixed_sub_items)) {
                $fixed_submenu[$parent] = $fixed_sub_items;
            }
        }
        $submenu = $fixed_submenu;
    }
    
    /**
     * Customize admin menu specifically for kunden users
     */
    public function customize_admin_menu_for_kunden() {
        global $menu, $submenu;
        
        $current_user = wp_get_current_user();
        
        // Only apply to kunden users (not admins)
        if (!in_array('kunden', $current_user->roles) || current_user_can('manage_options')) {
            return;
        }
        
        // Remove all menu items that kunden users shouldn't see
        $items_to_remove = array(
            'index.php', // Dashboard
            'edit.php', // Posts
            'upload.php', // Media
            'edit.php?post_type=page', // Pages
            'edit-comments.php', // Comments
            'themes.php', // Appearance
            'plugins.php', // Plugins
            'users.php', // Users
            'tools.php', // Tools
            'options-general.php', // Settings
            'rank-math', // RankMath SEO
            'seo-by-rank-math', // Alternative RankMath
            'profile.php' // Profile - we'll add this back later in a controlled way
        );
        
        // Remove unwanted menu items
        foreach ($items_to_remove as $menu_slug) {
            remove_menu_page($menu_slug);
        }
        
        // Remove all submenus from remaining items, but keep employee-related ones
        foreach ($submenu as $parent_slug => $sub_items) {
            if ($parent_slug !== 'rt-employee-manager' && $parent_slug !== 'edit.php?post_type=angestellte') {
                unset($submenu[$parent_slug]);
            }
        }
        
        // Add back only essential items for kunden users
        add_menu_page(
            __('Profil', 'rt-employee-manager'),
            __('Profil', 'rt-employee-manager'),
            'read',
            'profile.php',
            '',
            'dashicons-admin-users',
            80
        );
        
        // Get the admin settings instance for the callback
        $admin_settings = $GLOBALS['rt_employee_manager_admin_settings'] ?? null;
        
        if (!$admin_settings) {
            // Create temporary instance if not available
            $admin_settings = new RT_Employee_Manager_Admin_Settings();
            $GLOBALS['rt_employee_manager_admin_settings'] = $admin_settings;
        }
        
        // Ensure our Employee Manager menu is properly positioned
        add_menu_page(
            __('Mitarbeiterverwaltung', 'rt-employee-manager'),
            __('Mitarbeiterverwaltung', 'rt-employee-manager'),
            'read',
            'rt-employee-manager',
            array($admin_settings, 'admin_page'),
            'dashicons-groups',
            26
        );
        
        // Add Angestellte menu item for kunden users
        add_menu_page(
            __('Mitarbeiter', 'rt-employee-manager'),
            __('Mitarbeiter', 'rt-employee-manager'),
            'read',
            'edit.php?post_type=angestellte',
            '',
            'dashicons-admin-users',
            27
        );
        
        // Force menu rebuild
        $this->rebuild_kunden_menu();
    }
    
    /**
     * Rebuild menu structure for kunden users
     */
    private function rebuild_kunden_menu() {
        global $menu;
        
        $current_user = wp_get_current_user();
        
        if (!in_array('kunden', $current_user->roles) || current_user_can('manage_options')) {
            return;
        }
        
        // Create clean menu structure
        $clean_menu = array();
        
        // Add Employee Manager as primary menu item
        $clean_menu[26] = array(
            'Mitarbeiterverwaltung',
            'read',
            'rt-employee-manager',
            'Mitarbeiterverwaltung',
            'menu-top menu-icon-rt-employee',
            'menu-rt-employee-manager',
            'dashicons-groups'
        );
        
        // Add Angestellte submenu
        $clean_menu[27] = array(
            'Mitarbeiter',
            'read',
            'edit.php?post_type=angestellte',
            'Mitarbeiter',
            'menu-top',
            'menu-angestellte',
            'dashicons-admin-users'
        );
        
        // Add separator
        $clean_menu[79] = array(
            '',
            'read',
            'separator-custom',
            '',
            'wp-menu-separator',
            '',
            ''
        );
        
        // Add Profile menu
        $clean_menu[80] = array(
            'Profil',
            'read',
            'profile.php',
            'Profil',
            'menu-top',
            'menu-profile',
            'dashicons-admin-users'
        );
        
        // Replace the global menu
        $menu = $clean_menu;
    }
    
    /**
     * Force menu translation refresh to ensure German labels are shown
     */
    public function force_menu_translation_refresh() {
        global $menu, $submenu;
        
        if (!is_admin() || !$menu) {
            return;
        }
        
        // Update post type labels in the menu
        foreach ($menu as $key => $menu_item) {
            if (!is_array($menu_item) || !isset($menu_item[2])) {
                continue;
            }
            
            // Fix Angestellte -> Mitarbeiter
            if ($menu_item[2] === 'edit.php?post_type=angestellte') {
                $menu[$key][0] = __('Mitarbeiter', 'rt-employee-manager');
                $menu[$key][3] = __('Mitarbeiter', 'rt-employee-manager');
            }
            
            // Fix Kunden -> Unternehmen
            if ($menu_item[2] === 'edit.php?post_type=kunde') {
                $menu[$key][0] = __('Unternehmen', 'rt-employee-manager');
                $menu[$key][3] = __('Unternehmen', 'rt-employee-manager');
            }
            
            // Fix Employee Manager title
            if ($menu_item[2] === 'rt-employee-manager') {
                $menu[$key][0] = __('Mitarbeiterverwaltung', 'rt-employee-manager');
                $menu[$key][3] = __('Mitarbeiterverwaltung', 'rt-employee-manager');
            }
        }
        
        // Update submenu labels
        if (isset($submenu['rt-employee-manager'])) {
            foreach ($submenu['rt-employee-manager'] as $key => $submenu_item) {
                if ($submenu_item[2] === 'rt-employee-manager-registrations') {
                    $submenu['rt-employee-manager'][$key][0] = __('Registrierungen', 'rt-employee-manager');
                }
                if ($submenu_item[2] === 'rt-employee-manager-settings') {
                    $submenu['rt-employee-manager'][$key][0] = __('Einstellungen', 'rt-employee-manager');
                }
                if ($submenu_item[2] === 'rt-employee-manager-logs') {
                    $submenu['rt-employee-manager'][$key][0] = __('Logs', 'rt-employee-manager');
                }
            }
        }
        
        // Update Angestellte submenu
        if (isset($submenu['edit.php?post_type=angestellte'])) {
            foreach ($submenu['edit.php?post_type=angestellte'] as $key => $submenu_item) {
                if (strpos($submenu_item[2], 'post-new.php?post_type=angestellte') !== false) {
                    $submenu['edit.php?post_type=angestellte'][$key][0] = __('Neuen hinzufügen', 'rt-employee-manager');
                }
            }
        }
        
        // Update Kunde submenu
        if (isset($submenu['edit.php?post_type=kunde'])) {
            foreach ($submenu['edit.php?post_type=kunde'] as $key => $submenu_item) {
                if (strpos($submenu_item[2], 'post-new.php?post_type=kunde') !== false) {
                    $submenu['edit.php?post_type=kunde'][$key][0] = __('Neues hinzufügen', 'rt-employee-manager');
                }
            }
        }
    }
    
    /**
     * Hide WordPress admin notices for better user experience
     */
    public function hide_wp_admin_notices() {
        global $wp_filter;
        
        // Only hide notices for kunden users and on our plugin pages
        $current_user = wp_get_current_user();
        $current_screen = get_current_screen();
        
        // Hide for kunden users or on our plugin pages
        $should_hide = false;
        
        if (in_array('kunden', $current_user->roles) && !current_user_can('manage_options')) {
            $should_hide = true;
        }
        
        // Also hide on our plugin admin pages for cleaner interface
        if ($current_screen && (
            strpos($current_screen->id, 'rt-employee-manager') !== false ||
            ($current_screen->post_type && in_array($current_screen->post_type, ['angestellte', 'kunde']))
        )) {
            $should_hide = true;
        }
        
        if ($should_hide) {
            // Remove WordPress core notices
            remove_action('admin_notices', 'update_nag', 3);
            remove_action('admin_notices', 'maintenance_nag');
            remove_action('network_admin_notices', 'update_nag', 3);
            remove_action('network_admin_notices', 'maintenance_nag');
            
            // Remove plugin/theme update notices
            remove_action('admin_notices', array('WP_Theme_Install_List_Table', 'check_permissions'));
            
            // Add CSS to hide remaining notices
            echo '<style>
                .notice:not(.rt-employee-notice), 
                .update-nag, 
                .updated, 
                .error:not(.rt-employee-error),
                .notice-warning:not(.rt-employee-warning),
                .notice-info:not(.rt-employee-info),
                .notice-success:not(.rt-employee-success) {
                    display: none !important;
                }
                
                /* Allow our plugin notices to show */
                .notice.rt-employee-notice,
                .error.rt-employee-error,
                .notice-warning.rt-employee-warning,
                .notice-info.rt-employee-info,
                .notice-success.rt-employee-success {
                    display: block !important;
                }
            </style>';
            
            // Also remove from the global wp_filter if they exist
            if (isset($wp_filter['admin_notices'])) {
                foreach ($wp_filter['admin_notices']->callbacks as $priority => $callbacks) {
                    foreach ($callbacks as $callback_id => $callback) {
                        // Skip our own callbacks
                        if (is_array($callback['function']) && 
                            isset($callback['function'][0]) && 
                            $callback['function'][0] === $this) {
                            continue;
                        }
                        
                        // Remove WordPress core update notices
                        if (is_string($callback['function']) && 
                            in_array($callback['function'], ['update_nag', 'maintenance_nag'])) {
                            unset($wp_filter['admin_notices']->callbacks[$priority][$callback_id]);
                        }
                    }
                }
            }
        }
    }
}

// Only initialize the plugin if it's actually active
if (!function_exists('is_plugin_active')) {
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');
}

if (is_plugin_active(plugin_basename(__FILE__))) {
    RT_Employee_Manager::get_instance();
} else {
    // Plugin is deactivated but file is loaded - force clean rewrite rules
    add_action('admin_init', function() {
        if (get_option('rewrite_rules') && strpos(get_option('rewrite_rules'), 'angestellte') !== false) {
            flush_rewrite_rules();
            
            // Show notice that cleanup was performed
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p><strong>RT Employee Manager:</strong> Cleaned up cached rewrite rules. Custom post types should now be removed.</p>';
                echo '</div>';
            });
        }
    });
}