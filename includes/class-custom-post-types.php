<?php

if (!defined('ABSPATH')) {
    exit;
}

class RT_Employee_Manager_Custom_Post_Types {
    
    public function __construct() {
        // Only initialize if the plugin is active
        if (!$this->is_plugin_active()) {
            return;
        }
        
        add_action('init', array($this, 'register_post_types'));
        
        // Add query filters for kunden users to only see their own employees
        add_action('pre_get_posts', array($this, 'filter_employee_posts_for_kunden'));
        add_filter('map_meta_cap', array($this, 'map_employee_meta_caps'), 10, 4);
        
        // Add custom columns and row actions for employees
        add_filter('manage_angestellte_posts_columns', array($this, 'add_employee_columns'));
        add_action('manage_angestellte_posts_custom_column', array($this, 'populate_employee_columns'), 10, 2);
        
        // Add custom columns and row actions for customers
        add_filter('manage_kunde_posts_columns', array($this, 'add_kunde_columns'));
        add_action('manage_kunde_posts_custom_column', array($this, 'populate_kunde_columns'), 10, 2);
        
        // Add row actions for both post types
        add_filter('post_row_actions', array($this, 'add_custom_row_actions'), 10, 2);
        
        // Disable visual editor and media uploads for employee posts
        add_action('admin_head', array($this, 'disable_visual_editor_for_employees'));
        add_action('admin_init', array($this, 'remove_media_buttons_for_employees'));
        add_action('add_meta_boxes', array($this, 'remove_unnecessary_metaboxes'));
    }
    
    /**
     * Check if plugin is actually active
     */
    private function is_plugin_active() {
        // Check if we're in admin and the function exists
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        
        return is_plugin_active('employee-manager/rt-employee-manager.php');
    }
    
    /**
     * Static helper to check if plugin is active
     */
    private static function check_plugin_active() {
        // Check if we're in admin and the function exists
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        
        return is_plugin_active('employee-manager/rt-employee-manager.php');
    }
    
    public static function register_post_types() {
        // Only register CPTs if the plugin is actually active
        if (!self::check_plugin_active()) {
            return;
        }
        
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
            'show_in_menu' => current_user_can('read'), // Allow kunden users to see menu
            'show_in_admin_bar' => true,
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
            'map_meta_cap' => true,
            'has_archive' => false,
            'hierarchical' => false,
            'menu_position' => 20,
            'menu_icon' => 'dashicons-groups',
            'supports' => array('title', 'custom-fields'),
            'show_in_rest' => false,
        );
        
        register_post_type('angestellte', $args);
        
