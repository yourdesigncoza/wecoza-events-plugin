<?php
/**
 * Plugin activation class
 */

namespace WecozaNotifications;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include security service
require_once WECOZA_NOTIFICATIONS_PLUGIN_DIR . 'app/Services/SecurityService.php';

/**
 * Activator class
 */
class Activator
{
    /**
     * Activate the plugin
     */
    public static function activate()
    {
        // Check WordPress version
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            wp_die(__('WECOZA Notifications Core requires WordPress 5.0 or higher.', 'wecoza-notifications'));
        }

        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            wp_die(__('WECOZA Notifications Core requires PHP 7.4 or higher.', 'wecoza-notifications'));
        }

        // Create database tables
        self::create_database_tables();

        // Set default options
        self::set_default_options();

        // Register custom capabilities
        self::register_capabilities();

        // Schedule cron events
        self::schedule_cron_events();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Set activation flag
        update_option('wecoza_notifications_activated', true);
    }

    /**
     * Create database tables
     */
    private static function create_database_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Supervisors table
        $supervisors_table = $wpdb->prefix . 'wecoza_supervisors';
        $supervisors_sql = "CREATE TABLE $supervisors_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            email varchar(255) NOT NULL,
            phone varchar(50),
            client_assignments text,
            site_assignments text,
            is_default tinyint(1) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY client_assignments (client_assignments(191)),
            KEY site_assignments (site_assignments(191)),
            KEY is_default (is_default),
            KEY is_active (is_active)
        ) $charset_collate;";

        // Notification queue table
        $queue_table = $wpdb->prefix . 'wecoza_notification_queue';
        $queue_sql = "CREATE TABLE $queue_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            event_name varchar(100) NOT NULL,
            idempotency_key varchar(255) NOT NULL,
            recipient_email varchar(255) NOT NULL,
            recipient_name varchar(255),
            channel varchar(50) DEFAULT 'email',
            template_name varchar(100) NOT NULL,
            payload text,
            status varchar(50) DEFAULT 'pending',
            attempts int(11) DEFAULT 0,
            max_attempts int(11) DEFAULT 3,
            last_error text,
            scheduled_at datetime DEFAULT CURRENT_TIMESTAMP,
            sent_at datetime NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idempotency_key (idempotency_key),
            KEY event_name (event_name),
            KEY recipient_email (recipient_email),
            KEY status (status),
            KEY scheduled_at (scheduled_at),
            KEY channel (channel)
        ) $charset_collate;";

        // Events log table
        $events_table = $wpdb->prefix . 'wecoza_events_log';
        $events_sql = "CREATE TABLE $events_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            event_name varchar(100) NOT NULL,
            event_payload text,
            class_id int(11),
            actor_id int(11),
            idempotency_key varchar(255) NOT NULL,
            processed tinyint(1) DEFAULT 0,
            occurred_at datetime DEFAULT CURRENT_TIMESTAMP,
            processed_at datetime NULL,
            PRIMARY KEY (id),
            UNIQUE KEY idempotency_key (idempotency_key),
            KEY event_name (event_name),
            KEY class_id (class_id),
            KEY actor_id (actor_id),
            KEY processed (processed),
            KEY occurred_at (occurred_at)
        ) $charset_collate;";

        // Dashboard status table
        $status_table = $wpdb->prefix . 'wecoza_dashboard_status';
        $status_sql = "CREATE TABLE $status_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            class_id int(11) NOT NULL,
            task_type varchar(100) NOT NULL,
            task_status varchar(50) DEFAULT 'pending',
            responsible_user_id int(11),
            due_date datetime NULL,
            completed_at datetime NULL,
            completion_data text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY class_task (class_id, task_type),
            KEY task_status (task_status),
            KEY responsible_user_id (responsible_user_id),
            KEY due_date (due_date),
            KEY class_id (class_id)
        ) $charset_collate;";

        // Template versions history table
        $template_versions_table = $wpdb->prefix . 'wecoza_template_versions';
        $template_versions_sql = "CREATE TABLE $template_versions_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            template_id varchar(100) NOT NULL,
            version varchar(20) NOT NULL,
            subject text NOT NULL,
            body longtext NOT NULL,
            custom_css longtext,
            created_by bigint(20) unsigned,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            is_backup tinyint(1) DEFAULT 0,
            is_restore tinyint(1) DEFAULT 0,
            notes text,
            PRIMARY KEY (id),
            KEY template_id (template_id),
            KEY version (version),
            KEY created_at (created_at),
            KEY created_by (created_by)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($supervisors_sql);
        dbDelta($queue_sql);
        dbDelta($events_sql);
        dbDelta($status_sql);
        dbDelta($template_versions_sql);

        // Store database version
        update_option('wecoza_notifications_db_version', '1.0.0');
    }

    /**
     * Register custom capabilities
     */
    private static function register_capabilities()
    {
        // Get roles
        $admin_role = get_role('administrator');
        $editor_role = get_role('editor');

        // Add capabilities to administrator role
        if ($admin_role) {
            $admin_role->add_cap(SecurityService::CAP_MANAGE_NOTIFICATIONS);
            $admin_role->add_cap(SecurityService::CAP_VIEW_REPORTS);
            $admin_role->add_cap(SecurityService::CAP_MANAGE_SUPERVISORS);
            $admin_role->add_cap(SecurityService::CAP_MANAGE_TEMPLATES);
            $admin_role->add_cap(SecurityService::CAP_VIEW_ANALYTICS);
            $admin_role->add_cap(SecurityService::CAP_MANAGE_SETTINGS);
        }

        // Add limited capabilities to editor role
        if ($editor_role) {
            $editor_role->add_cap(SecurityService::CAP_VIEW_REPORTS);
            $editor_role->add_cap(SecurityService::CAP_VIEW_ANALYTICS);
        }

        // Store capabilities registration flag
        update_option('wecoza_notifications_caps_registered', true);
    }

    /**
     * Set default options
     */
    private static function set_default_options()
    {
        $defaults = array(
            'wecoza_notifications_email_from_name' => get_bloginfo('name'),
            'wecoza_notifications_email_from_email' => get_option('admin_email'),
            'wecoza_notifications_reminder_interval' => 24, // hours
            'wecoza_notifications_max_attempts' => 3,
            'wecoza_notifications_retry_delay' => 60, // minutes
            'wecoza_notifications_enabled' => true,
        );

        foreach ($defaults as $option => $value) {
            if (!get_option($option, false)) {
                update_option($option, $value);
            }
        }
    }

    /**
     * Schedule cron events
     */
    private static function schedule_cron_events()
    {
        // Schedule reminder processing (every 30 minutes)
        if (!wp_next_scheduled('wecoza_process_reminders')) {
            wp_schedule_event(time(), 'wecoza_30min', 'wecoza_process_reminders');
        }

        // Schedule queue processing (every 5 minutes)
        if (!wp_next_scheduled('wecoza_process_queue')) {
            wp_schedule_event(time(), 'wecoza_5min', 'wecoza_process_queue');
        }

        // Schedule backup polling (every 5 minutes)
        if (!wp_next_scheduled('wecoza_backup_polling')) {
            wp_schedule_event(time(), 'wecoza_5min', 'wecoza_backup_polling');
        }
    }
}
