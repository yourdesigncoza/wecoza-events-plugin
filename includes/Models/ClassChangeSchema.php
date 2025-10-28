<?php
declare(strict_types=1);

namespace WeCozaEvents\Models;

use PDO;
use RuntimeException;
use function preg_match;
use function sprintf;
use function str_replace;

final class ClassChangeSchema
{
    public function ensureArtifacts(PDO $pdo, string $schema): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $schema)) {
            throw new RuntimeException('Schema name may only contain letters, numbers, and underscores.');
        }

        $schemaName = $this->quoteIdentifier($schema);
        $tableName = $schemaName . '.' . $this->quoteIdentifier('class_change_logs');
        $functionName = $schemaName . '.' . $this->quoteIdentifier('log_class_change');

        $createTableSql = sprintf(
            "CREATE TABLE IF NOT EXISTS %s (
    log_id BIGSERIAL PRIMARY KEY,
    class_id INTEGER NOT NULL,
    operation TEXT NOT NULL,
    changed_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT NOW(),
    new_row JSONB NOT NULL,
    old_row JSONB,
    diff JSONB NOT NULL DEFAULT '{}'::jsonb,
    tasks JSONB DEFAULT '[]'::jsonb,
    ai_summary JSONB DEFAULT NULL
);",
            $tableName
        );

        $ensureSummaryColumnSql = sprintf(
            'ALTER TABLE %s ADD COLUMN IF NOT EXISTS ai_summary JSONB DEFAULT NULL;',
            $tableName
        );

        $createIndexSql = sprintf(
            'CREATE INDEX IF NOT EXISTS class_change_logs_class_id_idx ON %s (class_id);',
            $tableName
        );

        $createFunctionSql = <<<SQL
CREATE OR REPLACE FUNCTION {$functionName}() RETURNS trigger AS $$
DECLARE
    op TEXT := TG_OP;
    event_time TIMESTAMP WITHOUT TIME ZONE := NOW();
    new_data JSONB := to_jsonb(NEW);
    old_data JSONB := CASE WHEN TG_OP = 'UPDATE' THEN to_jsonb(OLD) ELSE NULL END;
    diff JSONB := '{}'::jsonb;
BEGIN
    IF op = 'UPDATE' THEN
        diff := (
            SELECT COALESCE(
                jsonb_object_agg(key, jsonb_build_object('old', old_data -> key, 'new', new_data -> key)),
                '{}'::jsonb
            )
            FROM (
                SELECT key FROM jsonb_object_keys(new_data) AS new_keys(key)
                UNION
                SELECT key FROM jsonb_object_keys(COALESCE(old_data, '{}'::jsonb)) AS old_keys(key)
            ) AS keys(key)
            WHERE (old_data -> key) IS DISTINCT FROM (new_data -> key)
        );
    ELSE
        diff := new_data;
    END IF;

    INSERT INTO {$tableName} (class_id, operation, changed_at, new_row, old_row, diff)
    VALUES (NEW.class_id, op, event_time, new_data, old_data, diff);

    PERFORM pg_notify(
        'class_change_channel',
        json_build_object(
            'operation', op,
            'class_id', NEW.class_id,
            'class_code', NEW.class_code,
            'class_subject', NEW.class_subject,
            'changed_at', event_time,
            'diff', diff
        )::text
    );

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
SQL;

        $dropTriggerSql = 'DROP TRIGGER IF EXISTS classes_log_insert_update ON public.classes;';

        $createTriggerSql = <<<SQL
CREATE TRIGGER classes_log_insert_update
AFTER INSERT OR UPDATE ON public.classes
FOR EACH ROW EXECUTE FUNCTION {$functionName}();
SQL;

        foreach ([$createTableSql, $ensureSummaryColumnSql, $createIndexSql, $createFunctionSql, $dropTriggerSql, $createTriggerSql] as $statement) {
            $pdo->exec($statement);
        }
    }

    private function quoteIdentifier(string $identifier): string
    {
        return sprintf('"%s"', str_replace('"', '""', $identifier));
    }
}
