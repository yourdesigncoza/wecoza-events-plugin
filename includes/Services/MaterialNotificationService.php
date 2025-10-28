<?php
declare(strict_types=1);

namespace WeCozaEvents\Services;

use PDO;
use WeCozaEvents\Models\MaterialTrackingRepository;

use function error_log;
use function esc_html;
use function get_option;
use function gmdate;
use function is_email;
use function sprintf;
use function wp_mail;

/**
 * Service for managing material delivery notifications
 */
final class MaterialNotificationService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $schema,
        private readonly MaterialTrackingRepository $trackingRepo
    ) {
    }

    /**
     * Find classes needing Orange notifications (7 days before start)
     *
     * @return array<int, array<string, mixed>>
     */
    public function findOrangeStatusClasses(): array
    {
        return $this->findClassesByDaysUntilStart(7, 'orange');
    }

    /**
     * Find classes needing Red notifications (5 days before start)
     *
     * @return array<int, array<string, mixed>>
     */
    public function findRedStatusClasses(): array
    {
        return $this->findClassesByDaysUntilStart(5, 'red');
    }

    /**
     * Find classes that need notifications based on days until start
     *
     * @param int $daysUntilStart Days before class start (7 or 5)
     * @param string $notificationType Notification type ('orange' or 'red')
     * @return array<int, array<string, mixed>>
     */
    private function findClassesByDaysUntilStart(int $daysUntilStart, string $notificationType): array
    {
        $sql = sprintf(
            'SELECT 
                c.class_id,
                c.class_code,
                c.class_subject,
                c.original_start_date,
                c.delivery_date,
                cl.client_name,
                s.site_name,
                (c.original_start_date - CURRENT_DATE) as days_until_start
             FROM "%s".classes c
             LEFT JOIN "%s".clients cl ON c.client_id = cl.client_id
             LEFT JOIN "%s".sites s ON c.site_id = s.site_id
             WHERE c.original_start_date = CURRENT_DATE + INTERVAL \'%d days\'
               AND NOT EXISTS (
                   SELECT 1 
                   FROM "%s".class_material_tracking cmt
                   WHERE cmt.class_id = c.class_id
                     AND cmt.delivery_status = \'delivered\'
               )
               AND NOT EXISTS (
                   SELECT 1 
                   FROM "%s".class_material_tracking cmt
                   WHERE cmt.class_id = c.class_id
                     AND cmt.notification_type = :type
                     AND cmt.notification_sent_at IS NOT NULL
               )
             ORDER BY c.original_start_date, c.class_code',
            $this->schema,
            $this->schema,
            $this->schema,
            $daysUntilStart,
            $this->schema,
            $this->schema
        );

        $stmt = $this->pdo->prepare($sql);
        if (!$stmt) {
            error_log('WeCoza Material Notification: Failed to prepare query');
            return [];
        }

        $success = $stmt->execute([':type' => $notificationType]);
        if (!$success) {
            error_log('WeCoza Material Notification: Failed to execute query');
            return [];
        }

        /** @var array<int, array<string, mixed>> $results */
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $results;
    }

    /**
     * Send material delivery notifications for a list of classes
     *
     * @param array<int, array<string, mixed>> $classes Array of class data
     * @param string $notificationType Notification type ('orange' or 'red')
     * @return int Number of successfully sent notifications
     */
    public function sendMaterialNotifications(array $classes, string $notificationType): int
    {
        $recipientEmail = (string) get_option('wecoza_notification_material_delivery', '');
        
        if ($recipientEmail === '' || !is_email($recipientEmail)) {
            error_log('WeCoza Material Notification: No valid recipient email configured (option: wecoza_notification_material_delivery)');
            return 0;
        }

        $sent = 0;
        $statusLabel = $notificationType === 'orange' ? 'Orange (7 days)' : 'Red (5 days)';

        foreach ($classes as $class) {
            $classId = (int) $class['class_id'];
            $classCode = (string) ($class['class_code'] ?? 'Unknown');

            $subject = sprintf(
                '[WeCoza] Material Delivery Required - %s Status - %s',
                $statusLabel,
                $classCode
            );

            $body = $this->buildEmailBody($class, $notificationType, $statusLabel);
            $headers = ['Content-Type: text/html; charset=UTF-8'];

            $mailSent = wp_mail($recipientEmail, $subject, $body, $headers);
            
            if ($mailSent) {
                $this->trackingRepo->markNotificationSent($classId, $notificationType);
                $sent++;
                error_log(sprintf(
                    'WeCoza Material Notification: Sent %s notification for class %s (ID: %d) to %s',
                    $notificationType,
                    $classCode,
                    $classId,
                    $recipientEmail
                ));
            } else {
                error_log(sprintf(
                    'WeCoza Material Notification: Failed to send %s notification for class %s (ID: %d)',
                    $notificationType,
                    $classCode,
                    $classId
                ));
            }
        }

        return $sent;
    }

    /**
     * Build HTML email body for material delivery notification
     *
     * @param array<string, mixed> $class Class data
     * @param string $notificationType Notification type ('orange' or 'red')
     * @param string $statusLabel Human-readable status label
     * @return string HTML email body
     */
    private function buildEmailBody(array $class, string $notificationType, string $statusLabel): string
    {
        $daysRemaining = $notificationType === 'orange' ? 7 : 5;
        $backgroundColor = $notificationType === 'orange' ? 'ff9800' : 'dc3545';
        $textColor = $notificationType === 'orange' ? 'ff9800' : 'dc3545';

        $classCode = esc_html((string) ($class['class_code'] ?? 'N/A'));
        $classSubject = esc_html((string) ($class['class_subject'] ?? 'N/A'));
        $clientName = esc_html((string) ($class['client_name'] ?? 'N/A'));
        $siteName = esc_html((string) ($class['site_name'] ?? 'N/A'));
        $startDate = esc_html((string) ($class['original_start_date'] ?? 'N/A'));
        $deliveryDate = isset($class['delivery_date']) && $class['delivery_date'] !== null
            ? esc_html((string) $class['delivery_date'])
            : '<em>Not specified</em>';

        return sprintf(
            '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Material Delivery Notification</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table cellpadding="0" cellspacing="0" border="0" width="100%%" style="background-color: #f4f4f4; padding: 20px;">
        <tr>
            <td align="center">
                <table cellpadding="0" cellspacing="0" border="0" width="600" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background-color: #%s; color: white; padding: 20px; text-align: center;">
                            <h1 style="margin: 0; font-size: 24px;">Material Delivery Notification</h1>
                            <p style="margin: 10px 0 0 0; font-size: 16px; font-weight: bold;">Status: %s</p>
                        </td>
                    </tr>
                    
                    <!-- Body -->
                    <tr>
                        <td style="padding: 30px;">
                            <p style="margin: 0 0 20px 0; font-size: 16px; color: #333;">
                                <strong>⚠️ Action Required:</strong> Materials need to be delivered for the following class.
                            </p>
                            
                            <table cellpadding="0" cellspacing="0" border="0" width="100%%" style="border-collapse: collapse;">
                                <tr style="background-color: #f8f9fa;">
                                    <td style="padding: 12px; border: 1px solid #dee2e6; font-weight: bold; width: 40%%;">Class Code</td>
                                    <td style="padding: 12px; border: 1px solid #dee2e6;">%s</td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px; border: 1px solid #dee2e6; font-weight: bold;">Subject</td>
                                    <td style="padding: 12px; border: 1px solid #dee2e6;">%s</td>
                                </tr>
                                <tr style="background-color: #f8f9fa;">
                                    <td style="padding: 12px; border: 1px solid #dee2e6; font-weight: bold;">Client</td>
                                    <td style="padding: 12px; border: 1px solid #dee2e6;">%s</td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px; border: 1px solid #dee2e6; font-weight: bold;">Site</td>
                                    <td style="padding: 12px; border: 1px solid #dee2e6;">%s</td>
                                </tr>
                                <tr style="background-color: #f8f9fa;">
                                    <td style="padding: 12px; border: 1px solid #dee2e6; font-weight: bold;">Class Start Date</td>
                                    <td style="padding: 12px; border: 1px solid #dee2e6;">%s</td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px; border: 1px solid #dee2e6; font-weight: bold;">Expected Delivery Date</td>
                                    <td style="padding: 12px; border: 1px solid #dee2e6;">%s</td>
                                </tr>
                                <tr style="background-color: #fff3cd;">
                                    <td style="padding: 12px; border: 1px solid #dee2e6; font-weight: bold;">⏰ Days Until Start</td>
                                    <td style="padding: 12px; border: 1px solid #dee2e6; color: #%s; font-weight: bold; font-size: 18px;">%d days</td>
                                </tr>
                            </table>
                            
                            <div style="margin-top: 25px; padding: 15px; background-color: #e7f3ff; border-left: 4px solid #0066cc; border-radius: 4px;">
                                <p style="margin: 0; font-size: 14px; color: #333;">
                                    <strong>📋 Note:</strong> This is an automated reminder based on the class start date. 
                                    Please ensure materials are prepared and delivered in time for the class.
                                </p>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 20px; background-color: #f8f9fa; border-top: 1px solid #dee2e6;">
                            <p style="margin: 0; font-size: 12px; color: #6c757d; text-align: center;">
                                This is an automated notification from the WeCoza Events Plugin.<br>
                                Notification Type: <strong>%s</strong> | Sent: %s
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>',
            $backgroundColor,
            $statusLabel,
            $classCode,
            $classSubject,
            $clientName,
            $siteName,
            $startDate,
            $deliveryDate,
            $textColor,
            $daysRemaining,
            $notificationType,
            gmdate('Y-m-d H:i:s T')
        );
    }
}
