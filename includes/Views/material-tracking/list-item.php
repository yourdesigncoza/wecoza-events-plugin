<?php
/**
 * Material Tracking Table Row Template
 *
 * @var array<string, mixed> $record Formatted tracking record
 * @var bool $can_manage Whether user can manage tracking
 * @var string $tracking_nonce Shared security nonce for AJAX requests
 */

if (!defined('ABSPATH')) {
    exit;
}

// Build client/site display
$clientSiteDisplay = esc_html($record['client_name']);
if (!empty($record['site_name'])) {
    $clientSiteDisplay .= ' - ' . esc_html($record['site_name']);
}
?>

<tr data-status="<?php echo esc_attr($record['delivery_status']); ?>"
    data-notification-type="<?php echo esc_attr($record['notification_type']); ?>"
    data-class-id="<?php echo esc_attr((string) $record['class_id']); ?>"
    data-class-code="<?php echo esc_attr($record['class_code']); ?>"
    data-client-name="<?php echo esc_attr($record['client_name']); ?>"
    data-start-date="<?php echo esc_attr($record['original_start_date']); ?>">
    
    <!-- Class Code/Subject -->
    <td class="py-2 align-middle ps-3">
        <span class="fw-medium">
            <?php echo esc_html($record['class_code']); ?> - <?php echo esc_html($record['class_subject']); ?>
        </span>
    </td>
    
    <!-- Client/Site -->
    <td class="py-2 align-middle">
        <?php echo $clientSiteDisplay; ?>
    </td>
    
    <!-- Class Start Date -->
    <td class="py-2 align-middle">
        <?php echo esc_html($record['original_start_date']); ?>
    </td>
    
    <!-- Notification Type -->
    <td class="py-2 align-middle">
        <?php echo $record['notification_badge_html']; ?>
    </td>
    
    <!-- Status -->
    <td class="py-2 align-middle delivery-status-badge">
        <?php echo $record['status_badge_html']; ?>
    </td>
    
    <!-- Actions -->
    <td class="py-2 align-middle text-center pe-3">
        <?php if ($can_manage): ?>
            <?php if ($record['delivery_status'] === 'delivered'): ?>
                <input type="checkbox" 
                       class="form-check-input mark-delivered-checkbox" 
                       checked 
                       disabled
                       title="Marked as delivered">
            <?php else: ?>
                <input type="checkbox" 
                       class="form-check-input mark-delivered-checkbox" 
                       data-class-id="<?php echo esc_attr((string) $record['class_id']); ?>"
                       data-nonce="<?php echo esc_attr($tracking_nonce); ?>"
                       title="Mark as delivered">
            <?php endif; ?>
        <?php endif; ?>
    </td>
</tr>
