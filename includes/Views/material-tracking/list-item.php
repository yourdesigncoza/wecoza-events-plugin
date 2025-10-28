<?php
/**
 * Material Tracking List Item Template
 *
 * @var array<string, mixed> $record Formatted tracking record
 * @var bool $can_manage Whether user can manage tracking
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="material-tracking-list-item py-3 border-translucent border-top" 
     data-status="<?php echo esc_attr($record['delivery_status']); ?>"
     data-notification-type="<?php echo esc_attr($record['notification_type']); ?>"
     data-class-id="<?php echo esc_attr((string) $record['class_id']); ?>">
    
    <div class="row align-items-center">
        <!-- Class Information -->
        <div class="col-12 col-md-6 mb-2 mb-md-0">
            <div class="d-flex align-items-start">
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center mb-1">
                        <h6 class="mb-0 fs-8 fw-bold text-body-emphasis me-2">
                            <?php echo $record['class_code']; ?> - <?php echo $record['class_subject']; ?>
                        </h6>
                        <span class="notification-type-badge">
                            <?php echo $record['notification_badge_html']; ?>
                        </span>
                    </div>
                    <p class="mb-1 fs-9 text-body-tertiary">
                        <i class="bi bi-building me-1"></i><?php echo $record['client_name']; ?>
                        <?php if ($record['site_name']): ?>
                            <span class="mx-1">•</span>
                            <i class="bi bi-geo-alt me-1"></i><?php echo $record['site_name']; ?>
                        <?php endif; ?>
                    </p>
                    <p class="mb-0 fs-10 text-body-tertiary">
                        <i class="bi bi-calendar-event me-1"></i>
                        Class Start: <?php echo $record['original_start_date']; ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Status and Timestamps -->
        <div class="col-12 col-md-4 mb-2 mb-md-0">
            <div>
                <div class="mb-1 delivery-status-badge">
                    <?php echo $record['status_badge_html']; ?>
                </div>
                <?php if ($record['notification_sent_at']): ?>
                    <p class="mb-0 fs-10 text-body-tertiary">
                        <i class="bi bi-envelope me-1"></i>
                        Notified: <?php echo $record['notification_sent_at']; ?>
                    </p>
                <?php endif; ?>
                <?php if ($record['materials_delivered_at']): ?>
                    <p class="mb-0 fs-10 text-success">
                        <i class="bi bi-check-circle me-1"></i>
                        Delivered: <?php echo $record['materials_delivered_at']; ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Action Button -->
        <div class="col-12 col-md-2 text-md-end">
            <?php if ($can_manage): ?>
                <?php echo $record['action_button_html']; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
