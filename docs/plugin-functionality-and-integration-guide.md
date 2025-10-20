# WeCoza Events Plugin - Functionality & Integration Guide

**Version:** 1.0  
**Last Updated:** October 20, 2025  
**Plugin Version:** 0.1.0  

---

## 📖 Table of Contents

1. [Plugin Overview](#plugin-overview)
2. [Email Notification System](#email-notification-system)
3. [Integration Guide for Other Plugins](#integration-guide-for-other-plugins)
4. [Configuration Management](#configuration-management)
5. [Database Schema Documentation](#database-schema-documentation)
6. [API Reference](#api-reference)
7. [Development Guidelines](#development-guidelines)
8. [Troubleshooting](#troubleshooting)

---

## 🚀 Plugin Overview

### Purpose
The WeCoza Events Plugin is a comprehensive WordPress plugin that monitors WeCoza class changes from PostgreSQL and provides task management functionality. Built with strict PHP typing, MVC architecture, and extensible service patterns for seamless integration with other plugins.

### Key Features
- **PostgreSQL Integration**: Real-time monitoring of class changes via database triggers
- **Task Management**: Interactive UI for tracking completion of class-related tasks
- **Email Notifications**: Automated notifications for INSERT/UPDATE operations
- **MVC Architecture**: Clean separation of concerns with controllers, models, services, and views
- **Extensible Design**: Service container pattern for easy integration by other plugins
- **Shortcode Support**: Embed task management UI anywhere with `[wecoza_event_tasks]`
- **WordPress Hooks**: Full integration with WordPress cron, AJAX, and admin system

### MVC Architecture Structure
```
includes/
├── Controllers/     # AJAX handlers and request routing
├── Models/          # Data objects and repository patterns
├── Services/        # Business logic and external integrations
├── Views/           # UI rendering and templating
├── Shortcodes/      # WordPress shortcode handlers
├── Admin/           # WordPress admin interfaces
└── Support/         # Utilities and dependency injection
```

### Key Services
- **TaskManager**: Core task CRUD operations and template management
- **ClassTaskService**: Business logic for class change processing
- **TaskTemplateRegistry**: Manages task templates for different operations
- **NotificationProcessor**: Handles email notifications via WordPress cron
- **Container**: Dependency injection container for service resolution
- **NotificationSettings**: Manages email configuration resolution

---

## 📧 Email Notification System

### Email Data Sent for "Create Class" Notifications

#### Email Subject Format
```
[WeCoza] Class insert: {CLASS_ID} ({CLASS_CODE})
```

#### Email Body Contents
The notification email includes the following data sections:

**1. Basic Information**
- **Operation**: "INSERT"
- **Changed At**: Timestamp of when the class was created
- **Class ID**: The unique identifier of the class
- **Class Code**: The class code (if available)
- **Class Subject**: The class subject/name (if available)

**2. New Row Snapshot (Complete Class Data)**
For INSERT operations, the `new_row` JSONB field contains the complete class record with all fields from the `classes` table, including:
- All class properties (start_date, end_date, location, instructor, etc.)
- All metadata fields
- Full JSON representation of the newly created class

**3. Changes Section**
For INSERT operations, this shows what fields were set during creation.

#### Email Structure Example
```
Operation: INSERT
Changed At: 2025-01-15 14:30:25
Class ID: 12345
Class Code: MATH-101
Class Subject: Mathematics Fundamentals

New Row Snapshot:
{
  "class_id": 12345,
  "class_code": "MATH-101",
  "class_subject": "Mathematics Fundamentals",
  "start_date": "2025-02-01",
  "end_date": "2025-05-30",
  "location": "Room 201",
  "instructor_id": 789,
  "capacity": 30,
  "enrolled": 0,
  "status": "active",
  "created_at": "2025-01-15T14:30:25Z",
  "created_by": "admin",
  "...": "all other class fields"
}
```

### Recipient Configuration

#### Priority Order (Highest to Lowest)
1. **Environment Variables**
   - `WECOZA_NOTIFY_INSERT_EMAIL` (for new classes)
   - `WECOZA_NOTIFY_UPDATE_EMAIL` (for class updates)

2. **WordPress Constants**
   - `WECOZA_NOTIFY_INSERT_EMAIL` (defined in wp-config.php)
   - `WECOZA_NOTIFY_UPDATE_EMAIL` (defined in wp-config.php)

3. **WordPress Options**
   - `wecoza_notification_class_created` (stored in wp_options)
   - `wecoza_notification_class_updated` (stored in wp_options)

#### Admin Interface
- **Menu Location**: WeCoza Dashboard → Notifications
- **Page Slug**: `wecoza-events-notifications`
- **Required Capability**: `manage_options`

### Data Source
The notification data comes from the `class_change_logs` table:
- **log_id**: Unique log entry identifier
- **class_id**: Foreign key to the classes table
- **operation**: "INSERT" for new classes
- **changed_at**: Timestamp of the database operation
- **new_row**: JSONB containing complete new class data
- **old_row**: NULL for INSERT operations
- **diff**: JSONB showing what changed during creation

---

## 🔌 Integration Guide for Other Plugins

### Accessing Core Services

The plugin uses a dependency injection container for service access:

```php
use WeCozaEvents\Support\Container;
use WeCozaEvents\Services\TaskManager;
use WeCozaEvents\Services\ClassTaskService;

// Get the task manager
$taskManager = Container::taskManager();

// Get the class task service
$classTaskService = Container::classTaskService();

// Get PostgreSQL connection
$pdo = Container::pdo();
$schema = Container::schema();
```

### Working with Tasks

```php
// Get tasks for a specific log entry
$tasks = $taskManager->getTasksWithTemplate($logId, 'insert');

// Mark a task as completed
$updatedTasks = $taskManager->markTaskCompleted(
    $logId,
    'create-class',
    $userId,
    date('Y-m-d H:i:s'),
    'Additional notes'
);

// Reopen a task
$reopenedTasks = $taskManager->reopenTask($logId, 'create-class');
```

### Retrieving Class Tasks

```php
// Get class tasks with filtering
$classTasks = $classTaskService->getClassTasks(
    20,           // limit
    'desc',       // sort direction
    true,         // prioritize open tasks
    $classId      // optional class filter
);

// Each item contains:
// - row: class data from database
// - tasks: TaskCollection object
// - log_id: database log entry ID
// - operation: 'insert', 'update', or 'delete'
// - manageable: whether tasks can be updated
// - open_count: number of open tasks
```

### Custom Task Templates

Extend the default task templates using WordPress filters:

```php
add_filter('wecoza_events_task_templates', function($templates, $operation) {
    if ($operation === 'insert') {
        $templates['insert'][] = [
            'id' => 'custom-task',
            'label' => 'Custom Task Label'
        ];
    }
    
    // Add entirely new operation type
    $templates['custom_operation'] = [
        ['id' => 'step1', 'label' => 'First Step'],
        ['id' => 'step2', 'label' => 'Second Step']
    ];
    
    return $templates;
}, 10, 2);
```

### Database Integration

```php
use WeCozaEvents\Database\Connection;

// Get PostgreSQL connection
$pdo = Connection::getPdo();
$schema = Connection::getSchema();

// Execute custom queries
$stmt = $pdo->prepare("SELECT * FROM {$schema}.class_change_logs WHERE operation = :op");
$stmt->execute(['op' => 'insert']);
$results = $stmt->fetchAll();
```

### AJAX Integration

The plugin provides AJAX endpoints that can be extended:

```php
// Existing endpoint: wp_ajax_wecoza_events_task_update
// Method: POST
// Parameters:
// - action: wecoza_events_task_update
// - nonce: WordPress nonce
// - log_id: Log entry ID
// - task_id: Task identifier
// - task_action: 'complete' or 'reopen'
// - note: Optional note for completion

// Custom AJAX handler example
add_action('wp_ajax_custom_class_action', function() {
    check_ajax_referer('wecoza_events_tasks', 'nonce');
    
    $logId = intval($_POST['log_id']);
    $taskManager = Container::taskManager();
    
    // Your custom logic here
    
    wp_send_json_success(['message' => 'Action completed']);
});
```

### Notification System Integration

```php
use WeCozaEvents\Services\NotificationProcessor;

// Manually process notifications
$processor = NotificationProcessor::boot();
$processor->process();

// Custom notification logic
add_action('wecoza_events_before_notification', function($data) {
    // $data contains: log_id, operation, class_data, etc.
    error_log('Processing notification for: ' . $data['operation']);
});
```

### WordPress Hooks Available

#### Filters
```php
// Modify task templates
add_filter('wecoza_events_task_templates', $callback, 10, 2);

// Modify notification recipients
add_filter('wecoza_events_notify_insert_email', $callback);
add_filter('wecoza_events_notify_update_email', $callback);
```

#### Actions
```php
// Before notification processing
add_action('wecoza_events_before_notification', $callback);

// After notification processing
add_action('wecoza_events_after_notification', $callback);

// Cron schedule registration
add_filter('cron_schedules', $callback);
```

---

## ⚙️ Configuration Management

### PostgreSQL Connection Setup

#### Priority Order
1. Environment variables (`PG*`)
2. WordPress options (`wecoza_postgres_*`)
3. Hard-coded fallbacks

#### Environment Variables (Recommended)
```bash
export PGHOST=localhost
export PGPORT=5432
export PGDATABASE=wecoza
export PGUSER=your_user
export PGPASSWORD=your_password
export PGSCHEMA=public
```

#### WordPress Options
```bash
wp option update wecoza_postgres_host localhost
wp option update wecoza_postgres_port 5432
wp option update wecoza_postgres_database wecoza
wp option update wecoza_postgres_user your_user
wp option update wecoza_postgres_password your_password
wp option update wecoza_postgres_schema public
```

### Email Notification Settings

#### Configuration Methods

**1. Environment Variables**
```bash
export WECOZA_NOTIFY_INSERT_EMAIL="insert@example.com"
export WECOZA_NOTIFY_UPDATE_EMAIL="update@example.com"
```

**2. WordPress Options (via Admin Interface)**
- Menu: WeCoza Dashboard → Notifications
- Settings stored in `wp_options` table:
  - `wecoza_notification_class_created`
  - `wecoza_notification_class_updated`

**3. WordPress Constants (wp-config.php)**
```php
define('WECOZA_NOTIFY_INSERT_EMAIL', 'insert@example.com');
define('WECOZA_NOTIFY_UPDATE_EMAIL', 'update@example.com');
```

### Shortcode Configuration

```php
// Basic usage
[wecoza_event_tasks]

// With custom limit
[wecoza_event_tasks limit="50"]

// Filter by specific class ID
[wecoza_event_tasks class_id="123"]

// URL parameter filtering
// ?class_id=123 will automatically filter the shortcode output

// In PHP code
echo do_shortcode('[wecoza_event_tasks limit="20"]');
```

---

## 🗄️ Database Schema Documentation

### Core Tables

#### class_change_logs
```sql
CREATE TABLE public.class_change_logs (
    log_id BIGSERIAL PRIMARY KEY,
    class_id INTEGER NOT NULL,
    operation TEXT NOT NULL CHECK (operation IN ('INSERT', 'UPDATE', 'DELETE')),
    changed_at TIMESTAMP WITHOUT TIME ZONE DEFAULT NOW() NOT NULL,
    new_row JSONB NOT NULL,
    old_row JSONB,
    diff JSONB NOT NULL DEFAULT '{}'::jsonb,
    tasks JSONB DEFAULT '[]'::jsonb
);

-- Foreign Key
ALTER TABLE public.class_change_logs
ADD CONSTRAINT class_change_logs_class_id_fkey 
FOREIGN KEY (class_id) REFERENCES public.classes(class_id) 
ON UPDATE CASCADE ON DELETE SET NULL;

-- Indexes
CREATE INDEX idx_class_change_logs_class_id 
ON public.class_change_logs (class_id);

CREATE INDEX idx_class_change_logs_changed_at 
ON public.class_change_logs (changed_at DESC);

CREATE INDEX idx_class_change_logs_diff_gin 
ON public.class_change_logs USING gin (diff);
```

### Trigger System

The plugin installs database triggers that automatically log changes to the `classes` table:

- **Trigger Function**: `log_class_change()`
- **Trigger**: `classes_log_insert_update` (AFTER INSERT OR UPDATE)
- **Purpose**: Automatically creates log entries for all class changes

### Data Flow for Class Changes

1. **Class Created/Updated** → Database trigger fires
2. **Trigger Function** → Creates entry in `class_change_logs`
3. **Notification Processor** → Scans for new log entries every 5 minutes
4. **Email Sent** → Recipient configured via environment/WordPress settings
5. **Task Management** → UI displays class with associated tasks

### Schema Updates

The schema SQL is idempotent and can be safely re-run:

```bash
psql -h localhost -U user -d database -f schema/class_change_trigger.sql
```

---

## 📚 API Reference

### TaskManager API

```php
// Get tasks with auto-template application
TaskManager::getTasksWithTemplate(int $logId, ?string $operation = null): TaskCollection

// Mark task as completed
TaskManager::markTaskCompleted(
    int $logId, 
    string $taskId, 
    int $userId, 
    string $timestamp, 
    ?string $note = null
): TaskCollection

// Reopen task
TaskManager::reopenTask(int $logId, string $taskId): TaskCollection

// Get existing tasks (no template auto-application)
TaskManager::getTasksForLog(int $logId): TaskCollection

// Save tasks for log entry
TaskManager::saveTasksForLog(int $logId, TaskCollection $tasks): void
```

### ClassTaskService API

```php
// Get class tasks with filtering and sorting
ClassTaskService::getClassTasks(
    int $limit, 
    string $sortDirection, 
    bool $prioritiseOpen, 
    ?int $classIdFilter
): array
```

### Container API

```php
// Service access methods
Container::pdo(): PDO
Container::schema(): string
Container::taskManager(): TaskManager
Container::classTaskService(): ClassTaskService
Container::taskTemplateRegistry(): TaskTemplateRegistry
Container::classTaskRepository(): ClassTaskRepository
Container::classTaskPresenter(): ClassTaskPresenter
Container::templateRenderer(): TemplateRenderer
Container::wordpressRequest(): WordPressRequest
Container::jsonResponder(): JsonResponder
```

### NotificationSettings API

```php
// Get email recipient for operation
NotificationSettings::getRecipientForOperation(string $operation): ?string
```

---

## 🛠️ Development Guidelines

### Code Quality Checks

```bash
# Syntax check individual files
php -l includes/Services/TaskManager.php

# Check all PHP files
find includes -name "*.php" -exec php -l {} \;

# WordPress coding standards (if available)
phpcs --standard=WordPress includes/
```

### Debugging

```php
// Enable debug mode
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// View debug output in wp-content/debug.log
tail -f wp-content/debug.log | grep "WeCoza"
```

### Testing Database Connection

```php
use WeCozaEvents\Database\Connection;

try {
    $pdo = Connection::getPdo();
    echo "Connection successful!";
} catch (Exception $e) {
    echo "Connection failed: " . $e->getMessage();
}
```

### Security Considerations

- All database queries use prepared statements
- AJAX requests require WordPress nonces
- User input is properly sanitized
- Email addresses are validated before use
- Database credentials should use environment variables

### Performance Optimization

```sql
-- Create additional indexes if needed
CREATE INDEX CONCURRENTLY idx_class_change_logs_operation 
ON class_change_logs(operation);

-- Monitor query performance
EXPLAIN ANALYZE SELECT * FROM public.class_change_logs 
WHERE operation = 'INSERT' AND changed_at > NOW() - INTERVAL '1 day';
```

---

## 🔧 Troubleshooting

### Common Issues

#### Missing PostgreSQL Extension
```bash
# Ubuntu/Debian
sudo apt-get install php-pgsql

# CentOS/RHEL
sudo yum install php-pgsql

# Verify installation
php -m | grep pgsql
```

#### Connection Issues
```sql
-- Test PostgreSQL connection
\c your_database
SELECT version();

-- Check trigger installation
\d classes
\df log_class_change
```

#### Cron Not Running
```bash
# Check WordPress cron status
wp cron event list

# Manually run cron
wp cron event run wecoza_events_process_notifications

# Or trigger via web request
curl -I http://your-site.com/wp-cron.php?doing_wp_cron
```

#### Permission Issues
```bash
# Ensure proper file permissions
chown -R www-data:www-data wp-content/plugins/wecoza-events-plugin
chmod -R 755 wp-content/plugins/wecoza-events-plugin
```

### Error Resolution

#### Plugin SQL Errors
- Check table structure matches expected schema
- Verify trigger installation
- Ensure proper column names (log_id vs id)

#### Email Not Sending
- Verify WordPress email configuration
- Check recipient email addresses
- Test with `wp_mail()` function
- Review WordPress debug logs

#### Shortcode Not Working
- Verify plugin is activated
- Check for PHP errors in debug logs
- Ensure database connection is working
- Validate shortcode syntax

---

## 📞 Support & Maintenance

### Regular Maintenance Tasks

1. **Database Backups**: Regular backups before major updates
2. **Monitor Performance**: Check query execution times
3. **Review Logs**: Monitor WordPress debug logs for errors
4. **Update Documentation**: Keep API docs current with code changes
5. **Security Audits**: Regular security reviews of code and configurations

### Getting Help

1. Check this documentation for common solutions
2. Review debug logs in `wp-content/debug.log`
3. Verify PostgreSQL connection and trigger installation
4. Test with default themes and minimal plugins to isolate conflicts
5. Contact development team for technical assistance

### Contributing

When contributing to the plugin:
- Follow existing code style and patterns
- Add proper documentation for new features
- Include unit tests for new functionality
- Update this documentation as needed
- Test thoroughly before submitting changes

---

*This guide is maintained by the WeCoza Development Team and updated regularly to reflect current plugin functionality and best practices.*
