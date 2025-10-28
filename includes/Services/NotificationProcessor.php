<?php
declare(strict_types=1);

namespace WeCozaEvents\Services;

use PDO;
use RuntimeException;
use WeCozaEvents\Database\Connection;
use WeCozaEvents\Services\AISummaryService;
use WeCozaEvents\Support\OpenAIConfig;
use WeCozaEvents\Views\Presenters\NotificationEmailPresenter;

use function error_log;
use function get_option;
use function get_transient;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function microtime;
use function set_transient;
use function delete_transient;
use function max;
use function sprintf;
use function strtoupper;
use function strtolower;
use function update_option;
use function wp_mail;
use function absint;
use function do_action;
use function gmdate;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final class NotificationProcessor
{
    private const OPTION_LAST_ID = 'wecoza_last_notified_log_id';
    private const LOCK_KEY = 'wecoza_ai_summary_lock';
    private const LOCK_TTL = 30;
    private const MAX_RUNTIME_SECONDS = 20;
    private const MIN_REMAINING_SECONDS = 5;
    private const BATCH_LIMIT = 1;
    private const SKIP_MESSAGES = [
        'config_missing' => 'OpenAI configuration missing or invalid.',
        'feature_disabled' => 'AI summaries disabled via admin settings.',
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $schema,
        private readonly NotificationSettings $settings,
        private readonly AISummaryService $aiSummaryService,
        private readonly OpenAIConfig $openAIConfig,
        private readonly NotificationEmailPresenter $presenter
    ) {
    }

    public static function boot(): self
    {
        $pdo = Connection::getPdo();
        $schema = Connection::getSchema();
        $openAIConfig = new OpenAIConfig();
        $aiSummaryService = new AISummaryService($openAIConfig);
        $presenter = new NotificationEmailPresenter();

        return new self($pdo, $schema, new NotificationSettings(), $aiSummaryService, $openAIConfig, $presenter);
    }

    public function process(): void
    {
        if (!$this->acquireLock()) {
            return;
        }

        $start = microtime(true);
        $lastProcessed = (int) get_option(self::OPTION_LAST_ID, 0);
        $latestId = $lastProcessed;

        try {
            $rows = $this->fetchRows($lastProcessed, self::BATCH_LIMIT);

            foreach ($rows as $row) {
                if ($this->shouldStop($start)) {
                    break;
                }

                $latestId = max($latestId, (int) $row['log_id']);
            $operation = strtoupper((string) ($row['operation'] ?? ''));
            $recipient = $this->settings->getRecipientForOperation($operation);
            if ($recipient === null) {
                continue;
            }

            $logId = (int) $row['log_id'];
            $newRow = $this->decodeJson($row['new_row'] ?? null);
            $oldRow = $this->decodeJson($row['old_row'] ?? null);
            $diff = $this->decodeJson($row['diff'] ?? null);
            $summaryRecord = $this->decodeJson($row['ai_summary'] ?? null);

            $emailContext = ['alias_map' => [], 'obfuscated' => []];

            $eligibility = $this->openAIConfig->assessEligibility($logId);

            if ($eligibility['eligible'] === false) {
                if ($this->shouldMarkFailure($summaryRecord)) {
                    $reason = is_string($eligibility['reason']) ? $eligibility['reason'] : 'feature_disabled';
                    $summaryRecord = $this->finalizeSkippedSummary($summaryRecord, $reason);
                    $this->persistSummary($logId, $summaryRecord);
                    $this->emitSummaryMetrics($logId, $summaryRecord);
                }
            } elseif ($this->shouldGenerateSummary($summaryRecord)) {
                $result = $this->aiSummaryService->generateSummary([
                    'log_id' => $logId,
                    'operation' => $operation,
                    'changed_at' => $row['changed_at'] ?? null,
                    'class_id' => $row['class_id'] ?? null,
                    'new_row' => $newRow,
                    'old_row' => $oldRow,
                    'diff' => $diff,
                ], $summaryRecord);

                $summaryRecord = $result['record'];
                $emailContext = $result['email_context'];
                $this->persistSummary($logId, $summaryRecord);
                $this->emitSummaryMetrics($logId, $summaryRecord);
            }

            $mailData = $this->presenter->present([
                'operation' => $operation,
                'row' => $row,
                'recipient' => $recipient,
                'new_row' => $newRow,
                'old_row' => $oldRow,
                'diff' => $diff,
                'summary' => $summaryRecord,
                'email_context' => $emailContext,
            ]);

            $subject = $mailData['subject'];
            $body = $mailData['body'];
            $headers = $mailData['headers'];

            $sent = wp_mail($recipient, $subject, $body, $headers);
            if (!$sent) {
                error_log(sprintf('WeCoza notification failed for row %d to %s', (int) $row['log_id'], $recipient));
            } else {
                // error_log(sprintf('WeCoza notification sent for row %d to %s', (int) $row['log_id'], $recipient));
            }
            }

            if ($latestId !== $lastProcessed) {
                update_option(self::OPTION_LAST_ID, $latestId, false);
            }
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchRows(int $afterId, int $limit): array
    {
        $table = sprintf('"%s".class_change_logs', $this->schema);
        $sql = <<<SQL
SELECT
    log_id,
    operation,
    changed_at,
    class_id,
    new_row,
    old_row,
    diff,
    ai_summary
FROM {$table}
WHERE log_id > :after_id
ORDER BY log_id ASC
LIMIT :limit;
SQL;

        $stmt = $this->pdo->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare notification query.');
        }

        $stmt->bindValue(':after_id', $afterId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to execute notification query.');
        }

        /** @var array<int, array<string, mixed>> $results */
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $results;
    }

    /**
     * @param array<string, mixed> $row
     * @return array{subject:string, body:string}
     */
    private function decodeJson(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $value
     */
    private function shouldGenerateSummary(array $summary): bool
    {
        $status = is_string($summary['status'] ?? null) ? strtolower((string) $summary['status']) : 'pending';
        if ($status === 'success' || $status === 'failed') {
            return false;
        }

        $attempts = absint($summary['attempts'] ?? 0);
        return $attempts < $this->aiSummaryService->getMaxAttempts();
    }

    private function shouldMarkFailure(array $summary): bool
    {
        $status = is_string($summary['status'] ?? null) ? strtolower((string) $summary['status']) : 'pending';
        return $status !== 'failed' && $status !== 'success';
    }

    private function finalizeSkippedSummary(array $summary, string $reason): array
    {
        $normalised = $this->normaliseSummaryPayload($summary);
        $normalised['status'] = 'failed';
        $normalised['error_code'] = $reason;
        $normalised['error_message'] = self::SKIP_MESSAGES[$reason] ?? 'AI summary skipped.';
        if (!is_string($normalised['generated_at']) || $normalised['generated_at'] === '') {
            $normalised['generated_at'] = gmdate('c');
        }

        return $normalised;
    }

    private function normaliseSummaryPayload(array $summary): array
    {
        return [
            'summary' => $summary['summary'] ?? null,
            'status' => (string) ($summary['status'] ?? 'pending'),
            'error_code' => $summary['error_code'] ?? null,
            'error_message' => $summary['error_message'] ?? null,
            'attempts' => absint($summary['attempts'] ?? 0),
            'viewed' => (bool) ($summary['viewed'] ?? false),
            'viewed_at' => $summary['viewed_at'] ?? null,
            'generated_at' => $summary['generated_at'] ?? null,
            'model' => $summary['model'] ?? null,
            'tokens_used' => isset($summary['tokens_used']) ? (int) $summary['tokens_used'] : 0,
            'processing_time_ms' => isset($summary['processing_time_ms']) ? (int) $summary['processing_time_ms'] : 0,
        ];
    }

    private function persistSummary(int $logId, array $summary): void
    {
        $table = sprintf('"%s".class_change_logs', $this->schema);
        $stmt = $this->pdo->prepare(sprintf('UPDATE %s SET ai_summary = :summary WHERE log_id = :log_id', $table));
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare AI summary update.');
        }

        $payload = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            $payload = '{}';
        }
        $stmt->bindValue(':summary', $payload, PDO::PARAM_STR);
        $stmt->bindValue(':log_id', $logId, PDO::PARAM_INT);

        $stmt->execute();
    }

    private function emitSummaryMetrics(int $logId, array $summary): void
    {
        do_action('wecoza_ai_summary_generated', [
            'log_id' => $logId,
            'status' => $summary['status'] ?? 'pending',
            'model' => $summary['model'] ?? null,
            'tokens_used' => $summary['tokens_used'] ?? 0,
            'processing_time_ms' => $summary['processing_time_ms'] ?? 0,
            'attempts' => $summary['attempts'] ?? 0,
        ]);
    }

    private function shouldStop(float $start): bool
    {
        $elapsed = microtime(true) - $start;
        if ($elapsed >= self::MAX_RUNTIME_SECONDS) {
            return true;
        }

        return (self::MAX_RUNTIME_SECONDS - $elapsed) < self::MIN_REMAINING_SECONDS;
    }

    private function acquireLock(): bool
    {
        $existing = get_transient(self::LOCK_KEY);
        if ($existing !== false) {
            return false;
        }

        return set_transient(self::LOCK_KEY, '1', self::LOCK_TTL);
    }

    private function releaseLock(): void
    {
        delete_transient(self::LOCK_KEY);
    }
}
