<?php
/** @var string $assets */
/** @var array<int, array<string, mixed>> $summaries */
/** @var string $layout */
/** @var string $instanceId */
/** @var string $searchInputId */
/** @var string $operationFilterId */
/** @var int|null $classIdFilter */
/** @var string|null $operationFilter */
echo $assets;
?>
<div class="wecoza-ai-summary-wrapper" data-instance-id="<?php echo esc_attr($instanceId); ?>">
    <div class="card shadow-none my-3 mt-5">
        <div class="card-header p-3 border-bottom">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <h4 class="text-body mb-0">
                        <?php echo esc_html__('AI Class Change Summaries', 'wecoza-events'); ?>
                    </h4>
                </div>
                <?php $count = count($summaries); ?>
                <span class="badge badge-phoenix fs-10 badge-phoenix-primary">
                    <?php echo esc_html(sprintf(_n('%d summary', '%d summaries', $count, 'wecoza-events'), $count)); ?>
                </span>
            </div>
            <div class="d-flex flex-wrap align-items-start gap-2 mt-3">
                <div class="search-box flex-grow-1">
                    <form class="position-relative" role="search" data-role="ai-filter-form">
                        <label class="visually-hidden" for="<?php echo esc_attr($searchInputId); ?>"><?php echo esc_html__('Search summaries', 'wecoza-events'); ?></label>
                        <input
                            id="<?php echo esc_attr($searchInputId); ?>"
                            class="form-control search-input form-control-sm ps-5"
                            type="search"
                            placeholder="<?php echo esc_attr__('Search by class code, subject, or summary', 'wecoza-events'); ?>"
                            autocomplete="off"
                            data-role="ai-search"
                        >
                        <span class="search-box-icon" aria-hidden="true">
                            <i class="bi bi-search"></i>
                        </span>
                    </form>
                </div>
                <div class="flex-grow-1 flex-sm-grow-0" style="min-width: 180px;">
                    <label class="visually-hidden" for="<?php echo esc_attr($operationFilterId); ?>"><?php echo esc_html__('Filter by operation', 'wecoza-events'); ?></label>
                    <select
                        id="<?php echo esc_attr($operationFilterId); ?>"
                        class="form-select form-select-sm"
                        data-role="operation-filter"
                    >
                        <option value=""><?php echo esc_html__('All operations', 'wecoza-events'); ?></option>
                        <option value="INSERT"><?php echo esc_html__('New Classes (INSERT)', 'wecoza-events'); ?></option>
                        <option value="UPDATE"><?php echo esc_html__('Updates (UPDATE)', 'wecoza-events'); ?></option>
                    </select>
                </div>
            </div>
        </div>
        <div class="card-body p-3">
            <div
                class="mb-3"
                data-role="filter-status"
                data-empty-message="<?php echo esc_attr__('No summaries match the current filters.', 'wecoza-events'); ?>"
                data-match-template="<?php echo esc_attr__('Showing %1$d of %2$d summaries matching "%3$s"', 'wecoza-events'); ?>"
                hidden
            ></div>
            <?php
            if ($layout === 'timeline') {
                include __DIR__ . '/timeline.php';
            } else {
                include __DIR__ . '/card.php';
            }
            ?>
            <div
                class="alert alert-info text-center"
                data-role="no-results"
                hidden
            >
                <?php echo esc_html__('No summaries match your search criteria.', 'wecoza-events'); ?>
            </div>
        </div>
    </div>
</div>
