--
-- WECOZA Class Workflow Task Schema
--
-- Defines task tracking, audit logging, and notification outbox tables
-- supporting the redesigned class workflow + notification system.
--

BEGIN;

-- ---------------------------------------------------------------------------
-- class_tasks: primary record for workflow task instances
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS public.class_tasks (
    id BIGSERIAL PRIMARY KEY,
    class_id INTEGER NOT NULL,
    workflow_event VARCHAR(100) NOT NULL,
    task_key VARCHAR(100) NOT NULL,
    title VARCHAR(255) NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'pending',
    owner_type VARCHAR(40) NOT NULL DEFAULT 'role',
    owner_id VARCHAR(100),
    due_on DATE,
    started_on TIMESTAMP WITHOUT TIME ZONE,
    completed_on TIMESTAMP WITHOUT TIME ZONE,
    confirmed_on TIMESTAMP WITHOUT TIME ZONE,
    reminder_state VARCHAR(40) NOT NULL DEFAULT 'idle',
    reminder_count INTEGER NOT NULL DEFAULT 0,
    source VARCHAR(40) NOT NULL DEFAULT 'auto',
    payload JSONB NOT NULL DEFAULT '{}'::JSONB,
    created_by INTEGER,
    created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE public.class_tasks IS 'Workflow task instances for WECOZA classes.';
COMMENT ON COLUMN public.class_tasks.class_id IS 'Reference to public.classes.class_id.';
COMMENT ON COLUMN public.class_tasks.workflow_event IS 'Originating workflow event name.';
COMMENT ON COLUMN public.class_tasks.task_key IS 'Unique key within a workflow bundle.';
COMMENT ON COLUMN public.class_tasks.status IS 'Task state (pending, action_done, informed, blocked, skipped).';
COMMENT ON COLUMN public.class_tasks.owner_type IS 'Assignment type: role, user, email, external.';
COMMENT ON COLUMN public.class_tasks.owner_id IS 'Identifier determined by owner_type (WP user ID, role slug, email).';
COMMENT ON COLUMN public.class_tasks.reminder_state IS 'Reminder state machine (idle, scheduled, sent, escalated).';
COMMENT ON COLUMN public.class_tasks.source IS 'auto = template-created, manual = user added.';
COMMENT ON COLUMN public.class_tasks.payload IS 'JSON metadata (class context, template variables).';

CREATE INDEX IF NOT EXISTS class_tasks_class_id_idx ON public.class_tasks (class_id);
CREATE INDEX IF NOT EXISTS class_tasks_status_idx ON public.class_tasks (status);
CREATE INDEX IF NOT EXISTS class_tasks_due_on_idx ON public.class_tasks (due_on);
CREATE INDEX IF NOT EXISTS class_tasks_owner_idx ON public.class_tasks (owner_type, owner_id);
CREATE INDEX IF NOT EXISTS class_tasks_updated_at_idx ON public.class_tasks (updated_at);

-- ---------------------------------------------------------------------------
-- class_task_logs: immutable audit history for task state and notifications
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS public.class_task_logs (
    id BIGSERIAL PRIMARY KEY,
    task_id BIGINT NOT NULL REFERENCES public.class_tasks (id) ON DELETE CASCADE,
    entry_type VARCHAR(60) NOT NULL,
    details JSONB NOT NULL DEFAULT '{}'::JSONB,
    created_by INTEGER,
    created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE public.class_task_logs IS 'Audit trail entries for class workflow tasks.';
COMMENT ON COLUMN public.class_task_logs.entry_type IS 'status.change, reminder.sent, confirmation.sent, note, etc.';

CREATE INDEX IF NOT EXISTS class_task_logs_task_id_idx ON public.class_task_logs (task_id);
CREATE INDEX IF NOT EXISTS class_task_logs_entry_type_idx ON public.class_task_logs (entry_type);
CREATE INDEX IF NOT EXISTS class_task_logs_created_at_idx ON public.class_task_logs (created_at);

-- ---------------------------------------------------------------------------
-- notifications_outbox: pending reminder/confirmation deliveries
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS public.notifications_outbox (
    id BIGSERIAL PRIMARY KEY,
    task_id BIGINT REFERENCES public.class_tasks (id) ON DELETE SET NULL,
    message_type VARCHAR(40) NOT NULL,
    channel VARCHAR(40) NOT NULL DEFAULT 'email',
    recipient VARCHAR(255) NOT NULL,
    subject VARCHAR(255),
    body TEXT,
    send_after TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT NOW(),
    status VARCHAR(40) NOT NULL DEFAULT 'pending',
    attempt_count INTEGER NOT NULL DEFAULT 0,
    last_attempt_at TIMESTAMP WITHOUT TIME ZONE,
    last_error TEXT,
    payload JSONB NOT NULL DEFAULT '{}'::JSONB,
    created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE public.notifications_outbox IS 'Queue of reminder/confirmation messages awaiting delivery.';
COMMENT ON COLUMN public.notifications_outbox.message_type IS 'reminder, confirmation, escalation, etc.';
COMMENT ON COLUMN public.notifications_outbox.channel IS 'email, sms, in_app, webhook, etc.';

CREATE INDEX IF NOT EXISTS notifications_outbox_status_idx ON public.notifications_outbox (status);
CREATE INDEX IF NOT EXISTS notifications_outbox_send_after_idx ON public.notifications_outbox (send_after);
CREATE INDEX IF NOT EXISTS notifications_outbox_task_id_idx ON public.notifications_outbox (task_id);

COMMIT;
