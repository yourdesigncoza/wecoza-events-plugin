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
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Models/ClassChangeLogRepository.php';
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Models/MaterialTrackingRepository.php';
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Admin/SettingsPage.php';
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Services/NotificationSettings.php';
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Services/NotificationProcessor.php';
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Services/MaterialNotificationService.php';
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Services/MaterialTrackingDashboardService.php';
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Services/TaskTemplateRegistry.php';
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Services/TaskManager.php';
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Services/ClassTaskService.php';
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Services/AISummaryDisplayService.php';
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Services/AISummaryService/Traits/DataObfuscator.php';
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Support/OpenAIConfig.php';
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Support/FieldMapper.php';
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Services/AISummaryService.php';
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Support/Container.php';
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Support/WordPressRequest.php';
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Views/TemplateRenderer.php';
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Views/Presenters/ClassTaskPresenter.php';
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Views/Presenters/NotificationEmailPresenter.php';
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Views/Presenters/AISummaryPresenter.php';
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Views/Presenters/MaterialTrackingPresenter.php';
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Shortcodes/EventTasksShortcode.php';
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Shortcodes/AISummaryShortcode.php';
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Shortcodes/MaterialTrackingShortcode.php';
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Controllers/JsonResponder.php';
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Controllers/TaskController.php';
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Controllers/MaterialTrackingController.php';

if (defined('WP_CLI') && WP_CLI) {
    require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/CLI/AISummaryStatusCommand.php';
    \WeCozaEvents\CLI\AISummaryStatusCommand::register();
}

\WeCozaEvents\Shortcodes\EventTasksShortcode::register(
    new \WeCozaEvents\Shortcodes\EventTasksShortcode(
        \WeCozaEvents\Support\Container::classTaskService(),
        \WeCozaEvents\Support\Container::classTaskPresenter(),
        \WeCozaEvents\Support\Container::templateRenderer(),
        \WeCozaEvents\Support\Container::wordpressRequest()
    )
);
\WeCozaEvents\Shortcodes\AISummaryShortcode::register(
    new \WeCozaEvents\Shortcodes\AISummaryShortcode(
        new \WeCozaEvents\Services\AISummaryDisplayService(),
        new \WeCozaEvents\Views\Presenters\AISummaryPresenter(),
        \WeCozaEvents\Support\Container::templateRenderer(),
        \WeCozaEvents\Support\Container::wordpressRequest()
    )
);
\WeCozaEvents\Shortcodes\MaterialTrackingShortcode::register();
\WeCozaEvents\Admin\SettingsPage::register();
\WeCozaEvents\Controllers\TaskController::register(
    new \WeCozaEvents\Controllers\TaskController(
        \WeCozaEvents\Support\Container::taskManager(),
        \WeCozaEvents\Support\Container::classTaskPresenter(),
        \WeCozaEvents\Support\Container::wordpressRequest(),
        \WeCozaEvents\Support\Container::jsonResponder()
    )
);
\WeCozaEvents\Controllers\MaterialTrackingController::register();

add_filter('cron_schedules', 'wecoza_events_register_schedule');
register_activation_hook(__FILE__, 'wecoza_events_activate_plugin');
register_deactivation_hook(__FILE__, 'wecoza_events_deactivate_plugin');
add_action('wecoza_events_process_notifications', 'wecoza_events_run_notification_processor');

function wecoza_events_register_schedule(array $schedules): array
{
    if (!isset($schedules['wecoza_events_one_minute'])) {
        $schedules['wecoza_events_one_minute'] = [
            'interval' => 60,
            'display' => __('Every Minute (WeCoza Events)', 'wecoza-events'),
        ];
    }

    if (!isset($schedules['wecoza_events_daily'])) {
        $schedules['wecoza_events_daily'] = [
            'interval' => 86400,
            'display' => __('Once Daily (WeCoza Events)', 'wecoza-events'),
        ];
    }

    return $schedules;
}

function wecoza_events_activate_plugin(): void
{
    wecoza_events_schedule_notifications();
    wecoza_events_setup_capabilities();
}

function wecoza_events_deactivate_plugin(): void
{
    wecoza_events_clear_notifications();
    wecoza_events_remove_capabilities();
}

function wecoza_events_setup_capabilities(): void
{
    $editor = get_role('editor');
    if ($editor) {
        $editor->add_cap('view_material_tracking');
        $editor->add_cap('manage_material_tracking');
    }

    $administrator = get_role('administrator');
    if ($administrator) {
        $administrator->add_cap('view_material_tracking');
        $administrator->add_cap('manage_material_tracking');
    }
}

function wecoza_events_remove_capabilities(): void
{
    $editor = get_role('editor');
    if ($editor) {
        $editor->remove_cap('view_material_tracking');
        $editor->remove_cap('manage_material_tracking');
    }

    $administrator = get_role('administrator');
    if ($administrator) {
        $administrator->remove_cap('view_material_tracking');
        $administrator->remove_cap('manage_material_tracking');
    }
}

function wecoza_events_schedule_notifications(): void
{
    if (!wp_next_scheduled('wecoza_events_process_notifications')) {
        wp_schedule_event(time() + 60, 'wecoza_events_one_minute', 'wecoza_events_process_notifications');
    }

    if (!wp_next_scheduled('wecoza_material_notification_check')) {
        wp_schedule_event(time(), 'wecoza_events_daily', 'wecoza_material_notification_check');
    }
}

function wecoza_events_clear_notifications(): void
{
    wp_clear_scheduled_hook('wecoza_events_process_notifications');
    wp_clear_scheduled_hook('wecoza_material_notification_check');
}

function wecoza_events_run_notification_processor(): void
{
    try {
        \WeCozaEvents\Services\NotificationProcessor::boot()->process();
    } catch (\Throwable $exception) {
        error_log('WeCoza notification processing failed: ' . $exception->getMessage());
    }
}

add_action('wecoza_material_notification_check', 'wecoza_events_run_material_notification_check');

function wecoza_events_run_material_notification_check(): void
{
    try {
        $pdo = \WeCozaEvents\Database\Connection::getPdo();
        $schema = \WeCozaEvents\Database\Connection::getSchema();
        
        $trackingRepo = new \WeCozaEvents\Models\MaterialTrackingRepository($pdo, $schema);
        $service = new \WeCozaEvents\Services\MaterialNotificationService($pdo, $schema, $trackingRepo);
        
        // Check and send Orange notifications (7 days before start)
        $orangeClasses = $service->findOrangeStatusClasses();
        if (!empty($orangeClasses)) {
            $sent = $service->sendMaterialNotifications($orangeClasses, 'orange');
            error_log(sprintf('WeCoza Material Notifications: Sent %d Orange (7-day) notifications', $sent));
        }
        
        // Check and send Red notifications (5 days before start)
        $redClasses = $service->findRedStatusClasses();
        if (!empty($redClasses)) {
            $sent = $service->sendMaterialNotifications($redClasses, 'red');
            error_log(sprintf('WeCoza Material Notifications: Sent %d Red (5-day) notifications', $sent));
        }
    } catch (\Throwable $exception) {
        error_log('WeCoza material notification check failed: ' . $exception->getMessage());
    }
}
