<?php
/** @var string $assets */
/** @var array<int, array<string, mixed>> $classes */
/** @var bool $classSpecific */
/** @var array<int, array{label:string,value:string}> $openTaskOptions */
/** @var string $instanceId */
/** @var string $searchInputId */
/** @var string $openTaskSelectId */
/** @var string $viewAllUrl */
/** @var string $nonce */
/** @var string $ajaxUrl */
/** @var string $sortDirection */
/** @var string $sortIconClass */
/** @var int|null $classIdFilter */
echo $assets;
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
    data-class-filter="<?php echo esc_attr($classSpecific ? (string) $classIdFilter : ''); ?>"
>
    <div class="card shadow-none my-3 mt-5">
        <div class="card-header p-3 border-bottom">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <h4 class="text-body mb-0">
                        <?php
                        echo esc_html(
                            $classSpecific
                                ? sprintf(__('Tasks for Class #%d', 'wecoza-events'), (int) $classIdFilter)
                                : __('Classes Open Tasks', 'wecoza-events')
                        );
                        ?>
                    </h4>
                    <?php if ($classSpecific && $viewAllUrl !== ''): ?>
                        <a class="btn btn-link btn-sm px-0" href="<?php echo esc_url($viewAllUrl); ?>">
                            <?php echo esc_html__('View all classes', 'wecoza-events'); ?>
                        </a>
                    <?php endif; ?>
                </div>
                <?php $count = count($classes); ?>
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
                            <?php foreach ($openTaskOptions as $option): ?>
                                <option value="<?php echo esc_attr($option['value']); ?>"><?php echo esc_html($option['label']); ?></option>
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
                        <?php foreach ($classes as $class): ?>
                            <tr
                                data-role="class-row"
                                data-search-base="<?php echo esc_attr($class['search']['base']); ?>"
                                data-search-index="<?php echo esc_attr($class['search']['index']); ?>"
                                data-open-tasks="<?php echo esc_attr($class['search']['open_tokens']); ?>"
                                data-status-label="<?php echo esc_attr($class['search']['status']); ?>"
                                data-class-id="<?php echo esc_attr((string) $class['id']); ?>"
                                data-log-id="<?php echo esc_attr((string) ($class['log_id'] ?? '')); ?>"
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
                                    <div class="p-4 wecoza-task-panel-content" data-log-id="<?php echo esc_attr((string) ($class['log_id'] ?? '')); ?>" data-class-id="<?php echo esc_attr((string) $class['id']); ?>" data-manageable="<?php echo $class['manageable'] ? '1' : '0'; ?>">
                                        <div class="row g-4 align-items-start">
                                            <?php /* if (!$class['manageable']): ?>
                                                <div class="col-12">
                                                    <div class="alert alert-subtle-warning mb-0" role="alert">
                                                        <?php echo esc_html__('Tasks are not yet available for this class.', 'wecoza-events'); ?>
                                                    </div>
                                                </div>
                                            <?php endif; */ ?>

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
                                                                    <div class="d-flex flex-row gap-2 align-items-start flex-grow-1">
                                                                        <div class="flex-grow-1">
                                                                            <label class="visually-hidden" for="wecoza-note-<?php echo esc_attr($class['id'] . '-' . $task['id']); ?>"><?php echo esc_html($task['note_label']); ?></label>
                                                                            <input
                                                                                id="wecoza-note-<?php echo esc_attr($class['id'] . '-' . $task['id']); ?>"
                                                                                class="form-control form-control-sm wecoza-task-note"
                                                                                type="text"
                                                                                placeholder="<?php echo esc_attr($task['note_placeholder']); ?>"
                                                                                data-note-required="<?php echo !empty($task['note_required']) ? '1' : '0'; ?>"
                                                                                <?php echo !empty($task['note_required']) ? 'aria-required="true"' : ''; ?>
                                                                                <?php echo $class['manageable'] ? '' : 'disabled'; ?>
                                                                            >
                                                                            <?php if (!empty($task['note_required_message'])): ?>
                                                                                <div class="invalid-feedback small">
                                                                                    <?php echo esc_html($task['note_required_message']); ?>
                                                                                </div>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                        <div class="d-flex">
                                                                            <button
                                                                                type="button"
                                                                                class="btn btn-subtle-success btn-sm wecoza-task-action"
                                                                                data-action="complete"
                                                                                <?php echo $class['manageable'] ? '' : 'disabled'; ?>
                                                                            >
                                                                                <?php echo esc_html($task['complete_label']); ?>
                                                                            </button>
                                                                        </div>
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
