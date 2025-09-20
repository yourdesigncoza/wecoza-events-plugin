# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

WECOZA Notifications Core is a centralized notification system for WordPress that handles reminders and confirmations across multiple modules via event-driven architecture. The plugin is designed for the WECOZA education management system and uses a dual-database architecture: WordPress MySQL for core functionality and PostgreSQL for application data.

## Development Commands

### Database Operations
```bash
# Navigate to plugin directory
cd /opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-events-plugin/

# PostgreSQL Commands
# Check PostgreSQL tables exist
psql -U your_user -d your_db -c "\dt wecoza_events.*"

# Check classes table integration
psql -U your_user -d your_db -c "SELECT class_id, client_id, class_code FROM public.classes LIMIT 5;"

# Run PostgreSQL schema creation
psql -U your_user -d your_db -f schema/postgresql_events_schema.sql

# Test PostgreSQL connection
php -r "require_once 'app/Services/PostgreSQLDatabaseService.php';
        use WecozaNotifications\PostgreSQLDatabaseService;
        \$db = PostgreSQLDatabaseService::getInstance();
        var_dump(\$db->test_connection());"
```

### Plugin Testing and Activation
```bash
# Test plugin activation (creates PostgreSQL tables if enabled)
# This must be done through WordPress admin interface

# Test event emission
# In WordPress context:
do_action('wecoza_event', [
    'event' => 'class.created',
    'class_id' => 123,
    'actor_id' => 1,
    'idempotency_key' => 'class.created:123:' . time(),
    'occurred_at' => current_time('mysql'),
    'metadata' => ['client_name' => 'Test Client']
]);
```

### Translation Commands
```bash
# Generate POT file for translators
xgettext --language=PHP --from-code=UTF-8 --keyword=__ --keyword=_e --keyword=_n:1,2 \
  --package-name="WECOZA Notifications Core" --package-version="1.0.0" \
  --output=languages/wecoza-notifications.pot app/**/*.php
```

## Architecture Overview

### Event-Driven Design
The system uses a centralized event bus where feature plugins emit events and this plugin processes notifications:

- **Event Source**: Other plugins emit events via `do_action('wecoza_event', $event_data)`
- **Event Processor**: `EventProcessor.php` handles all incoming events
- **Notification Pipeline**: Events trigger notifications via email/dashboard channels
- **Idempotency**: Each event has an idempotency key to prevent duplicates

### Core Components

#### Services Layer (`app/Services/`)
- **EventProcessor**: Central event handling, routing, and processing using PostgreSQL
- **EmailService**: WordPress `wp_mail()` integration with queue management
- **TemplateService**: Email template rendering with variable replacement
- **PostgreSQLDatabaseService**: Primary database service using PDO with transaction support
- **SecurityService**: Comprehensive security utilities for input validation and sanitization
- **AuditService**: Security and operation audit trail
- **AnalyticsService**: Metrics and analytics storage
- **CronService**: Background task processing and reminders

#### Configuration System (`config/`)
- **events.php**: Defines all event types (EVT-01 through EVT-06) and their notification rules
- **templates.php**: Email template definitions with subject/body/variables
- **settings.php**: System settings for email, reminders, database, security

#### Database Architecture (Dual Database)
**PostgreSQL Schema (wecoza_events):**
- `supervisors`: Supervisor assignments with JSONB client/site assignments
- `notification_queue`: Queued notifications with retry logic and idempotency
- `events_log`: Event audit trail with JSONB payloads
- `dashboard_status`: Task status tracking for dashboard
- `audit_log`: Security and operation audit trail
- `analytics`: Metrics storage with JSONB values
- `template_versions`: Email template management

**Classes Integration (public.classes):**
- Integrates with existing PostgreSQL classes table using class_id as foreign key
- Uses JSONB columns: learner_ids, backup_agent_ids, schedule_data

### Notification Flow

1. **Event Emission**: `do_action('wecoza_event', $event_data)`
2. **Validation**: EventProcessor validates and logs event to PostgreSQL
3. **Routing**: Event config determines recipients and templates
4. **Queueing**: Notifications queued in PostgreSQL with idempotency checking
5. **Processing**: Cron jobs process queue using EmailService
6. **Delivery**: wp_mail() sends emails, status tracked in PostgreSQL

