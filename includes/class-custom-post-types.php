<?php

if (!defined('ABSPATH')) {
    exit;
}

class RT_Employee_Manager_Custom_Post_Types {
    
    public function __construct() {
        add_action('init', array($this, 'register_post_types'));
    }
    
    public static function register_post_types() {
        // Register Angestellte (Employee) post type
        $args = array(
            'label' => __('Angestellte', 'rt-employee-manager'),
            'labels' => array(
                'name' => __('Angestellte', 'rt-employee-manager'),
                'singular_name' => __('Angestellte', 'rt-employee-manager'),
                'menu_name' => __('Angestellte', 'rt-employee-manager'),
                'name_admin_bar' => __('Angestellte', 'rt-employee-manager'),
                'add_new' => __('Neue hinzufügen', 'rt-employee-manager'),
                'add_new_item' => __('Neue Angestellte hinzufügen', 'rt-employee-manager'),
                'new_item' => __('Neue Angestellte', 'rt-employee-manager'),
                'edit_item' => __('Angestellte bearbeiten', 'rt-employee-manager'),
                'view_item' => __('Angestellte anzeigen', 'rt-employee-manager'),
                'all_items' => __('Alle Angestellte', 'rt-employee-manager'),
                'search_items' => __('Angestellte suchen', 'rt-employee-manager'),
                'not_found' => __('Keine Angestellte gefunden', 'rt-employee-manager'),
                'not_found_in_trash' => __('Keine Angestellte im Papierkorb gefunden', 'rt-employee-manager'),
            ),
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'angestellte'),
            'capability_type' => 'post',
            'capabilities' => array(
                'create_posts' => 'create_employees',
                'edit_posts' => 'edit_employees',
                'edit_others_posts' => 'edit_others_employees',
                'publish_posts' => 'publish_employees',
                'read_post' => 'read_employee',
                'read_private_posts' => 'read_private_employees',
                'delete_posts' => 'delete_employees',
                'delete_others_posts' => 'delete_others_employees',
                'delete_private_posts' => 'delete_private_employees',
                'delete_published_posts' => 'delete_published_employees',
                'edit_private_posts' => 'edit_private_employees',
                'edit_published_posts' => 'edit_published_employees',
            ),
            'has_archive' => false,
            'hierarchical' => false,
            'menu_position' => 20,
            'menu_icon' => 'dashicons-groups',
            'supports' => array('title', 'editor', 'custom-fields'),
            'show_in_rest' => false,
        );
        
        register_post_type('angestellte', $args);
        
        // Register Kunde (Client) post type
        $kunde_args = array(
            'label' => __('Kunden', 'rt-employee-manager'),
            'labels' => array(
                'name' => __('Kunden', 'rt-employee-manager'),
                'singular_name' => __('Kunde', 'rt-employee-manager'),
                'menu_name' => __('Kunden', 'rt-employee-manager'),
                'name_admin_bar' => __('Kunde', 'rt-employee-manager'),
                'add_new' => __('Neuen hinzufügen', 'rt-employee-manager'),
                'add_new_item' => __('Neuen Kunde hinzufügen', 'rt-employee-manager'),
                'new_item' => __('Neuer Kunde', 'rt-employee-manager'),
                'edit_item' => __('Kunde bearbeiten', 'rt-employee-manager'),
                'view_item' => __('Kunde anzeigen', 'rt-employee-manager'),
                'all_items' => __('Alle Kunden', 'rt-employee-manager'),
                'search_items' => __('Kunden suchen', 'rt-employee-manager'),
                'not_found' => __('Keine Kunden gefunden', 'rt-employee-manager'),
                'not_found_in_trash' => __('Keine Kunden im Papierkorb gefunden', 'rt-employee-manager'),
            ),
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'kunden'),
            'capability_type' => 'post',
            'capabilities' => array(
                'create_posts' => 'create_clients',
                'edit_posts' => 'edit_clients',
                'edit_others_posts' => 'edit_others_clients',
                'publish_posts' => 'publish_clients',
                'read_post' => 'read_client',
                'read_private_posts' => 'read_private_clients',
                'delete_posts' => 'delete_clients',
                'delete_others_posts' => 'delete_others_clients',
                'delete_private_posts' => 'delete_private_clients',
                'delete_published_posts' => 'delete_published_clients',
                'edit_private_posts' => 'edit_private_clients',
                'edit_published_posts' => 'edit_published_clients',
            ),
            'has_archive' => false,
            'hierarchical' => false,
            'menu_position' => 21,
            'menu_icon' => 'dashicons-businessman',
            'supports' => array('title', 'editor', 'custom-fields'),
            'show_in_rest' => false,
        );
        
        register_post_type('kunde', $kunde_args);
        
        // Add custom capabilities to administrator role
        self::add_custom_capabilities();
    }
    
    private static function add_custom_capabilities() {
        $admin_role = get_role('administrator');
        
        if ($admin_role) {
            // Employee capabilities
            $admin_role->add_cap('create_employees');
            $admin_role->add_cap('edit_employees');
            $admin_role->add_cap('edit_others_employees');
            $admin_role->add_cap('publish_employees');
            $admin_role->add_cap('read_employee');
            $admin_role->add_cap('read_private_employees');
            $admin_role->add_cap('delete_employees');
            $admin_role->add_cap('delete_others_employees');
            $admin_role->add_cap('delete_private_employees');
            $admin_role->add_cap('delete_published_employees');
            $admin_role->add_cap('edit_private_employees');
            $admin_role->add_cap('edit_published_employees');
            
            // Client capabilities
            $admin_role->add_cap('create_clients');
            $admin_role->add_cap('edit_clients');
            $admin_role->add_cap('edit_others_clients');
            $admin_role->add_cap('publish_clients');
            $admin_role->add_cap('read_client');
            $admin_role->add_cap('read_private_clients');
            $admin_role->add_cap('delete_clients');
            $admin_role->add_cap('delete_others_clients');
            $admin_role->add_cap('delete_private_clients');
            $admin_role->add_cap('delete_published_clients');
            $admin_role->add_cap('edit_private_clients');
            $admin_role->add_cap('edit_published_clients');
        }
        
        // Create Kunden role with limited capabilities
        if (!get_role('kunden')) {
            add_role('kunden', __('Kunden', 'rt-employee-manager'), array(
                'read' => true,
                'edit_posts' => true,  // Added for dashboard access
                'create_employees' => true,
                'edit_employees' => true,
                'delete_employees' => true,
                'read_employee' => true,
                'upload_files' => true,
            ));
        }
    }
}