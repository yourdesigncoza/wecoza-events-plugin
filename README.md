# WeCoza Events Plugin

A comprehensive WordPress plugin that monitors WeCoza class changes from PostgreSQL and provides task management functionality. Built with strict PHP typing, MVC architecture, and extensible service patterns for seamless integration with other plugins.

## Features

- **PostgreSQL Integration**: Real-time monitoring of class changes via database triggers
- **Task Management**: Interactive UI for tracking completion of class-related tasks
- **Email Notifications**: Automated notifications for INSERT/UPDATE operations
- **MVC Architecture**: Clean separation of concerns with controllers, models, services, and views
- **Extensible Design**: Service container pattern for easy integration by other plugins
- **Shortcode Support**: Embed task management UI anywhere with `[wecoza_event_tasks]`
- **WordPress Hooks**: Full integration with WordPress cron, AJAX, and admin system

## Requirements

- PHP 8.1+
- WordPress 6.0+
- PostgreSQL 12+ with trigger support
- `pdo_pgsql` PHP extension (CLI and web contexts)

## Architecture Overview

### MVC Structure
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

## Quick Start

### 1. Installation
```bash
# Copy to WordPress plugins directory
cp -r wecoza-events-plugin /path/to/wordpress/wp-content/plugins/

# Activate in WordPress admin or via WP-CLI
wp plugin activate wecoza-events-plugin
```

### 2. Database Setup
```sql
-- Execute the schema setup (idempotent)
\i schema/class_change_trigger.sql
```

### 3. Configure PostgreSQL Connection
```bash
# Via environment variables (recommended)
export PGHOST=localhost
export PGPORT=5432
export PGDATABASE=wecoza
export PGUSER=your_user
export PGPASSWORD=your_password
export PGSCHEMA=public

# Or via WordPress options
wp option update wecoza_postgres_host localhost
wp option update wecoza_postgres_port 5432
wp option update wecoza_postgres_database wecoza
wp option update wecoza_postgres_user your_user
wp option update wecoza_postgres_password your_password
wp option update wecoza_postgres_schema public
```

## Configuration

### PostgreSQL Connection Priority
1. Environment variables (`PG*`)
2. WordPress options (`wecoza_postgres_*`)
3. Hard-coded fallbacks

### Email Notifications
Configure notification recipients:

```bash
# Environment variables
export WECOZA_NOTIFY_INSERT_EMAIL="insert@example.com"
export WECOZA_NOTIFY_UPDATE_EMAIL="update@example.com"

# Or WordPress options
wp option update wecoza_notify_insert_email "insert@example.com"
wp option update wecoza_notify_update_email "update@example.com"

# Or wp-config.php constants
define('WECOZA_NOTIFY_INSERT_EMAIL', 'insert@example.com');
define('WECOZA_NOTIFY_UPDATE_EMAIL', 'update@example.com');
```

## How Triggers Are Invoked

The WeCoza Events Plugin notification system works through a **multi-layered automated process** involving database triggers, WordPress cron, and email processing.

### **1. Database Layer - Real-time Triggers**

**When a class is created/updated:**
```sql
-- PostgreSQL Trigger
CREATE TRIGGER classes_log_insert_update 
AFTER INSERT OR UPDATE ON public.classes 
FOR EACH ROW EXECUTE FUNCTION public.log_class_change();
```

**Trigger Function Actions:**
1. **Creates Log Entry**: Inserts a new row into `class_change_logs` table
2. **Sends PostgreSQL Notification**: Uses `pg_notify()` to broadcast real-time event
3. **Calculates Diff**: For UPDATE operations, computes JSON diff of changed fields
4. **Stores Complete Data**: Saves full row snapshot in `new_row` JSONB field

**pg_notify() Call:**
```sql
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
```

### **2. WordPress Cron Layer - Scheduled Processing**

**Cron Schedule Setup:**
```php
// Plugin activation registers custom schedule
add_filter('cron_schedules', 'wecoza_events_register_schedule');
register_activation_hook(__FILE__, 'wecoza_events_schedule_notifications');

// Creates 5-minute interval schedule
'schedules' => [
    'wecoza_events_five_minutes' => [
        'interval' => 300, // 5 minutes
        'display' => 'Every Five Minutes (WeCoza Events)'
    ]
]
```

**Cron Event:**
```php
// Scheduled action that runs every 5 minutes
add_action('wecoza_events_process_notifications', 'wecoza_events_run_notification_processor');

function wecoza_events_run_notification_processor(): void {
    try {
        \WeCozaEvents\Services\NotificationProcessor::boot()->process();
    } catch (\Throwable $exception) {
        error_log('WeCoza notification processing failed: ' . $exception->getMessage());
    }
}
```

### **3. NotificationProcessor - Email Processing**

**Processing Logic:**
```php
public function process(): void {
    // 1. Get last processed log ID
    $lastProcessed = (int) get_option('wecoza_last_notified_log_id', 0);
    
    // 2. Fetch unprocessed log entries
    $rows = $this->fetchRows($lastProcessed);
    
    // 3. Process each log entry
    foreach ($rows as $row) {
        $operation = strtoupper((string) ($row['operation'] ?? ''));
        $recipient = $this->settings->getRecipientForOperation($operation);
        
        if ($recipient !== null) {
            // 4. Build and send email
            $mailData = $this->buildMailPayload($row, $operation);
            $sent = wp_mail($recipient, $subject, $body, $headers);
        }
    }
    
    // 5. Update last processed ID
    update_option('wecoza_last_notified_log_id', $latestId, false);
}
```