### Two-Phase Notification Model

- **Reminders**: Sent to responsible users for pending tasks
- **Confirmations**: Sent to supervisors/stakeholders when tasks complete

### Key WordPress Hooks

- `wecoza_event`: Main event listener hook
- `wp_ajax_wecoza_*`: AJAX endpoints for dashboard updates
- `wecoza_process_reminders`: Cron for reminder processing
- `wecoza_process_queue`: Cron for email queue processing
- `wecoza_backup_polling`: Cron for missed event detection

## Integration Points

### Classes Plugin Integration
The notification system integrates with the existing PostgreSQL classes table:
- Classes plugin/system emits events when workflows progress
- This plugin queries public.classes table for class information using class_id
- Resolves learners and agents from JSONB columns (learner_ids, backup_agent_ids)
- Dashboard status updates reflect class workflow progress

### Theme Integration
- CSS styles added to: `/opt/lampp/htdocs/wecoza/wp-content/themes/wecoza_3_child_theme/includes/css/ydcoza-styles.css`
- Uses Phoenix Bootstrap 5 framework variables
- Shortcode-based dashboard components for portability

## Development Status

**Security & Database Migration Complete:**
- ✅ All 15 critical security vulnerabilities fixed
- ✅ PostgreSQL migration complete with full schema
- ✅ Internationalization (i18n) implemented with POT file
- ✅ Plugin infrastructure and database layer complete
- ✅ Event processing system functional
- ✅ Email and template services built
- ✅ SecurityService with comprehensive validation
- ✅ Database abstraction with transaction support

**Next Phase Options:**
- REST API implementation for modern WordPress integration
- Performance optimization and advanced caching
- WordPress Settings API integration
- Comprehensive testing and documentation

Refer to `docs/task_review.md` for current priority tasks.

## Important Notes

### Namespace and Constants
- All classes use `namespace WecozaNotifications;`
- Plugin constants: `WECOZA_NOTIFICATIONS_*`
- Autoloader maps classes to file paths in `includes/class-autoloader.php`

### Security and Reliability
- **SecurityService**: Comprehensive security helper with 20+ utility methods
- **SQL Injection Prevention**: All queries use prepared statements ($1, $2 parameterization)
- **Input Validation**: Strict sanitization using SecurityService methods
- **Output Escaping**: All user-facing content properly escaped
- **Custom Capabilities**: Role-based access control with specific permissions
- **CSRF Protection**: Nonce verification on all AJAX endpoints
- **Rate Limiting**: AJAX endpoints limited to prevent abuse
- **Database Transactions**: Nested transaction support with savepoints
- **Idempotency**: Keys prevent duplicate event processing
- **Audit Trail**: Comprehensive logging of all security events

### Event Configuration
Events are defined in `config/events.php` with routing rules:
- `type`: 'confirmation' or 'reminder'
- `recipients`: 'supervisor', 'responsible_user', 'learners', 'agents'
- `template`: Template name from `config/templates.php`
- `channels`: Array of 'email', 'dashboard'

### PostgreSQL Development Workflow
1. **Database Setup**: Ensure PostgreSQL connection configured in PostgreSQLDatabaseService
2. **Schema Deployment**: Run `schema/postgresql_events_schema.sql` to create tables
3. **Testing**: Use PostgreSQL-specific test commands for validation
4. **Event Processing**: Test event emission with PostgreSQL storage
5. **Integration**: Verify classes table integration using class_id foreign keys

### Key Files for PostgreSQL Integration
- `app/Services/PostgreSQLDatabaseService.php`: Main database service
- `schema/postgresql_events_schema.sql`: Complete schema definition
- `schema/mysql_to_postgresql_migration.sql`: Migration scripts
- `schema/classes_schema.sql`: Classes table reference for integration

### Internationalization
- Text domain: `wecoza-notifications`
- POT file: `languages/wecoza-notifications.pot`
- All user-facing strings use `__()`, `_n()`, and `sprintf()` functions