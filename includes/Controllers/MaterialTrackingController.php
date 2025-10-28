<?php
declare(strict_types=1);

namespace WeCozaEvents\Controllers;

use WeCozaEvents\Services\MaterialTrackingDashboardService;

use function __;
use function absint;
use function add_action;
use function check_ajax_referer;
use function is_user_logged_in;

/**
 * Controller for material tracking AJAX actions
 */
final class MaterialTrackingController
{
    public function __construct(
        private readonly MaterialTrackingDashboardService $service,
        private readonly JsonResponder $responder
    ) {
    }

    /**
     * Register AJAX hooks
     */
    public static function register(?self $controller = null): void
    {
        $instance = $controller ?? new self(
            new MaterialTrackingDashboardService(
                new \WeCozaEvents\Models\MaterialTrackingRepository(
                    \WeCozaEvents\Database\Connection::getPdo(),
                    \WeCozaEvents\Database\Connection::getSchema()
                )
            ),
            new JsonResponder()
        );

        add_action('wp_ajax_wecoza_mark_material_delivered', [$instance, 'handleMarkDelivered']);
        add_action('wp_ajax_nopriv_wecoza_mark_material_delivered', [$instance, 'handleUnauthorized']);
    }

    /**
     * Handle unauthorized access
     */
    public function handleUnauthorized(): void
    {
        $this->responder->error(__('Authentication required.', 'wecoza-events'), 401);
    }

    /**
     * Handle mark as delivered action
     */
    public function handleMarkDelivered(): void
    {
        check_ajax_referer('wecoza_material_tracking_action', 'nonce');

        if (!is_user_logged_in()) {
            $this->responder->error(__('Please sign in to manage material tracking.', 'wecoza-events'), 403);
        }

        if (!$this->service->canManageMaterialTracking()) {
            $this->responder->error(__('You do not have permission to manage material tracking.', 'wecoza-events'), 403);
        }

        $classId = isset($_POST['class_id']) ? absint($_POST['class_id']) : 0;

        if ($classId <= 0) {
            $this->responder->error(__('Invalid class ID.', 'wecoza-events'), 400);
        }

        $success = $this->service->markAsDelivered($classId);

        if ($success) {
            $this->responder->success([
                'message' => __('Materials marked as delivered successfully.', 'wecoza-events'),
                'class_id' => $classId,
            ]);
        } else {
            $this->responder->error(__('Failed to mark materials as delivered. Please try again.', 'wecoza-events'), 500);
        }
    }
}
