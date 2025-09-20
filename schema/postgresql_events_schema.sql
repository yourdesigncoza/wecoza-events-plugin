-- PostgreSQL Schema for WeCoza Events Plugin
-- Created: September 19, 2025
-- Purpose: Migrate Events Plugin from MySQL to PostgreSQL

-- ============================================================================
-- EVENTS PLUGIN TABLES
-- ============================================================================

-- Create schema if it doesn't exist
CREATE SCHEMA IF NOT EXISTS wecoza_events;

-- Set search path
SET search_path TO wecoza_events, public;

-- ============================================================================
-- 1. SUPERVISORS TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS supervisors (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(50),
    role VARCHAR(50) DEFAULT 'supervisor',
    client_assignments JSONB DEFAULT '[]'::jsonb,
    site_assignments JSONB DEFAULT '[]'::jsonb,
    is_default BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for supervisors
CREATE INDEX IF NOT EXISTS idx_supervisors_email ON supervisors(email);
CREATE INDEX IF NOT EXISTS idx_supervisors_is_default ON supervisors(is_default);
CREATE INDEX IF NOT EXISTS idx_supervisors_is_active ON supervisors(is_active);
CREATE INDEX IF NOT EXISTS idx_supervisors_client_assignments ON supervisors USING GIN(client_assignments);
CREATE INDEX IF NOT EXISTS idx_supervisors_site_assignments ON supervisors USING GIN(site_assignments);

-- ============================================================================
-- 2. NOTIFICATION QUEUE TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS notification_queue (
    id SERIAL PRIMARY KEY,
    event_name VARCHAR(100) NOT NULL,
    idempotency_key VARCHAR(255) NOT NULL UNIQUE,
    recipient_email VARCHAR(255) NOT NULL,
    recipient_name VARCHAR(255),
    channel VARCHAR(50) DEFAULT 'email',
    template_name VARCHAR(100) NOT NULL,
    payload JSONB DEFAULT '{}'::jsonb,
    status VARCHAR(50) DEFAULT 'pending',
    attempts INTEGER DEFAULT 0,
    max_attempts INTEGER DEFAULT 3,
    last_error TEXT,
    scheduled_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for notification_queue
CREATE INDEX IF NOT EXISTS idx_notification_queue_event_name ON notification_queue(event_name);
CREATE INDEX IF NOT EXISTS idx_notification_queue_recipient_email ON notification_queue(recipient_email);
CREATE INDEX IF NOT EXISTS idx_notification_queue_status ON notification_queue(status);
CREATE INDEX IF NOT EXISTS idx_notification_queue_scheduled_at ON notification_queue(scheduled_at);
CREATE INDEX IF NOT EXISTS idx_notification_queue_channel ON notification_queue(channel);
CREATE INDEX IF NOT EXISTS idx_notification_queue_template_name ON notification_queue(template_name);

-- ============================================================================
-- 3. EVENTS LOG TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS events_log (
    id SERIAL PRIMARY KEY,
    event_name VARCHAR(100) NOT NULL,
    event_payload JSONB DEFAULT '{}'::jsonb,
    class_id INTEGER,
    actor_id INTEGER,
    idempotency_key VARCHAR(255) NOT NULL UNIQUE,
    processed BOOLEAN DEFAULT FALSE,
    occurred_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for events_log
CREATE INDEX IF NOT EXISTS idx_events_log_event_name ON events_log(event_name);
CREATE INDEX IF NOT EXISTS idx_events_log_class_id ON events_log(class_id);
CREATE INDEX IF NOT EXISTS idx_events_log_actor_id ON events_log(actor_id);
CREATE INDEX IF NOT EXISTS idx_events_log_processed ON events_log(processed);
CREATE INDEX IF NOT EXISTS idx_events_log_occurred_at ON events_log(occurred_at);
CREATE INDEX IF NOT EXISTS idx_events_log_payload ON events_log USING GIN(event_payload);

-- ============================================================================
-- 4. DASHBOARD STATUS TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS dashboard_status (
    id SERIAL PRIMARY KEY,
    class_id INTEGER NOT NULL,
    task_type VARCHAR(100) NOT NULL,
    task_status VARCHAR(50) DEFAULT 'pending',
    responsible_user_id INTEGER,
    due_date TIMESTAMP WITH TIME ZONE,
    completed_at TIMESTAMP WITH TIME ZONE,
    completion_data JSONB DEFAULT '{}'::jsonb,
    last_reminder TIMESTAMP WITH TIME ZONE,
    overdue_notified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(class_id, task_type)
);

-- Indexes for dashboard_status
CREATE INDEX IF NOT EXISTS idx_dashboard_status_class_id ON dashboard_status(class_id);
CREATE INDEX IF NOT EXISTS idx_dashboard_status_task_status ON dashboard_status(task_status);
CREATE INDEX IF NOT EXISTS idx_dashboard_status_responsible_user_id ON dashboard_status(responsible_user_id);
CREATE INDEX IF NOT EXISTS idx_dashboard_status_due_date ON dashboard_status(due_date);
CREATE INDEX IF NOT EXISTS idx_dashboard_status_task_type ON dashboard_status(task_type);

-- ============================================================================
-- 5. AUDIT LOG TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS audit_log (
    id SERIAL PRIMARY KEY,
    level VARCHAR(20) NOT NULL DEFAULT 'info',
    action VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    context JSONB DEFAULT '{}'::jsonb,
    user_id INTEGER,
    ip_address INET,
    user_agent TEXT,
    request_uri TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for audit_log
CREATE INDEX IF NOT EXISTS idx_audit_log_level ON audit_log(level);
CREATE INDEX IF NOT EXISTS idx_audit_log_action ON audit_log(action);
CREATE INDEX IF NOT EXISTS idx_audit_log_user_id ON audit_log(user_id);
CREATE INDEX IF NOT EXISTS idx_audit_log_created_at ON audit_log(created_at);
CREATE INDEX IF NOT EXISTS idx_audit_log_context ON audit_log USING GIN(context);

-- ============================================================================
-- 6. ANALYTICS TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS analytics (
    id SERIAL PRIMARY KEY,
    metric_type VARCHAR(50) NOT NULL,
    metric_key VARCHAR(100) NOT NULL,
    metric_value JSONB NOT NULL,
    date DATE NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(metric_type, metric_key, date)
);

-- Indexes for analytics
CREATE INDEX IF NOT EXISTS idx_analytics_metric_type ON analytics(metric_type);
CREATE INDEX IF NOT EXISTS idx_analytics_metric_key ON analytics(metric_key);
CREATE INDEX IF NOT EXISTS idx_analytics_date ON analytics(date);
CREATE INDEX IF NOT EXISTS idx_analytics_metric_value ON analytics USING GIN(metric_value);

-- ============================================================================
-- 7. TEMPLATE VERSIONS TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS template_versions (
    id SERIAL PRIMARY KEY,
    template_name VARCHAR(100) NOT NULL,
    version INTEGER NOT NULL DEFAULT 1,
    subject TEXT,
    body TEXT,
    variables JSONB DEFAULT '{}'::jsonb,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INTEGER,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(template_name, version)
);

-- Indexes for template_versions
CREATE INDEX IF NOT EXISTS idx_template_versions_template_name ON template_versions(template_name);
CREATE INDEX IF NOT EXISTS idx_template_versions_is_active ON template_versions(is_active);
CREATE INDEX IF NOT EXISTS idx_template_versions_created_by ON template_versions(created_by);
CREATE INDEX IF NOT EXISTS idx_template_versions_variables ON template_versions USING GIN(variables);

-- ============================================================================
-- TRIGGERS FOR AUTOMATIC UPDATED_AT
-- ============================================================================

-- Function to update updated_at column
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Create triggers for updated_at
CREATE TRIGGER update_supervisors_updated_at
    BEFORE UPDATE ON supervisors
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_notification_queue_updated_at
    BEFORE UPDATE ON notification_queue
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_dashboard_status_updated_at
    BEFORE UPDATE ON dashboard_status
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_analytics_updated_at
    BEFORE UPDATE ON analytics
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ============================================================================
-- FOREIGN KEY CONSTRAINTS (Reference to classes table)
-- ============================================================================

-- Foreign keys to classes table (assuming classes table is in public schema)
-- Uncomment these after ensuring classes table exists

-- ALTER TABLE events_log ADD CONSTRAINT fk_events_log_class_id
--   FOREIGN KEY (class_id) REFERENCES public.classes(class_id)
--   ON UPDATE CASCADE ON DELETE SET NULL;

-- ALTER TABLE dashboard_status ADD CONSTRAINT fk_dashboard_status_class_id
--   FOREIGN KEY (class_id) REFERENCES public.classes(class_id)
--   ON UPDATE CASCADE ON DELETE CASCADE;

-- Note: The classes table uses class_id as primary key, not id

-- ============================================================================
-- FUNCTIONS FOR COMMON OPERATIONS
-- ============================================================================

-- Function to get pending notifications
CREATE OR REPLACE FUNCTION get_pending_notifications(limit_count INTEGER DEFAULT 50)
RETURNS TABLE(
    id INTEGER,
    event_name VARCHAR(100),
    recipient_email VARCHAR(255),
    template_name VARCHAR(100),
    payload JSONB,
    scheduled_at TIMESTAMP WITH TIME ZONE
) AS $$
BEGIN
    RETURN QUERY
    SELECT
        nq.id,
        nq.event_name,
        nq.recipient_email,
        nq.template_name,
        nq.payload,
        nq.scheduled_at
    FROM notification_queue nq
    WHERE nq.status = 'pending'
        AND nq.scheduled_at <= CURRENT_TIMESTAMP
        AND nq.attempts < nq.max_attempts
    ORDER BY nq.scheduled_at ASC
    LIMIT limit_count;
END;
$$ LANGUAGE plpgsql;

-- Function to get unprocessed events
CREATE OR REPLACE FUNCTION get_unprocessed_events(limit_count INTEGER DEFAULT 50)
RETURNS TABLE(
    id INTEGER,
    event_name VARCHAR(100),
    event_payload JSONB,
    class_id INTEGER,
    actor_id INTEGER,
    idempotency_key VARCHAR(255),
    occurred_at TIMESTAMP WITH TIME ZONE
) AS $$
BEGIN
    RETURN QUERY
    SELECT
        el.id,
        el.event_name,
        el.event_payload,
        el.class_id,
        el.actor_id,
        el.idempotency_key,
        el.occurred_at
    FROM events_log el
    WHERE el.processed = FALSE
    ORDER BY el.occurred_at ASC
    LIMIT limit_count;
END;
$$ LANGUAGE plpgsql;

-- Function to get dashboard statistics
CREATE OR REPLACE FUNCTION get_dashboard_statistics()
RETURNS TABLE(
    total_supervisors BIGINT,
    active_supervisors BIGINT,
    pending_notifications BIGINT,
    processed_events BIGINT,
    pending_tasks BIGINT,
    overdue_tasks BIGINT
) AS $$
BEGIN
    RETURN QUERY
    SELECT
        (SELECT COUNT(*) FROM supervisors) as total_supervisors,
        (SELECT COUNT(*) FROM supervisors WHERE is_active = TRUE) as active_supervisors,
        (SELECT COUNT(*) FROM notification_queue WHERE status = 'pending') as pending_notifications,
        (SELECT COUNT(*) FROM events_log WHERE processed = TRUE) as processed_events,
        (SELECT COUNT(*) FROM dashboard_status WHERE task_status = 'pending') as pending_tasks,
        (SELECT COUNT(*) FROM dashboard_status WHERE task_status = 'pending' AND due_date < CURRENT_TIMESTAMP) as overdue_tasks;
END;
$$ LANGUAGE plpgsql;

-- ============================================================================
-- SAMPLE DATA FOR TESTING (Optional)
-- ============================================================================

-- Insert default supervisor (uncomment if needed for testing)
-- INSERT INTO supervisors (name, email, role, is_default, is_active)
-- VALUES ('Default Supervisor', 'supervisor@wecoza.co.za', 'supervisor', TRUE, TRUE)
-- ON CONFLICT (email) DO NOTHING;

-- ============================================================================
-- GRANTS AND PERMISSIONS
-- ============================================================================

-- Grant usage on schema
-- GRANT USAGE ON SCHEMA wecoza_events TO your_app_user;

-- Grant permissions on all tables
-- GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA wecoza_events TO your_app_user;

-- Grant permissions on sequences
-- GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA wecoza_events TO your_app_user;

-- ============================================================================
-- COMMENTS
-- ============================================================================

COMMENT ON SCHEMA wecoza_events IS 'WeCoza Events Plugin schema for notifications, events, and dashboard management';

COMMENT ON TABLE supervisors IS 'Supervisors assigned to manage classes and receive notifications';
COMMENT ON TABLE notification_queue IS 'Queue for outgoing notifications (email, dashboard, etc.)';
COMMENT ON TABLE events_log IS 'Log of all events processed by the system';
COMMENT ON TABLE dashboard_status IS 'Status tracking for class-related tasks';
COMMENT ON TABLE audit_log IS 'Security and operation audit trail';
COMMENT ON TABLE analytics IS 'Analytics and metrics storage';
COMMENT ON TABLE template_versions IS 'Email template versions and management';

-- ============================================================================
-- MIGRATION NOTES
-- ============================================================================

/*
MIGRATION FROM MYSQL TO POSTGRESQL:

1. Data Types Changed:
   - INT(11) AUTO_INCREMENT → SERIAL
   - TINYINT(1) → BOOLEAN
   - DATETIME → TIMESTAMP WITH TIME ZONE
   - TEXT → TEXT (same)
   - JSON → JSONB (better performance)

2. Features Added:
   - JSONB for better JSON performance
   - Proper timezone support
   - GIN indexes for JSON columns
   - INET type for IP addresses
   - Stored functions for common operations
   - Proper constraints and triggers

3. WordPress Integration:
   - user_id fields still reference WordPress users table
   - Plugin will need to handle cross-database queries
   - Consider using FDW (Foreign Data Wrapper) if needed

4. Performance Improvements:
   - Better indexing strategy
   - JSONB instead of TEXT for JSON data
   - Proper foreign keys (when enabled)
   - Stored functions for complex queries

5. Migration Script Needed:
   - Export data from MySQL
   - Transform to PostgreSQL format
   - Import with proper type conversion
   - Update application code to use PostgreSQL syntax
*/