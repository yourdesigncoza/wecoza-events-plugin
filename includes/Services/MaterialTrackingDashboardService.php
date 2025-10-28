<?php
declare(strict_types=1);

namespace WeCozaEvents\Services;

use WeCozaEvents\Models\MaterialTrackingRepository;

use function current_user_can;

/**
 * Service for managing material tracking dashboard
 */
final class MaterialTrackingDashboardService
{
    public function __construct(
        private readonly MaterialTrackingRepository $repository
    ) {
    }

    /**
     * Get dashboard data with optional filters
     *
     * @param array<string, mixed> $filters Array with keys: limit, status, notification_type, days_range
     * @return array<int, array<string, mixed>> Array of tracking records
     */
    public function getDashboardData(array $filters = []): array
    {
        $limit = isset($filters['limit']) ? (int) $filters['limit'] : 50;
        $limit = max(1, min(200, $limit)); // Enforce 1-200 range

        $status = $filters['status'] ?? null;
        if ($status !== null && !in_array($status, ['pending', 'notified', 'delivered'], true)) {
            $status = null;
        }

        $notificationType = $filters['notification_type'] ?? null;
        if ($notificationType !== null && !in_array($notificationType, ['orange', 'red'], true)) {
            $notificationType = null;
        }

        $daysRange = isset($filters['days_range']) ? (int) $filters['days_range'] : 30;
        $daysRange = max(1, $daysRange);

        return $this->repository->getTrackingDashboardData(
            $limit,
            $status,
            $notificationType,
            $daysRange
        );
    }

    /**
     * Get tracking statistics
     *
     * @param int $daysRange Number of days to look back
     * @return array<string, int> Statistics array
     */
    public function getStatistics(int $daysRange = 30): array
    {
        return $this->repository->getTrackingStatistics($daysRange);
    }

    /**
     * Mark materials as delivered for a class
     *
     * @param int $classId The class ID
     * @return bool True on success, false on failure
     */
    public function markAsDelivered(int $classId): bool
    {
        if (!$this->canManageMaterialTracking()) {
            return false;
        }

        try {
            $this->repository->markDelivered($classId);
            return true;
        } catch (\Throwable $e) {
            error_log('Material Tracking: Failed to mark delivered - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if current user can view material tracking dashboard
     *
     * @return bool True if user has permission
     */
    public function canViewDashboard(): bool
    {
        return current_user_can('view_material_tracking') || current_user_can('manage_options');
    }

    /**
     * Check if current user can manage material tracking
     *
     * @return bool True if user has permission
     */
    public function canManageMaterialTracking(): bool
    {
        return current_user_can('manage_material_tracking') || current_user_can('manage_options');
    }
}
