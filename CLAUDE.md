# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a WordPress plugin that monitors WeCoza class changes from PostgreSQL and provides task management functionality. It follows an MVC architecture with strict PHP typing and uses WordPress hooks for AJAX handling and cron scheduling.

## Architecture

### MVC Structure
- **Controllers**: Handle AJAX requests and route actions (`includes/Controllers/`)
- **Models**: Data objects and repository patterns (`includes/Models/`)
- **Services**: Business logic and external integrations (`includes/Services/`)
- **Views**: UI rendering components (`includes/Views/`)
- **Shortcodes**: WordPress shortcode handlers (`includes/Shortcodes/`)

### Key Components
- `TaskManager`: Core service for managing class change tasks and templates
- `TaskController`: AJAX handler for task completion/reopening operations
- `EventTasksShortcode`: Main shortcode for rendering task UI (`[wecoza_event_tasks]`)
- `NotificationProcessor`: Automated email notifications via WordPress cron
- `ClassChangeLogRepository`: PostgreSQL data access layer

## Database Schema

The plugin requires PostgreSQL with:
- Table: `public.class_change_logs` (stores class change audit trail)
- Trigger function: `public.log_class_change()` (automatic logging)
- Trigger: `classes_log_insert_update` on `public.classes`

Run `schema/class_change_trigger.sql` to initialize or update the database schema (idempotent).

## Configuration

PostgreSQL connection resolved in priority order:
1. Environment variables: `PGHOST`, `PGPORT`, `PGDATABASE`, `PGUSER`, `PGPASSWORD`, `PGSCHEMA`
2. WordPress options: `wecoza_postgres_*`
3. Hard-coded fallbacks

Email notification addresses:
- INSERT operations: `WECOZA_NOTIFY_INSERT_EMAIL` (env/constant/option)
- UPDATE operations: `WECOZA_NOTIFY_UPDATE_EMAIL` (env/constant/option)

## Development Commands

### Linting
```bash
# Individual file syntax check
php -l includes/path/to/file.php
```

### Testing Database Connection
The plugin includes connection validation in `includes/class-wecoza-events-database.php`.

### WordPress Hooks
- Cron schedule: `wecoza_events_five_minutes` (5-minute intervals)
- AJAX actions: `wecoza_events_task_update` (authenticated task updates)
- Activation/deactivation hooks manage cron scheduling

## Code Standards

- Strict PHP 8.1+ typing (`declare(strict_types=1);`)
- Namespace: `WeCozaEvents\*`
- WordPress coding standards for hooks and sanitization
- PSR-4 autoloading structure under `includes/`
- All database queries use prepared statements with PDO

## Key Files

- `wecoza-events-plugin.php`: Main plugin bootstrapper
- `includes/class-wecoza-events-database.php`: Database connection helper
- `includes/Services/TaskManager.php`: Core task management logic
- `schema/class_change_trigger.sql`: PostgreSQL schema installer