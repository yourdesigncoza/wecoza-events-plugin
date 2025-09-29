<?php
declare(strict_types=1);

namespace WeCozaEvents\Services;

use PDO;
use RuntimeException;
use WeCozaEvents\Database\Connection;

use function error_log;
use function get_option;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function max;
use function sprintf;
use function strtoupper;
use function update_option;
use function wp_mail;
use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final class NotificationProcessor
{
    private const OPTION_LAST_ID = 'wecoza_last_notified_log_id';

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $schema,
        private readonly NotificationSettings $settings
    ) {
    }

    public static function boot(): self
    {
        $pdo = Connection::getPdo();
        $schema = Connection::getSchema();

        return new self($pdo, $schema, new NotificationSettings());
    }

    public function process(): void
    {
        $lastProcessed = (int) get_option(self::OPTION_LAST_ID, 0);
        $rows = $this->fetchRows($lastProcessed);
        $latestId = $lastProcessed;

        foreach ($rows as $row) {
            $latestId = max($latestId, (int) $row['id']);
            $operation = strtoupper((string) ($row['operation'] ?? ''));
            $recipient = $this->settings->getRecipientForOperation($operation);
            if ($recipient === null) {
                continue;
            }

            $mailData = $this->buildMailPayload($row, $operation);
            $subject = $mailData['subject'];
            $body = $mailData['body'];

            $headers = [
                'Content-Type: text/plain; charset=UTF-8',
            ];

            $sent = wp_mail($recipient, $subject, $body, $headers);
            if (!$sent) {
                error_log(sprintf('WeCoza notification failed for row %d to %s', (int) $row['id'], $recipient));
            } else {
                error_log(sprintf('WeCoza notification sent for row %d to %s', (int) $row['id'], $recipient));
            }
        }

        if ($latestId !== $lastProcessed) {
            update_option(self::OPTION_LAST_ID, $latestId, false);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchRows(int $afterId): array
    {
        $table = sprintf('"%s".class_change_logs', $this->schema);
        $sql = <<<SQL
SELECT
    id,
    operation,
    changed_at,
    class_id,
    new_row,
    old_row,
    diff
FROM {$table}
WHERE id > :after_id
ORDER BY id ASC;
SQL;

        $stmt = $this->pdo->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare notification query.');
        }

        $stmt->bindValue(':after_id', $afterId, PDO::PARAM_INT);

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
    private function buildMailPayload(array $row, string $operation): array
    {
        $newRow = $this->decodeJson($row['new_row'] ?? null);
        $diff = $this->decodeJson($row['diff'] ?? null);

        $classId = (string) ($row['class_id'] ?? '');
        $classCode = (string) ($newRow['class_code'] ?? '');
        $classSubject = (string) ($newRow['class_subject'] ?? '');
        $changedAt = (string) ($row['changed_at'] ?? '');

        $subject = sprintf('[WeCoza] Class %s: %s (%s)', strtolower($operation), $classId, $classCode ?: 'no-code');

        $bodyParts = [
            sprintf('Operation: %s', $operation),
            sprintf('Changed At: %s', $changedAt),
            sprintf('Class ID: %s', $classId),
            sprintf('Class Code: %s', $classCode ?: 'n/a'),
            sprintf('Class Subject: %s', $classSubject ?: 'n/a'),
        ];

        if (!empty($diff)) {
            $bodyParts[] = 'Changes:';
            $bodyParts[] = $this->encodeJson($diff);
        }

        if (!empty($newRow)) {
            $bodyParts[] = 'New Row Snapshot:';
            $bodyParts[] = $this->encodeJson($newRow);
        }

        return [
            'subject' => $subject,
            'body' => implode("\n\n", $bodyParts) . "\n",
        ];
    }

    /**
     * @return array<string, mixed>
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
    private function encodeJson(array $value): string
    {
        $encoded = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $encoded !== false ? $encoded : '';
    }
}
