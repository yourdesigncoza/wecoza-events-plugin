<?php
/**
 * Plugin uninstall class
 */

namespace WecozaNotifications;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Uninstaller class
 */
class Uninstaller
{
    /**
     * Uninstall the plugin
     */
    public static function uninstall()
    {
        // Check if user has permission to delete plugins
        if (!current_user_can('delete_plugins')) {
            return;
        }

        // Check nonce for security
        if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'delete-plugin_' . WECOZA_NOTIFICATIONS_PLUGIN_BASENAME)) {
            return;
        }

        global $wpdb;

        // Drop database tables
        $tables = array(
            $wpdb->prefix . 'wecoza_supervisors',
            $wpdb->prefix . 'wecoza_notification_queue',
            $wpdb->prefix . 'wecoza_events_log',
            $wpdb->prefix . 'wecoza_dashboard_status'
        );

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }

        // Delete options
        $options = array(
            'wecoza_notifications_db_version',
            'wecoza_notifications_email_from_name',
            'wecoza_notifications_email_from_email',
            'wecoza_notifications_reminder_interval',
            'wecoza_notifications_max_attempts',
            'wecoza_notifications_retry_delay',
            'wecoza_notifications_enabled',
            'wecoza_notifications_activated'
        );

        foreach ($options as $option) {
            delete_option($option);
        }

        // Clear any remaining cron events
        wp_clear_scheduled_hook('wecoza_process_reminders');
        wp_clear_scheduled_hook('wecoza_process_queue');
        wp_clear_scheduled_hook('wecoza_backup_polling');

        // Clear any cached data
        wp_cache_flush();
    }
}