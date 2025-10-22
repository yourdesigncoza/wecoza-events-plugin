<?php
declare(strict_types=1);

namespace WeCozaEvents\Controllers;

use Throwable;
use WeCozaEvents\Services\TaskManager;
use WeCozaEvents\Support\WordPressRequest;
use WeCozaEvents\Views\Presenters\ClassTaskPresenter;

use function __;
use function add_action;
use function check_ajax_referer;
use function current_time;
use function get_current_user_id;
use function in_array;
use function is_user_logged_in;
use function trim;

final class TaskController
{
    private TaskManager $manager;
    private ClassTaskPresenter $presenter;
    private WordPressRequest $request;
    private JsonResponder $responder;

    public function __construct(
        ?TaskManager $manager = null,
        ?ClassTaskPresenter $presenter = null,
        ?WordPressRequest $request = null,
        ?JsonResponder $responder = null
    )
    {
        $this->manager = $manager ?? new TaskManager();
        $this->presenter = $presenter ?? new ClassTaskPresenter();
        $this->request = $request ?? new WordPressRequest();
        $this->responder = $responder ?? new JsonResponder();
    }

    public static function register(?self $controller = null): void
    {
        $instance = $controller ?? new self();
        add_action('wp_ajax_wecoza_events_task_update', [$instance, 'handleUpdate']);
        add_action('wp_ajax_nopriv_wecoza_events_task_update', [$instance, 'handleUnauthorized']);
    }

    public function handleUnauthorized(): void
    {
        $this->responder->error(__('Authentication required.', 'wecoza-events'), 401);
    }

    public function handleUpdate(): void
    {
        check_ajax_referer('wecoza_events_tasks', 'nonce');

        if (!is_user_logged_in()) {
            $this->responder->error(__('Please sign in to manage tasks.', 'wecoza-events'), 403);
        }

        $logId = $this->request->getPostInt('log_id') ?? 0;
        $taskId = $this->request->getPostString('task_id', '') ?? '';
        $taskAction = $this->request->getPostString('task_action', '') ?? '';

        if ($logId <= 0 || $taskId === '' || !in_array($taskAction, ['complete', 'reopen'], true)) {
            $this->responder->error(__('Invalid task request.', 'wecoza-events'), 400);
        }

        try {
            if ($taskAction === 'complete') {
                $note = $this->request->getPostString('note');
                $note = $note !== null ? trim($note) : null;
                $tasks = $this->manager->markTaskCompleted(
                    $logId,
                    $taskId,
                    get_current_user_id(),
                    current_time('mysql', true),
                    $note
                );
            } else {
                $tasks = $this->manager->reopenTask($logId, $taskId);
            }
        } catch (Throwable $exception) {
            $this->responder->error($exception->getMessage(), 500);
        }

        $this->responder->success([
            'tasks' => $this->presenter->presentTasks($tasks),
        ]);
    }
}
