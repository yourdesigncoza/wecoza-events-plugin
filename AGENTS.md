# Repository Guidelines

## Project Structure & Module Organization
Core logic lives under `app/` with `Controllers/` handling shortcodes, `Services/` managing PostgreSQL, cron, and email pipelines, and `Models/` encapsulating reusable data helpers. Bootstrap code sits in `includes/` (activator, autoloader, migrations). Configuration arrays live in `config/`, while view assets are split between `templates/` (Blade-style PHP views) and `assets/` (CSS/JS bundles). PostgreSQL DDL resides in `schema/`, translations in `languages/`, and reference notes in `docs/`.

## Build, Test, and Development Commands
- `wp plugin activate wecoza-events-plugin` — enable the plugin after syncing code to a WordPress install.
- `psql -U your_user -d your_db -f schema/postgresql_events_schema.sql` — install or refresh the PostgreSQL schema used by queue, audit, and analytics services.
- `php -r "require 'app/Services/PostgreSQLDatabaseService.php';use WecozaNotifications\\PostgreSQLDatabaseService;var_dump(PostgreSQLDatabaseService::get_instance()->test_connection());"` — smoke-test the database connection from the plugin context.
- `wp cron event run wecoza_process_queue` — manually trigger queued notification processing during development.

## Coding Style & Naming Conventions
Follow WordPress PHP standards: 4-space indentation, braces on the same line, and `array()` syntax for compatibility. Classes are `PascalCase` and namespaced under `WecozaNotifications`; methods are `camelCase`, while hook names and shortcode tags stay `snake_case`. Escape output with `esc_html`, `esc_attr`, and `wp_kses` before rendering. Store plugin paths and feature toggles in `config/*.php` rather than hard-coding.

## Testing Guidelines
Automated tests are not yet present; rely on targeted manual checks. After schema updates, run `psql -c "\\dt wecoza_events.*"` to confirm table availability. Validate event flow by calling `do_action('wecoza_event', $payload)` in a WP-CLI shell and verifying inserts in `wecoza_events.notification_queue`. Use the WordPress admin dashboard to confirm shortcode outputs and cron-driven reminders. Document any new manual scenario in `docs/` for repeatability.

## Commit & Pull Request Guidelines
Use imperative, scope-focused commit messages (`Fix array key warnings`, `Refactor supervisors and audit features`). Each pull request must summarize behavior changes, flag schema alterations, and include before/after screenshots for UI-facing updates. Link related Jira/Trello issues in the description and note any follow-up tasks. Ensure local activation, cron runs, and database migrations are re-verified before requesting review.

## Security & Configuration Tips
Keep database credentials outside the repo (e.g., `.env` or server-configured constants), and never commit filled copies of `app/Services/PostgreSQLDatabaseService.php`. Double-check `SecurityService` validation when introducing new request surfaces, and sanitise all dynamic SQL with prepared statements. When exposing new shortcodes or AJAX endpoints, enforce capability checks mirroring existing controller patterns.
