<?php
// Debug script to test the database query
require_once __DIR__ . '/includes/class-wecoza-events-database.php';

use WeCozaEvents\Database\Connection;

try {
    $pdo = Connection::getPdo();
    $schema = Connection::getSchema();
    
    echo "Connected to schema: " . $schema . "\n";
    
    // Test if classes table exists and has data
    $classesTable = sprintf('"%s".classes', $schema);
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM {$classesTable}");
    $count = $stmt->fetchColumn();
    echo "Classes count: " . $count . "\n";
    
    // Test the actual query
    $logsTable = sprintf('"%s".class_change_logs', $schema);
    $clientsTable = sprintf('"%s".clients', $schema);
    $agentsTable = sprintf('"%s".agents', $schema);
    
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
ORDER BY c.original_start_date DESC NULLS LAST, c.class_id DESC
LIMIT 5;
SQL;
    
    echo "Executing query...\n";
    $stmt = $pdo->query($sql);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Results count: " . count($results) . "\n";
    
    foreach ($results as $row) {
        echo "Class ID: " . $row['class_id'] . ", Code: " . ($row['class_code'] ?? 'N/A') . ", Log ID: " . ($row['log_id'] ?? 'NULL') . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
