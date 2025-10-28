<?php
declare(strict_types=1);

namespace WeCozaEvents\Models;

use PDO;
use RuntimeException;

use function sprintf;

/**
 * Repository for managing material delivery tracking records
 */
final class MaterialTrackingRepository
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $schema
    ) {
    }

    /**
     * Record that a notification was sent for a class
     *
     * @param int $classId The class ID
     * @param string $notificationType Either 'orange' or 'red'
     * @throws RuntimeException If database operation fails
     */
    public function markNotificationSent(int $classId, string $notificationType): void
    {
        $sql = sprintf(
            'INSERT INTO "%s".class_material_tracking 
             (class_id, notification_type, notification_sent_at, delivery_status, created_at, updated_at)
             VALUES (:class_id, :type, NOW(), \'notified\', NOW(), NOW())
             ON CONFLICT (class_id, notification_type) 
             DO UPDATE SET 
                notification_sent_at = NOW(), 
                delivery_status = \'notified\',
                updated_at = NOW()',
            $this->schema
        );

        $stmt = $this->pdo->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare material tracking insert statement.');
        }

        $success = $stmt->execute([
            ':class_id' => $classId,
            ':type' => $notificationType
        ]);

        if (!$success) {
            throw new RuntimeException(
                sprintf('Failed to mark notification sent for class %d, type %s', $classId, $notificationType)
            );
        }
    }

    /**
     * Mark materials as delivered for a class (all notification types)
     *
     * @param int $classId The class ID
     * @throws RuntimeException If database operation fails
     */
    public function markDelivered(int $classId): void
    {
        $sql = sprintf(
            'UPDATE "%s".class_material_tracking 
             SET delivery_status = \'delivered\',
                 materials_delivered_at = NOW(),
                 updated_at = NOW()
             WHERE class_id = :class_id
               AND delivery_status != \'delivered\'',
            $this->schema
        );

        $stmt = $this->pdo->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare material delivery update statement.');
        }

        $stmt->execute([':class_id' => $classId]);
    }

    /**
     * Check if a notification was already sent for a class
     *
     * @param int $classId The class ID
     * @param string $notificationType Either 'orange' or 'red'
     * @return bool True if notification was sent, false otherwise
     */
    public function wasNotificationSent(int $classId, string $notificationType): bool
    {
        $sql = sprintf(
            'SELECT notification_sent_at 
             FROM "%s".class_material_tracking 
             WHERE class_id = :class_id 
               AND notification_type = :type
               AND notification_sent_at IS NOT NULL',
            $this->schema
        );

        $stmt = $this->pdo->prepare($sql);
        if (!$stmt) {
            return false;
        }

        $stmt->execute([
            ':class_id' => $classId,
            ':type' => $notificationType
        ]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Get delivery status for a class
     *
     * @param int $classId The class ID
     * @return array<string, mixed> Array with orange_status, red_status, and overall_status
     */
    public function getDeliveryStatus(int $classId): array
    {
        $sql = sprintf(
            'SELECT 
                notification_type,
                delivery_status,
                notification_sent_at,
                materials_delivered_at
             FROM "%s".class_material_tracking 
             WHERE class_id = :class_id',
            $this->schema
        );

        $stmt = $this->pdo->prepare($sql);
        if (!$stmt) {
            return [
                'orange_status' => null,
                'red_status' => null,
                'overall_status' => 'pending'
            ];
        }

        $stmt->execute([':class_id' => $classId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $status = [
            'orange_status' => null,
            'red_status' => null,
            'overall_status' => 'pending'
        ];

        foreach ($rows as $row) {
            if ($row['notification_type'] === 'orange') {
                $status['orange_status'] = $row['delivery_status'];
            } elseif ($row['notification_type'] === 'red') {
                $status['red_status'] = $row['delivery_status'];
            }

            if ($row['delivery_status'] === 'delivered') {
                $status['overall_status'] = 'delivered';
            } elseif ($row['delivery_status'] === 'notified' && $status['overall_status'] !== 'delivered') {
                $status['overall_status'] = 'notified';
            }
        }

        return $status;
    }

    /**
     * Get all tracking records for a class
     *
     * @param int $classId The class ID
     * @return array<int, array<string, mixed>> Array of tracking records
     */
    public function getTrackingRecords(int $classId): array
    {
        $sql = sprintf(
            'SELECT 
                id,
                class_id,
                notification_type,
                notification_sent_at,
                materials_delivered_at,
                delivery_status,
                created_at,
                updated_at
             FROM "%s".class_material_tracking 
             WHERE class_id = :class_id
             ORDER BY notification_type',
            $this->schema
        );

        $stmt = $this->pdo->prepare($sql);
        if (!$stmt) {
            return [];
        }

        $stmt->execute([':class_id' => $classId]);
        
        /** @var array<int, array<string, mixed>> $results */
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $results;
    }

    /**
     * Get tracking dashboard data with class and client information
     *
     * @param int $limit Maximum number of records to return
     * @param string|null $status Filter by delivery status (pending, notified, delivered, or null for all)
     * @param string|null $notificationType Filter by notification type (orange, red, or null for all)
     * @param int $daysRange Number of days to look back (default 30)
     * @return array<int, array<string, mixed>> Array of tracking records with joined data
     */
    public function getTrackingDashboardData(
        int $limit = 50,
        ?string $status = null,
        ?string $notificationType = null,
        int $daysRange = 30
    ): array {
        // Build WHERE clause dynamically to avoid PostgreSQL ambiguous parameter issues
        $whereConditions = ['cmt.created_at >= (CURRENT_DATE - INTERVAL \'' . (int)$daysRange . ' days\')'];
        $params = [':limit' => $limit];
        
        if ($status !== null) {
            $whereConditions[] = 'cmt.delivery_status = :status';
            $params[':status'] = $status;
        }
        
        if ($notificationType !== null) {
            $whereConditions[] = 'cmt.notification_type = :notification_type';
            $params[':notification_type'] = $notificationType;
        }
        
        $whereClause = implode(' AND ', $whereConditions);

        $sql = sprintf(
            'SELECT 
                cmt.id,
                cmt.class_id,
                cmt.notification_type,
                cmt.notification_sent_at,
                cmt.materials_delivered_at,
                cmt.delivery_status,
                cmt.created_at,
                cmt.updated_at,
                c.class_code,
                c.class_subject,
                c.original_start_date,
                cl.client_name,
                s.site_name
             FROM "%s".class_material_tracking cmt
             LEFT JOIN "%s".classes c ON cmt.class_id = c.class_id
             LEFT JOIN "%s".clients cl ON c.client_id = cl.client_id
             LEFT JOIN "%s".sites s ON c.site_id = s.site_id
             WHERE %s
             ORDER BY 
                 CASE cmt.delivery_status
                     WHEN \'notified\' THEN 1
                     WHEN \'pending\' THEN 2
                     WHEN \'delivered\' THEN 3
                 END,
                 cmt.notification_sent_at DESC,
                 cmt.created_at DESC
             LIMIT :limit',
            $this->schema,
            $this->schema,
            $this->schema,
            $this->schema,
            $whereClause
        );

        $stmt = $this->pdo->prepare($sql);
        if (!$stmt) {
            return [];
        }

        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
        }

        $stmt->execute();
        
        /** @var array<int, array<string, mixed>> $results */
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $results;
    }

    /**
     * Get tracking statistics for dashboard
     *
     * @param int $daysRange Number of days to look back (default 30)
     * @return array<string, int> Array with keys: total, pending, notified, delivered
     */
    public function getTrackingStatistics(int $daysRange = 30): array
    {
        $sql = sprintf(
            'SELECT 
                COUNT(*) as total,
                COALESCE(SUM(CASE WHEN delivery_status = \'pending\' THEN 1 ELSE 0 END), 0) as pending,
                COALESCE(SUM(CASE WHEN delivery_status = \'notified\' THEN 1 ELSE 0 END), 0) as notified,
                COALESCE(SUM(CASE WHEN delivery_status = \'delivered\' THEN 1 ELSE 0 END), 0) as delivered
             FROM "%s".class_material_tracking
             WHERE created_at >= (CURRENT_DATE - INTERVAL \'%d days\')',
            $this->schema,
            (int) $daysRange
        );

        $stmt = $this->pdo->prepare($sql);
        if (!$stmt) {
            return ['total' => 0, 'pending' => 0, 'notified' => 0, 'delivered' => 0];
        }

        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            return ['total' => 0, 'pending' => 0, 'notified' => 0, 'delivered' => 0];
        }

        return [
            'total' => (int) ($result['total'] ?? 0),
            'pending' => (int) ($result['pending'] ?? 0),
            'notified' => (int) ($result['notified'] ?? 0),
            'delivered' => (int) ($result['delivered'] ?? 0),
        ];
    }
}
