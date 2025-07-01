<?php
/**
 * Uninstall RT Employee Manager
 * 
 * This file runs when the plugin is deleted (not just deactivated).
 * It removes all plugin data, options, and database tables.
 */

// Security check
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit('Direct access forbidden.');
}

/**
 * Remove all plugin data on uninstall
 */
function rt_employee_manager_uninstall() {
    global $wpdb;
    
    // Remove all plugin options
    $options_to_delete = array(
        'rt_employee_manager_enable_email_notifications',
        'rt_employee_manager_admin_email',
        'rt_employee_manager_employee_form_id',
        'rt_employee_manager_client_form_id',
        'rt_employee_manager_enable_logging',
        'rt_employee_manager_enable_svnr_validation',
        'rt_employee_manager_max_employees_per_client',
        'rt_employee_manager_enable_frontend_editing',
        'rt_test_employee_fixed',
        'rt_missing_kunde_posts_fixed'
    );
    
    foreach ($options_to_delete as $option) {
        delete_option($option);
    }
    
    // Remove custom post types and their meta data
    $post_types = array('angestellte', 'kunde');
    
    foreach ($post_types as $post_type) {
        $posts = get_posts(array(
            'post_type' => $post_type,
            'posts_per_page' => -1,
            'post_status' => array('publish', 'draft', 'trash', 'private')
        ));
        
        foreach ($posts as $post) {
            // Delete all meta data
            $wpdb->delete($wpdb->postmeta, array('post_id' => $post->ID));
            // Delete the post
            wp_delete_post($post->ID, true);
        }
    }
    
    // Remove custom user meta data
    $user_meta_keys = array(
        'company_name',
        'uid_number', 
        'phone',
        'address_street',
        'address_city',
        'address_postcode',
        'address_country',
        'kunde_post_id'
    );
    
    foreach ($user_meta_keys as $meta_key) {
        $wpdb->delete($wpdb->usermeta, array('meta_key' => $meta_key));
    }
    
    // Remove custom capabilities from all roles
    $capabilities_to_remove = array(
        'create_employees', 'edit_employees', 'edit_others_employees', 'publish_employees',
        'read_employee', 'read_private_employees', 'delete_employees', 'delete_others_employees',
        'delete_private_employees', 'delete_published_employees', 'edit_private_employees',
        'edit_published_employees', 'create_clients', 'edit_clients', 'edit_others_clients',
        'publish_clients', 'read_client', 'read_private_clients', 'delete_clients',
        'delete_others_clients', 'delete_private_clients', 'delete_published_clients',
        'edit_private_clients', 'edit_published_clients'
    );
    
    // Get all roles
    $roles = wp_roles()->roles;
    foreach ($roles as $role_name => $role_data) {
        $role = get_role($role_name);
        if ($role) {
            foreach ($capabilities_to_remove as $cap) {
                $role->remove_cap($cap);
            }
        }
    }
    
    // Optional: Remove the kunden role entirely (uncomment if desired)
    // remove_role('kunden');
    
    // Drop custom database tables
    $table_name = $wpdb->prefix . 'rt_employee_logs';
    $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
    
    $table_name = $wpdb->prefix . 'rt_pending_registrations';
    $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
    
    // Clear all caches
    wp_cache_flush();
    flush_rewrite_rules();
    
    // Clear any remaining cache entries
    wp_cache_delete('rt_employee_manager_post_types', 'options');
}

// Run the uninstall function
rt_employee_manager_uninstall();