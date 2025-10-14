<?php
/**
 * Plugin Name: WeCoza Events Plugin
 * Description: Provides shortcodes and integrations for WeCoza Events.
 * Version: 0.1.0
 * Author: WeCoza
 * Text Domain: wecoza-events
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WECOZA_EVENTS_PLUGIN_FILE', __FILE__);

define('WECOZA_EVENTS_PLUGIN_DIR', plugin_dir_path(__FILE__));

define('WECOZA_EVENTS_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/class-wecoza-events-database.php';
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Models/Task.php';
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Models/TaskCollection.php';
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Models/ClassTaskRepository.php';
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Admin/SettingsPage.php';
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Services/NotificationSettings.php';
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Services/NotificationProcessor.php';
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Services/TaskTemplateRegistry.php';
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Services/TaskManager.php';
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Services/ClassTaskService.php';
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Support/Container.php';
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Support/WordPressRequest.php';
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Views/TemplateRenderer.php';
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Views/Presenters/ClassTaskPresenter.php';
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Shortcodes/EventTasksShortcode.php';
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Controllers/JsonResponder.php';
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Controllers/TaskController.php';

\WeCozaEvents\Shortcodes\EventTasksShortcode::register(
    new \WeCozaEvents\Shortcodes\EventTasksShortcode(
        \WeCozaEvents\Support\Container::classTaskService(),
        \WeCozaEvents\Support\Container::classTaskPresenter(),
        \WeCozaEvents\Support\Container::templateRenderer(),
        \WeCozaEvents\Support\Container::wordpressRequest()
    )
);
\WeCozaEvents\Admin\SettingsPage::register();
\WeCozaEvents\Controllers\TaskController::register(
    new \WeCozaEvents\Controllers\TaskController(
        \WeCozaEvents\Support\Container::taskManager(),
        \WeCozaEvents\Support\Container::classTaskPresenter(),
        \WeCozaEvents\Support\Container::wordpressRequest(),
        \WeCozaEvents\Support\Container::jsonResponder()
    )
);

add_filter('cron_schedules', 'wecoza_events_register_schedule');
register_activation_hook(__FILE__, 'wecoza_events_schedule_notifications');
register_deactivation_hook(__FILE__, 'wecoza_events_clear_notifications');
add_action('wecoza_events_process_notifications', 'wecoza_events_run_notification_processor');

function wecoza_events_register_schedule(array $schedules): array
{
    if (!isset($schedules['wecoza_events_five_minutes'])) {
        $schedules['wecoza_events_five_minutes'] = [
            'interval' => 300,
            'display' => __('Every Five Minutes (WeCoza Events)', 'wecoza-events'),
        ];
    }

    return $schedules;
}

function wecoza_events_schedule_notifications(): void
{
    if (!wp_next_scheduled('wecoza_events_process_notifications')) {
        wp_schedule_event(time() + 60, 'wecoza_events_five_minutes', 'wecoza_events_process_notifications');
    }
}

function wecoza_events_clear_notifications(): void
{
    wp_clear_scheduled_hook('wecoza_events_process_notifications');
}

function wecoza_events_run_notification_processor(): void
{
    try {
        \WeCozaEvents\Services\NotificationProcessor::boot()->process();
    } catch (\Throwable $exception) {
        error_log('WeCoza notification processing failed: ' . $exception->getMessage());
    }
}
