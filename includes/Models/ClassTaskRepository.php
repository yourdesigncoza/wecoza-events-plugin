<?php
declare(strict_types=1);

namespace WeCozaEvents\Models;

use PDO;
use RuntimeException;
use WeCozaEvents\Database\Connection;

use function preg_match;
use function sprintf;
use function strtolower;
use function str_replace;

final class ClassTaskRepository
{
    private PDO $pdo;
    private string $schema;

    public function __construct(?PDO $pdo = null, ?string $schema = null)
    {
        $this->pdo = $pdo ?? Connection::getPdo();
        $this->schema = $schema ?? Connection::getSchema();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchClasses(int $limit, string $sortDirection, ?int $classIdFilter): array
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $this->schema)) {
            throw new RuntimeException('Invalid schema name.');
        }

        $orderDirection = strtolower($sortDirection) === 'asc' ? 'ASC' : 'DESC';

        $classesTable = $this->qualifiedTableName('classes');
        $clientsTable = $this->qualifiedTableName('clients');
        $agentsTable = $this->qualifiedTableName('agents');
        $logsTable = $this->qualifiedTableName('class_change_logs');

        $whereClause = '';
        if ($classIdFilter !== null) {
            $whereClause = 'WHERE c.class_id = :class_id';
        }

        $sql = <<<SQL
SELECT
    c.class_id,
    c.client_id,
    c.class_type,
    c.class_subject,
    c.class_code,
    c.original_start_date,
    c.delivery_date,
    c.initial_class_agent,
    c.class_agent,
    ia.agent_id AS initial_agent_id,
    ia.first_name AS initial_agent_first,
    ia.surname AS initial_agent_surname,
    ia.initials AS initial_agent_initials,
    pa.agent_id AS primary_agent_id,
    pa.first_name AS primary_agent_first,
    pa.surname AS primary_agent_surname,
    pa.initials AS primary_agent_initials,
    c.exam_class,
    c.exam_type,
    c.seta_funded,
    COALESCE(c.seta, cl.seta) AS seta_name,
    c.stop_restart_dates,
    c.updated_at,
    cl.client_name,
    l.log_id AS log_id,
    l.operation,
    l.changed_at
FROM {$classesTable} c
LEFT JOIN {$clientsTable} cl ON cl.client_id = c.client_id
LEFT JOIN {$agentsTable} ia ON ia.agent_id = c.initial_class_agent
LEFT JOIN {$agentsTable} pa ON pa.agent_id = c.class_agent
LEFT JOIN LATERAL (
    SELECT log_id, operation, changed_at
    FROM {$logsTable} log
    WHERE log.class_id = c.class_id
      AND LOWER(log.operation) IN ('insert', 'update')
    ORDER BY log.changed_at DESC
    LIMIT 1
) l ON TRUE
{$whereClause}
ORDER BY c.original_start_date {$orderDirection} NULLS LAST, c.class_id {$orderDirection}
LIMIT :limit;
SQL;

        $stmt = $this->pdo->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare class query.');
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        if ($classIdFilter !== null) {
            $stmt->bindValue(':class_id', $classIdFilter, PDO::PARAM_INT);
        }

        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to execute class query.');
        }

        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row === false) {
                continue;
            }
            $result[] = $row;
        }

        return $result;
    }

    private function qualifiedTableName(string $table): string
    {
        return sprintf('%s.%s', $this->quoteIdentifier($this->schema), $this->quoteIdentifier($table));
    }

    private function quoteIdentifier(string $identifier): string
    {
        return sprintf('"%s"', str_replace('"', '""', $identifier));
    }
}
