<?php
declare(strict_types=1);

namespace WeCozaEvents\Controllers;

use Throwable;
use WeCozaEvents\Services\TaskManager;
use WeCozaEvents\Shortcodes\EventTasksShortcode;

use function __;
use function absint;
use function add_action;
use function check_ajax_referer;
use function current_time;
use function get_current_user_id;
use function is_user_logged_in;
use function sanitize_text_field;
use function wp_send_json_error;
use function wp_send_json_success;
use function wp_unslash;

final class TaskController
{
    private TaskManager $manager;

    public function __construct(?TaskManager $manager = null)
    {
        $this->manager = $manager ?? new TaskManager();
    }

    public static function register(): void
    {
        $controller = new self();
        add_action('wp_ajax_wecoza_events_task_update', [$controller, 'handleUpdate']);
        add_action('wp_ajax_nopriv_wecoza_events_task_update', [$controller, 'handleUnauthorized']);
    }

    public function handleUnauthorized(): void
    {
        wp_send_json_error([
            'message' => __('Authentication required.', 'wecoza-events'),
        ], 401);
    }

    public function handleUpdate(): void
    {
        check_ajax_referer('wecoza_events_tasks', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error([
                'message' => __('Please sign in to manage tasks.', 'wecoza-events'),
            ], 403);
        }

        $logId = absint(wp_unslash($_POST['log_id'] ?? 0));
        $taskId = sanitize_text_field(wp_unslash($_POST['task_id'] ?? ''));
        $taskAction = sanitize_text_field(wp_unslash($_POST['task_action'] ?? ''));

        if ($logId <= 0 || $taskId === '' || ($taskAction !== 'complete' && $taskAction !== 'reopen')) {
            wp_send_json_error([
                'message' => __('Invalid task request.', 'wecoza-events'),
            ], 400);
        }

        try {
            if ($taskAction === 'complete') {
                $note = sanitize_text_field(wp_unslash($_POST['note'] ?? ''));
                $tasks = $this->manager->markTaskCompleted(
                    $logId,
                    $taskId,
                    get_current_user_id(),
                    current_time('mysql', true),
                    $note !== '' ? $note : null
                );
            } else {
                $tasks = $this->manager->reopenTask($logId, $taskId);
            }
        } catch (Throwable $exception) {
            wp_send_json_error([
                'message' => $exception->getMessage(),
            ], 500);
        }

        wp_send_json_success([
            'tasks' => EventTasksShortcode::prepareTaskPayload($tasks),
        ]);
    }
}