### **4. Complete Flow Timeline**

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────────┐
│ Class Created   │    │ PostgreSQL       │    │ WordPress Cron      │
│ or Updated      │───▶│ Trigger Fires    │───▶│ (Every 5 minutes)   │
│ (classes table) │    │ - Logs change    │    │ - Checks for new    │
└─────────────────┘    │ - pg_notify()    │    │   log entries       │
                       └──────────────────┘    │ - Sends emails      │
                                                └─────────────────────┘
```

### **5. Invocation Triggers Summary**

| Layer | Trigger | Frequency | Purpose |
|-------|---------|-----------|---------|
| **Database** | `AFTER INSERT/UPDATE` | Immediate | Create log entry, real-time notification |
| **WordPress** | Cron Job | Every 5 minutes | Process pending log entries |
| **Plugin** | `NotificationProcessor::process()` | On cron run | Build and send emails |

### **6. Manual Invocation (For Testing)**

```php
// Direct invocation for testing
try {
    $processor = \WeCozaEvents\Services\NotificationProcessor::boot();
    $processor->process();
    echo "Notifications processed successfully";
} catch (Exception $e) {
    echo "Processing failed: " . $e->getMessage();
}
```

### **7. Real-time vs Batch Processing**

**Real-time Component:**
- Database `pg_notify()` provides immediate event broadcasting
- Could be used by external applications listening to PostgreSQL channels

**Batch Processing Component:**
- Email sending handled every 5 minutes via WordPress cron
- Prevents email overload from rapid successive changes
- Allows for retry logic and error handling

This hybrid approach ensures both immediate responsiveness (via database triggers) and reliable email delivery (via scheduled batch processing).

## Usage

### Shortcode Integration
```php
// Basic usage
[wecoza_event_tasks]

// With custom limit
[wecoza_event_tasks limit="50"]

// In PHP code
echo do_shortcode('[wecoza_event_tasks limit="20"]');
```

### Class-Specific Display
```php
// Filter by specific class ID
[wecoza_event_tasks class_id="123"]

// URL parameter filtering
// ?class_id=123 will automatically filter the shortcode output
```

## Integration Guide for Other Plugins

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

## API Reference

### TaskManager API

```php
// Get tasks with auto-template application
TaskManager::getTasksWithTemplate(int $logId, ?string $operation = null): TaskCollection

// Mark task as completed
TaskManager::markTaskCompleted(int $logId, string $taskId, int $userId, string $timestamp, ?string $note = null): TaskCollection

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
ClassTaskService::getClassTasks(int $limit, string $sortDirection, bool $prioritiseOpen, ?int $classIdFilter): array
```

### Available Filters

```php
// Modify task templates
add_filter('wecoza_events_task_templates', $callback, 10, 2);

// Modify notification recipients
add_filter('wecoza_events_notify_insert_email', $callback);
add_filter('wecoza_events_notify_update_email', $callback);
```

### Available Actions

```php
// Before notification processing
add_action('wecoza_events_before_notification', $callback);

// After notification processing
add_action('wecoza_events_after_notification', $callback);

// Cron schedule registration
add_filter('cron_schedules', $callback);
```

## Database Schema

### Core Tables

**class_change_logs**
```sql
CREATE TABLE class_change_logs (
    log_id SERIAL PRIMARY KEY,
    class_id INTEGER NOT NULL,
    operation VARCHAR(10) NOT NULL CHECK (operation IN ('INSERT', 'UPDATE', 'DELETE')),
    changed_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    changed_by TEXT,
    old_data JSONB,
    new_data JSONB,
    tasks JSONB
);
```

### Trigger System

The plugin installs database triggers that automatically log changes to the `classes` table:

- **Trigger Function**: `log_class_change()`
- **Trigger**: `classes_log_insert_update` (AFTER INSERT OR UPDATE)
- **Index**: `class_change_logs_class_id_idx` for performance

### Schema Updates

The schema SQL is idempotent and can be safely re-run:

```bash
psql -h localhost -U user -d database -f schema/class_change_trigger.sql
```

## Development

### Code Quality

```bash
# Syntax check individual files
php -l includes/Services/TaskManager.php

# Check all PHP files
find includes -name "*.php" -exec php -l {} \;
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

## Troubleshooting

### Common Issues

**Missing PostgreSQL Extension**
```bash
# Ubuntu/Debian
sudo apt-get install php-pgsql

# CentOS/RHEL
sudo yum install php-pgsql

# Verify installation
php -m | grep pgsql
```

**Connection Issues**
```sql
-- Test PostgreSQL connection
\c your_database
SELECT version();

-- Check trigger installation
\d classes
\df log_class_change
```

**Cron Not Running**
```bash
# Check WordPress cron status
wp cron event list

# Manually run cron
wp cron event run wecoza_events_process_notifications

# Or trigger via web request
curl -I http://your-site.com/wp-cron.php?doing_wp_cron
```

**Permission Issues**
```bash
# Ensure proper file permissions
chown -R www-data:www-data wp-content/plugins/wecoza-events-plugin
chmod -R 755 wp-content/plugins/wecoza-events-plugin
```

### Performance Optimization

```sql
-- Create additional indexes if needed
CREATE INDEX CONCURRENTLY idx_class_change_logs_operation 
ON class_change_logs(operation);

CREATE INDEX CONCURRENTLY idx_class_change_logs_changed_at 
ON class_change_logs(changed_at DESC);
```

## Security Considerations

- All database queries use prepared statements
- AJAX requests require WordPress nonces
- User input is properly sanitized
- Email addresses are validated before use
- Database credentials should use environment variables

## License

This plugin follows WordPress GPL v2 or later license.

## Support

For issues and support:
1. Check this README for common solutions
2. Review debug logs in WordPress debug.log
3. Verify PostgreSQL connection and trigger installation
4. Test with default themes and minimal plugins to isolate conflicts
