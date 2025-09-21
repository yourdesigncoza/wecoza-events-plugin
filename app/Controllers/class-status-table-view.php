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
?>
<button class="btn btn-subtle-primary me-1 mb-1" type="button" id="sync-class-data">Sync Class Data</button>
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
                          <form class="position-relative"><input class="form-control search-input search form-control-sm" type="search" placeholder="Search" aria-label="Search">
                            <svg class="svg-inline--fa fa-magnifying-glass search-box-icon" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="magnifying-glass" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" data-fa-i2svg=""><path fill="currentColor" d="M416 208c0 45.9-14.9 88.3-40 122.7L502.6 457.4c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L330.7 376c-34.4 25.2-76.8 40-122.7 40C93.1 416 0 322.9 0 208S93.1 0 208 0S416 93.1 416 208zM208 352a144 144 0 1 0 0-288 144 144 0 1 0 0 288z"></path></svg><!-- <span class="fas fa-search search-box-icon"></span> Font Awesome fontawesome.com -->
                          </form>
                        </div>
                        <div class="col-auto">
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="syncClassData()">
                                    Refresh
                                    <i class="bi bi-arrow-clockwise ms-1"></i>
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="exportClassStatus()">
                                    Export
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
