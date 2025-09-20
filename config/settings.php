<?php
/**
 * System settings configuration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

return array(
    // Email settings
    'email' => array(
        'from_name' => get_bloginfo('name'),
        'from_email' => get_option('admin_email'),
        'reply_to' => get_option('admin_email'),
        'max_attempts' => 3,
        'retry_delay' => 60, // minutes
        'batch_size' => 50,
        'content_type' => 'text/html'
    ),

    // Reminder settings
    'reminders' => array(
        'enabled' => true,
        'default_interval' => 24, // hours
        'max_reminders' => 5,
        'escalation_enabled' => true,
        'escalation_after' => 72, // hours
        'throttle_window' => 24, // hours - minimum time between reminders
        'due_date_buffer' => 48 // hours - how far in advance to start reminders
    ),

    // Task settings
    'tasks' => array(
        'load_learners' => array(
            'due_days' => 2,
            'reminder_enabled' => true,
            'reminder_interval' => 24
        ),
        'agent_order' => array(
            'due_days' => 3,
            'reminder_enabled' => true,
            'reminder_interval' => 24
        ),
        'training_schedule' => array(
            'due_days' => 5,
            'reminder_enabled' => true,
            'reminder_interval' => 24
        ),
        'material_delivery' => array(
            'due_days' => 7,
            'reminder_enabled' => true,
            'reminder_interval' => 24
        ),
        'agent_paperwork' => array(
            'due_days' => 10,
            'reminder_enabled' => true,
            'reminder_interval' => 24
        )
    ),

    // Dashboard settings
    'dashboard' => array(
        'auto_refresh_interval' => 120, // seconds
        'items_per_page' => 20,
        'show_completed_tasks' => true,
        'completed_tasks_days' => 30,
        'cache_duration' => 300 // seconds
    ),

    // System settings
    'system' => array(
        'enabled' => true,
        'debug_mode' => false,
        'log_level' => 'info', // debug, info, warning, error
        'backup_polling_enabled' => true,
        'backup_polling_interval' => 5, // minutes
        'idempotency_window' => 3600, // seconds
        'cleanup_old_logs' => true,
        'log_retention_days' => 30
    ),

    // Channels
    'channels' => array(
        'email' => array(
            'enabled' => true,
            'priority' => 1
        ),
        'dashboard' => array(
            'enabled' => true,
            'priority' => 2
        ),
        'sms' => array(
            'enabled' => false,
            'priority' => 3
        )
    ),

    // Template settings
    'templates' => array(
        'base_path' => WECOZA_NOTIFICATIONS_PLUGIN_DIR . 'templates/',
        'cache_enabled' => true,
        'cache_duration' => 3600,
        'allow_overrides' => true,
        'override_path' => get_stylesheet_directory() . '/wecoza-notifications/'
    ),

    // Security settings
    'security' => array(
        'nonce_lifetime' => 86400, // 24 hours
        'require_nonce' => true,
        'sanitize_inputs' => true,
        'validate_recipients' => true,
        'rate_limit_enabled' => true,
        'rate_limit_window' => 3600, // 1 hour
        'rate_limit_max_requests' => 100
    ),

    // Database settings
    'database' => array(
        'prefix' => 'wecoza_',
        'charset' => 'utf8mb4',
        'collate' => 'utf8mb4_unicode_ci',
        'engine' => 'InnoDB',
        'auto_cleanup' => true,
        'cleanup_interval' => 'daily',
        'keep_logs_days' => 90
    )
);