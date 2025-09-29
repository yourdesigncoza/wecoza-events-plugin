# Repository Guidelines

## Project Structure & Module Organization
The plugin entry point `wecoza-events-plugin.php` boots the MVC-style code held in `includes/`. Map updates across these directories before changing logic:
- `includes/Controllers`, `includes/Models`, `includes/Services`, `includes/Views` organise request flow, data access, background jobs, and render templates.
- `includes/Admin` manages the wp-admin settings page and related helpers.
- SQL assets live in `schema/`, reference docs and demo payloads sit in `docs/`, while UI overrides belong in `bootstrap-5-custom/`.
Keep PostgreSQL connection helpers isolated in `includes/class-wecoza-events-database.php` to avoid leaking credentials elsewhere.

## Build, Test, and Development Commands
- `wp plugin activate wecoza-events-plugin` — enable the plugin in your local WordPress stack.
- `php -l path/to/file.php` — run PHP linting on every file you modify; strict types will catch most mistakes.
- `psql -f schema/class_change_trigger.sql "$PGDATABASE"` — reapply database triggers after schema changes.
- `wp cron event run wecoza_events_process_notifications` — manually fire the notification processor when testing email logic.

## Coding Style & Naming Conventions
Target PHP 8.1+, strict types, and four-space indentation. Keep one class per file and align filenames with class names (`SettingsPage.php`, `NotificationProcessor.php`). Legacy procedural files use the `class-wecoza-*.php` pattern—follow it when touching those areas. Import WordPress globals with explicit `use function` statements for readability and easier static analysis. JSON output should remain pretty-printed, matching `NotificationProcessor::encodeJson()`.

## Testing Guidelines
No automated suite ships with the plugin; document manual checks in each pull request. Validate shortcode rendering by loading a page that uses `[wecoza_event_tasks]` and confirm task management functionality works correctly. For notifications, insert rows into `class_change_logs`, trigger the cron hook, and review outbound emails or `wp-content/debug.log`. Capture screenshots of admin changes (`includes/Admin`, `includes/Views`) to prove UI behaviour.

## Commit & Pull Request Guidelines
Mirror the existing history: concise, imperative subjects (`Update class status table view…`, `Add redesign documentation…`). Detail why the change is needed, list manual test steps, and link Jira/GitHub issues where applicable. Pull requests should mention affected directories, provide before/after screenshots for UI or Bootstrap edits, and tag reviewers responsible for database, notifications, or admin UX.

## Configuration & Environment
Prefer environment variables (`PGHOST`, `PGDATABASE`, `PGUSER`, etc.) consumed by `WeCozaEvents\Database\Connection`; fall back to WordPress options only when necessary (`wp option update wecoza_postgres_host ...`). Set `wecoza_notify_insert_email` and `wecoza_notify_update_email` before running cron to avoid dropped notifications. Keep secrets out of committed files and use local `.env` or wp-config definitions for per-agent overrides.
