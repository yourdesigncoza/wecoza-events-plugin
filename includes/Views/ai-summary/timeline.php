<?php
/** @var array<int, array<string, mixed>> $summaries */
?>
<style>
    .wecoza-timeline {
        position: relative;
        padding-left: 30px;
    }
    .wecoza-timeline::before {
        content: '';
        position: absolute;
        left: 6px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: var(--phoenix-border-color);
    }
    .wecoza-timeline-item {
        position: relative;
        padding-bottom: 2rem;
    }
    .wecoza-timeline-item:last-child {
        padding-bottom: 0;
    }
    .wecoza-timeline-marker {
        position: absolute;
        left: -24px;
        top: 6px;
        width: 14px;
        height: 14px;
        border-radius: 50%;
        border: 2px solid var(--phoenix-border-color);
        background: var(--phoenix-body-bg);
        z-index: 1;
    }
    .wecoza-timeline-marker.marker-insert {
        background: var(--phoenix-success);
        border-color: var(--phoenix-success);
    }
    .wecoza-timeline-marker.marker-update {
        background: var(--phoenix-primary);
        border-color: var(--phoenix-primary);
    }
</style>

<div class="wecoza-timeline">
    <?php foreach ($summaries as $summary): ?>
        <?php
        $searchIndex = strtolower(
            ($summary['class_code'] ?? '') . ' ' .
            ($summary['class_subject'] ?? '') . ' ' .
            ($summary['summary_text'] ?? '') . ' ' .
            ($summary['operation_label'] ?? '')
        );
        $markerClass = $summary['operation'] === 'INSERT' ? 'marker-insert' : 'marker-update';
        ?>
        <div
            class="wecoza-timeline-item"
            data-role="summary-item"
            data-search-index="<?php echo esc_attr($searchIndex); ?>"
            data-operation="<?php echo esc_attr($summary['operation'] ?? ''); ?>"
        >
            <div class="wecoza-timeline-marker <?php echo esc_attr($markerClass); ?>"></div>
            <div class="card shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <h6 class="mb-0 fw-bold text-body">
                                    <?php echo $summary['class_code'] ?: esc_html__('N/A', 'wecoza-events'); ?>
                                </h6>
                                <span class="badge <?php echo esc_attr($summary['operation_badge_class'] ?? 'badge-phoenix-secondary'); ?> text-uppercase fs-10">
                                    <?php echo esc_html($summary['operation_label'] ?? 'Unknown'); ?>
                                </span>
                            </div>
                            <p class="mb-0 fs-9 text-body-secondary">
                                <?php echo $summary['class_subject'] ?: esc_html__('No subject', 'wecoza-events'); ?>
                            </p>
                        </div>
                        <div class="text-end">
                            <div class="fs-10 text-body-secondary">
                                <i class="bi bi-clock"></i>
                                <?php echo esc_html($summary['changed_at_formatted']); ?>
                            </div>
                        </div>
                    </div>
                    <hr class="my-2">
                    <?php if ($summary['has_summary']): ?>
                        <div class="fs-9 text-body">
                            <?php echo $summary['summary_html']; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-body-secondary fs-9 fst-italic mb-0">
                            <?php echo esc_html__('No AI summary available for this change.', 'wecoza-events'); ?>
                        </p>
                    <?php endif; ?>
                    <hr class="my-2">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 fs-10">
                        <div class="d-flex align-items-center gap-3">
                            <span class="badge <?php echo esc_attr($summary['summary_status_badge_class']); ?>">
                                <?php echo esc_html(strtoupper($summary['summary_status'])); ?>
                            </span>
                            <?php if ($summary['summary_model']): ?>
                                <span class="text-body-secondary">
                                    <i class="bi bi-cpu"></i> <?php echo esc_html($summary['summary_model']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if ($summary['tokens_used']): ?>
                            <span class="text-body-secondary">
                                <i class="bi bi-coin"></i> <?php echo esc_html(number_format($summary['tokens_used'])); ?> tokens
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
