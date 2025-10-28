<?php
/** @var array<int, array<string, mixed>> $summaries */
?>
<div class="timeline-scroll-wrapper" style="max-height: 600px; overflow-y: auto; overflow-x: hidden; padding-right: 10px;">
    <div class="row g-3">
        <?php foreach ($summaries as $summary): ?>
        <?php
        $searchIndex = strtolower(
            ($summary['class_code'] ?? '') . ' ' .
            ($summary['class_subject'] ?? '') . ' ' .
            ($summary['summary_text'] ?? '') . ' ' .
            ($summary['operation_label'] ?? '')
        );
        ?>
        <div
            class="col-12 col-md-6 col-lg-4"
            data-role="summary-item"
            data-search-index="<?php echo esc_attr($searchIndex); ?>"
            data-operation="<?php echo esc_attr($summary['operation'] ?? ''); ?>"
        >
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-body-tertiary border-bottom">
                    <div class="d-flex align-items-start justify-content-between gap-2">
                        <div class="flex-grow-1">
                            <h6 class="mb-1 fw-bold text-body">
                                <?php echo $summary['class_code'] ?: esc_html__('N/A', 'wecoza-events'); ?>
                            </h6>
                            <p class="mb-0 fs-9 text-body-secondary">
                                <?php echo $summary['class_subject'] ?: esc_html__('No subject', 'wecoza-events'); ?>
                            </p>
                        </div>
                        <span class="badge <?php echo esc_attr($summary['operation_badge_class'] ?? 'badge-phoenix-secondary'); ?> text-uppercase fs-10">
                            <?php echo esc_html($summary['operation_label'] ?? 'Unknown'); ?>
                        </span>
                    </div>
                </div>
                <div class="card-body" style="max-height: 300px; overflow-y: auto; overflow-x: hidden;">
                    <?php if ($summary['has_summary']): ?>
                        <div class="fs-9 text-body">
                            <?php echo $summary['summary_html']; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-body-secondary fs-9 fst-italic mb-0">
                            <?php echo esc_html__('No AI summary available for this change.', 'wecoza-events'); ?>
                        </p>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-body-tertiary border-top">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 fs-10 text-body-secondary">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-clock"></i>
                            <span><?php echo esc_html($summary['changed_at_formatted']); ?></span>
                        </div>
                        <?php if ($summary['summary_model']): ?>
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-cpu"></i>
                                <span><?php echo esc_html($summary['summary_model']); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-2">
                        <span class="badge <?php echo esc_attr($summary['summary_status_badge_class']); ?> fs-10">
                            <?php echo esc_html(strtoupper($summary['summary_status'])); ?>
                        </span>
                        <?php if ($summary['tokens_used']): ?>
                            <span class="fs-10 text-body-secondary">
                                <i class="bi bi-coin"></i> <?php echo esc_html(number_format($summary['tokens_used'])); ?> tokens
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
</div>
