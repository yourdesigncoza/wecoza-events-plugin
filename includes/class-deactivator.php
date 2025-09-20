<?php
/**
 * Plugin deactivation class
 */

namespace WecozaNotifications;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Deactivator class
 */
class Deactivator
{
    /**
     * Deactivate the plugin
     */
    public static function deactivate()
    {
        // Clear scheduled cron events
        self::clear_cron_events();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Set deactivation flag
        delete_option('wecoza_notifications_activated');
    }

    /**
     * Clear scheduled cron events
     */
    private static function clear_cron_events()
    {
        // Clear reminder processing
        $timestamp = wp_next_scheduled('wecoza_process_reminders');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'wecoza_process_reminders');
        }

        // Clear queue processing
        $timestamp = wp_next_scheduled('wecoza_process_queue');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'wecoza_process_queue');
        }

        // Clear backup polling
        $timestamp = wp_next_scheduled('wecoza_backup_polling');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'wecoza_backup_polling');
        }
    }
}