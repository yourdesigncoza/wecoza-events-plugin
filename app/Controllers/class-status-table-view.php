<?php
/**
 * View: Class Status table layout (Phoenix table pattern)
 *
 * Variables available:
 * - string $container_id
 * - array  $filters
 * - array  $tasks
 * - ShortcodeController $controller
 */

if (!defined('ABSPATH')) {
    exit;
}
$filters = apply_filters('wecoza_class_status_filters', $filters, $tasks, $atts);
$tasks   = apply_filters('wecoza_class_status_tasks', $tasks, $atts);

$manual_sync_markup = $controller->render_manual_sync_button($container_id, 'top');
$can_sync = !empty(trim($manual_sync_markup));

if ($can_sync) {
    echo '<div class="d-none wecoza-sync-proxy">' . $manual_sync_markup . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
?>

<div class="card shadow-none border my-3" data-component-card="data-component-card">



                <div class="card-header p-3 border-bottom">
                    <div class="row g-3 justify-content-between align-items-center mb-3">
                        <div class="col-12 col-md">
                            <h4 class="text-body mb-0" data-anchor="data-anchor" id="classes-table-header">
                                <?php esc_html_e('Class Workflow Status', 'wecoza-notifications'); ?>
                                <i class="bi bi-calendar-event ms-2"></i>
                            </h4>
                        </div>
                        <div class="search-box col-auto">
                                <form class="position-relative mb-0" role="search">
                                    <input class="form-control search-input search form-control-sm wecoza-class-status-search"
                                           type="search"
                                           placeholder="<?php esc_attr_e('Search', 'wecoza-notifications'); ?>"
                                           aria-label="<?php esc_attr_e('Search class workflow rows', 'wecoza-notifications'); ?>">
                                    <i class="bi bi-search search-box-icon"></i>
                                </form>
                        </div>
                        <div class="col-auto">
                            <div class="d-flex gap-2">
                                <button type="button"
                                        class="btn btn-outline-secondary btn-sm wecoza-class-status-sync-btn"
                                        onclick="syncClassData('<?php echo esc_js($container_id); ?>')"
                                        <?php disabled(!$can_sync); ?>>
                                    <?php esc_html_e('Refresh', 'wecoza-notifications'); ?>
                                    <i class="bi bi-arrow-clockwise ms-1"></i>
                                </button>
                                <button type="button"
                                        class="btn btn-outline-primary btn-sm"
                                        onclick="exportClassStatus('<?php echo esc_js($container_id); ?>')">
                                    <?php esc_html_e('Export', 'wecoza-notifications'); ?>
                                    <i class="bi bi-download ms-1"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                        <!-- Summary strip -->
                  <div class="col-12">
                    <div class="scrollbar">
                      <div class="row g-0 flex-nowrap">
                        <div class="col-auto border-end pe-4">
                          <h6 class="text-body-tertiary">Total Classes : 41 <div class="badge badge-phoenix fs-10 badge-phoenix-success">+ 11</div></h6>
                        </div>
                        <div class="col-auto px-4 border-end">
                          <h6 class="text-body-tertiary">Active Classes : 33</h6>
                        </div>
                        <div class="col-auto px-4 border-end">
                          <h6 class="text-body-tertiary">SETA Funded : 37 <div class="badge badge-phoenix fs-10 badge-phoenix-success">+ 5</div></h6>
                        </div>
                        <div class="col-auto px-4 border-end">
                          <h6 class="text-body-tertiary">Exam Classes : 32 <div class="badge badge-phoenix fs-10 badge-phoenix-danger">+ 8</div></h6>
                        </div>
                        <div class="col-auto px-4">
                          <h6 class="text-body-tertiary">Unique Clients : 12 <div class="badge badge-phoenix fs-10 badge-phoenix-success">- 2</div></h6>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>



<div class="table-list" id="wecoza-class-status-table">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1 mt-3 ms-5">
        <?php if (!empty($filters)): ?>
            <div class="btn-group btn-group-sm wecoza-task-filter-group" role="group" aria-label="<?php esc_attr_e('Filter tasks', 'wecoza-notifications'); ?>">
                <?php foreach ($filters as $filter_key => $filter_label): ?>
                    <?php
                        $is_active = ($filter_key === 'all');
                        $base_classes = $is_active ? 'btn btn-phoenix-primary text-body-tertiary active' : 'btn btn-subtle-primary';
                    ?>
                    <button type="button"
                            class="<?php echo esc_attr($base_classes); ?> wecoza-task-filter"
                            data-task-filter="<?php echo esc_attr($filter_key); ?>">
                        <?php echo esc_html($filter_label); ?>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card-body p-4 py-2">
        <div class="table-responsive">
        <table class="table table-hover table-sm fs-9 mb-3 overflow-hidden">
            <thead class="text-body">
                <tr>
                    <th class="ps-3 pe-1 align-middle white-space-nowrap" scope="col"><?php esc_html_e('Task', 'wecoza-notifications'); ?></th>
                    <th class="pe-1 align-middle white-space-nowrap" scope="col"><?php esc_html_e('Class', 'wecoza-notifications'); ?></th>
                    <th class="pe-1 align-middle white-space-nowrap" scope="col"><?php esc_html_e('Supervisor', 'wecoza-notifications'); ?></th>
                    <th class="pe-1 align-middle white-space-nowrap" scope="col"><?php esc_html_e('Due Date', 'wecoza-notifications'); ?></th>
                    <th class="pe-1 align-middle white-space-nowrap text-center" scope="col"><?php esc_html_e('Status', 'wecoza-notifications'); ?></th>
                    <th class="pe-3 align-middle white-space-nowrap text-end" scope="col"><?php esc_html_e('Actions', 'wecoza-notifications'); ?></th>
                </tr>
            </thead>
            <tbody class="list">
                <?php foreach ($tasks as $task): ?>
                    <?php echo $controller->render_task_table_row($task); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
</div>
