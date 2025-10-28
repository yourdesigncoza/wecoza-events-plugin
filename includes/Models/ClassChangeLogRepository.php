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

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getLogsWithAISummary(PDO $pdo, string $schema, int $limit, ?int $classId, ?string $operation): array
    {
        $schemaName = $this->quoteIdentifier($schema);
        $tableName = $schemaName . '.' . $this->quoteIdentifier('class_change_logs');

        $conditions = [];
        $params = [];

        if ($classId !== null) {
            $conditions[] = 'class_id = :class_id';
            $params[':class_id'] = $classId;
        }

        if ($operation !== null && in_array(strtoupper($operation), ['INSERT', 'UPDATE'], true)) {
            $conditions[] = 'operation = :operation';
            $params[':operation'] = strtoupper($operation);
        }

        $whereClause = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = sprintf(
            "SELECT log_id, operation, changed_at, class_id, (new_row->>'class_code') AS class_code, (new_row->>'class_subject') AS class_subject, ai_summary FROM %s %s ORDER BY changed_at DESC LIMIT :limit;",
            $tableName,
            $whereClause
        );

        $stmt = $pdo->prepare($sql);
        if ($stmt === false) {
            return [];
        }

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

        if (!$stmt->execute()) {
            return [];
        }

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = [
                'log_id' => $row['log_id'] ?? null,
                'operation' => $row['operation'] ?? null,
                'changed_at' => $row['changed_at'] ?? null,
                'class_id' => $row['class_id'] ?? null,
                'class_code' => $row['class_code'] ?? null,
                'class_subject' => $row['class_subject'] ?? null,
                'ai_summary' => $row['ai_summary'] ?? null,
            ];
        }

        return $results;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return sprintf('"%s"', str_replace('"', '""', $identifier));
    }
}