        // Register Kunde (Client) post type - only visible to admins
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
            'show_ui' => current_user_can('manage_options'),
            'show_in_menu' => current_user_can('manage_options'),
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
            'supports' => array('title', 'custom-fields'),
            'show_in_rest' => false,
        );
        
        register_post_type('kunde', $kunde_args);
        
        // Add custom capabilities to administrator role
        self::add_custom_capabilities();
        
        // Force capability refresh for existing users
        self::refresh_user_capabilities();
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
                'edit_others_employees' => false,  // Can't edit other users' employees
                'publish_employees' => true,
                'read_employee' => true,
                'read_private_employees' => false,
                'delete_employees' => true,
                'delete_others_employees' => false,
                'delete_private_employees' => false,
                'delete_published_employees' => true,
                'edit_private_employees' => false,
                'edit_published_employees' => true,
                'upload_files' => true,
                // Client capabilities for editing their own kunde post
                'edit_clients' => true,
                'read_client' => true,
                'delete_clients' => true,
            ));
        } else {
            // Update existing kunden role with new capabilities
            $kunden_role = get_role('kunden');
            if ($kunden_role) {
                // Employee capabilities
                $kunden_role->add_cap('create_employees');
                $kunden_role->add_cap('edit_employees');
                $kunden_role->add_cap('publish_employees');
                $kunden_role->add_cap('read_employee');
                $kunden_role->add_cap('delete_employees');
                $kunden_role->add_cap('delete_published_employees');
                $kunden_role->add_cap('edit_published_employees');
                // Client capabilities
                $kunden_role->add_cap('edit_clients');
                $kunden_role->add_cap('read_client');
                $kunden_role->add_cap('delete_clients');
            }
        }
    }
    
    /**
     * Filter employee posts for kunden users to only show their own employees
     */
    public function filter_employee_posts_for_kunden($query) {
        // Only apply to admin area and angestellte post type
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }
        
        // Only apply to angestellte post type
        if ($query->get('post_type') !== 'angestellte') {
            return;
        }
        
        $current_user = wp_get_current_user();
        
        // Only apply to kunden users (not admins)
        if (!in_array('kunden', $current_user->roles) || current_user_can('manage_options')) {
            return;
        }
        
        // Filter to only show employees where current user is the employer
        $meta_query = $query->get('meta_query') ?: array();
        $meta_query[] = array(
            'key' => 'employer_id',
            'value' => $current_user->ID,
            'compare' => '='
        );
        
        $query->set('meta_query', $meta_query);
    }
    
    /**
     * Map meta capabilities for employee and kunde posts
     */
    public function map_employee_meta_caps($caps, $cap, $user_id, $args) {
        // Handle employee and kunde post capabilities - including standard WordPress caps
        if (in_array($cap, array('edit_employee', 'delete_employee', 'read_employee', 'edit_client', 'delete_client', 'read_client', 'edit_post', 'delete_post', 'read_post'))) {
            $post_id = isset($args[0]) ? $args[0] : 0;
            $post = get_post($post_id);
            
            // Only handle angestellte and kunde post types
            if (!$post || !in_array($post->post_type, array('angestellte', 'kunde'))) {
                return $caps;
            }
            
            $user = get_user_by('ID', $user_id);
            
            // Admins can do everything
            if (user_can($user_id, 'manage_options')) {
                return array('manage_options');
            }
            
            // Handle kunde posts
            if ($post->post_type === 'kunde') {
                $post_user_id = get_post_meta($post_id, 'user_id', true) ?: $post->post_author;
                
                // Kunden users can only edit their own kunde post
                if ($user && in_array('kunden', $user->roles) && $post_user_id == $user_id) {
                    switch ($cap) {
                        case 'edit_client':
                        case 'edit_post':
                            return array('edit_clients');
                        case 'delete_client':
                        case 'delete_post':
                            return array('delete_clients');
                        case 'read_client':
                        case 'read_post':
                            return array('read_client');
                    }
                } else {
                    return array('do_not_allow');
                }
            }
            
            // Handle angestellte posts
            if ($post->post_type === 'angestellte') {
                // Kunden users can only edit their own employees
                if ($user && in_array('kunden', $user->roles)) {
                    $employer_id = get_post_meta($post_id, 'employer_id', true);
                    
                    // Debug: Force metadata fix if missing
                    if (empty($employer_id)) {
                        $this->force_fix_employee_metadata($post_id, $user_id);
                        $employer_id = get_post_meta($post_id, 'employer_id', true);
                    }
                    
                    if ($employer_id == $user_id) {
                        // User owns this employee - allow the action
                        switch ($cap) {
                            case 'edit_employee':
                            case 'edit_post':
                                return array('edit_employees');
                            case 'delete_employee':
                            case 'delete_post':
                                return array('delete_employees');
                            case 'read_employee':
                            case 'read_post':
                                return array('read_employee');
                        }
                    } else {
                        // User doesn't own this employee - deny
                        return array('do_not_allow');
                    }
                }
            }
        }
        
        return $caps;
    }
    
    /**
     * Force fix employee metadata if missing
     */
    private function force_fix_employee_metadata($post_id, $user_id) {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'angestellte') {
            return;
        }
        
        // Check if any required metadata is missing
        $vorname = get_post_meta($post_id, 'vorname', true);
        $employer_id = get_post_meta($post_id, 'employer_id', true);
        
        if (empty($vorname) || empty($employer_id)) {
            // Only set essential metadata without dummy data
            if (empty($employer_id)) {
                update_post_meta($post_id, 'employer_id', $user_id);
            }
            if (empty(get_post_meta($post_id, 'status', true))) {
                update_post_meta($post_id, 'status', 'active');
            }
            
            error_log('RT Employee Manager: Force-fixed metadata for employee post #' . $post_id . ' for user #' . $user_id);
        }
    }
    
    /**
     * Refresh user capabilities - force update (optimized for performance)
     */
    private static function refresh_user_capabilities() {
        // Only refresh capabilities if not done recently
        $last_refresh = get_option('rt_capabilities_last_refresh', 0);
        if ((time() - $last_refresh) < 3600) { // Only refresh once per hour
            return;
        }
        
        // Clear capabilities cache to force refresh
        wp_cache_delete('user_meta', 'users');
        
        // Get all kunden users in batches to prevent memory issues
        $batch_size = 50;
        $page = 1;
        
        do {
            $kunden_users = get_users(array(
                'role' => 'kunden',
                'number' => $batch_size,
                'paged' => $page
            ));
            
            foreach ($kunden_users as $user) {
                // Force capability refresh by removing and re-adding the role
                $user->remove_role('kunden');
                $user->add_role('kunden');
            }
            
            $page++;
        } while (count($kunden_users) === $batch_size);
        
        // Update the last refresh timestamp
        update_option('rt_capabilities_last_refresh', time());
    }
    
    /**
     * Add custom columns to employee list
     */
    public function add_employee_columns($columns) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = $columns['title'];
        $new_columns['employee_svnr'] = __('SVNR', 'rt-employee-manager');
        $new_columns['employee_status'] = __('Status', 'rt-employee-manager');
        $new_columns['employee_employer'] = __('Arbeitgeber', 'rt-employee-manager');
        $new_columns['date'] = $columns['date'];
        
        return $new_columns;
    }
    
    /**
     * Populate custom columns
     */
    public function populate_employee_columns($column, $post_id) {
        switch ($column) {
            case 'employee_svnr':
                $svnr = get_post_meta($post_id, 'sozialversicherungsnummer', true);
                if ($svnr && strlen($svnr) === 10) {
                    echo substr($svnr, 0, 2) . ' ' . substr($svnr, 2, 4) . ' ' . substr($svnr, 6, 2) . ' ' . substr($svnr, 8, 2);
                } else {
                    echo '-';
                }
                break;
                
            case 'employee_status':
                $status = get_post_meta($post_id, 'status', true) ?: 'active';
                $status_labels = array(
                    'active' => __('Aktiv', 'rt-employee-manager'),
                    'inactive' => __('Inaktiv', 'rt-employee-manager'),
                    'suspended' => __('Gesperrt', 'rt-employee-manager'),
                    'terminated' => __('Gekündigt', 'rt-employee-manager')
                );
                echo '<span class="status-' . esc_attr($status) . '">' . esc_html($status_labels[$status] ?? $status) . '</span>';
                break;
                
            case 'employee_employer':
                $employer_id = get_post_meta($post_id, 'employer_id', true);
                if ($employer_id) {
                    $employer = get_user_by('ID', $employer_id);
                    if ($employer) {
                        echo esc_html($employer->display_name);
                        $company = get_user_meta($employer_id, 'company_name', true);
                        if ($company) {
                            echo '<br><small>' . esc_html($company) . '</small>';
                        }
                    } else {
                        echo __('Unbekannt', 'rt-employee-manager');
                    }
                } else {
                    echo '<em>' . __('Nicht zugewiesen', 'rt-employee-manager') . '</em>';
                }
                break;
        }
    }
    
    /**
     * Add custom columns to kunde list
     */
    public function add_kunde_columns($columns) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = $columns['title'];
        $new_columns['kunde_uid'] = __('UID-Nummer', 'rt-employee-manager');
        $new_columns['kunde_phone'] = __('Telefon', 'rt-employee-manager');
        $new_columns['kunde_email'] = __('E-Mail', 'rt-employee-manager');
        $new_columns['date'] = $columns['date'];
        
        return $new_columns;
    }
    
    /**
     * Populate kunde columns
     */
    public function populate_kunde_columns($column, $post_id) {
        switch ($column) {
            case 'kunde_uid':
                $uid = get_post_meta($post_id, 'uid_number', true);
                echo esc_html($uid ?: '-');
                break;
                
            case 'kunde_phone':
                $phone = get_post_meta($post_id, 'phone', true);
                echo esc_html($phone ?: '-');
                break;
                
            case 'kunde_email':
                $email = get_post_meta($post_id, 'email', true);
                if ($email) {
                    echo '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
                } else {
                    echo '-';
                }
                break;
        }
    }
    
    /**
     * Add custom row actions for both post types
     */
    public function add_custom_row_actions($actions, $post) {
        $current_user_id = get_current_user_id();
        $current_user = wp_get_current_user();
        
        if ($post->post_type === 'angestellte') {
            $employer_id = get_post_meta($post->ID, 'employer_id', true);
            
            // Fix missing metadata automatically
            if (empty($employer_id) && in_array('kunden', $current_user->roles)) {
                $this->force_fix_employee_metadata($post->ID, $current_user_id);
                $employer_id = get_post_meta($post->ID, 'employer_id', true);
            }
            
            // Only show edit/delete for owned employees or admins
            if (!current_user_can('manage_options') && $employer_id != $current_user_id) {
                unset($actions['edit']);
                unset($actions['inline hide-if-no-js']);
                unset($actions['trash']);
            }
        }
        
        if ($post->post_type === 'kunde') {
            $post_user_id = get_post_meta($post->ID, 'user_id', true) ?: $post->post_author;
            
            // Only show edit/delete for own kunde post or admins
            if (!current_user_can('manage_options') && $post_user_id != $current_user_id) {
                unset($actions['edit']);
                unset($actions['inline hide-if-no-js']);
                unset($actions['trash']);
            }
        }
        
        return $actions;
    }
    
    /**
     * Disable visual editor for employee posts
     */
    public function disable_visual_editor_for_employees() {
        global $post_type;
        
        if ($post_type === 'angestellte') {
            remove_post_type_support('angestellte', 'editor');
            remove_post_type_support('angestellte', 'thumbnail');
            
            // Add CSS to hide remaining editor elements
            echo '<style>
                #postdivrich, #wp-content-wrap, #media-buttons { display: none !important; }
                #normal-sortables .postbox { margin-bottom: 0; }
                #post-body-content { margin-bottom: 10px; }
            </style>';
        }
    }
    
    /**
     * Remove media buttons for employee posts
     */
    public function remove_media_buttons_for_employees() {
        global $post_type;
        
        if ($post_type === 'angestellte') {
            remove_action('media_buttons', 'media_buttons');
        }
    }
    
    /**
     * Remove unnecessary metaboxes for employee posts
     */
    public function remove_unnecessary_metaboxes() {
        // Remove for angestellte post type
        remove_meta_box('postexcerpt', 'angestellte', 'normal');
        remove_meta_box('trackbacksdiv', 'angestellte', 'normal');
        remove_meta_box('postcustom', 'angestellte', 'normal');
        remove_meta_box('commentstatusdiv', 'angestellte', 'normal');
        remove_meta_box('commentsdiv', 'angestellte', 'normal');
        remove_meta_box('revisionsdiv', 'angestellte', 'normal');
        remove_meta_box('authordiv', 'angestellte', 'normal');
        remove_meta_box('postimagediv', 'angestellte', 'side');
        remove_meta_box('pageparentdiv', 'angestellte', 'side');
        
        // Remove for kunde post type as well
        remove_meta_box('postexcerpt', 'kunde', 'normal');
        remove_meta_box('trackbacksdiv', 'kunde', 'normal');
        remove_meta_box('postcustom', 'kunde', 'normal');
        remove_meta_box('commentstatusdiv', 'kunde', 'normal');
        remove_meta_box('commentsdiv', 'kunde', 'normal');
        remove_meta_box('revisionsdiv', 'kunde', 'normal');
        remove_meta_box('authordiv', 'kunde', 'normal');
        remove_meta_box('postimagediv', 'kunde', 'side');
        remove_meta_box('pageparentdiv', 'kunde', 'side');
    }
}