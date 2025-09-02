<?php

if (!defined('ABSPATH')) {
    exit;
}

class RT_Employee_Manager_Login_Redirect {
    
    public function __construct() {
        add_filter('login_redirect', array($this, 'custom_login_redirect'), 10, 3);
        add_action('wp_login', array($this, 'set_welcome_message'), 10, 2);
        add_action('admin_notices', array($this, 'show_welcome_message'));
        add_filter('wp_admin_bar_show', array($this, 'hide_admin_bar_for_kunden'));
        add_action('admin_init', array($this, 'restrict_admin_access'));
        add_action('init', array($this, 'handle_secure_login'));
        add_action('admin_notices', array($this, 'show_login_errors'));
    }
    
    /**
     * Custom login redirect based on user role
     */
    public function custom_login_redirect($redirect_to, $request, $user) {
        // Check if login was successful and user object exists
        if (isset($user->roles) && is_array($user->roles)) {
            
            // If user is kunden, redirect to employee manager main page
            if (in_array('kunden', $user->roles)) {
                $dashboard_url = admin_url('admin.php?page=rt-employee-manager');
                
                // Add welcome parameter for first-time users
                if (get_user_meta($user->ID, 'first_login', true) !== 'completed') {
                    $dashboard_url = add_query_arg('welcome', '1', $dashboard_url);
                    update_user_meta($user->ID, 'first_login', 'completed');
                }
                
                return $dashboard_url;
            }
            
            // If user is administrator, check if they want to go to employee manager
            if (in_array('administrator', $user->roles)) {
                // If coming from employee manager context, redirect there
                if (isset($_GET['redirect_to']) && strpos($_GET['redirect_to'], 'rt-employee-manager') !== false) {
                    return $_GET['redirect_to'];
                }
                
                // Otherwise, use default admin redirect
                return admin_url();
            }
        }
        
        // Default redirect for other users
        return $redirect_to;
    }
    
    /**
     * Set welcome message for new users
     */
    public function set_welcome_message($user_login, $user) {
        if (in_array('kunden', $user->roles)) {
            // Check if this is a recently approved user
            $kunde_post_id = get_user_meta($user->ID, 'kunde_post_id', true);
            if ($kunde_post_id) {
                $approval_flag = get_post_meta($kunde_post_id, 'approved_from_registration', true);
                if ($approval_flag && !get_user_meta($user->ID, 'welcome_shown', true)) {
                    set_transient('rt_welcome_message_' . $user->ID, array(
                        'type' => 'success',
                        'message' => sprintf(
                            __('Willkommen bei %s! Ihr Konto wurde genehmigt und Sie können nun Ihre Mitarbeiter verwalten.', 'rt-employee-manager'),
                            get_bloginfo('name')
                        )
                    ), 300); // 5 minutes
                    
                    update_user_meta($user->ID, 'welcome_shown', true);
                }
            }
        }
    }
    
    /**
     * Show welcome message on dashboard
     */
    public function show_welcome_message() {
        $user_id = get_current_user_id();
        $message_data = get_transient('rt_welcome_message_' . $user_id);
        
        if ($message_data && is_array($message_data)) {
            $class = isset($message_data['type']) ? 'notice-' . $message_data['type'] : 'notice-info';
            echo '<div class="notice ' . esc_attr($class) . ' is-dismissible">';
            echo '<p>' . esc_html($message_data['message']) . '</p>';
            echo '</div>';
            
            // Delete the transient so it only shows once
            delete_transient('rt_welcome_message_' . $user_id);
        }
        
        // Show first-time setup guidance
        if (isset($_GET['welcome']) && $_GET['welcome'] === '1' && current_user_can('create_employees')) {
            echo '<div class="notice notice-info">';
            echo '<h3>' . __('Erste Schritte', 'rt-employee-manager') . '</h3>';
            echo '<p>' . __('Willkommen in Ihrer Mitarbeiterverwaltung! Hier können Sie:', 'rt-employee-manager') . '</p>';
            echo '<ul style="margin-left: 20px;">';
            echo '<li>' . __('Neue Mitarbeiter zu Ihrem Unternehmen hinzufügen', 'rt-employee-manager') . '</li>';
            echo '<li>' . __('Vorhandene Mitarbeiterdatensätze einsehen und verwalten', 'rt-employee-manager') . '</li>';
            echo '<li>' . __('Aktive und inaktive Mitarbeiter verfolgen', 'rt-employee-manager') . '</li>';
            echo '<li>' . __('Ihre Unternehmensinformationen aktualisieren', 'rt-employee-manager') . '</li>';
            echo '</ul>';
            echo '<p><a href="' . esc_url(admin_url('post-new.php?post_type=angestellte')) . '" class="button button-primary">' . __('Ihren ersten Mitarbeiter hinzufügen', 'rt-employee-manager') . '</a></p>';
            echo '</div>';
        }
    }
    
