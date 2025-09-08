<?php

if (!defined('ABSPATH')) {
    exit;
}

class RT_Employee_Manager_Registration_Admin
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_approve_registration', array($this, 'handle_approve_registration'));
        add_action('wp_ajax_reject_registration', array($this, 'handle_reject_registration'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Add admin menu for registration management
     */
    public function add_admin_menu()
    {
        // Only add this menu for administrators
        if (current_user_can('manage_options')) {
            add_submenu_page(
                'rt-employee-manager',
                __('Unternehmensregistrierungen', 'rt-employee-manager'),
                __('Registrierungen', 'rt-employee-manager'),
                'manage_options',
                'rt-employee-manager-registrations',
                array($this, 'admin_page')
            );
        }
    }

    /**
     * Admin page for managing registrations
     */
    public function admin_page()
    {
        // Check user permissions - only administrators can access this page
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'pending';
?>
<div class="wrap">
    <h1><?php _e('Unternehmensregistrierungen', 'rt-employee-manager'); ?></h1>

    <h2 class="nav-tab-wrapper">
        <a href="?page=rt-employee-manager-registrations&tab=pending"
            class="nav-tab <?php echo $tab === 'pending' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Ausstehend', 'rt-employee-manager'); ?>
            <?php
                    $pending_count = $this->get_registrations_count('pending');
                    if ($pending_count > 0) {
                        echo '<span class="awaiting-mod">' . $pending_count . '</span>';
                    }
                    ?>
        </a>
        <a href="?page=rt-employee-manager-registrations&tab=approved"
            class="nav-tab <?php echo $tab === 'approved' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Genehmigt', 'rt-employee-manager'); ?>
        </a>
        <a href="?page=rt-employee-manager-registrations&tab=rejected"
            class="nav-tab <?php echo $tab === 'rejected' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Abgelehnt', 'rt-employee-manager'); ?>
        </a>
    </h2>

    <div id="registration-messages"></div>

    <?php
            switch ($tab) {
                case 'pending':
                    $this->render_pending_registrations();
                    break;
                case 'approved':
                    $this->render_approved_registrations();
                    break;
                case 'rejected':
                    $this->render_rejected_registrations();
                    break;
            }
            ?>
</div>
<?php
    }

    /**
     * Render pending registrations
     */
    private function render_pending_registrations()
    {
        $registrations = $this->get_registrations('pending');
    ?>
<div class="tablenav top">
    <div class="alignleft actions">
        <p><?php _e('Unternehmensregistrierungsanfragen prüfen und genehmigen oder ablehnen.', 'rt-employee-manager'); ?>
        </p>
    </div>
</div>

<?php if (empty($registrations)): ?>
<div class="notice notice-info">
    <p><?php _e('No pending registrations found.', 'rt-employee-manager'); ?></p>
</div>
<?php else: ?>
<table class="wp-list-table widefat fixed striped">
    <thead>
        <tr>
            <th><?php _e('Unternehmen', 'rt-employee-manager'); ?></th>
            <th><?php _e('Kontakt', 'rt-employee-manager'); ?></th>
            <th><?php _e('E-Mail', 'rt-employee-manager'); ?></th>
            <th><?php _e('Eingereicht', 'rt-employee-manager'); ?></th>
            <th><?php _e('Aktionen', 'rt-employee-manager'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($registrations as $registration): ?>
        <tr data-registration-id="<?php echo esc_attr($registration->id); ?>">
            <td>
                <strong><?php echo esc_html($registration->company_name); ?></strong>
                <?php if ($registration->uid_number): ?>
                <br><small>UID: <?php echo esc_html($registration->uid_number); ?></small>
                <?php endif; ?>
                <?php if ($registration->company_city): ?>
                <br><small><?php echo esc_html($registration->company_city); ?>,
                    <?php echo esc_html($registration->company_country); ?></small>
                <?php endif; ?>
            </td>
            <td>
                <?php echo esc_html($registration->contact_first_name . ' ' . $registration->contact_last_name); ?>
                <?php if ($registration->company_phone): ?>
                <br><small><?php echo esc_html($registration->company_phone); ?></small>
                <?php endif; ?>
            </td>
            <td>
                <strong><?php echo esc_html($registration->company_email); ?></strong>
                <?php if ($registration->contact_email !== $registration->company_email): ?>
                <br><small><?php echo esc_html($registration->contact_email); ?></small>
                <?php endif; ?>
            </td>
            <td>
                <?php echo wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($registration->submitted_at)); ?>
                <br><small><?php echo human_time_diff(strtotime($registration->submitted_at)); ?> ago</small>
            </td>
            <td>
                <div class="registration-actions">
                    <button type="button" class="button button-primary approve-registration"
                        data-id="<?php echo esc_attr($registration->id); ?>">
                        <?php _e('Approve', 'rt-employee-manager'); ?>
                    </button>
                    <button type="button" class="button reject-registration"
                        data-id="<?php echo esc_attr($registration->id); ?>">
                        <?php _e('Reject', 'rt-employee-manager'); ?>
                    </button>
                    <button type="button" class="button view-details"
                        data-id="<?php echo esc_attr($registration->id); ?>">
                        <?php _e('Details', 'rt-employee-manager'); ?>
                    </button>
                </div>
            </td>
        </tr>

        <!-- Hidden details row -->
        <tr class="registration-details" id="details-<?php echo esc_attr($registration->id); ?>" style="display: none;">
            <td colspan="5">
                <div class="registration-detail-content">
                    <h4><?php _e('Registration Details', 'rt-employee-manager'); ?></h4>
                    <div class="detail-columns">
                        <div class="detail-column">
                            <h5><?php _e('Unternehmensinformationen', 'rt-employee-manager'); ?></h5>
                            <p><strong><?php _e('Name:', 'rt-employee-manager'); ?></strong>
                                <?php echo esc_html($registration->company_name); ?></p>
                            <?php if ($registration->uid_number): ?>
                            <p><strong><?php _e('UID:', 'rt-employee-manager'); ?></strong>
                                <?php echo esc_html($registration->uid_number); ?></p>
                            <?php endif; ?>
                            <p><strong><?php _e('Email:', 'rt-employee-manager'); ?></strong>
                                <?php echo esc_html($registration->company_email); ?></p>
                            <?php if ($registration->company_phone): ?>
                            <p><strong><?php _e('Phone:', 'rt-employee-manager'); ?></strong>
                                <?php echo esc_html($registration->company_phone); ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="detail-column">
                            <h5><?php _e('Address', 'rt-employee-manager'); ?></h5>
                            <?php if ($registration->company_street): ?>
                            <p><?php echo esc_html($registration->company_street); ?></p>
                            <?php endif; ?>
                            <?php if ($registration->company_postcode || $registration->company_city): ?>
                            <p><?php echo esc_html($registration->company_postcode . ' ' . $registration->company_city); ?>
                            </p>
                            <?php endif; ?>
                            <?php if ($registration->company_country): ?>
                            <p><?php echo esc_html($registration->company_country); ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="detail-column">
                            <h5><?php _e('Contact Person', 'rt-employee-manager'); ?></h5>
                            <p><strong><?php _e('Name:', 'rt-employee-manager'); ?></strong>
                                <?php echo esc_html($registration->contact_first_name . ' ' . $registration->contact_last_name); ?>
                            </p>
                            <p><strong><?php _e('Email:', 'rt-employee-manager'); ?></strong>
                                <?php echo esc_html($registration->contact_email); ?></p>
                        </div>

                        <div class="detail-column">
                            <h5><?php _e('Technical Details', 'rt-employee-manager'); ?></h5>
                            <p><strong><?php _e('IP Address:', 'rt-employee-manager'); ?></strong>
                                <?php echo esc_html($registration->ip_address); ?></p>
                            <p><strong><?php _e('Submitted:', 'rt-employee-manager'); ?></strong>
                                <?php echo wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($registration->submitted_at)); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<!-- Rejection Modal -->
<div id="rejection-modal" style="display: none;">
    <div class="rejection-modal-content">
        <h3><?php _e('Reject Registration', 'rt-employee-manager'); ?></h3>
        <p><?php _e('Please provide a reason for rejecting this registration:', 'rt-employee-manager'); ?></p>
        <textarea id="rejection-reason" rows="4" cols="50"
            placeholder="<?php esc_attr_e('Reason for rejection...', 'rt-employee-manager'); ?>"></textarea>
        <div class="modal-actions">
            <button type="button" id="confirm-rejection"
                class="button button-primary"><?php _e('Reject', 'rt-employee-manager'); ?></button>
            <button type="button" id="cancel-rejection"
                class="button"><?php _e('Cancel', 'rt-employee-manager'); ?></button>
        </div>
    </div>
</div>

<style>
.registration-actions {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.registration-actions .button {
    margin-bottom: 5px;
}

.registration-detail-content {
    background: #f9f9f9;
    padding: 15px;
    border-radius: 5px;
    margin: 10px 0;
}

.detail-columns {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 15px;
}

.detail-column h5 {
    margin-bottom: 10px;
    color: #0073aa;
    border-bottom: 1px solid #ddd;
    padding-bottom: 5px;
}

.detail-column p {
    margin: 5px 0;
}

#rejection-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.rejection-modal-content {
    background: white;
    padding: 20px;
    border-radius: 5px;
    max-width: 500px;
    width: 90%;
}

.modal-actions {
    margin-top: 15px;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

#rejection-reason {
    width: 100%;
    margin: 10px 0;
}

.awaiting-mod {
    background: #d54e21;
    color: white;
    border-radius: 10px;
    padding: 2px 6px;
    font-size: 11px;
    margin-left: 5px;
}
</style>
<?php
    }

    /**
     * Render approved registrations
     */
    private function render_approved_registrations()
    {
        $registrations = $this->get_registrations('approved');
    ?>
<p><?php _e('Unternehmen, die genehmigt wurden und aktive Konten haben.', 'rt-employee-manager'); ?></p>

<?php if (empty($registrations)): ?>
<div class="notice notice-info">
    <p><?php _e('No approved registrations found.', 'rt-employee-manager'); ?></p>
</div>
<?php else: ?>
<table class="wp-list-table widefat fixed striped">
    <thead>
        <tr>
            <th><?php _e('Unternehmen', 'rt-employee-manager'); ?></th>
            <th><?php _e('Kontakt', 'rt-employee-manager'); ?></th>
            <th><?php _e('E-Mail', 'rt-employee-manager'); ?></th>
            <th><?php _e('Approved', 'rt-employee-manager'); ?></th>
            <th><?php _e('Status', 'rt-employee-manager'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($registrations as $registration): ?>
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
                <?php echo wp_date(get_option('date_format'), strtotime($registration->approved_at)); ?>
                <br><small><?php echo human_time_diff(strtotime($registration->approved_at)); ?> ago</small>
            </td>
            <td>
                <span class="status-approved"><?php _e('Active', 'rt-employee-manager'); ?></span>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<style>
.status-approved {
    color: #00a32a;
    font-weight: bold;
}
</style>
<?php
    }

    /**
     * Render rejected registrations
     */
    private function render_rejected_registrations()
    {
        $registrations = $this->get_registrations('rejected');
    ?>
<p><?php _e('Unternehmen, die abgelehnt wurden. ', 'rt-employee-manager'); ?></p>

<?php if (empty($registrations)): ?>
<div class="notice notice-info">
    <p><?php _e('No rejected registrations found.', 'rt-employee-manager'); ?></p>
</div>
<?php else: ?>
<table class="wp-list-table widefat fixed striped">
    <thead>
        <tr>
            <th><?php _e('Unternehmen', 'rt-employee-manager'); ?></th>
            <th><?php _e('Kontakt', 'rt-employee-manager'); ?></th>
            <th><?php _e('E-Mail', 'rt-employee-manager'); ?></th>
            <th><?php _e('Rejected', 'rt-employee-manager'); ?></th>
            <th><?php _e('Reason', 'rt-employee-manager'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($registrations as $registration): ?>
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
                <?php echo wp_date(get_option('date_format'), strtotime($registration->approved_at)); ?>
                <br><small><?php echo human_time_diff(strtotime($registration->approved_at)); ?> ago</small>
            </td>
            <td>
                <?php echo $registration->rejection_reason ? esc_html($registration->rejection_reason) : '<em>' . __('No reason provided', 'rt-employee-manager') . '</em>'; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
<?php
    }

    /**
     * Get registrations by status
     */
    private function get_registrations($status = 'pending')
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'rt_pending_registrations';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE status = %s ORDER BY submitted_at DESC",
            $status
        ));
    }

    /**
     * Get registrations count by status
     */
    private function get_registrations_count($status = 'pending')
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'rt_pending_registrations';

        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE status = %s",
            $status
        ));
    }

    /**
     * Handle approval request
     */
    public function handle_approve_registration()
    {
        check_ajax_referer('rt_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'rt-employee-manager')));
        }

        $registration_id = intval($_POST['registration_id']);

        if (!$registration_id) {
            wp_send_json_error(array('message' => __('Invalid registration ID.', 'rt-employee-manager')));
        }

        $registration = $this->get_registration_by_id($registration_id);

        if (!$registration) {
            wp_send_json_error(array('message' => __('Registration not found.', 'rt-employee-manager')));
        }

        if ($registration->status !== 'pending') {
            wp_send_json_error(array('message' => __('Registration has already been processed.', 'rt-employee-manager')));
        }

        try {
            // Check if user was already created by the form AND it's not an admin user
            if (!empty($registration->created_user_id)) {
                $user_id = $registration->created_user_id;
                $user = get_user_by('ID', $user_id);

                // Only use existing user if it's not an administrator (avoid using admin account)
                if ($user && !in_array('administrator', $user->roles) && $user->user_email === $registration->company_email) {
                    // User already exists and is not admin, just approve and create kunde post

                    // Ensure user has correct role
                    if (!in_array('kunden', $user->roles)) {
                        $user->set_role('kunden');
                    }

                    // Create kunde post if it doesn't exist
                    $kunde_post_id = get_user_meta($user_id, 'kunde_post_id', true);
                    if (!$kunde_post_id) {
                        $kunde_post_id = $this->create_kunde_post_for_existing_user($user_id, $registration);
                    }

                    // Update user meta with company information
                    $this->update_user_meta_from_registration($user_id, $registration);

                    $result = array(
                        'user_id' => $user_id,
                        'post_id' => $kunde_post_id,
                        'password' => null // User already has password
                    );
                } else {
                    // Admin user or email mismatch - create new user instead
                    $result = $this->create_approved_client($registration);

                    if (is_wp_error($result)) {
                        wp_send_json_error(array('message' => $result->get_error_message()));
                    }
                }
            } else {
                // Create new WordPress user and kunde post
                $result = $this->create_approved_client($registration);

                if (is_wp_error($result)) {
                    wp_send_json_error(array('message' => $result->get_error_message()));
                }
            }

            // Update registration status
            $this->update_registration_status($registration_id, 'approved');

            // Send approval email
            $this->send_approval_email($registration, $result);

            wp_send_json_success(array(
                'message' => __('Registration approved successfully. Approval email sent.', 'rt-employee-manager')
            ));
        } catch (Exception $e) {
            error_log('Registration approval error: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => __('An error occurred while processing the approval.', 'rt-employee-manager')
            ));
        }
    }

    /**
     * Handle rejection request
     */
    public function handle_reject_registration()
    {
        check_ajax_referer('rt_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'rt-employee-manager')));
        }

        $registration_id = intval($_POST['registration_id']);
        $reason = sanitize_textarea_field($_POST['reason']);

        if (!$registration_id) {
            wp_send_json_error(array('message' => __('Invalid registration ID.', 'rt-employee-manager')));
        }

        $registration = $this->get_registration_by_id($registration_id);

        if (!$registration) {
            wp_send_json_error(array('message' => __('Registration not found.', 'rt-employee-manager')));
        }

        if ($registration->status !== 'pending') {
            wp_send_json_error(array('message' => __('Registration has already been processed.', 'rt-employee-manager')));
        }

        // Update registration status
        $this->update_registration_status($registration_id, 'rejected', $reason);

        // Send rejection email
        $this->send_rejection_email($registration, $reason);

        wp_send_json_success(array(
            'message' => __('Registration rejected and notification email sent.', 'rt-employee-manager')
        ));
    }

    /**
     * Get registration by ID
     */
    private function get_registration_by_id($id)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'rt_pending_registrations';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $id
        ));
    }

    /**
     * Update registration status
     */
    private function update_registration_status($id, $status, $reason = '')
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'rt_pending_registrations';

        $update_data = array(
            'status' => $status,
            'approved_at' => current_time('mysql'),
            'approved_by' => get_current_user_id()
        );

        if ($reason) {
            $update_data['rejection_reason'] = $reason;
        }

        return $wpdb->update(
            $table_name,
            $update_data,
            array('id' => $id)
        );
    }

    /**
     * Create approved client (user + kunde post)
     */
    private function create_approved_client($registration)
    {
        // Generate secure password
        $password = wp_generate_password(12, true, true);

        // Check if user already exists
        if (email_exists($registration->company_email)) {
            return new WP_Error('user_exists', __('A user with this email already exists.', 'rt-employee-manager'));
        }

        // Create WordPress user
        $user_data = array(
            'user_login' => sanitize_user($registration->company_email),
            'user_email' => $registration->company_email,
            'user_pass' => $password,
            'first_name' => $registration->contact_first_name,
            'last_name' => $registration->contact_last_name,
            'display_name' => $registration->company_name,
            'role' => 'kunden'
        );

        $user_id = wp_insert_user($user_data);

        if (is_wp_error($user_id)) {
            return $user_id;
        }

        // Add user meta
        $user_meta = array(
            'company_name' => $registration->company_name,
            'uid_number' => $registration->uid_number,
            'phone' => $registration->company_phone,
            'address_street' => $registration->company_street,
            'address_postcode' => $registration->company_postcode,
            'address_city' => $registration->company_city,
            'address_country' => $registration->company_country
        );

        foreach ($user_meta as $key => $value) {
            if (!empty($value)) {
                update_user_meta($user_id, $key, $value);
            }
        }

        // Create kunde post
        $post_data = array(
            'post_title' => $registration->company_name,
            'post_type' => 'kunde',
            'post_status' => 'publish',
            'post_author' => $user_id,
            'meta_input' => array(
                'company_name' => $registration->company_name,
                'uid_number' => $registration->uid_number,
                'phone' => $registration->company_phone,
                'email' => $registration->company_email,
                'registration_date' => current_time('d.m.Y H:i'),
                'user_id' => $user_id,
                'approved_from_registration' => $registration->id
            )
        );

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            // Clean up user if post creation failed
            wp_delete_user($user_id);
            return $post_id;
        }

        // Link user to kunde post
        update_user_meta($user_id, 'kunde_post_id', $post_id);

        return array(
            'user_id' => $user_id,
            'post_id' => $post_id,
            'password' => $password
        );
    }

    /**
     * Create kunde post for existing user
     */
    private function create_kunde_post_for_existing_user($user_id, $registration)
    {
        $post_data = array(
            'post_title' => $registration->company_name,
            'post_type' => 'kunde',
            'post_status' => 'publish',
            'post_author' => $user_id,
            'meta_input' => array(
                'company_name' => $registration->company_name,
                'uid_number' => $registration->uid_number,
                'phone' => $registration->company_phone,
                'email' => $registration->company_email,
                'registration_date' => current_time('d.m.Y H:i'),
                'user_id' => $user_id,
                'approved_from_registration' => $registration->id
            )
        );

        $post_id = wp_insert_post($post_data);

        if (!is_wp_error($post_id)) {
            // Link user to kunde post
            update_user_meta($user_id, 'kunde_post_id', $post_id);
            return $post_id;
        }

        return false;
    }

    /**
     * Update user meta from registration data
     */
    private function update_user_meta_from_registration($user_id, $registration)
    {
        $meta_updates = array(
            'company_name' => $registration->company_name,
            'uid_number' => $registration->uid_number,
            'phone' => $registration->company_phone,
            'address_street' => $registration->company_street,
            'address_postcode' => $registration->company_postcode,
            'address_city' => $registration->company_city,
            'address_country' => $registration->company_country
        );

        foreach ($meta_updates as $meta_key => $meta_value) {
            if (!empty($meta_value)) {
                update_user_meta($user_id, $meta_key, $meta_value);
            }
        }

        // Update display name to company name
        wp_update_user(array(
            'ID' => $user_id,
            'display_name' => $registration->company_name
        ));
    }

    /**
     * Send approval email
     */
    private function send_approval_email($registration, $result)
    {
        // Generate secure login URL that bypasses password requirement
        $secure_login_url = RT_Employee_Manager_Login_Redirect::generate_secure_login_url($result['user_id'], 72); // 72 hours
        $regular_login_url = wp_login_url();

        $subject = sprintf(__('[%s] Konto genehmigt - Willkommen!', 'rt-employee-manager'), get_bloginfo('name'));

        if (!empty($result['password'])) {
            // New account with generated password
            $message = sprintf(
                __('
Hallo %s,

Großartige Neuigkeiten! Ihre Unternehmensregistrierung für %s wurde genehmigt!

Sie können sofort auf Ihr Mitarbeiterverwaltungs-Dashboard zugreifen, indem Sie diesen sicheren Link verwenden:
%s

Dieser sichere Link ist 72 Stunden gültig. Danach können Sie sich normal anmelden mit:
- E-Mail: %s
- Passwort: %s
- Anmelde-URL: %s

Was Sie als nächstes tun können:
✓ Ihre Mitarbeiter zum System hinzufügen
✓ Mitarbeiterdatensätze und Status verwalten
✓ Aktive und inaktive Mitarbeiter verfolgen
✓ Ihre Unternehmensinformationen aktualisieren

Benötigen Sie Hilfe beim Einstieg? Antworten Sie auf diese E-Mail und wir helfen Ihnen gerne.

Willkommen bei %s!

---
Diese E-Mail wurde automatisch generiert.
', 'rt-employee-manager'),
                $registration->contact_first_name,
                $registration->company_name,
                $secure_login_url,
                $registration->company_email,
                $result['password'],
                $regular_login_url,
                get_bloginfo('name')
            );
        } else {
            // Existing account, no password needed
            $message = sprintf(
                __('
Hallo %s,

Großartige Neuigkeiten! Ihre Unternehmensregistrierung für %s wurde genehmigt!

Sie können sofort auf Ihr Mitarbeiterverwaltungs-Dashboard zugreifen, indem Sie diesen sicheren Link verwenden:
%s

Dieser sichere Link ist 72 Stunden gültig. Danach können Sie sich normal mit Ihren bestehenden Anmeldedaten anmelden unter:
%s

Was Sie als nächstes tun können:
✓ Ihre Mitarbeiter zum System hinzufügen
✓ Mitarbeiterdatensätze und Status verwalten
✓ Aktive und inaktive Mitarbeiter verfolgen
✓ Ihre Unternehmensinformationen aktualisieren

Benötigen Sie Hilfe beim Einstieg? Antworten Sie auf diese E-Mail und wir helfen Ihnen gerne.

Willkommen bei %s!

---
Diese E-Mail wurde automatisch generiert.
', 'rt-employee-manager'),
                $registration->contact_first_name,
                $registration->company_name,
                $secure_login_url,
                $regular_login_url,
                get_bloginfo('name')
            );
        }

        return wp_mail($registration->company_email, $subject, $message);
    }

    /**
     * Send rejection email
     */
    private function send_rejection_email($registration, $reason)
    {
        $subject = sprintf(__('[%s] Registration Update', 'rt-employee-manager'), get_bloginfo('name'));

        $message = sprintf(
            __('
Hello %s,

Thank you for your interest in registering %s with our employee management system.

Unfortunately, we are unable to approve your registration at this time.

%s

If you have any questions or would like to discuss this further, please feel free to contact us.

Best regards,
The %s Team

---
This email was generated automatically.
', 'rt-employee-manager'),
            $registration->contact_first_name,
            $registration->company_name,
            $reason ? "Reason: " . $reason : '',
            get_bloginfo('name')
        );

        return wp_mail($registration->company_email, $subject, $message);
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook)
    {
        // Check if we're on the registrations page
        if (strpos($hook, 'rt-employee-manager-registrations') === false) {
            return;
        }

        wp_enqueue_script('jquery');
        wp_localize_script('jquery', 'rtAdminAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rt_admin_nonce')
        ));

        wp_add_inline_script('jquery', $this->get_admin_js());
    }

    /**
     * Get admin JavaScript
     */
    private function get_admin_js()
    {
        return "
        jQuery(document).ready(function($) {
            let currentRegistrationId = null;
            
            // Approve registration
            $('.approve-registration').on('click', function() {
                if (!confirm('" . esc_js(__('Are you sure you want to approve this registration? This will create a user account and send login credentials.', 'rt-employee-manager')) . "')) {
                    return;
                }
                
                const registrationId = $(this).data('id');
                const button = $(this);
                
                button.prop('disabled', true).text('" . esc_js(__('Processing...', 'rt-employee-manager')) . "');
                
                $.ajax({
                    url: rtAdminAjax.ajax_url,
                    method: 'POST',
                    data: {
                        action: 'approve_registration',
                        registration_id: registrationId,
                        nonce: rtAdminAjax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#registration-messages').html('<div class=\"notice notice-success is-dismissible\"><p>' + response.data.message + '</p></div>');
                            $('tr[data-registration-id=\"' + registrationId + '\"]').fadeOut();
                        } else {
                            $('#registration-messages').html('<div class=\"notice notice-error is-dismissible\"><p>' + response.data.message + '</p></div>');
                            button.prop('disabled', false).text('" . esc_js(__('Approve', 'rt-employee-manager')) . "');
                        }
                    },
                    error: function() {
                        $('#registration-messages').html('<div class=\"notice notice-error is-dismissible\"><p>" . esc_js(__('An error occurred. Please try again.', 'rt-employee-manager')) . "</p></div>');
                        button.prop('disabled', false).text('" . esc_js(__('Approve', 'rt-employee-manager')) . "');
                    }
                });
            });
            
            // Reject registration
            $('.reject-registration').on('click', function() {
                currentRegistrationId = $(this).data('id');
                $('#rejection-modal').show();
                $('#rejection-reason').focus();
            });
            
            // Confirm rejection
            $('#confirm-rejection').on('click', function() {
                const reason = $('#rejection-reason').val().trim();
                
                if (!reason) {
                    alert('" . esc_js(__('Please provide a reason for rejection.', 'rt-employee-manager')) . "');
                    return;
                }
                
                $(this).prop('disabled', true).text('" . esc_js(__('Processing...', 'rt-employee-manager')) . "');
                
                $.ajax({
                    url: rtAdminAjax.ajax_url,
                    method: 'POST',
                    data: {
                        action: 'reject_registration',
                        registration_id: currentRegistrationId,
                        reason: reason,
                        nonce: rtAdminAjax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#registration-messages').html('<div class=\"notice notice-success is-dismissible\"><p>' + response.data.message + '</p></div>');
                            $('tr[data-registration-id=\"' + currentRegistrationId + '\"]').fadeOut();
                            $('#rejection-modal').hide();
                            $('#rejection-reason').val('');
                        } else {
                            $('#registration-messages').html('<div class=\"notice notice-error is-dismissible\"><p>' + response.data.message + '</p></div>');
                        }
                        $('#confirm-rejection').prop('disabled', false).text('" . esc_js(__('Reject', 'rt-employee-manager')) . "');
                    },
                    error: function() {
                        $('#registration-messages').html('<div class=\"notice notice-error is-dismissible\"><p>" . esc_js(__('An error occurred. Please try again.', 'rt-employee-manager')) . "</p></div>');
                        $('#confirm-rejection').prop('disabled', false).text('" . esc_js(__('Reject', 'rt-employee-manager')) . "');
                    }
                });
            });
            
            // Cancel rejection
            $('#cancel-rejection').on('click', function() {
                $('#rejection-modal').hide();
                $('#rejection-reason').val('');
                currentRegistrationId = null;
            });
            
            // Close modal on escape key
            $(document).on('keyup', function(e) {
                if (e.key === 'Escape') {
                    $('#rejection-modal').hide();
                    $('#rejection-reason').val('');
                    currentRegistrationId = null;
                }
            });
            
            // View details toggle
            $('.view-details').on('click', function() {
                const registrationId = $(this).data('id');
                const detailsRow = $('#details-' + registrationId);
                
                if (detailsRow.is(':visible')) {
                    detailsRow.hide();
                    $(this).text('" . esc_js(__('Details', 'rt-employee-manager')) . "');
                } else {
                    detailsRow.show();
                    $(this).text('" . esc_js(__('Hide', 'rt-employee-manager')) . "');
                }
            });
        });
        ";
    }
}