<?php
/**
 * Main plugin core class
 */

namespace WecozaNotifications;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Core class
 */
class Core
{
    /**
     * Service instances
     */
    private $email_service;
    private $event_processor;
    private $template_service;
    private $db_service;
    private $audit_service;
    private $analytics_service;

    /**
     * Controller instances
     */
    private $supervisor_controller;
    private $shortcode_controller;
    private $template_controller;
    private $audit_controller;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->init_hooks();
        $this->init_services();
        $this->init_controllers();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        // Main event listener
        add_action('wecoza_event', array($this, 'handle_event'), 10, 1);

        // AJAX hooks
        add_action('wp_ajax_wecoza_update_class_status', array($this, 'ajax_update_class_status'));
        add_action('wp_ajax_wecoza_update_pending_tasks', array($this, 'ajax_update_pending_tasks'));
        add_action('wp_ajax_wecoza_complete_task', array($this, 'ajax_complete_task'));
        add_action('wp_ajax_wecoza_refresh_notifications', array($this, 'ajax_refresh_notifications'));

        // Cron hooks
        add_action('wecoza_process_reminders', array($this, 'process_reminders'));
        add_action('wecoza_process_queue', array($this, 'process_queue'));
        add_action('wecoza_backup_polling', array($this, 'backup_polling'));

        // Action Scheduler hooks for email processing
        add_action('wecoza_send_single_email', array($this, 'send_single_email'), 10, 1);

        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // Frontend hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));

        // Custom cron intervals
        add_filter('cron_schedules', array($this, 'add_cron_intervals'));

