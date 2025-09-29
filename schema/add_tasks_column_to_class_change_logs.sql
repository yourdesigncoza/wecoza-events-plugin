-- Adds a JSONB `tasks` column to track per-event tasks and their completion metadata.
ALTER TABLE class_change_logs
    ADD COLUMN IF NOT EXISTS tasks JSONB DEFAULT '[]'::jsonb;

-- Ensure existing rows have an array value rather than NULL.
UPDATE class_change_logs
SET tasks = '[]'::jsonb
WHERE tasks IS NULL;
