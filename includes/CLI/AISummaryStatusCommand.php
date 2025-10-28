<?php
declare(strict_types=1);

namespace WeCozaEvents\CLI;

use PDO;
use WP_CLI;
use WP_CLI_Command;
use WeCozaEvents\Database\Connection;

use function apply_filters;
use function array_column;
use function array_map;
use function array_sum;
use function count;
use function max;
use function round;
use function sprintf;
use function ucfirst;

final class AISummaryStatusCommand extends WP_CLI_Command
{
    private const DEFAULT_HOURS = 24;
    private const DEFAULT_MODEL_RATES = [
        'gpt-5-mini' => 0.0020,
    ];

    public static function register(): void
    {
        WP_CLI::add_command('wecoza ai-summary status', new self());
    }

    /**
     * Display AI summary metrics for the last N hours.
     *
     * ## OPTIONS
     *
     * [--hours=<int>]
     * : Time window in hours (default 24).
     */
    public function status(array $args, array $assocArgs): void
    {
        $hours = isset($assocArgs['hours']) ? max(1, (int) $assocArgs['hours']) : self::DEFAULT_HOURS;

        $pdo = Connection::getPdo();
        $schema = Connection::getSchema();

        $statusRows = $this->fetchStatusCounts($pdo, $schema, $hours);
        $modelRows = $this->fetchModelBreakdown($pdo, $schema, $hours);

        $totalProcessed = array_sum(array_column($statusRows, 'count'));

        WP_CLI::log(sprintf('AI summary activity for the last %d hour(s):', $hours));
        if ($totalProcessed === 0) {
            WP_CLI::log('No notification rows processed in this window.');
            return;
        }

        WP_CLI::log('Status counts:');
        WP_CLI\Utils\format_items('table', $statusRows, ['status', 'count']);

        $totals = $this->calculateTotals($modelRows);

        WP_CLI::log('Model usage:');
        WP_CLI\Utils\format_items('table', $modelRows, ['model', 'tokens', 'processing_ms', 'estimated_cost_usd']);

        WP_CLI::log(sprintf('Total tokens: %d', $totals['tokens']));
        WP_CLI::log(sprintf('Estimated spend (USD): %.4f', $totals['cost']));
        WP_CLI::log(sprintf('Average processing time (ms): %.2f', $totals['average_processing']));
    }

    /**
     * @return array<int,array{status:string,count:int}>
     */
    private function fetchStatusCounts(PDO $pdo, string $schema, int $hours): array
    {
        $sql = sprintf(
            'SELECT COALESCE(ai_summary->>\'status\', \'pending\') AS status, COUNT(*)::int AS total
             FROM "%s".class_change_logs
             WHERE changed_at >= (NOW() AT TIME ZONE \'UTC\' - (:hours || \' hours\')::interval)
             AND ai_summary IS NOT NULL
             GROUP BY status
             ORDER BY status ASC',
            $schema
        );

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':hours', $hours, PDO::PARAM_INT);
        $stmt->execute();

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static function (array $row): array {
            return [
                'status' => ucfirst((string) ($row['status'] ?? 'unknown')),
                'count' => (int) ($row['total'] ?? 0),
            ];
        }, $results);
    }

    /**
     * @return array<int,array{model:string,tokens:int,processing_ms:int,estimated_cost_usd:float}>
     */
    private function fetchModelBreakdown(PDO $pdo, string $schema, int $hours): array
    {
        $sql = sprintf(
            'SELECT
                COALESCE(ai_summary->>\'model\', \'unknown\') AS model,
                SUM(COALESCE((ai_summary->>\'tokens_used\')::int, 0))::int AS tokens,
                SUM(COALESCE((ai_summary->>\'processing_time_ms\')::int, 0))::int AS processing_ms
             FROM "%s".class_change_logs
             WHERE changed_at >= (NOW() AT TIME ZONE \'UTC\' - (:hours || \' hours\')::interval)
             AND ai_summary IS NOT NULL
             GROUP BY model
             ORDER BY model ASC',
            $schema
        );

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':hours', $hours, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(function (array $row): array {
            $model = (string) ($row['model'] ?? 'unknown');
            $tokens = max(0, (int) ($row['tokens'] ?? 0));
            $processing = max(0, (int) ($row['processing_ms'] ?? 0));
            $cost = $this->estimateCost($model, $tokens);

            return [
                'model' => $model,
                'tokens' => $tokens,
                'processing_ms' => $processing,
                'estimated_cost_usd' => round($cost, 4),
            ];
        }, $rows);
    }

    /**
     * @param array<int,array{model:string,tokens:int,processing_ms:int,estimated_cost_usd:float}> $rows
     * @return array{tokens:int,cost:float,average_processing:float}
     */
    private function calculateTotals(array $rows): array
    {
        if ($rows === []) {
            return ['tokens' => 0, 'cost' => 0.0, 'average_processing' => 0.0];
        }

        $tokens = array_sum(array_column($rows, 'tokens'));
        $cost = array_sum(array_column($rows, 'estimated_cost_usd'));
        $processing = array_sum(array_column($rows, 'processing_ms'));
        $averageProcessing = $tokens > 0 ? $processing / max(count($rows), 1) : 0.0;

        return [
            'tokens' => (int) $tokens,
            'cost' => round((float) $cost, 4),
            'average_processing' => round((float) $averageProcessing, 2),
        ];
    }

    private function estimateCost(string $model, int $tokens): float
    {
        $rates = apply_filters('wecoza_ai_summary_cost_per_1k', self::DEFAULT_MODEL_RATES, $model);
        $rate = $rates[$model] ?? ($rates['default'] ?? 0.0);

        return ($tokens / 1000) * (float) $rate;
    }
}