    /**
     * Hide admin bar for kunden users on frontend
     */
    public function hide_admin_bar_for_kunden($show) {
        if (current_user_can('kunden') && !current_user_can('administrator')) {
            return false;
        }
        return $show;
    }
    
    /**
     * Restrict admin access for kunden users
     */
    public function restrict_admin_access() {
        // Allow AJAX requests
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }
        
        // Check if user is kunden role
        if (current_user_can('kunden') && !current_user_can('administrator')) {
            
            // Allow access to specific employee manager pages
            $allowed_pages = array(
                'rt-employee-manager',
                'rt-employee-manager-dashboard',
                'edit.php',
                'post-new.php',
                'post.php',
                'profile.php',
                'user-edit.php',
                'admin-ajax.php'
            );
            
            $current_page = '';
            if (isset($_GET['page'])) {
                $current_page = $_GET['page'];
            } elseif (isset($GLOBALS['pagenow'])) {
                $current_page = $GLOBALS['pagenow'];
            }
            
            // Check if trying to access employee-related pages
            if ($current_page === 'edit.php' || $current_page === 'post-new.php' || $current_page === 'post.php') {
                $post_type = isset($_GET['post_type']) ? $_GET['post_type'] : 'post';
                
                // For post.php, check the actual post type
                if ($current_page === 'post.php' && isset($_GET['post'])) {
                    $post = get_post($_GET['post']);
                    if ($post) {
                        $post_type = $post->post_type;
                    }
                }
                
                // Only allow access to angestellte post type
                if ($post_type !== 'angestellte') {
                    wp_redirect(admin_url('admin.php?page=rt-employee-manager&error=restricted'));
                    exit;
                }
            }
            
            // Check if current page is in allowed list or starts with rt-employee-manager
            $is_allowed = in_array($current_page, $allowed_pages) || 
                         strpos($current_page, 'rt-employee-manager') === 0;
            
            if (!$is_allowed) {
                // Redirect to dashboard with error message
                wp_redirect(admin_url('admin.php?page=rt-employee-manager&error=access_denied'));
                exit;
            }
        }
    }
    
    /**
     * Get dashboard URL for current user
     */
    public static function get_dashboard_url() {
        if (current_user_can('manage_options')) {
            return admin_url('admin.php?page=rt-employee-manager');
        } elseif (current_user_can('kunden')) {
            return admin_url('admin.php?page=rt-employee-manager');
        }
        
        return home_url();
    }
    
    /**
     * Generate secure login URL for approved clients
     */
    public static function generate_secure_login_url($user_id, $expires_hours = 24) {
        $token = wp_generate_password(32, false);
        $expires = time() + ($expires_hours * HOUR_IN_SECONDS);
        
        // Store token with expiry
        update_user_meta($user_id, 'secure_login_token', array(
            'token' => $token,
            'expires' => $expires,
            'used' => false
        ));
        
        $login_url = add_query_arg(array(
            'rt_secure_login' => $token,
            'user_id' => $user_id
        ), wp_login_url());
        
        return $login_url;
    }
    
    /**
     * Handle secure login tokens
     */
    public function handle_secure_login() {
        if (isset($_GET['rt_secure_login']) && isset($_GET['user_id'])) {
            $token = sanitize_text_field($_GET['rt_secure_login']);
            $user_id = intval($_GET['user_id']);
            
            $stored_token = get_user_meta($user_id, 'secure_login_token', true);
            
            if (is_array($stored_token) && 
                $stored_token['token'] === $token && 
                $stored_token['expires'] > time() && 
                !$stored_token['used']) {
                
                // Mark token as used
                $stored_token['used'] = true;
                update_user_meta($user_id, 'secure_login_token', $stored_token);
                
                // Log the user in
                wp_set_current_user($user_id);
                wp_set_auth_cookie($user_id, true);
                
                // Redirect to dashboard
                wp_redirect(self::get_dashboard_url());
                exit;
            } else {
                // Invalid or expired token
                wp_redirect(wp_login_url() . '?rt_error=invalid_token');
                exit;
            }
        }
    }
    
    /**
     * Show login error messages
     */
    public function show_login_errors() {
        if (isset($_GET['rt_error'])) {
            $error = $_GET['rt_error'];
            $message = '';
            
            switch ($error) {
                case 'invalid_token':
                    $message = __('Ungültiger oder abgelaufener Anmelde-Link. Bitte fordern Sie einen neuen an.', 'rt-employee-manager');
                    break;
                case 'access_denied':
                    $message = __('Zugriff verweigert. Sie haben keine Berechtigung, diese Seite anzuzeigen.', 'rt-employee-manager');
                    break;
                case 'restricted':
                    $message = __('Sie können nur Mitarbeiterdatensätze verwalten. Andere Inhalte sind eingeschränkt.', 'rt-employee-manager');
                    break;
            }
            
            if ($message) {
                echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
            }
        }
    }
}