<?php
declare(strict_types=1);

namespace WeCozaEvents\Models;

use PDO;
use WeCozaEvents\Services\PayloadFormatter;
use function sprintf;
use function str_replace;

final class ClassChangeLogRepository
{
    /**
     * @param callable(array<string, mixed>):void $callback
     */
    public function exportLogs(PDO $pdo, string $schema, PayloadFormatter $formatter, callable $callback): void
    {
        $schemaName = $this->quoteIdentifier($schema);
        $tableName = $schemaName . '.' . $this->quoteIdentifier('class_change_logs');

        $sql = sprintf(
            "SELECT log_id, operation, changed_at, class_id, (new_row->>'class_code') AS class_code, (new_row->>'class_subject') AS class_subject, diff FROM %s ORDER BY log_id ASC;",
            $tableName
        );

        $stmt = $pdo->query($sql);
        if ($stmt === false) {
            return;
        }

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $payload = [
                'operation' => $row['operation'] ?? null,
                'changed_at' => $row['changed_at'] ?? null,
                'class_id' => $row['class_id'] ?? null,
                'class_code' => $row['class_code'] ?? null,
                'class_subject' => $row['class_subject'] ?? null,
                'diff' => $formatter->decodeDatabasePayload($row['diff'] ?? null),
            ];

            $callback($payload);
        }
    }

    private function quoteIdentifier(string $identifier): string
    {
        return sprintf('"%s"', str_replace('"', '""', $identifier));
    }
}
