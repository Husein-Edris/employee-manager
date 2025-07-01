<?php
/**
 * Plugin Name: RT Employee Manager
 * Plugin URI: https://edrishusein.com
 * Description: Professional employee management system with Gravity Forms integration, ACF fields, and Austrian SVNR validation
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
        
        // Add admin notice if plugin was recently deactivated
        add_action('admin_notices', array($this, 'deactivation_notice'));
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
        
        // Check for ACF (optional - will work without it)
        // if (!function_exists('acf')) {
        //     return false;
        // }
        
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
        
        // if (!function_exists('acf')) {
        //     $missing[] = 'Advanced Custom Fields (ACF)';
        // }
        
        if (!class_exists('GF_Advanced_Post_Creation')) {
            $missing[] = 'Gravity Forms Advanced Post Creation';
        }
        
        if (!empty($missing)) {
            echo '<div class="notice notice-error"><p>';
            echo sprintf(
                __('RT Employee Manager requires the following plugins: %s', 'rt-employee-manager'),
                implode(', ', $missing)
            );
            echo '</p></div>';
        }
    }
    
    private function load_includes() {
        require_once RT_EMPLOYEE_MANAGER_PLUGIN_DIR . 'includes/class-custom-post-types.php';
        require_once RT_EMPLOYEE_MANAGER_PLUGIN_DIR . 'includes/class-gravity-forms-integration.php';
        require_once RT_EMPLOYEE_MANAGER_PLUGIN_DIR . 'includes/class-user-fields.php';
        require_once RT_EMPLOYEE_MANAGER_PLUGIN_DIR . 'includes/class-acf-integration.php';
        require_once RT_EMPLOYEE_MANAGER_PLUGIN_DIR . 'includes/class-employee-dashboard.php';
        require_once RT_EMPLOYEE_MANAGER_PLUGIN_DIR . 'includes/class-admin-settings.php';
        require_once RT_EMPLOYEE_MANAGER_PLUGIN_DIR . 'includes/class-security.php';
    }
    
    private function init_components() {
        new RT_Employee_Manager_Custom_Post_Types();
        new RT_Employee_Manager_Gravity_Forms_Integration();
        new RT_Employee_Manager_User_Fields();
        
        // Only load ACF integration if ACF is active
        if (function_exists('acf')) {
            new RT_Employee_Manager_ACF_Integration();
        }
        
        new RT_Employee_Manager_Employee_Dashboard();
        new RT_Employee_Manager_Admin_Settings();
        new RT_Employee_Manager_Security();
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
            echo '<div class="notice notice-info is-dismissible">';
            echo '<p><strong>RT Employee Manager:</strong> ';
            echo __('Plugin has been deactivated. Custom post types (Angestellte, Kunden) and related functionality are now disabled. You may need to refresh the page to see changes.', 'rt-employee-manager');
            echo '</p></div>';
            
            // Clear the flag after showing the notice
            delete_option('rt_employee_manager_deactivated');
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
    }
    
    private function set_default_options() {
        $default_options = array(
            'enable_email_notifications' => '1',
            'admin_email' => get_option('admin_email'),
            'employee_form_id' => '1',
            'client_form_id' => '3',
            'enable_logging' => '1',
            'enable_svnr_validation' => '1'
        );
        
        foreach ($default_options as $key => $value) {
            if (!get_option('rt_employee_manager_' . $key)) {
                update_option('rt_employee_manager_' . $key, $value);
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