        // Shortcode registration
        add_action('init', array($this, 'register_shortcodes'));
    }

    /**
     * Initialize services
     */
    private function init_services()
    {
        // Initialize EmailService
        $this->email_service = new EmailService();
        $this->email_service->init_action_scheduler();

        // Initialize EventProcessor
        $this->event_processor = new EventProcessor();

        // Initialize TemplateService (already loaded by EmailService but make it available)
        $this->template_service = new TemplateService();

        // Initialize DatabaseService
        $this->db_service = new DatabaseService();

        // Initialize AuditService
        $this->audit_service = new AuditService();

        // Initialize AnalyticsService
        $this->analytics_service = new AnalyticsService();
    }

    /**
     * Initialize controllers
     */
    private function init_controllers()
    {
        // Initialize SupervisorController
        $this->supervisor_controller = new SupervisorController();

        // Initialize ShortcodeController
        $this->shortcode_controller = new ShortcodeController();

        // Initialize TemplateController
        $this->template_controller = new TemplateController();
        $this->template_controller->init();

        // Initialize AuditController
        $this->audit_controller = new AuditController();
        $this->audit_controller->init();

        // Initialize other controllers as needed
        // $this->dashboard_controller = new DashboardController();
    }

    /**
     * Handle incoming events
     */
    public function handle_event($event_data)
    {
        if (!$this->validate_event($event_data)) {
            error_log('WECOZA Notifications: Invalid event data received');
            return;
        }

        // Process the event
        $event_processor = new EventProcessor();
        $event_processor->process_event($event_data);
    }

    /**
     * Validate event data
     */
    private function validate_event($event_data)
    {
        if (!is_array($event_data)) {
            return false;
        }

        $required_fields = array('event', 'class_id', 'actor_id', 'occurred_at', 'idempotency_key');

        foreach ($required_fields as $field) {
            if (!isset($event_data[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * AJAX: Update class status
     */
    public function ajax_update_class_status()
    {
        check_ajax_referer('wecoza_nonce', 'nonce');

        $class_id = intval($_POST['class_id']);
        $response = array('success' => false);

        if ($class_id > 0) {
            // Get updated status data
            $status_data = $this->get_class_status($class_id);
            $response = array(
                'success' => true,
                'data' => $status_data
            );
        }

        wp_send_json($response);
    }

    /**
     * AJAX: Update pending tasks
     */
    public function ajax_update_pending_tasks()
    {
        check_ajax_referer('wecoza_nonce', 'nonce');

        $class_id = intval($_POST['class_id']);
        $response = array('success' => false);

        if ($class_id > 0) {
            // Get pending tasks
            $tasks = $this->get_pending_tasks($class_id);
            $response = array(
                'success' => true,
                'data' => $tasks
            );
        }

        wp_send_json($response);
    }

    /**
     * AJAX: Complete task
     */
    public function ajax_complete_task()
    {
        check_ajax_referer('wecoza_nonce', 'nonce');

        $class_id = intval($_POST['class_id']);
        $task_type = sanitize_text_field($_POST['task_type']);

        $response = array('success' => false);

        if ($class_id > 0 && !empty($task_type)) {
            // Mark task as complete
            $success = $this->complete_task($class_id, $task_type);
            $response = array(
                'success' => $success,
                'message' => $success ? __('Task completed successfully', 'wecoza-notifications') : __('Failed to complete task', 'wecoza-notifications')
            );
        }

        wp_send_json($response);
    }

    /**
     * AJAX: Refresh notifications
     */
    public function ajax_refresh_notifications()
    {
        check_ajax_referer('wecoza_nonce', 'nonce');

        $user_id = get_current_user_id();
        $response = array('success' => false);

        if ($user_id > 0) {
            // Get user notifications
            $notifications = $this->get_user_notifications($user_id);
            $response = array(
                'success' => true,
                'data' => $notifications
            );
        }

        wp_send_json($response);
    }

    /**
     * Process reminders cron
     */
    public function process_reminders()
    {
        $cron_service = new CronService();
        $cron_service->process_reminders();
    }

    /**
     * Process notification queue cron
     */
    public function process_queue()
    {
        if ($this->email_service) {
            $this->email_service->process_queue();
        } else {
            $email_service = new EmailService();
            $email_service->process_queue();
        }
    }

    /**
     * Send single email via Action Scheduler
     */
    public function send_single_email($notification_id)
    {
        if ($this->email_service && $this->db_service) {
            $notification = $this->db_service->get_notification_by_id($notification_id);
            if ($notification) {
                $this->email_service->send_notification($notification);
            }
        }
    }

    /**
     * Backup polling cron
     */
    public function backup_polling()
    {
        if ($this->event_processor) {
            $this->event_processor->backup_polling();
        } else {
            $event_processor = new EventProcessor();
            $event_processor->backup_polling();
        }
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu()
    {
        add_menu_page(
            __('WECOZA Notifications', 'wecoza-notifications'),
            __('Notifications', 'wecoza-notifications'),
            'manage_options',
            'wecoza-notifications',
            array($this, 'admin_page'),
            'dashicons-bell',
            30
        );

        add_submenu_page(
            'wecoza-notifications',
            __('Supervisors', 'wecoza-notifications'),
            __('Supervisors', 'wecoza-notifications'),
            'manage_options',
            'wecoza-supervisors',
            array($this, 'supervisors_page')
        );
    }

    /**
     * Admin page callback
     */
    public function admin_page()
    {
        echo '<div class="wrap"><h1>' . __('WECOZA Notifications', 'wecoza-notifications') . '</h1>';
        echo '<p>' . __('Notification system dashboard coming soon...', 'wecoza-notifications') . '</p>';
        echo '</div>';
    }

    /**
     * Supervisors page callback
     */
    public function supervisors_page()
    {
        $supervisor_controller = new SupervisorController();
        $supervisor_controller->render_page();
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook)
    {
        if (strpos($hook, 'wecoza-notifications') !== false) {
            wp_enqueue_script(
                'wecoza-notifications-admin',
                WECOZA_NOTIFICATIONS_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                WECOZA_NOTIFICATIONS_VERSION,
                true
            );

            wp_localize_script('wecoza-notifications-admin', 'wecoza_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wecoza_nonce')
            ));
        }
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts()
    {
        if ($this->should_load_frontend_scripts()) {
            wp_enqueue_script(
                'wecoza-notifications-frontend',
                WECOZA_NOTIFICATIONS_PLUGIN_URL . 'assets/js/frontend.js',
                array('jquery'),
                WECOZA_NOTIFICATIONS_VERSION,
                true
            );

            wp_localize_script('wecoza-notifications-frontend', 'wecoza_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wecoza_nonce')
            ));
        }
    }

    /**
     * Check if frontend scripts should be loaded
     */
    private function should_load_frontend_scripts()
    {
        global $post;

        if (is_object($post) && has_shortcode($post->post_content, 'wecoza_')) {
            return true;
        }

        return false;
    }

    /**
     * Add custom cron intervals
     */
    public function add_cron_intervals($schedules)
    {
        $schedules['wecoza_5min'] = array(
            'interval' => 300, // 5 minutes
            'display' => __('Every 5 minutes', 'wecoza-notifications')
        );

        $schedules['wecoza_30min'] = array(
            'interval' => 1800, // 30 minutes
            'display' => __('Every 30 minutes', 'wecoza-notifications')
        );

        return $schedules;
    }

    /**
     * Register shortcodes
     */
    public function register_shortcodes()
    {
        $shortcode_controller = new ShortcodeController();
        $shortcode_controller->register_shortcodes();
    }

    /**
     * Helper methods for AJAX callbacks
     */
    private function get_class_status($class_id)
    {
        // Implementation will be added when StatusModel is created
        return array();
    }

    private function get_pending_tasks($class_id)
    {
        // Implementation will be added when TaskModel is created
        return array();
    }

    private function complete_task($class_id, $task_type)
    {
        // Implementation will be added when TaskModel is created
        return false;
    }

    private function get_user_notifications($user_id)
    {
        // Implementation will be added when NotificationModel is created
        return array();
    }
}