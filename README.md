# WeCoza Events Plugin

WordPress plugin for centralized notification system using PostgreSQL database integration.

## Features

- **Event-driven notification system** - Centralized event processing with idempotency
- **PostgreSQL database integration** - Modern database architecture with JSONB support
- **Email queue management** - Reliable email delivery with retry mechanisms
- **Security-focused implementation** - Comprehensive input validation and output escaping
- **Internationalization support** - Translation-ready with POT file
- **Classes integration** - Connects with existing PostgreSQL classes table
- **Audit trail** - Complete logging of all security and operational events

## Requirements

- WordPress 5.0+
- PHP 7.4+
- PostgreSQL 12+

## Installation

1. Clone repository to wp-content/plugins/
```bash
git clone git@github.com:yourdesigncoza/wecoza-events-plugin.git
```

2. Configure PostgreSQL connection in `app/Services/PostgreSQLDatabaseService.php`

3. Run PostgreSQL schema:
```bash
psql -U your_user -d your_db -f schema/postgresql_events_schema.sql
```

4. Activate plugin in WordPress admin

## Development

See `CLAUDE.md` for comprehensive development guidelines and architecture overview.

### Key Components

- **PostgreSQL Database Service** - PDO-based database abstraction
- **Event Processor** - Central event handling and routing
- **Security Service** - Input validation and sanitization utilities
- **Email Service** - WordPress mail integration with queueing

## Architecture

The plugin uses a dual-database architecture:
- WordPress MySQL for core WordPress functionality
- PostgreSQL for application data with advanced JSONB support

Events flow: Emission → Validation → Routing → Queueing → Processing → Delivery

## Available Shortcodes

The plugin provides 11 shortcodes for displaying various notification and task management components:

### 1. Class Status Display
```
[wecoza_class_status]
```
Displays an overview of all tasks for specific classes.

**Parameters:**
- `class_id` - Specific class ID (optional)
- `client_id` - Filter by client ID (optional)
- `user_id` - Filter by user ID ('current' or user ID, default: 'current')
- `status` - Task status filter ('all', 'open', 'completed', default: 'all')
- `view` - Display style ('grid', 'list', default: 'grid')
- `show_completed` - Show completed tasks ('true'/'false', default: 'false')
- `limit` - Maximum number of items (default: '10')
- `sort` - Sort order ('due_date', 'status', 'class', default: 'due_date')
- `refresh_interval` - Auto-refresh in seconds (default: '30')

**Example:**
```
[wecoza_class_status view="grid" status="open" limit="5"]
```

### 2. Pending Tasks
```
[wecoza_pending_tasks]
```
Shows a compact list of pending tasks for the current user.

**Parameters:**
- `user_id` - User ID ('current' or specific ID, default: 'current')
- `priority` - Priority filter ('all', 'high', 'medium', 'low', default: 'all')
- `limit` - Maximum tasks to show (default: '5')
- `show_overdue_first` - Prioritize overdue tasks ('true'/'false', default: 'true')
- `group_by` - Group tasks by ('class', 'priority', 'none', default: 'class')
- `compact` - Compact display mode ('true'/'false', default: 'false')

**Example:**
```
[wecoza_pending_tasks limit="3" priority="high" compact="true"]
```

### 3. Status Tile
```
[wecoza_status_tile]
```
Displays a single task status tile for specific class/task combinations.

**Parameters:**
- `class_id` - Class ID (required)
- `task_type` - Task type (required)
- `style` - Tile style ('default', 'compact', 'card')
- `show_actions` - Show action buttons ('true'/'false', default: 'true')

**Example:**
```
[wecoza_status_tile class_id="123" task_type="load_learners" style="card"]
```

### 4. Notification Center
```
[wecoza_notification_center]
```
Full-featured notification management interface.

**Parameters:**
- `user_id` - User ID ('current' or specific ID, default: 'current')
- `types` - Notification types ('all', 'reminder', 'confirmation', default: 'all')
- `limit` - Number of notifications (default: '20')
- `show_filters` - Show filter controls ('true'/'false', default: 'true')
- `allow_actions` - Allow mark as read/delete ('true'/'false', default: 'true')

