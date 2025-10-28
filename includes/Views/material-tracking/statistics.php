<?php
/**
 * Material Tracking Statistics Strip Template
 *
 * @var array<string, mixed> $statistics Formatted statistics
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="col-12">
    <div class="scrollbar">
        <div class="row g-0 flex-nowrap">
            <?php 
            $statKeys = ['total', 'pending', 'notified', 'delivered'];
            $lastKey = end($statKeys);
            reset($statKeys);
            foreach ($statKeys as $key): 
                $stat = $statistics[$key];
                $isLast = ($key === $lastKey);
                $borderClass = $isLast ? '' : 'border-end';
                $paddingClass = ($key === 'total') ? 'pe-4' : ($isLast ? 'ps-4' : 'px-4');
            ?>
                <div class="col-auto <?php echo esc_attr($borderClass . ' ' . $paddingClass); ?>">
                    <h6 class="text-body-tertiary mb-0 cursor-pointer stat-item" 
                        data-status="<?php echo esc_attr($key === 'total' ? 'all' : $key); ?>">
                        <?php echo esc_html($stat['label']); ?> : 
                        <span id="stat-<?php echo esc_attr($key); ?>" class="text-body-emphasis">
                            <?php echo esc_html((string) $stat['count']); ?>
                        </span>
                        <?php echo esc_html($stat['icon']); ?>
                    </h6>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<style>
.stat-item {
    cursor: pointer;
    transition: color 0.2s;
}
.stat-item:hover {
    color: var(--bs-primary) !important;
}
</style>
