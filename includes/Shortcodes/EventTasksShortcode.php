<?php
declare(strict_types=1);

namespace WeCozaEvents\Shortcodes;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use RuntimeException;
use WeCozaEvents\Database\Connection;
use WeCozaEvents\Models\TaskCollection;
use WeCozaEvents\Services\TaskManager;
use WeCozaEvents\Services\TaskTemplateRegistry;

use function __;
use function _n;
use function absint;
use function add_shortcode;
use function admin_url;
use function apply_filters;
use function array_filter;
use function array_pop;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function get_userdata;
use function is_array;
use function json_decode;
use function mysql2date;
use function preg_match;
use function shortcode_atts;
use function sprintf;
use function strtolower;
use function trim;
use function wp_create_nonce;

final class EventTasksShortcode
{
    private const DEFAULT_LIMIT = 20;

    private static bool $assetsPrinted = false;
    private static ?TaskManager $taskManager = null;
    private static ?TaskTemplateRegistry $templateRegistry = null;

    /** @var array<int, string> */
    private static array $userNameCache = [];

    public static function register(): void
    {
        add_shortcode('wecoza_event_tasks', [self::class, 'render']);
    }

    public static function render(array $atts = [], string $content = '', string $tag = ''): string
    {
        $atts = shortcode_atts([
            'limit' => self::DEFAULT_LIMIT,
        ], $atts, $tag);

        $limit = absint($atts['limit']);
        if ($limit <= 0) {
            $limit = self::DEFAULT_LIMIT;
        }

        try {
            $rows = self::fetchClasses($limit);
        } catch (RuntimeException $exception) {
            return self::wrapMessage(
                sprintf(
                    esc_html__('Unable to load tasks: %s', 'wecoza-events'),
                    esc_html($exception->getMessage())
                )
            );
        }

        if ($rows === []) {
            return self::wrapMessage(esc_html__('No classes available.', 'wecoza-events'));
        }

        $nonce = wp_create_nonce('wecoza_events_tasks');
        $ajaxUrl = admin_url('admin-ajax.php');

        self::maybePrintAssets();

        ob_start();
        ?>
        <div
            class="wecoza-event-tasks"
            data-nonce="<?php echo esc_attr($nonce); ?>"
            data-ajax-url="<?php echo esc_attr($ajaxUrl); ?>"
        >
            <div class="card shadow-none border my-3">
                <div class="card-header p-3 border-bottom">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <h4 class="text-body mb-0"><?php echo esc_html__('Class Tasks', 'wecoza-events'); ?></h4>
                        <?php $count = count($rows); ?>
                        <span class="badge badge-phoenix fs-10 badge-phoenix-primary">
                            <?php echo esc_html(sprintf(_n('%d class', '%d classes', $count, 'wecoza-events'), $count)); ?>
                        </span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm fs-9 mb-0 overflow-hidden" id="wecoza-event-tasks-table">
                            <thead class="border-bottom">
                                <tr>
                                    <th scope="col" class="border-0 ps-4"><?php echo esc_html__('ID', 'wecoza-events'); ?><i class="bi bi-hash ms-1"></i></th>
                                    <th scope="col" class="border-0"><?php echo esc_html__('Client ID & Name', 'wecoza-events'); ?><i class="bi bi-building ms-1"></i></th>
                                    <th scope="col" class="border-0"><?php echo esc_html__('Type', 'wecoza-events'); ?><i class="bi bi-tag ms-1"></i></th>
                                    <th scope="col" class="border-0"><?php echo esc_html__('Subject', 'wecoza-events'); ?><i class="bi bi-book ms-1"></i></th>
                                    <th scope="col" class="border-0"><?php echo esc_html__('Start Date', 'wecoza-events'); ?><i class="bi bi-calendar-date ms-1"></i></th>
                                    <th scope="col" class="border-0"><?php echo esc_html__('Agent ID & Name', 'wecoza-events'); ?><i class="bi bi-person ms-1"></i></th>
                                    <th scope="col" class="border-0"><?php echo esc_html__('Exam Class', 'wecoza-events'); ?><i class="bi bi-mortarboard ms-1"></i></th>
                                    <th scope="col" class="border-0"><?php echo esc_html__('Status', 'wecoza-events'); ?><i class="bi bi-activity ms-1"></i></th>
                                    <th scope="col" class="border-0"><?php echo esc_html__('SETA', 'wecoza-events'); ?><i class="bi bi-award ms-1"></i></th>
                                    <th scope="col" class="border-0 text-end pe-4"><?php echo esc_html__('Actions', 'wecoza-events'); ?><i class="bi bi-gear ms-1"></i></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $class): ?>
                                    <tr
                                        data-class-id="<?php echo esc_attr((string) $class['id']); ?>"
                                        data-log-id="<?php echo esc_attr($class['log_id'] ?? ''); ?>"
                                        data-manageable="<?php echo $class['manageable'] ? '1' : '0'; ?>"
                                        data-panel-id="task-panel-<?php echo esc_attr((string) $class['id']); ?>"
                                    >
                                        <td class="py-2 align-middle text-center fs-8 white-space-nowrap">
                                                <span class="badge fs-10 badge-phoenix badge-phoenix-secondary">#<?php echo esc_html((string) $class['id']); ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-body"><?php echo esc_html((string) $class['client']['id']); ?> : <?php echo esc_html($class['client']['name']); ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary bg-opacity-10 text-primary"><?php echo esc_html($class['type']); ?></span>
                                        </td>
                                        <td><?php echo esc_html($class['subject']); ?></td>
                                        <td>
                                            <div class="fw-semibold text-body"><?php echo esc_html($class['event_date']['human']); ?></div>
                                            <?php if ($class['due_date']['iso'] !== '' && $class['due_date']['human'] !== $class['event_date']['human']): ?>
                                                <div class="text-body-tertiary small"><?php echo esc_html(sprintf(__('Due %s', 'wecoza-events'), $class['due_date']['human'])); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong>Initial:</strong> <?php echo esc_html($class['agent_display']); ?></td>
                                        <td>
                                            <span class="badge badge-phoenix fs-10 <?php echo esc_attr($class['exam']['class']); ?>">
                                                <?php echo esc_html($class['exam']['label']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-phoenix fs-10 <?php echo esc_attr($class['status']['class']); ?>">
                                                <?php echo esc_html($class['status']['label']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-phoenix fs-10 <?php echo esc_attr($class['seta']['class']); ?>">
                                                <?php echo esc_html($class['seta']['label']); ?>
                                            </span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <button
                                                type="button"
                                                class="btn btn-link btn-sm px-1 text-decoration-none wecoza-task-toggle"
                                                data-target="task-panel-<?php echo esc_attr((string) $class['id']); ?>"
                                                aria-expanded="false"
                                                aria-controls="task-panel-<?php echo esc_attr((string) $class['id']); ?>"
                                            >
                                                <span class="visually-hidden"><?php echo esc_html__('Toggle tasks', 'wecoza-events'); ?></span>
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr
                                        class="wecoza-task-panel-row"
                                        id="task-panel-<?php echo esc_attr((string) $class['id']); ?>"
                                        data-panel-id="task-panel-<?php echo esc_attr((string) $class['id']); ?>"
                                        hidden
                                    >
                                        <td colspan="10" class="bg-body-tertiary">
                                            <div class="p-4 wecoza-task-panel-content" data-log-id="<?php echo esc_attr($class['log_id'] ?? ''); ?>" data-class-id="<?php echo esc_attr((string) $class['id']); ?>" data-manageable="<?php echo $class['manageable'] ? '1' : '0'; ?>">
                                                <div class="row g-4 align-items-start">
                                                    <?php /*
                                                    <div class="col-12">
                                                        <div class="d-flex flex-wrap gap-3 justify-content-between align-items-start pb-3 border-bottom">
                                                            <div>
                                                                <h5 class="mb-1 text-body"><?php echo esc_html($class['client']['name']); ?></h5>
                                                                <p class="text-body-secondary small mb-0"><?php echo esc_html(sprintf(__('Class #%1$s · %2$s', 'wecoza-events'), $class['id'], $class['subject'])); ?></p>
                                                            </div>
                                                            <div class="text-body-secondary small text-md-end">
                                                                <div><?php echo esc_html(sprintf(__('Event Date: %s', 'wecoza-events'), $class['event_date']['human'])); ?></div>
                                                                <?php if ($class['due_date']['iso'] !== ''): ?>
                                                                    <div><?php echo esc_html(sprintf(__('Due: %s', 'wecoza-events'), $class['due_date']['human'])); ?></div>
                                                                <?php endif; ?>
                                                                <div><?php echo esc_html($class['agent_display']); ?></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    */ ?>

                                                    <?php if (!$class['manageable']): ?>
                                                        <div class="col-12">
                                                            <div class="alert alert-warning border-warning mb-0" role="alert">
                                                                <?php echo esc_html__('Tasks are not yet available for this class.', 'wecoza-events'); ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>

                                                    <div class="col-12 col-lg-6">
                                                        <div class="card shadow-none border h-100">
                                                            <div class="card-header bg-body-tertiary py-2 px-3 border-bottom">
                                                                <h6 class="card-title fs-8 mb-0 text-body-tertiary"><?php echo esc_html__('Open Tasks', 'wecoza-events'); ?></h6>
                                                            </div>
                                                            <div class="card-body p-0">
                                                                <ul class="list-group list-group-flush wecoza-task-list wecoza-task-list-open" data-role="open-list">
                                                                    <?php foreach ($class['tasks']['open'] as $task): ?>
                                                                        <li class="list-group-item d-flex flex-row align-items-center justify-content-between gap-2 m-1" data-task-id="<?php echo esc_attr($task['id']); ?>">
                                                                            <div class="fw-semibold text-body w-30"><?php echo esc_html($task['label']); ?></div>
                                                                            <div class="d-flex flex-row gap-2 align-items-center flex-grow-1">
                                                                                <label class="visually-hidden" for="wecoza-note-<?php echo esc_attr($class['id'] . '-' . $task['id']); ?>"><?php echo esc_html($task['note_label']); ?></label>
                                                                                <input
                                                                                    id="wecoza-note-<?php echo esc_attr($class['id'] . '-' . $task['id']); ?>"
                                                                                    class="form-control form-control-sm wecoza-task-note"
                                                                                    type="text"
                                                                                    placeholder="<?php echo esc_attr($task['note_placeholder']); ?>"
                                                                                    <?php echo $class['manageable'] ? '' : 'disabled'; ?>
                                                                                >
                                                                                <button
                                                                                    type="button"
                                                                                    class="btn btn-subtle-success btn-sm wecoza-task-action"
                                                                                    data-action="complete"
                                                                                    <?php echo $class['manageable'] ? '' : 'disabled'; ?>
                                                                                >
                                                                                    <?php echo esc_html($task['complete_label']); ?>
                                                                                </button>
                                                                            </div>
                                                                        </li>
                                                                    <?php endforeach; ?>
                                                                </ul>
                                                                <div class="alert alert-light border-top mb-0 py-2 px-3 small text-body-secondary" data-empty="open" <?php echo empty($class['tasks']['open']) ? '' : 'hidden'; ?>>
                                                                    <?php echo esc_html__('All tasks are completed.', 'wecoza-events'); ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="col-12 col-lg-6">
                                                        <div class="card shadow-none border h-100">
                                                            <div class="card-header bg-body-tertiary py-2 px-3 border-bottom">
                                                                <h6 class="card-title fs-8 mb-0 text-body-tertiary"><?php echo esc_html__('Completed Tasks', 'wecoza-events'); ?></h6>
                                                            </div>
                                                            <div class="card-body p-0">
                                                                <ul class="list-group list-group-flush wecoza-task-list wecoza-task-list-completed" data-role="completed-list">
                                                                    <?php foreach ($class['tasks']['completed'] as $task): ?>
                                                                        <li class="list-group-item" data-task-id="<?php echo esc_attr($task['id']); ?>">
                                                                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                                                                <span class="fw-semibold text-body"><?php echo esc_html($task['label']); ?></span>
                                                                                <button
                                                                                    type="button"
                                                                                    class="btn btn-subtle-primary btn-sm wecoza-task-action"
                                                                                    data-action="reopen"
                                                                                    <?php echo $class['manageable'] ? '' : 'disabled'; ?>
                                                                                >
                                                                                    <?php echo esc_html($task['reopen_label']); ?>
                                                                                </button>
                                                                            </div>
                                                                            <div class="text-body-secondary mt-2">
                                                                                <?php echo esc_html(sprintf('%s • %s', $task['completed_by'], $task['completed_at'])); ?>
                                                                            </div>
                                                                            <?php if (!empty($task['note'])): ?>
                                                                                <div class="border rounded-2 bg-body-tertiary text-body-secondary mt-2 px-2 py-1">
                                                                                    <?php echo esc_html($task['note']); ?>
                                                                                </div>
                                                                            <?php endif; ?>
                                                                        </li>
                                                                    <?php endforeach; ?>
                                                                </ul>
                                                                <div class="alert alert-subtle-warning py-2 px-3 m-2" style="border-radius: 0;" data-empty="completed" <?php echo empty($class['tasks']['completed']) ? '' : 'hidden'; ?>>
                                                                    <?php echo esc_html__('No tasks completed yet.', 'wecoza-events'); ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return trim((string) ob_get_clean());
    }

    private static function wrapMessage(string $message): string
    {
        return '<div class="alert alert-warning" role="alert">' . $message . '</div>';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function fetchClasses(int $limit): array
    {
        $pdo = Connection::getPdo();
        $schema = Connection::getSchema();

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $schema)) {
            throw new RuntimeException('Invalid schema name.');
        }

        $classesTable = self::tableName($schema, 'classes');
        $clientsTable = self::tableName($schema, 'clients');
        $agentsTable = self::tableName($schema, 'agents');
        $logsTable = self::tableName($schema, 'class_change_logs');

        $sql = <<<SQL
SELECT
    c.class_id,
    c.client_id,
    c.class_type,
    c.class_subject,
    c.class_code,
    c.original_start_date,
    c.delivery_date,
    c.initial_class_agent,
    c.class_agent,
    ia.agent_id AS initial_agent_id,
    ia.first_name AS initial_agent_first,
    ia.surname AS initial_agent_surname,
    ia.initials AS initial_agent_initials,
    pa.agent_id AS primary_agent_id,
    pa.first_name AS primary_agent_first,
    pa.surname AS primary_agent_surname,
    pa.initials AS primary_agent_initials,
    c.exam_class,
    c.exam_type,
    c.seta_funded,
    COALESCE(c.seta, cl.seta) AS seta_name,
    c.stop_restart_dates,
    c.updated_at,
    cl.client_name,
    l.id AS log_id,
    l.operation,
    l.changed_at
FROM {$classesTable} c
LEFT JOIN {$clientsTable} cl ON cl.client_id = c.client_id
LEFT JOIN {$agentsTable} ia ON ia.agent_id = c.initial_class_agent
LEFT JOIN {$agentsTable} pa ON pa.agent_id = c.class_agent
LEFT JOIN LATERAL (
    SELECT id, operation, changed_at
    FROM {$logsTable} log
    WHERE log.class_id = c.class_id
    ORDER BY log.changed_at DESC
    LIMIT 1
) l ON TRUE
ORDER BY c.original_start_date DESC NULLS LAST, c.class_id DESC
LIMIT :limit;
SQL;

        $stmt = $pdo->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare class query.');
        }

        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to execute class query.');
        }

        $result = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $result[] = self::formatClassRow($row);
        }

        return $result;
    }

    private static function formatClassRow(array $row): array
    {
        $classId = (int) ($row['class_id'] ?? 0);
        $clientId = (int) ($row['client_id'] ?? 0);
        $clientName = trim((string) ($row['client_name'] ?? '')) ?: __('Unnamed client', 'wecoza-events');
        $type = strtoupper(trim((string) ($row['class_type'] ?? '')));
        $subject = trim((string) ($row['class_subject'] ?? ''));
        $code = trim((string) ($row['class_code'] ?? ''));
        $startDate = self::formatDatePair((string) ($row['original_start_date'] ?? ''));
        $dueDate = self::formatDueDate((string) ($row['delivery_date'] ?? ''), $startDate);
        $agentDisplay = self::formatAgentDisplay($row);
        $exam = self::formatExamLabel((bool) ($row['exam_class'] ?? false), (string) ($row['exam_type'] ?? ''));
        $status = self::deriveStatus($row, $startDate, $dueDate);
        $seta = self::formatSetaLabel((bool) ($row['seta_funded'] ?? false), (string) ($row['seta_name'] ?? ''));

        $logId = isset($row['log_id']) ? (int) $row['log_id'] : null;
        $operation = strtolower((string) ($row['operation'] ?? 'insert')) ?: 'insert';

        if ($logId !== null && $logId > 0) {
            $tasksCollection = self::taskManager()->getTasksWithTemplate($logId, $operation);
            $manageable = true;
        } else {
            $tasksCollection = self::templateRegistry()->getTemplateForOperation($operation);
            $manageable = false;
        }

        return [
            'id' => $classId,
            'code' => $code,
            'client' => [
                'id' => $clientId,
                'name' => $clientName,
            ],
            'type' => $type ?: __('N/A', 'wecoza-events'),
            'subject' => $subject ?: __('No subject', 'wecoza-events'),
            'event_date' => $startDate,
            'due_date' => $dueDate,
            'agent_display' => $agentDisplay,
            'exam' => $exam,
            'status' => $status,
            'seta' => $seta,
            'log_id' => $logId,
            'manageable' => $manageable,
            'tasks' => self::prepareTaskPayload($tasksCollection),
        ];
    }

    private static function formatAgentDisplay(array $row): string
    {
        $initialId = isset($row['initial_agent_id']) ? (int) $row['initial_agent_id'] : null;
        $primaryId = isset($row['primary_agent_id']) ? (int) $row['primary_agent_id'] : null;

        if ($initialId !== null && $initialId > 0) {
            $name = self::formatPersonName(
                (string) ($row['initial_agent_first'] ?? ''),
                (string) ($row['initial_agent_surname'] ?? ''),
                (string) ($row['initial_agent_initials'] ?? '')
            );
            return sprintf(__('%1$s · %2$s', 'wecoza-events'), $initialId, $name);
        }

        if ($primaryId !== null && $primaryId > 0) {
            $name = self::formatPersonName(
                (string) ($row['primary_agent_first'] ?? ''),
                (string) ($row['primary_agent_surname'] ?? ''),
                (string) ($row['primary_agent_initials'] ?? '')
            );
            return sprintf(__('Primary: %1$s · %2$s', 'wecoza-events'), '#' . $primaryId, $name);
        }

        return __('No agent assigned', 'wecoza-events');
    }

    private static function formatPersonName(string $first, string $surname, string $initials): string
    {
        $parts = array_filter([
            trim($first),
            trim($initials),
            trim($surname),
        ], static fn (string $value): bool => $value !== '');

        return $parts !== [] ? implode(' ', $parts) : __('Unnamed agent', 'wecoza-events');
    }

    private static function formatExamLabel(bool $isExam, string $examType): array
    {
        if ($isExam) {
            $label = __('Exam Class', 'wecoza-events');
            if ($examType !== '') {
                $label = sprintf('%s', $label);
            }

            return [
                'label' => $label,
                'class' => 'badge-phoenix-success',
            ];
        }

        return [
            'label' => __('Not Exam', 'wecoza-events'),
            'class' => 'badge-phoenix-secondary',
        ];
    }

    private static function deriveStatus(array $row, array $startDate, array $dueDate): array
    {
        $status = null;
        $variant = 'success';

        $raw = (string) ($row['stop_restart_dates'] ?? '');
        if ($raw !== '' && $raw !== '[]') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && $decoded !== []) {
                $last = array_pop($decoded);
                if (is_array($last) && isset($last['status'])) {
                    $statusValue = strtolower((string) $last['status']);
                    if ($statusValue === 'paused') {
                        $status = __('Paused', 'wecoza-events');
                        $variant = 'warning';
                    } elseif ($statusValue === 'stopped') {
                        $status = __('Stopped', 'wecoza-events');
                        $variant = 'danger';
                    }
                }
            }
        }

        if ($status === null) {
            $now = new DateTimeImmutable('now');
            try {
                if ($dueDate['iso'] !== '') {
                    $due = new DateTimeImmutable($dueDate['iso']);
                    if ($due < $now) {
                        $status = __('Completed', 'wecoza-events');
                        $variant = 'neutral';
                    }
                }
            } catch (Exception $exception) {
                // ignore
            }
        }

        if ($status === null) {
            try {
                if ($startDate['iso'] !== '') {
                    $start = new DateTimeImmutable($startDate['iso']);
                    if ($start > new DateTimeImmutable('now')) {
                        $status = __('Scheduled', 'wecoza-events');
                        $variant = 'info';
                    }
                }
            } catch (Exception $exception) {
                // ignore
            }
        }

        if ($status === null) {
            $status = __('Active', 'wecoza-events');
            $variant = 'success';
        }

        $classMap = [
            'success' => 'badge-phoenix-success',
            'warning' => 'badge-phoenix-warning',
            'danger' => 'badge-phoenix-danger',
            'info' => 'badge-phoenix-info',
            'neutral' => 'badge-phoenix-secondary',
        ];

        return [
            'label' => $status,
            'class' => $classMap[$variant] ?? 'badge-phoenix-secondary',
        ];
    }

    private static function formatSetaLabel(bool $funded, string $name): array
    {
        if ($funded) {
            if ($name !== '') {
                $label = sprintf('%s', $name);
            }

            return [
                'label' => $label,
                'class' => 'badge-phoenix-success',
            ];
        }

        return [
            'label' => __('Not SETA', 'wecoza-events'),
            'class' => 'badge-phoenix-secondary',
        ];
    }

    private static function formatDueDate(string $rawDelivery, array $startDate): array
    {
        if ($rawDelivery !== '') {
            return self::formatDatePair($rawDelivery);
        }

        return $startDate['iso'] !== '' ? $startDate : ['iso' => '', 'human' => ''];
    }

    private static function formatDatePair(string $timestamp): array
    {
        if ($timestamp === '') {
            return ['iso' => '', 'human' => __('No date', 'wecoza-events')];
        }

        try {
            $dt = new DateTimeImmutable($timestamp);
            return [
                'iso' => $dt->format(DateTimeInterface::ATOM),
                'human' => $dt->format('M j, Y'),
            ];
        } catch (Exception $exception) {
            return ['iso' => $timestamp, 'human' => $timestamp];
        }
    }

    public static function prepareTaskPayload(TaskCollection $tasks): array
    {
        $open = [];
        $completed = [];

        foreach ($tasks->all() as $task) {
            $payload = [
                'id' => $task->getId(),
                'label' => $task->getLabel(),
            ];

            if ($task->isCompleted()) {
                $payload['completed_by'] = self::resolveUserName($task->getCompletedBy());
                $payload['completed_at'] = self::formatCompletedAt($task->getCompletedAt());
                $payload['note'] = $task->getNote();
                $payload['reopen_label'] = __('Reopen', 'wecoza-events');
                $completed[] = $payload;
            } else {
                $payload['note_label'] = __('Completion note', 'wecoza-events');
                $payload['note_placeholder'] = __('Note (optional)', 'wecoza-events');
                $payload['complete_label'] = __('Complete', 'wecoza-events');
                $open[] = $payload;
            }
        }

        return [
            'open' => $open,
            'completed' => $completed,
        ];
    }

    private static function resolveUserName(?int $userId): string
    {
        if ($userId === null || $userId <= 0) {
            return __('Unknown user', 'wecoza-events');
        }

        if (isset(self::$userNameCache[$userId])) {
            return self::$userNameCache[$userId];
        }

        $user = get_userdata($userId);
        $name = $user?->display_name ?? $user?->user_login ?? __('Unknown user', 'wecoza-events');
        self::$userNameCache[$userId] = $name;
        return $name;
    }

    private static function formatCompletedAt(?string $timestamp): string
    {
        if ($timestamp === null || $timestamp === '') {
            return __('Unknown time', 'wecoza-events');
        }

        return mysql2date('M j, Y H:i', $timestamp, true);
    }

    private static function maybePrintAssets(): void
    {
        if (self::$assetsPrinted) {
            return;
        }

        self::$assetsPrinted = true;
        ?>
        <script>
            (function() {
                function escapeHtml(value) {
                    return String(value ?? '').replace(/[&<>"']/g, function(match) {
                        switch (match) {
                            case '&': return '&amp;';
                            case '<': return '&lt;';
                            case '>': return '&gt;';
                            case '"': return '&quot;';
                            case "'": return '&#039;';
                        }
                        return match;
                    });
                }

                function buildOpenTaskHtml(task, classId, disabled) {
                    var noteId = 'wecoza-note-' + classId + '-' + task.id;
                    return '' +
                        '<li class="list-group-item d-flex flex-row align-items-center justify-content-between gap-2 m-1" data-task-id="' + escapeHtml(task.id) + '">' +
                            '<div class="fw-semibold text-body w-30">' + escapeHtml(task.label) + '</div>' +
                            '<div class="d-flex flex-row gap-2 align-items-center flex-grow-1">' +
                                '<label class="visually-hidden" for="' + escapeHtml(noteId) + '">' + escapeHtml(task.note_label) + '</label>' +
                                '<input id="' + escapeHtml(noteId) + '" class="form-control form-control-sm wecoza-task-note" type="text" placeholder="' + escapeHtml(task.note_placeholder) + '" ' + (disabled ? 'disabled' : '') + '>' +
                                '<button type="button" class="btn btn-subtle-success btn-sm wecoza-task-action" data-action="complete" ' + (disabled ? 'disabled' : '') + '>' + escapeHtml(task.complete_label) + '</button>' +
                            '</div>' +
                        '</li>';
                }

                function buildCompletedTaskHtml(task, disabled) {
                    return '' +
                        '<li class="list-group-item" data-task-id="' + escapeHtml(task.id) + '">' +
                            '<div class="d-flex flex-wrap align-items-center justify-content-between gap-2">' +
                                '<span class="fw-semibold text-body">' + escapeHtml(task.label) + '</span>' +
                                '<button type="button" class="btn btn-subtle-primary btn-sm wecoza-task-action" data-action="reopen" ' + (disabled ? 'disabled' : '') + '>' + escapeHtml(task.reopen_label) + '</button>' +
                            '</div>' +
                            '<div class="text-body-secondary small mt-2">' + escapeHtml(task.completed_by + ' • ' + task.completed_at) + '</div>' +
                            (task.note ? '<div class="border rounded-2 bg-body-tertiary text-body-secondary mt-2 px-2 py-1">' + escapeHtml(task.note) + '</div>' : '') +
                        '</li>';
                }

                function updateEmptyState(panel, type, isEmpty) {
                    var indicator = panel.querySelector('[data-empty="' + type + '"]');
                    if (!indicator) {
                        return;
                    }

                    if (isEmpty) {
                        indicator.removeAttribute('hidden');
                    } else {
                        indicator.setAttribute('hidden', 'hidden');
                    }
                }

                document.addEventListener('click', function(event) {
                    var toggle = event.target.closest('.wecoza-task-toggle');
                    if (toggle) {
                        event.preventDefault();

                        var container = toggle.closest('.wecoza-event-tasks');
                        if (!container) {
                            return;
                        }

                        var targetId = toggle.getAttribute('data-target');
                        if (!targetId) {
                            return;
                        }

                        var panel = container.querySelector('.wecoza-task-panel-row[data-panel-id="' + targetId + '"]');
                        if (!panel) {
                            return;
                        }

                        var shouldOpen = panel.hasAttribute('hidden');

                        container.querySelectorAll('.wecoza-task-panel-row').forEach(function(row) {
                            row.setAttribute('hidden', 'hidden');
                        });

                        container.querySelectorAll('.wecoza-task-toggle').forEach(function(btn) {
                            btn.setAttribute('aria-expanded', 'false');
                        });

                        if (shouldOpen) {
                            panel.removeAttribute('hidden');
                            toggle.setAttribute('aria-expanded', 'true');
                        }

                        return;
                    }

                    var button = event.target.closest('.wecoza-task-action');
                    if (!button) {
                        return;
                    }

                    event.preventDefault();

                    var panelRow = button.closest('.wecoza-task-panel-row');
                    var panel = panelRow ? panelRow.querySelector('.wecoza-task-panel-content') : null;
                    var wrapper = button.closest('.wecoza-event-tasks');
                    if (!panel || !wrapper) {
                        return;
                    }

                    if (panel.dataset.manageable !== '1') {
                        window.alert('<?php echo esc_js(__('Tasks cannot be updated for this class yet.', 'wecoza-events')); ?>');
                        return;
                    }

                    var taskItem = button.closest('[data-task-id]');
                    if (!taskItem) {
                        return;
                    }

                    var formData = new FormData();
                    formData.append('action', 'wecoza_events_task_update');
                    formData.append('nonce', wrapper.dataset.nonce);
                    formData.append('log_id', panel.dataset.logId || '');
                    formData.append('task_id', taskItem.dataset.taskId || '');
                    formData.append('task_action', button.dataset.action || '');

                    if (button.dataset.action === 'complete') {
                        var noteField = taskItem.querySelector('.wecoza-task-note');
                        formData.append('note', noteField ? noteField.value : '');
                    }

                    button.disabled = true;

                    fetch(wrapper.dataset.ajaxUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: formData
                    }).then(function(response) {
                        if (!response.ok) {
                            throw new Error('Network error');
                        }
                        return response.json();
                    }).then(function(payload) {
                        if (!payload || !payload.success) {
                            throw new Error(payload && payload.data && payload.data.message ? payload.data.message : 'Request failed');
                        }

                        var data = payload.data;
                        var disabled = panel.dataset.manageable !== '1';
                        var openList = panel.querySelector('[data-role="open-list"]');
                        var completedList = panel.querySelector('[data-role="completed-list"]');
                        if (openList) {
                            openList.innerHTML = data.tasks.open.map(function(task) { return buildOpenTaskHtml(task, panel.dataset.classId, disabled); }).join('');
                            updateEmptyState(panel, 'open', data.tasks.open.length === 0);
                        }
                        if (completedList) {
                            completedList.innerHTML = data.tasks.completed.map(function(task) { return buildCompletedTaskHtml(task, disabled); }).join('');
                            updateEmptyState(panel, 'completed', data.tasks.completed.length === 0);
                        }
                    }).catch(function(error) {
                        window.alert(error.message || '<?php echo esc_js(__('Unable to update task.', 'wecoza-events')); ?>');
                    }).finally(function() {
                        button.disabled = false;
                    });
                });
            })();
        </script>
        <?php
    }
    private static function taskManager(): TaskManager
    {
        if (self::$taskManager === null) {
            self::$taskManager = new TaskManager();
        }

        return self::$taskManager;
    }

    private static function templateRegistry(): TaskTemplateRegistry
    {
        if (self::$templateRegistry === null) {
            self::$templateRegistry = new TaskTemplateRegistry();
        }

        return self::$templateRegistry;
    }

    private static function tableName(string $schema, string $table): string
    {
        return self::quoteIdentifier($schema) . '.' . self::quoteIdentifier($table);
    }

    private static function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}
