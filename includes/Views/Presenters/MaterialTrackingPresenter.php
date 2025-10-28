<?php
declare(strict_types=1);

namespace WeCozaEvents\Views\Presenters;

use function esc_html;
use function gmdate;
use function wp_date;

/**
 * Presenter for material tracking dashboard data
 */
final class MaterialTrackingPresenter
{
    /**
     * Format tracking records for display
     *
     * @param array<int, array<string, mixed>> $records Raw tracking records
     * @return array<int, array<string, mixed>> Formatted records
     */
    public function presentRecords(array $records): array
    {
        $presented = [];

        foreach ($records as $record) {
            $presented[] = [
                'id' => (int) $record['id'],
                'class_id' => (int) $record['class_id'],
                'class_code' => esc_html((string) ($record['class_code'] ?? 'N/A')),
                'class_subject' => esc_html((string) ($record['class_subject'] ?? 'N/A')),
                'client_name' => esc_html((string) ($record['client_name'] ?? 'N/A')),
                'site_name' => esc_html((string) ($record['site_name'] ?? 'N/A')),
                'notification_type' => (string) $record['notification_type'],
                'notification_sent_at' => $this->formatDateTime($record['notification_sent_at']),
                'materials_delivered_at' => $this->formatDateTime($record['materials_delivered_at']),
                'delivery_status' => (string) $record['delivery_status'],
                'original_start_date' => $this->formatDate($record['original_start_date']),
                'notification_badge_html' => $this->getNotificationBadge((string) $record['notification_type']),
                'status_badge_html' => $this->getStatusBadge((string) $record['delivery_status']),
                'action_button_html' => $this->getActionButton(
                    (int) $record['class_id'],
                    (string) $record['delivery_status']
                ),
            ];
        }

        return $presented;
    }

    /**
     * Format statistics for display
     *
     * @param array<string, int> $stats Raw statistics
     * @return array<string, mixed> Formatted statistics
     */
    public function presentStatistics(array $stats): array
    {
        return [
            'total' => [
                'count' => $stats['total'],
                'label' => 'Total Tracking',
                'sublabel' => 'records',
                'icon' => '',
                'color' => 'secondary',
            ],
            'pending' => [
                'count' => $stats['pending'],
                'label' => 'Pending',
                'sublabel' => 'awaiting notification',
                'icon' => '',
                'color' => 'warning',
            ],
            'notified' => [
                'count' => $stats['notified'],
                'label' => 'Notified',
                'sublabel' => 'emails sent',
                'icon' => '',
                'color' => 'info',
            ],
            'delivered' => [
                'count' => $stats['delivered'],
                'label' => 'Delivered',
                'sublabel' => 'confirmed',
                'icon' => '',
                'color' => 'success',
            ],
        ];
    }

    /**
     * Get notification type badge HTML
     *
     * @param string $type Notification type (orange or red)
     * @return string Badge HTML
     */
    private function getNotificationBadge(string $type): string
    {
        if ($type === 'orange') {
            return '<span class="badge badge-phoenix badge-phoenix-warning fs-10">🟠 (7d)</span>';
        }

        if ($type === 'red') {
            return '<span class="badge badge-phoenix badge-phoenix-danger fs-10">🔴 (5d)</span>';
        }

        return '';
    }

    /**
     * Get delivery status badge HTML
     *
     * @param string $status Delivery status
     * @return string Badge HTML
     */
    private function getStatusBadge(string $status): string
    {
        return match ($status) {
            'pending' => '<span class="badge badge-phoenix badge-phoenix-secondary fs-10">⏳ Pending</span>',
            'notified' => '<span class="badge badge-phoenix badge-phoenix-info fs-10">📧 Notified</span>',
            'delivered' => '<span class="badge badge-phoenix badge-phoenix-success fs-10">✅ Delivered</span>',
            default => '<span class="badge badge-phoenix badge-phoenix-secondary fs-10">' . esc_html($status) . '</span>',
        };
    }

    /**
     * Get action button HTML
     *
     * @param int $classId The class ID
     * @param string $status Current delivery status
     * @return string Button HTML or empty string
     */
    private function getActionButton(int $classId, string $status): string
    {
        if ($status === 'notified') {
            return sprintf(
                '<button class="btn btn-phoenix-primary btn-sm mark-delivered-btn" 
                         data-class-id="%d" 
                         data-nonce="%s">
                    <i class="bi bi-check-circle me-1"></i>Mark as Delivered
                </button>',
                $classId,
                wp_create_nonce('wecoza_material_tracking_action')
            );
        }

        if ($status === 'delivered') {
            return '<span class="text-success fw-bold fs-9">✓ Confirmed</span>';
        }

        return '';
    }

    /**
     * Format date for display
     *
     * @param mixed $date Date value
     * @return string Formatted date or empty string
     */
    private function formatDate($date): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        return wp_date('M j, Y', strtotime((string) $date));
    }

    /**
     * Format datetime for display
     *
     * @param mixed $datetime Datetime value
     * @return string Formatted datetime or empty string
     */
    private function formatDateTime($datetime): string
    {
        if ($datetime === null || $datetime === '') {
            return '';
        }

        return wp_date('M j, Y g:i A', strtotime((string) $datetime));
    }
}