**Example:**
```
[wecoza_notification_center types="reminder" limit="10"]
```

### 5. Notification Badges
```
[wecoza_notification_badges]
```
Small notification count indicators.

**Parameters:**
- `types` - Types to count ('reminder,confirmation', default: 'reminder,confirmation')
- `user_id` - User ID ('current' or specific ID, default: 'current')
- `style` - Badge style ('bubble', 'pill', 'square', default: 'bubble')
- `position` - Position ('top-right', 'top-left', 'bottom-right', default: 'top-right')

**Example:**
```
[wecoza_notification_badges types="reminder" style="pill"]
```

### 6. Progress Bar
```
[wecoza_progress_bar]
```
Visual progress indicator for class task completion.

**Parameters:**
- `class_id` - Class ID (optional, shows overall if empty)
- `style` - Bar style ('horizontal', 'vertical', 'circular', default: 'horizontal')
- `show_percentage` - Show percentage text ('true'/'false', default: 'true')
- `show_tasks` - Show task details ('true'/'false', default: 'true')
- `color_scheme` - Color scheme ('default', 'success', 'warning', default: 'default')

**Example:**
```
[wecoza_progress_bar class_id="123" style="horizontal" show_percentage="true"]
```

### 7. Recent Activity
```
[wecoza_recent_activity]
```
Timeline of recent notification and task activities.

**Parameters:**
- `limit` - Number of activities (default: '10')
- `user_id` - Filter by user ('current', 'all', or specific ID, default: 'current')
- `days` - Days to look back (default: '7')
- `types` - Activity types ('all', 'tasks', 'notifications', default: 'all')
- `compact` - Compact display ('true'/'false', default: 'false')

**Example:**
```
[wecoza_recent_activity limit="5" days="3" types="tasks"]
```

### 8. Deadline Tracker
```
[wecoza_deadline_tracker]
```
Tracks and displays upcoming task deadlines.

**Parameters:**
- `days_ahead` - Days to look ahead (default: '14')
- `user_id` - User filter ('current', 'all', or specific ID, default: 'current')
- `priority_only` - Show only priority tasks ('true'/'false', default: 'false')
- `group_by_date` - Group by due date ('true'/'false', default: 'true')

**Example:**
```
[wecoza_deadline_tracker days_ahead="7" priority_only="true"]
```

### 9. Supervisor Dashboard
```
[wecoza_supervisor_dashboard]
```
Comprehensive dashboard for supervisors managing multiple classes.

**Parameters:**
- `client_filter` - Filter by client ID (optional)
- `site_filter` - Filter by site ID (optional)
- `show_stats` - Show statistics ('true'/'false', default: 'true')
- `show_charts` - Show charts ('true'/'false', default: 'true')
- `layout` - Layout style ('grid', 'tabs', 'accordion', default: 'grid')

**Example:**
```
[wecoza_supervisor_dashboard show_stats="true" layout="tabs"]
```

### 10. Quick Actions
```
[wecoza_quick_actions]
```
Quick action buttons for common tasks.

**Parameters:**
- `actions` - Available actions ('all' or comma-separated list, default: 'all')
- `style` - Button style ('buttons', 'dropdown', 'menu', default: 'buttons')
- `user_context` - User context ('current' or specific ID, default: 'current')

**Example:**
```
[wecoza_quick_actions actions="complete_task,send_reminder" style="dropdown"]
```

### 11. Class Timeline
```
[wecoza_class_timeline]
```
Visual timeline showing class progression and milestones.

**Parameters:**
- `class_id` - Class ID (required)
- `show_completed` - Show completed milestones ('true'/'false', default: 'true')
- `style` - Timeline style ('vertical', 'horizontal', default: 'vertical')
- `compact` - Compact display ('true'/'false', default: 'false')

**Example:**
```
[wecoza_class_timeline class_id="123" style="vertical" show_completed="true"]
```

### Usage Notes

- All shortcodes support auto-refresh capabilities
- Shortcodes respect user permissions and capabilities
- Most shortcodes work with both specific IDs and current user context
- All shortcodes are mobile-responsive and follow Bootstrap 5 conventions

## Contributing

Follow WordPress coding standards and ensure all security best practices are maintained.