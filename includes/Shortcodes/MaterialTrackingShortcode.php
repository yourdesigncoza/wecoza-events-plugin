<?php
declare(strict_types=1);

namespace WeCozaEvents\Shortcodes;

use WeCozaEvents\Services\MaterialTrackingDashboardService;
use WeCozaEvents\Views\Presenters\MaterialTrackingPresenter;
use WeCozaEvents\Views\TemplateRenderer;

use function absint;
use function add_shortcode;
use function esc_html;
use function esc_html__;
use function shortcode_atts;

/**
 * Shortcode for material tracking dashboard
 */
final class MaterialTrackingShortcode
{
    private const DEFAULT_LIMIT = 50;
    private const DEFAULT_DAYS_RANGE = 30;

    public function __construct(
        private readonly MaterialTrackingDashboardService $service,
        private readonly MaterialTrackingPresenter $presenter,
        private readonly TemplateRenderer $renderer
    ) {
    }

    /**
     * Register shortcode
     */
    public static function register(?self $shortcode = null): void
    {
        $instance = $shortcode ?? new self(
            new MaterialTrackingDashboardService(
                new \WeCozaEvents\Models\MaterialTrackingRepository(
                    \WeCozaEvents\Database\Connection::getPdo(),
                    \WeCozaEvents\Database\Connection::getSchema()
                )
            ),
            new MaterialTrackingPresenter(),
            new TemplateRenderer()
        );

        add_shortcode('wecoza_material_tracking', [$instance, 'render']);
    }

    /**
     * Render shortcode
     *
     * @param array<string, mixed> $atts Shortcode attributes
     * @return string Rendered HTML
     */
    public function render(array $atts = []): string
    {
        if (!$this->service->canViewDashboard()) {
            return $this->wrapMessage(
                esc_html__('You do not have permission to view the material tracking dashboard.', 'wecoza-events')
            );
        }

        $atts = shortcode_atts([
            'limit' => self::DEFAULT_LIMIT,
            'status' => 'all',
            'notification_type' => 'all',
            'days_range' => self::DEFAULT_DAYS_RANGE,
        ], $atts, 'wecoza_material_tracking');

        $filters = $this->parseAttributes($atts);

        try {
            $records = $this->service->getDashboardData($filters);
            $stats = $this->service->getStatistics($filters['days_range']);
        } catch (\Throwable $e) {
            error_log('Material Tracking Dashboard Error: ' . $e->getMessage());
            return $this->wrapMessage(
                esc_html__('Unable to load material tracking data. Please try again later.', 'wecoza-events')
            );
        }

        $presentedRecords = $this->presenter->presentRecords($records);
        $presentedStats = $this->presenter->presentStatistics($stats);

        return $this->renderer->render('material-tracking/dashboard', [
            'records' => $presentedRecords,
            'statistics' => $presentedStats,
            'filters' => $filters,
            'can_manage' => $this->service->canManageMaterialTracking(),
        ]);
    }

    /**
     * Parse shortcode attributes
     *
     * @param array<string, mixed> $atts Raw attributes
     * @return array<string, mixed> Parsed attributes
     */
    private function parseAttributes(array $atts): array
    {
        $limit = absint($atts['limit']);
        $limit = $limit > 0 ? min($limit, 200) : self::DEFAULT_LIMIT;

        $status = (string) $atts['status'];
        $status = $status === 'all' ? null : $status;

        $notificationType = (string) $atts['notification_type'];
        $notificationType = $notificationType === 'all' ? null : $notificationType;

        $daysRange = absint($atts['days_range']);
        $daysRange = $daysRange > 0 ? $daysRange : self::DEFAULT_DAYS_RANGE;

        return [
            'limit' => $limit,
            'status' => $status,
            'notification_type' => $notificationType,
            'days_range' => $daysRange,
        ];
    }

    /**
     * Wrap message in container
     *
     * @param string $message Message to display
     * @return string Wrapped HTML
     */
    private function wrapMessage(string $message): string
    {
        return sprintf(
            '<div class="wecoza-material-tracking-message alert alert-info">%s</div>',
            $message
        );
    }
}
