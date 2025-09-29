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
use function array_unique;
use function ctype_digit;
use function esc_attr;
use function esc_attr__;
use function esc_html;
use function esc_html__;
use function esc_url;
use function get_userdata;
use function is_array;
use function json_decode;
use function mysql2date;
use function natcasesort;
use function preg_match;
use function preg_replace;
use function remove_query_arg;
use function implode;
use function sanitize_text_field;
use function shortcode_atts;
use function sprintf;
use function str_replace;
use function strtolower;
use function strtoupper;
use function trim;
use function uniqid;
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

        $sortParam = isset($_GET['wecoza_tasks_sort']) ? sanitize_text_field((string) $_GET['wecoza_tasks_sort']) : '';
        $sortDirection = $sortParam === 'start_asc' ? 'asc' : 'desc';
        $classIdParam = isset($_GET['class_id']) ? sanitize_text_field((string) $_GET['class_id']) : '';
        $classIdFilter = ctype_digit($classIdParam) ? (int) $classIdParam : null;
        $prioritiseOpen = $sortParam === '' && $classIdFilter === null;

        try {
            $rows = self::fetchClasses($limit, $sortDirection, $prioritiseOpen, $classIdFilter);
        } catch (RuntimeException $exception) {
            return self::wrapMessage(
                sprintf(
                    esc_html__('Unable to load tasks: %s', 'wecoza-events'),
                    esc_html($exception->getMessage())
                )
            );
        }

        if ($rows === []) {
            if ($classIdFilter !== null) {
                return self::wrapMessage(
                    sprintf(
                        esc_html__('No tasks are available for class #%d.', 'wecoza-events'),
                        $classIdFilter
                    )
                );
            }

            return self::wrapMessage(esc_html__('No classes available.', 'wecoza-events'));
        }

        $classSpecific = $classIdFilter !== null;
        $openTaskOptions = $classSpecific ? [] : self::collectOpenTaskLabels($rows);
        $instanceId = uniqid('wecoza-tasks-');
        $searchInputId = $instanceId . '-search';
        $openTaskSelectId = $instanceId . '-open-task';
        $viewAllUrl = $classSpecific ? esc_url(remove_query_arg('class_id')) : '';

        $nonce = wp_create_nonce('wecoza_events_tasks');
        $ajaxUrl = admin_url('admin-ajax.php');

        $sortIconClass = $sortDirection === 'asc' ? 'bi-sort-up' : 'bi-sort-down';

        ob_start();
        echo self::getAssets();
        ?>
        <div
            class="wecoza-event-tasks"
            data-nonce="<?php echo esc_attr($nonce); ?>"
            data-ajax-url="<?php echo esc_attr($ajaxUrl); ?>"
            data-instance-id="<?php echo esc_attr($instanceId); ?>"
            data-sort-direction="<?php echo esc_attr($sortDirection); ?>"
            data-open-label-template="<?php echo esc_attr__('Open +%d', 'wecoza-events'); ?>"
            data-complete-label="<?php echo esc_attr__('Completed', 'wecoza-events'); ?>"
            data-open-badge-class="badge-phoenix-warning"
            data-complete-badge-class="badge-phoenix-secondary"
            data-class-filter="<?php echo esc_attr($classIdFilter !== null ? (string) $classIdFilter : ''); ?>"
        >
            <div class="card shadow-none my-3">
                <div class="card-header p-3 border-bottom">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <h4 class="text-body mb-0">
                                <?php
                                echo esc_html(
                                    $classSpecific
                                        ? sprintf(__('Tasks for Class #%d', 'wecoza-events'), $classIdFilter)
                                        : __('Class Tasks', 'wecoza-events')
                                );
                                ?>
                            </h4>
                            <?php if ($classSpecific && $viewAllUrl !== ''): ?>
                                <a class="btn btn-link btn-sm px-0" href="<?php echo $viewAllUrl; ?>">
                                    <?php echo esc_html__('View all classes', 'wecoza-events'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                        <?php $count = count($rows); ?>
                        <span class="badge badge-phoenix fs-10 badge-phoenix-primary">
                            <?php echo esc_html(sprintf(_n('%d class', '%d classes', $count, 'wecoza-events'), $count)); ?>
                        </span>
                    </div>
                    <?php if (!$classSpecific): ?>
                        <div class="d-flex flex-wrap align-items-start gap-2 mt-3">
                            <div class="search-box flex-grow-1">
                                <form class="position-relative" role="search" data-role="tasks-filter-form">
                                    <label class="visually-hidden" for="<?php echo esc_attr($searchInputId); ?>"><?php echo esc_html__('Search classes', 'wecoza-events'); ?></label>
                                    <input
                                        id="<?php echo esc_attr($searchInputId); ?>"
                                        class="form-control search-input form-control-sm ps-5"
                                        type="search"
                                        placeholder="<?php echo esc_attr__('Search clients, classes, or agents', 'wecoza-events'); ?>"
                                        autocomplete="off"
                                        data-role="tasks-search"
                                    >
                                    <span class="search-box-icon" aria-hidden="true">
                                        <i class="bi bi-search"></i>
                                    </span>
                                </form>
                            </div>
                            <div class="flex-grow-1 flex-sm-grow-0" style="min-width: 180px;">
                                <label class="visually-hidden" for="<?php echo esc_attr($openTaskSelectId); ?>"><?php echo esc_html__('Filter by open task', 'wecoza-events'); ?></label>
                                <select
                                    id="<?php echo esc_attr($openTaskSelectId); ?>"
                                    class="form-select form-select-sm"
                                    data-role="open-task-filter"
                                    <?php echo $openTaskOptions === [] ? 'disabled' : ''; ?>
                                >
                                    <option value=""><?php echo esc_html__('All open tasks', 'wecoza-events'); ?></option>
                                    <?php foreach ($openTaskOptions as $optionLabel): ?>
                                        <?php $optionValue = self::normaliseForToken($optionLabel); ?>
                                        <option value="<?php echo esc_attr($optionValue); ?>"><?php echo esc_html($optionLabel); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <div
                        class="px-3 py-2 border-bottom"
                        data-role="filter-status"
                        data-status-template="<?php echo esc_attr__('Showing %1$d of %2$d classes', 'wecoza-events'); ?>"
                        data-empty-message="<?php echo esc_attr__('No classes match the current filters.', 'wecoza-events'); ?>"
                        data-match-template="<?php echo esc_attr__('Showing %1$d of %2$d classes matching "%3$s"', 'wecoza-events'); ?>"
                        data-match-filter-template="<?php echo esc_attr__('Showing %1$d of %2$d classes matching "%3$s" with filters', 'wecoza-events'); ?>"
                        data-filter-template="<?php echo esc_attr__('Showing %1$d of %2$d classes with filters applied', 'wecoza-events'); ?>"
                        hidden
                    ></div>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm fs-9 mb-0 overflow-hidden" id="wecoza-event-tasks-table">
                            <thead class="border-bottom">
                                <tr>
                                    <th scope="col" class="border-0 ps-4"><?php echo esc_html__('ID', 'wecoza-events'); ?><i class="bi bi-hash ms-1"></i></th>
                                    <th scope="col" class="border-0"><?php echo esc_html__('Task Status', 'wecoza-events'); ?><i class="bi bi-activity ms-1"></i></th>
                                    <th scope="col" class="border-0"><?php echo esc_html__('Change', 'wecoza-events'); ?><i class="bi bi-arrow-repeat ms-1"></i></th>
                                    <th scope="col" class="border-0"><?php echo esc_html__('Client ID & Name', 'wecoza-events'); ?><i class="bi bi-building ms-1"></i></th>
                                    <th scope="col" class="border-0"><?php echo esc_html__('Type', 'wecoza-events'); ?><i class="bi bi-tag ms-1"></i></th>
                                    <th scope="col" class="border-0"><?php echo esc_html__('Subject', 'wecoza-events'); ?><i class="bi bi-book ms-1"></i></th>
                                    <th scope="col" class="border-0">
                                        <span class="d-inline-flex align-items-center gap-1">
                                            <?php echo esc_html__('Start Date', 'wecoza-events'); ?>
                                            <i class="bi bi-calendar-date"></i>
                                            <?php if (!$classSpecific): ?>
                                                <button
                                                    type="button"
                                                    class="btn btn-link btn-sm p-0 text-decoration-none align-baseline"
                                                    data-role="sort-toggle"
                                                    data-sort-target="start"
                                                    aria-label="<?php echo esc_attr__('Toggle start date sort order', 'wecoza-events'); ?>"
                                                >
                                                    <i class="bi <?php echo esc_attr($sortIconClass); ?>"></i>
                                                </button>
                                            <?php endif; ?>
                                        </span>
                                    </th>
                                    <th scope="col" class="border-0"><?php echo esc_html__('Agent ID & Name', 'wecoza-events'); ?><i class="bi bi-person ms-1"></i></th>
                                    <th scope="col" class="border-0"><?php echo esc_html__('Exam Class', 'wecoza-events'); ?><i class="bi bi-mortarboard ms-1"></i></th>
                                    <th scope="col" class="border-0"><?php echo esc_html__('SETA', 'wecoza-events'); ?><i class="bi bi-award ms-1"></i></th>
                                    <th scope="col" class="border-0 text-end pe-4"><?php echo esc_html__('Actions', 'wecoza-events'); ?><i class="bi bi-gear ms-1"></i></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $class): ?>
                                    <?php
                                        $searchBaseParts = self::buildSearchBaseParts($class);
                                        $searchBase = implode(' ', $searchBaseParts);
                                        $searchIndex = self::buildSearchIndexFromParts($class, $searchBaseParts);
                                        $openTaskTokens = self::buildOpenTaskTokens($class);
                                    ?>
                                    <tr
                                        data-role="class-row"
                                        data-search-base="<?php echo esc_attr($searchBase); ?>"
                                        data-search-index="<?php echo esc_attr($searchIndex); ?>"
                                        data-open-tasks="<?php echo esc_attr($openTaskTokens); ?>"
                                        data-status-label="<?php echo esc_attr(self::normaliseForIndex((string) ($class['status']['label'] ?? ''))); ?>"
                                        data-class-id="<?php echo esc_attr((string) $class['id']); ?>"
                                        data-log-id="<?php echo esc_attr($class['log_id'] ?? ''); ?>"
                                        data-manageable="<?php echo $class['manageable'] ? '1' : '0'; ?>"
                                        data-panel-id="task-panel-<?php echo esc_attr((string) $class['id']); ?>"
                                    >
                                        <td class="py-2 align-middle text-center fs-8 white-space-nowrap">
                                                <span class="badge fs-10 badge-phoenix badge-phoenix-secondary">#<?php echo esc_html((string) $class['id']); ?></span>
                                        </td>
                                        <td data-role="status-cell">
                                            <span class="badge badge-phoenix fs-10 <?php echo esc_attr($class['status']['class']); ?>" data-role="status-badge">
                                                <?php echo esc_html($class['status']['label']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-phoenix fs-10 <?php echo esc_attr($class['change']['class']); ?>">
                                                <?php echo esc_html($class['change']['label']); ?>
                                            </span>
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
                                        <td colspan="11" class="bg-body-tertiary">
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
                                                        <div class="card shadow-none h-100">
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
                                                                <div class="alert alert-subtle-primary py-2 px-3 m-2" style="border-radius: 0;" data-empty="open" <?php echo empty($class['tasks']['open']) ? '' : 'hidden'; ?>>
                                                                    <?php echo esc_html__('All tasks are completed.', 'wecoza-events'); ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="col-12 col-lg-6">
                                                        <div class="card shadow-none h-100">
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
                                <tr data-role="no-results" hidden>
                                    <td colspan="11" class="text-center py-4 text-body-secondary">
                                        <?php echo esc_html__('No classes match the current filters.', 'wecoza-events'); ?>
                                    </td>
                                </tr>
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
    private static function fetchClasses(int $limit, string $sortDirection, bool $prioritiseOpen, ?int $classIdFilter): array
    {
        $pdo = Connection::getPdo();
        $schema = Connection::getSchema();

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $schema)) {
            throw new RuntimeException('Invalid schema name.');
        }

        $orderDirection = strtolower($sortDirection) === 'asc' ? 'ASC' : 'DESC';

        $classesTable = self::tableName($schema, 'classes');
        $clientsTable = self::tableName($schema, 'clients');
        $agentsTable = self::tableName($schema, 'agents');
        $logsTable = self::tableName($schema, 'class_change_logs');

        $whereClause = '';
        if ($classIdFilter !== null) {
            $whereClause = 'WHERE c.class_id = :class_id';
        }

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
JOIN LATERAL (
    SELECT id, operation, changed_at
    FROM {$logsTable} log
    WHERE log.class_id = c.class_id
      AND LOWER(log.operation) IN ('insert', 'update')
    ORDER BY log.changed_at DESC
    LIMIT 1
) l ON TRUE
{$whereClause}
ORDER BY c.original_start_date {$orderDirection} NULLS LAST, c.class_id {$orderDirection}
LIMIT :limit;
SQL;

        $stmt = $pdo->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare class query.');
        }

        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        if ($classIdFilter !== null) {
            $stmt->bindValue(':class_id', $classIdFilter, \PDO::PARAM_INT);
        }
        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to execute class query.');
        }

        $result = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $result[] = self::formatClassRow($row);
        }

        if ($prioritiseOpen) {
            $open = [];
            $completed = [];
            foreach ($result as $payload) {
                if (($payload['open_count'] ?? 0) > 0) {
                    $open[] = $payload;
                } else {
                    $completed[] = $payload;
                }
            }
            $result = array_merge($open, $completed);
        }

        foreach ($result as &$payload) {
            unset($payload['open_count']);
        }
        unset($payload);

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
        $seta = self::formatSetaLabel((bool) ($row['seta_funded'] ?? false), (string) ($row['seta_name'] ?? ''));

        $logId = isset($row['log_id']) ? (int) $row['log_id'] : null;
        $operation = strtolower((string) ($row['operation'] ?? 'insert')) ?: 'insert';
        $change = self::formatChangeBadge($operation);

        if ($logId !== null && $logId > 0) {
            $tasksCollection = self::taskManager()->getTasksWithTemplate($logId, $operation);
            $manageable = true;
        } else {
            $tasksCollection = self::templateRegistry()->getTemplateForOperation($operation);
            $manageable = false;
        }

        $tasksPayload = self::prepareTaskPayload($tasksCollection);
        $openCount = count($tasksPayload['open'] ?? []);
        $status = self::formatTaskStatusBadge($openCount);

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
            'change' => $change,
            'log_id' => $logId,
            'manageable' => $manageable,
            'tasks' => $tasksPayload,
            'open_count' => $openCount,
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

    private static function formatTaskStatusBadge(int $openCount): array
    {
        if ($openCount > 0) {
            return [
                'label' => sprintf(__('Open +%d', 'wecoza-events'), $openCount),
                'class' => 'badge-phoenix-warning',
            ];
        }

        return [
            'label' => strtoupper(__('Completed', 'wecoza-events')),
            'class' => 'badge-phoenix-secondary',
        ];
    }

    private static function formatChangeBadge(string $operation): array
    {
        $value = strtolower(trim($operation));

        switch ($value) {
            case 'insert':
                $label = strtoupper(__('Insert', 'wecoza-events'));
                $class = 'badge-phoenix-success';
                break;
            case 'update':
                $label = strtoupper(__('Update', 'wecoza-events'));
                $class = 'badge-phoenix-primary';
                break;
            default:
                $labelBase = $value !== '' ? ucfirst($value) : __('Unknown', 'wecoza-events');
                $label = strtoupper($labelBase);
                $class = 'badge-phoenix-secondary';
                break;
        }

        return [
            'value' => $value !== '' ? $value : 'unknown',
            'label' => $label,
            'class' => $class,
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

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, string>
     */
    private static function collectOpenTaskLabels(array $rows): array
    {
        $labels = [];

        foreach ($rows as $row) {
            $tasks = $row['tasks']['open'] ?? [];
            if (!is_array($tasks)) {
                continue;
            }

            foreach ($tasks as $task) {
                if (!is_array($task)) {
                    continue;
                }

                $label = trim((string) ($task['label'] ?? ''));
                if ($label === '') {
                    continue;
                }

                $labels[$label] = true;
            }
        }

        $unique = array_keys($labels);
        natcasesort($unique);

        return array_values($unique);
    }

    private static function buildSearchIndexString(array $class): string
    {
        $baseParts = self::buildSearchBaseParts($class);

        return self::buildSearchIndexFromParts($class, $baseParts);
    }

    private static function buildSearchBaseString(array $class): string
    {
        return implode(' ', self::buildSearchBaseParts($class));
    }

    /**
     * @return array<int, string>
     */
    private static function buildSearchBaseParts(array $class): array
    {
        $parts = [];
        $parts[] = (string) ($class['id'] ?? '');
        $parts[] = (string) ($class['code'] ?? '');
        $parts[] = (string) ($class['subject'] ?? '');
        $parts[] = (string) ($class['type'] ?? '');
        $parts[] = (string) ($class['client']['id'] ?? '');
        $parts[] = (string) ($class['client']['name'] ?? '');
        $parts[] = (string) ($class['agent_display'] ?? '');
        $parts[] = (string) ($class['seta']['label'] ?? '');
        $parts[] = (string) ($class['change']['label'] ?? '');
        $parts[] = (string) ($class['change']['value'] ?? '');

        $parts = array_filter(array_map([self::class, 'normaliseForIndex'], $parts), static fn (string $value): bool => $value !== '');

        return array_values(array_unique($parts));
    }

    /**
     * @param array<int, string> $baseParts
     */
    private static function buildSearchIndexFromParts(array $class, array $baseParts): string
    {
        $tokens = $baseParts;

        $openTasks = $class['tasks']['open'] ?? [];
        if (is_array($openTasks)) {
            foreach ($openTasks as $task) {
                if (is_array($task) && isset($task['label'])) {
                    $token = self::normaliseForIndex((string) $task['label']);
                    if ($token !== '') {
                        $tokens[] = $token;
                    }
                }
            }
        }

        if (isset($class['status']['label'])) {
            $statusToken = self::normaliseForIndex((string) $class['status']['label']);
            if ($statusToken !== '') {
                $tokens[] = $statusToken;
            }
        }

        if ($tokens === []) {
            return '';
        }

        $tokens = array_values(array_unique($tokens));

        return implode(' ', $tokens);
    }

    private static function buildOpenTaskTokens(array $class): string
    {
        $openTasks = $class['tasks']['open'] ?? [];
        if (!is_array($openTasks) || $openTasks === []) {
            return '';
        }

        $tokens = [];
        foreach ($openTasks as $task) {
            if (!is_array($task)) {
                continue;
            }

            $token = self::normaliseForToken((string) ($task['label'] ?? ''));
            if ($token !== '') {
                $tokens[] = $token;
            }
        }

        if ($tokens === []) {
            return '';
        }

        $tokens = array_values(array_unique($tokens));

        return implode('|', $tokens);
    }

    private static function normaliseForToken(string $value): string
    {
        return self::normaliseForIndex($value);
    }

    private static function normaliseForIndex(string $value): string
    {
        $value = str_replace('|', ' ', strtolower(trim($value)));
        $value = preg_replace('/\s+/', ' ', $value);

        return $value === null ? '' : $value;
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

    private static function getAssets(): string
    {
        if (self::$assetsPrinted) {
            return '';
        }

        self::$assetsPrinted = true;

        ob_start();
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

                function normaliseToken(value) {
                    return String(value || '')
                        .toLowerCase()
                        .replace(/\|/g, ' ')
                        .replace(/\s+/g, ' ')
                        .trim();
                }

                function normaliseSearch(value) {
                    return String(value || '')
                        .toLowerCase()
                        .replace(/\s+/g, ' ')
                        .trim();
                }

                function applyTaskFilters(container) {
                    if (!container) {
                        return;
                    }

                    var searchInput = container.querySelector('[data-role="tasks-search"]');
                    var select = container.querySelector('[data-role="open-task-filter"]');
                    var status = container.querySelector('[data-role="filter-status"]');
                    var noResultsRow = container.querySelector('[data-role="no-results"]');

                    var searchTerm = searchInput ? normaliseSearch(searchInput.value) : '';
                    var selectedTask = select ? normaliseToken(select.value) : '';
                    var filtersActive = searchTerm !== '' || selectedTask !== '';

                    var rows = container.querySelectorAll('tr[data-role="class-row"]');
                    var total = rows.length;
                    var visible = 0;

                    rows.forEach(function(row) {
                        var matches = true;
                        var searchIndex = row.getAttribute('data-search-index') || '';

                        if (searchTerm !== '') {
                            matches = searchIndex.indexOf(searchTerm) !== -1;
                        }

                        if (matches && selectedTask !== '') {
                            var tokens = row.getAttribute('data-open-tasks') || '';
                            tokens = tokens ? tokens.split('|') : [];
                            matches = tokens.indexOf(selectedTask) !== -1;
                        }

                        if (matches) {
                            row.removeAttribute('hidden');
                            visible += 1;
                        } else {
                            row.setAttribute('hidden', 'hidden');
                        }

                        var panelId = row.getAttribute('data-panel-id');
                        if (!matches && panelId) {
                            var panelRow = container.querySelector('.wecoza-task-panel-row[data-panel-id="' + panelId + '"]');
                            if (panelRow) {
                                panelRow.setAttribute('hidden', 'hidden');
                            }
                        }
                    });

                    if (noResultsRow) {
                        if (visible === 0 && total > 0) {
                            noResultsRow.removeAttribute('hidden');
                        } else {
                            noResultsRow.setAttribute('hidden', 'hidden');
                        }
                    }

                    if (status) {
                        if (!filtersActive) {
                            status.setAttribute('hidden', 'hidden');
                            status.textContent = '';
                            status.className = '';
                        } else {
                            var searchActive = searchTerm !== '';
                            var message;

                            if (visible === 0) {
                                message = status.getAttribute('data-empty-message') || '';
                            } else if (searchActive && selectedTask !== '') {
                                var matchFilterTemplate = status.getAttribute('data-match-filter-template') || '';
                                message = matchFilterTemplate ? matchFilterTemplate.replace('%1$d', visible).replace('%2$d', total).replace('%3$s', searchTerm) : visible + ' / ' + total;
                            } else if (searchActive) {
                                var matchTemplate = status.getAttribute('data-match-template') || '';
                                message = matchTemplate ? matchTemplate.replace('%1$d', visible).replace('%2$d', total).replace('%3$s', searchTerm) : visible + ' / ' + total;
                            } else {
                                var filterTemplate = status.getAttribute('data-filter-template') || '';
                                message = filterTemplate ? filterTemplate.replace('%1$d', visible).replace('%2$d', total) : visible + ' / ' + total;
                            }

                            status.textContent = message;
                            status.className = 'badge badge-phoenix badge-phoenix-primary text-uppercase fs-9 mb-2';
                            status.removeAttribute('hidden');
                        }
                    }
                }

                function initTaskFilters(container) {
                    if (!container || container.dataset.filtersInitialised === '1') {
                        return;
                    }

                    if (container.dataset.classFilter) {
                        container.dataset.filtersInitialised = '1';
                        return;
                    }

                    container.dataset.filtersInitialised = '1';

                    var searchInput = container.querySelector('[data-role="tasks-search"]');
                    var select = container.querySelector('[data-role="open-task-filter"]');
                    var form = container.querySelector('[data-role="tasks-filter-form"]');

                    var handler = function() {
                        applyTaskFilters(container);
                    };

                    if (form) {
                        form.addEventListener('submit', function(event) {
                            event.preventDefault();
                            handler();
                        });
                    }

                    if (searchInput) {
                        searchInput.addEventListener('input', handler);
                    }

                    if (select) {
                        select.addEventListener('change', handler);
                    }

                    handler();
                }

                function updateRowFilterMetadata(container, panelRow, openTasks) {
                    if (!container || !panelRow) {
                        return;
                    }

                    var panelId = panelRow.getAttribute('data-panel-id');
                    if (!panelId) {
                        return;
                    }

                    var summaryRow = container.querySelector('tr[data-role="class-row"][data-panel-id="' + panelId + '"]');
                    if (!summaryRow) {
                        return;
                    }

                    var tokens = Array.isArray(openTasks) ? openTasks.map(function(task) {
                        if (!task || typeof task !== 'object') {
                            return '';
                        }
                        return normaliseToken(task.label);
                    }).filter(function(token) {
                        return token !== '';
                    }) : [];

                    summaryRow.setAttribute('data-open-tasks', tokens.join('|'));

                    var base = summaryRow.getAttribute('data-search-base') || '';
                    var searchIndex = base;

                    var statusValue = summaryRow.getAttribute('data-status-label') || '';
                    if (statusValue) {
                        searchIndex = searchIndex ? searchIndex + ' ' + statusValue : statusValue;
                    }

                    tokens.forEach(function(token) {
                        if (token && searchIndex.indexOf(token) === -1) {
                            searchIndex = searchIndex ? searchIndex + ' ' + token : token;
                        }
                    });

                    summaryRow.setAttribute('data-search-index', searchIndex);
                }

                function ready(callback) {
                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', function onReady() {
                            document.removeEventListener('DOMContentLoaded', onReady);
                            callback();
                        });
                    } else {
                        callback();
                    }
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

                function updateSummaryStatus(container, panelRow, openTasks) {
                    if (!container || !panelRow) {
                        return;
                    }

                    var panelId = panelRow.getAttribute('data-panel-id');
                    if (!panelId) {
                        return;
                    }

                    var summaryRow = container.querySelector('tr[data-role="class-row"][data-panel-id="' + panelId + '"]');
                    if (!summaryRow) {
                        return;
                    }

                    var badge = summaryRow.querySelector('[data-role="status-badge"]');
                    if (!badge) {
                        return;
                    }

                    var openCount = Array.isArray(openTasks) ? openTasks.length : 0;
                    var openTemplate = container.getAttribute('data-open-label-template') || 'Open +%d';
                    var completeLabel = container.getAttribute('data-complete-label') || 'Completed';
                    var openClass = container.getAttribute('data-open-badge-class') || 'badge-phoenix-warning';
                    var completeClass = container.getAttribute('data-complete-badge-class') || 'badge-phoenix-secondary';

                    var label;
                    var variant;
                    if (openCount > 0) {
                        if (openTemplate.indexOf('%d') !== -1) {
                            label = openTemplate.replace('%d', openCount);
                        } else {
                            label = openTemplate + ' ' + openCount;
                        }
                        variant = openClass;
                    } else {
                        label = completeLabel.toUpperCase();
                        variant = completeClass;
                    }

                    badge.textContent = label;

                    var classes = badge.className.split(' ').filter(function(name) {
                        return name && !/^badge-phoenix-/.test(name);
                    });
                    classes.push(variant);
                    badge.className = classes.join(' ');

                    summaryRow.setAttribute('data-status-label', normaliseToken(label));
                }

                ready(function() {
                    document.querySelectorAll('.wecoza-event-tasks').forEach(initTaskFilters);
                });

                document.addEventListener('click', function(event) {
                    var sortToggle = event.target.closest('[data-role="sort-toggle"]');
                    if (sortToggle) {
                        event.preventDefault();

                        var wrapper = sortToggle.closest('.wecoza-event-tasks');
                        if (!wrapper) {
                            return;
                        }

                        if (wrapper.getAttribute('data-class-filter')) {
                            return;
                        }

                        var currentSort = (wrapper.getAttribute('data-sort-direction') || 'desc').toLowerCase();
                        var nextSort = currentSort === 'asc' ? 'desc' : 'asc';

                        var url = new URL(window.location.href);
                        url.searchParams.set('wecoza_tasks_sort', nextSort === 'asc' ? 'start_asc' : 'start_desc');
                        url.searchParams.delete('paged');
                        url.searchParams.delete('page');

                        window.location.href = url.toString();
                        return;
                    }

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

                        updateSummaryStatus(wrapper, panelRow, data.tasks.open || []);
                        updateRowFilterMetadata(wrapper, panelRow, data.tasks.open || []);
                        applyTaskFilters(wrapper);
                    }).catch(function(error) {
                        window.alert(error.message || '<?php echo esc_js(__('Unable to update task.', 'wecoza-events')); ?>');
                    }).finally(function() {
                        button.disabled = false;
                    });
                });
            })();
        </script>
        <?php
        return trim((string) ob_get_clean());
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
