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
                    <?php if (!empty($summary_stats)): ?>
                        <div class="scrollbar">
                          <?php $summary_count = count($summary_stats); ?>
                          <div class="d-flex flex-nowrap align-items-center gap-4">
                            <?php foreach ($summary_stats as $index => $stat): ?>
                              <div class="d-flex align-items-center gap-2<?php echo $index < $summary_count - 1 ? ' border-end pe-4' : ''; ?>">
                                <div>
                                  <h6 class="text-body mb-0">
                                    <?php echo esc_html($stat['label']); ?> :
                                    <span class="fw-semibold"><?php echo esc_html($stat['value_formatted']); ?></span>
                                  </h6>
                                  <?php if (!empty($stat['description'])): ?>
                                    <div class="text-body-tertiary fs-10">
                                      <?php echo esc_html($stat['description']); ?>
                                    </div>
                                  <?php endif; ?>
                                </div>
                                <div class="badge badge-phoenix fs-10 <?php
                                    if ($stat['delta_type'] === 'positive') {
                                        echo 'badge-phoenix-success';
                                    } elseif ($stat['delta_type'] === 'negative') {
                                        echo 'badge-phoenix-danger';
                                    } else {
                                        echo 'badge-phoenix-secondary';
                                    }
                                ?>">
                                  <?php echo esc_html($stat['delta_formatted']); ?>
                                </div>
                              </div>
                            <?php endforeach; ?>
                          </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-subtle-info mb-0" role="status">
                            <?php esc_html_e('Dashboard metrics will appear after the next sync.', 'wecoza-notifications'); ?>
                        </div>
                    <?php endif; ?>
                  </div>
                </div>



<div class="table-list" id="wecoza-class-status-table">
    <div class="wecoza-sync-alerts px-4 py-2">
        <div class="d-none alert alert-subtle-success wecoza-sync-alert-success py-2 my-0 mt-3 mx-5" role="alert">
            <?php esc_html_e('Dashboard data synced successfully.', 'wecoza-notifications'); ?>
        </div>
        <div class="d-none alert alert-subtle-danger wecoza-sync-alert-error py-2 my-0 mt-3 mx-5" role="alert">
            <?php esc_html_e('Failed to sync dashboard data.', 'wecoza-notifications'); ?>
        </div>
    </div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1 mt-1 ms-5">
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
        <!-- Footer Pagination -->
        <div class="d-flex justify-content-between align-items-center mt-3" data-pagination-container>
            <span class="d-none d-sm-inline-block" data-pagination-summary data-list-info="data-list-info">
                0 <span class="text-body-tertiary"> Items of </span>0
            </span>
            <div class="d-flex align-items-center gap-1">
                <button type="button"
                        class="page-link disabled"
                        data-pagination-action="prev"
                        data-list-pagination="prev"
                        aria-label="<?php esc_attr_e('Previous page', 'wecoza-notifications'); ?>"
                        disabled>
                    <span class="fas fa-chevron-left"></span>
                </button>
                <ul class="mb-0 pagination pagination-sm" data-pagination-pages></ul>
                <button type="button"
                        class="page-link pe-0 disabled"
                        data-pagination-action="next"
                        data-list-pagination="next"
                        aria-label="<?php esc_attr_e('Next page', 'wecoza-notifications'); ?>"
                        disabled>
                    <span class="fas fa-chevron-right"></span>
                </button>
            </div>
        </div>




    </div>
</div>
</div>
