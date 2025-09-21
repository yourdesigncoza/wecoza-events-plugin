<?php
/**
 * Supervisor controller for WECOZA Notifications
 */

namespace WecozaNotifications;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include security service
require_once WECOZA_NOTIFICATIONS_PLUGIN_DIR . 'app/Services/SecurityService.php';

/**
 * Supervisor controller class
 */
class SupervisorController
{
    /**
     * Supervisor model instance
     */
    private $supervisor_model;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->supervisor_model = new SupervisorModel();
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        add_action('admin_post_wecoza_create_supervisor', array($this, 'handle_create_supervisor'));
        add_action('admin_post_wecoza_update_supervisor', array($this, 'handle_update_supervisor'));
        add_action('admin_post_wecoza_delete_supervisor', array($this, 'handle_delete_supervisor'));
        add_action('admin_post_wecoza_set_default_supervisor', array($this, 'handle_set_default'));

        // AJAX hooks
        add_action('wp_ajax_wecoza_get_supervisor', array($this, 'ajax_get_supervisor'));
        add_action('wp_ajax_wecoza_supervisor_stats', array($this, 'ajax_get_stats'));
    }

    /**
     * Admin page
     */
    public function admin_page()
    {
        // Check capabilities
        if (!SecurityService::current_user_can(SecurityService::CAP_MANAGE_SUPERVISORS)) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $action = SecurityService::sanitize_text($_GET['action'] ?? 'list');
        $supervisor_id = SecurityService::sanitize_int($_GET['supervisor_id'] ?? 0);

        // Validate action against whitelist
        $allowed_actions = array('list', 'new', 'edit', 'view');
        if (!in_array($action, $allowed_actions)) {
            $action = 'list';
        }

        switch ($action) {
            case 'new':
                $this->render_new_supervisor_form();
                break;
            case 'edit':
                $this->render_edit_supervisor_form($supervisor_id);
                break;
            case 'view':
                $this->render_supervisor_details($supervisor_id);
                break;
            default:
                $this->render_supervisors_list();
                break;
        }
    }

    /**
     * Alias for admin_page - for backward compatibility
     */
    public function render_page()
    {
        $this->admin_page();
    }

    /**
     * Render supervisors list
     */
    private function render_supervisors_list()
    {
        $supervisors = $this->supervisor_model->get_all();
        $stats = $this->supervisor_model->get_statistics();

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Supervisors</h1>
            <a href="<?php echo admin_url('admin.php?page=wecoza-supervisors&action=new'); ?>" class="page-title-action">Add New</a>
            <hr class="wp-header-end">

            <!-- Statistics Cards -->
            <div class="wecoza-stats-cards" style="display: flex; gap: 20px; margin: 20px 0;">
                <div class="wecoza-stat-card" style="background: white; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; flex: 1;">
                    <h3 style="margin: 0 0 10px 0; color: #23282d;">Total Supervisors</h3>
                    <span style="font-size: 32px; font-weight: bold; color: #3874ff;"><?php echo SecurityService::escape_html($stats['total']); ?></span>
                </div>
                <div class="wecoza-stat-card" style="background: white; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; flex: 1;">
                    <h3 style="margin: 0 0 10px 0; color: #23282d;">Active Supervisors</h3>
                    <span style="font-size: 32px; font-weight: bold; color: #25b003;"><?php echo SecurityService::escape_html($stats['active']); ?></span>
                </div>
                <div class="wecoza-stat-card" style="background: white; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; flex: 1;">
                    <h3 style="margin: 0 0 10px 0; color: #23282d;">With Assignments</h3>
                    <span style="font-size: 32px; font-weight: bold; color: #e5780b;"><?php echo SecurityService::escape_html($stats['with_client_assignments'] + $stats['with_site_assignments']); ?></span>
                </div>
            </div>

            <!-- Supervisors Table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" class="manage-column">Name</th>
                        <th scope="col" class="manage-column">Email</th>
                        <th scope="col" class="manage-column">Role</th>
                        <th scope="col" class="manage-column">Status</th>
                        <th scope="col" class="manage-column">Default</th>
                        <th scope="col" class="manage-column">Assignments</th>
                        <th scope="col" class="manage-column">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($supervisors)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px;">
                                <p>No supervisors found. <a href="<?php echo SecurityService::escape_url(admin_url('admin.php?page=wecoza-supervisors&action=new')); ?>">Add your first supervisor</a>.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($supervisors as $supervisor): ?>
                            <tr>
                                <td><strong><?php echo esc_html($supervisor->name); ?></strong></td>
                                <td><?php echo esc_html($supervisor->email); ?></td>
                                <td><?php echo esc_html(ucfirst($supervisor->role)); ?></td>
                                <td>
                                    <?php if ($supervisor->is_active): ?>
                                        <span class="wecoza-status-active" style="color: #25b003; font-weight: bold;">● Active</span>
                                    <?php else: ?>
                                        <span class="wecoza-status-inactive" style="color: #fa3b1d; font-weight: bold;">● Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($supervisor->is_default): ?>
                                        <span class="wecoza-default-badge" style="background: #3874ff; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px;">DEFAULT</span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $client_assignments = json_decode($supervisor->client_assignments ?? '[]', true);
                                    $site_assignments = json_decode($supervisor->site_assignments ?? '[]', true);
                                    $total_assignments = count($client_assignments) + count($site_assignments);
                                    echo $total_assignments > 0 ? $total_assignments . ' assignment(s)' : 'None';
                                    ?>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=wecoza-supervisors&action=view&supervisor_id=' . $supervisor->id); ?>" class="button button-small">View</a>
                                    <a href="<?php echo admin_url('admin.php?page=wecoza-supervisors&action=edit&supervisor_id=' . $supervisor->id); ?>" class="button button-small">Edit</a>
                                    <?php if (!$supervisor->is_default): ?>
                                        <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=wecoza_delete_supervisor&supervisor_id=' . $supervisor->id), 'delete_supervisor_' . $supervisor->id); ?>"
                                           class="button button-small button-link-delete"
                                           onclick="return confirm('Are you sure you want to delete this supervisor?');">Delete</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render new supervisor form
     */
    private function render_new_supervisor_form()
    {
        ?>
        <div class="wrap">
            <h1>Add New Supervisor</h1>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <?php wp_nonce_field('create_supervisor', 'wecoza_supervisor_nonce'); ?>
                <input type="hidden" name="action" value="wecoza_create_supervisor">

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="name">Name *</label></th>
                        <td><input type="text" id="name" name="name" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="email">Email *</label></th>
                        <td><input type="email" id="email" name="email" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="role">Role</label></th>
                        <td>
                            <select id="role" name="role">
                                <option value="supervisor">Supervisor</option>
                                <option value="manager">Manager</option>
                                <option value="admin">Admin</option>
                                <option value="coordinator">Coordinator</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Settings</th>
                        <td>
                            <label>
                                <input type="checkbox" name="is_default" value="1">
                                Set as default supervisor
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="is_active" value="1" checked>
                                Active
                            </label>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Add Supervisor'); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render edit supervisor form
     */
    private function render_edit_supervisor_form($supervisor_id)
    {
        $supervisor = $this->supervisor_model->get($supervisor_id);

        if (!$supervisor) {
            wp_die('Supervisor not found.');
        }

        ?>
        <div class="wrap">
            <h1>Edit Supervisor</h1>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <?php wp_nonce_field('update_supervisor_' . $supervisor->id, 'wecoza_supervisor_nonce'); ?>
                <input type="hidden" name="action" value="wecoza_update_supervisor">
                <input type="hidden" name="supervisor_id" value="<?php echo $supervisor->id; ?>">

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="name">Name *</label></th>
                        <td><input type="text" id="name" name="name" class="regular-text" value="<?php echo esc_attr($supervisor->name); ?>" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="email">Email *</label></th>
                        <td><input type="email" id="email" name="email" class="regular-text" value="<?php echo esc_attr($supervisor->email); ?>" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="role">Role</label></th>
                        <td>
                            <select id="role" name="role">
                                <option value="supervisor" <?php selected($supervisor->role, 'supervisor'); ?>>Supervisor</option>
                                <option value="manager" <?php selected($supervisor->role, 'manager'); ?>>Manager</option>
                                <option value="admin" <?php selected($supervisor->role, 'admin'); ?>>Admin</option>
                                <option value="coordinator" <?php selected($supervisor->role, 'coordinator'); ?>>Coordinator</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Settings</th>
                        <td>
                            <label>
                                <input type="checkbox" name="is_default" value="1" <?php checked($supervisor->is_default); ?>>
                                Set as default supervisor
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="is_active" value="1" <?php checked($supervisor->is_active); ?>>
                                Active
                            </label>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Update Supervisor'); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render supervisor details
     */
    private function render_supervisor_details($supervisor_id)
    {
        $supervisor = $this->supervisor_model->get($supervisor_id);

        if (!$supervisor) {
            wp_die('Supervisor not found.');
        }

        $client_assignments = json_decode($supervisor->client_assignments ?? '[]', true);
        $site_assignments = json_decode($supervisor->site_assignments ?? '[]', true);

        ?>
        <div class="wrap">
            <h1>Supervisor Details</h1>
            <a href="<?php echo admin_url('admin.php?page=wecoza-supervisors&action=edit&supervisor_id=' . $supervisor->id); ?>" class="page-title-action">Edit</a>

            <table class="form-table">
                <tr>
                    <th scope="row">Name</th>
                    <td><?php echo esc_html($supervisor->name); ?></td>
                </tr>
                <tr>
                    <th scope="row">Email</th>
                    <td><?php echo esc_html($supervisor->email); ?></td>
                </tr>
                <tr>
                    <th scope="row">Role</th>
                    <td><?php echo esc_html(ucfirst($supervisor->role)); ?></td>
                </tr>
                <tr>
                    <th scope="row">Status</th>
                    <td>
                        <?php if ($supervisor->is_active): ?>
                            <span style="color: #25b003; font-weight: bold;">● Active</span>
                        <?php else: ?>
                            <span style="color: #fa3b1d; font-weight: bold;">● Inactive</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Default Supervisor</th>
                    <td><?php echo $supervisor->is_default ? 'Yes' : 'No'; ?></td>
                </tr>
                <tr>
                    <th scope="row">Client Assignments</th>
                    <td>
                        <?php if (!empty($client_assignments)): ?>
                            <ul>
                                <?php foreach ($client_assignments as $client_id): ?>
                                    <li>Client ID: <?php echo $client_id; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            None
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Site Assignments</th>
                    <td>
                        <?php if (!empty($site_assignments)): ?>
                            <ul>
                                <?php foreach ($site_assignments as $site_id): ?>
                                    <li>Site ID: <?php echo $site_id; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            None
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Created</th>
                    <td><?php echo date('Y-m-d H:i:s', strtotime($supervisor->created_at)); ?></td>
                </tr>
                <tr>
                    <th scope="row">Last Updated</th>
                    <td><?php echo date('Y-m-d H:i:s', strtotime($supervisor->updated_at)); ?></td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Handle create supervisor
     */
    public function handle_create_supervisor()
    {
        if (!wp_verify_nonce($_POST['wecoza_supervisor_nonce'], 'create_supervisor')) {
            wp_die('Security check failed.');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions.');
        }

        $data = array(
            'name' => sanitize_text_field($_POST['name']),
            'email' => sanitize_email($_POST['email']),
            'role' => sanitize_text_field($_POST['role']),
            'is_default' => isset($_POST['is_default']),
            'is_active' => isset($_POST['is_active'])
        );

        $result = $this->supervisor_model->create($data);

        if ($result['success']) {
            wp_redirect(admin_url('admin.php?page=wecoza-supervisors&message=created'));
        } else {
            wp_redirect(admin_url('admin.php?page=wecoza-supervisors&action=new&error=' . urlencode(implode(', ', $result['errors'] ?? array($result['error'])))));
        }
        exit;
    }

    /**
     * Handle update supervisor
     */
    public function handle_update_supervisor()
    {
        $supervisor_id = intval($_POST['supervisor_id']);

        if (!wp_verify_nonce($_POST['wecoza_supervisor_nonce'], 'update_supervisor_' . $supervisor_id)) {
            wp_die('Security check failed.');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions.');
        }

        $data = array(
            'name' => sanitize_text_field($_POST['name']),
            'email' => sanitize_email($_POST['email']),
            'role' => sanitize_text_field($_POST['role']),
            'is_default' => isset($_POST['is_default']),
            'is_active' => isset($_POST['is_active'])
        );

        $result = $this->supervisor_model->update($supervisor_id, $data);

        if ($result['success']) {
            wp_redirect(admin_url('admin.php?page=wecoza-supervisors&message=updated'));
        } else {
            wp_redirect(admin_url('admin.php?page=wecoza-supervisors&action=edit&supervisor_id=' . $supervisor_id . '&error=' . urlencode($result['error'])));
        }
        exit;
    }

    /**
     * Handle delete supervisor
     */
    public function handle_delete_supervisor()
    {
        $supervisor_id = intval($_GET['supervisor_id']);

        if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_supervisor_' . $supervisor_id)) {
            wp_die('Security check failed.');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions.');
        }

        $result = $this->supervisor_model->delete($supervisor_id);

        if ($result['success']) {
            wp_redirect(admin_url('admin.php?page=wecoza-supervisors&message=deleted'));
        } else {
            wp_redirect(admin_url('admin.php?page=wecoza-supervisors&error=' . urlencode($result['error'])));
        }
        exit;
    }

    /**
     * Handle set default supervisor
     */
    public function handle_set_default()
    {
        $supervisor_id = intval($_GET['supervisor_id']);

        if (!wp_verify_nonce($_GET['_wpnonce'], 'set_default_' . $supervisor_id)) {
            wp_die('Security check failed.');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions.');
        }

        $result = $this->supervisor_model->set_as_default($supervisor_id);

        if ($result['success']) {
            wp_redirect(admin_url('admin.php?page=wecoza-supervisors&message=default_set'));
        } else {
            wp_redirect(admin_url('admin.php?page=wecoza-supervisors&error=' . urlencode($result['error'])));
        }
        exit;
    }

    /**
     * AJAX: Get supervisor data
     */
    public function ajax_get_supervisor()
    {
        // Check capabilities
        if (!SecurityService::current_user_can(SecurityService::CAP_MANAGE_SUPERVISORS)) {
            wp_send_json_error('Insufficient permissions', 403);
        }

        // Verify nonce
        if (!SecurityService::verify_nonce($_POST['nonce'] ?? '', 'wecoza_supervisor_ajax')) {
            wp_send_json_error('Security check failed', 403);
        }

        // Rate limiting
        if (!SecurityService::check_rate_limit('supervisor_ajax', 30, 3600)) {
            wp_send_json_error('Rate limit exceeded', 429);
        }

        $supervisor_id = SecurityService::sanitize_int($_POST['supervisor_id'] ?? 0);

        if ($supervisor_id <= 0) {
            wp_send_json_error('Invalid supervisor ID');
        }

        $supervisor = $this->supervisor_model->get($supervisor_id);

        if ($supervisor) {
            // Sanitize output data
            $safe_supervisor = array(
                'id' => SecurityService::sanitize_int($supervisor->id),
                'name' => SecurityService::escape_html($supervisor->name),
                'email' => SecurityService::escape_html($supervisor->email),
                'role' => SecurityService::escape_html($supervisor->role),
                'is_active' => SecurityService::sanitize_int($supervisor->is_active),
                'is_default' => SecurityService::sanitize_int($supervisor->is_default)
            );
            wp_send_json_success($safe_supervisor);
        } else {
            wp_send_json_error('Supervisor not found');
        }
    }

    /**
     * AJAX: Get supervisor statistics
     */
    public function ajax_get_stats()
    {
        // Check capabilities
        if (!SecurityService::current_user_can(SecurityService::CAP_VIEW_REPORTS)) {
            wp_send_json_error('Insufficient permissions', 403);
        }

        // Verify nonce
        if (!SecurityService::verify_nonce($_POST['nonce'] ?? '', 'wecoza_supervisor_ajax')) {
            wp_send_json_error('Security check failed', 403);
        }

        // Rate limiting
        if (!SecurityService::check_rate_limit('supervisor_stats', 20, 3600)) {
            wp_send_json_error('Rate limit exceeded', 429);
        }

        $stats = $this->supervisor_model->get_statistics();

        // Sanitize stats output
        $safe_stats = array(
            'total' => SecurityService::sanitize_int($stats['total']),
            'active' => SecurityService::sanitize_int($stats['active']),
            'with_client_assignments' => SecurityService::sanitize_int($stats['with_client_assignments']),
            'with_site_assignments' => SecurityService::sanitize_int($stats['with_site_assignments'])
        );

        wp_send_json_success($safe_stats);
    }
}
