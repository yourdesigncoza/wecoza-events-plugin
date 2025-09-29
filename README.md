# WeCoza Events Plugin

This plugin extends WordPress with tooling and UI to monitor WeCoza class changes stored in PostgreSQL. It provides:

- An MVC-style internal structure (controllers, models, services, views) to keep logic organised and testable.
- A `[wecoza_agent_logs]` shortcode that renders recent class change logs directly from PostgreSQL.
- Automated email notifications for new or updated classes, processed through WordPress cron.

## Requirements

- PHP 8.1+
- WordPress with access to plugin directory
- PostgreSQL 12+ with the `pg_notify` capability enabled
- `pdo_pgsql` extension for PHP CLI and web contexts

## Initial Setup

1. Copy the plugin into `wp-content/plugins/wecoza-events-plugin`.
2. Ensure the PostgreSQL connection details are available via environment variables or WordPress options (see **Configuration**).
3. Execute the SQL in `schema/class_change_trigger.sql` against your PostgreSQL database to create the log table, trigger function, and trigger.

## Schema Maintenance

`schema/class_change_trigger.sql` can be re-run at any time; it is idempotent and safely recreates the trigger pipeline.

## Configuration

### PostgreSQL Connection

The `WeCozaEvents\Database\Connection` helper resolves settings in this order:

1. Environment variables: `PGHOST`, `PGPORT`, `PGDATABASE`, `PGUSER`, `PGPASSWORD`, `PGSCHEMA`
2. WordPress options: `wecoza_postgres_host`, `wecoza_postgres_port`, etc.
3. Hard-coded fallbacks as a last resort

To override any value, set the environment variable before using the plugin or define/update the corresponding WordPress option.

## Shortcode Usage

Embed the shortcode anywhere in WordPress:

```
[wecoza_agent_logs limit="25"]
```

- `limit` — optional; number of entries to display (default: 50).
- Output is rendered from the `class_change_logs` table and diff JSON is pretty-printed inside copy-friendly text areas.
- Human-readable messages are displayed when database access fails.

## Email Notifications

The plugin sends summary emails for each new audit row:

- `INSERT` operations go to the address configured via `WECOZA_NOTIFY_INSERT_EMAIL`, the constant `WECOZA_NOTIFY_INSERT_EMAIL`, or the WordPress option `wecoza_notify_insert_email`.
- `UPDATE` operations use `WECOZA_NOTIFY_UPDATE_EMAIL`, `WECOZA_NOTIFY_UPDATE_EMAIL`, or the option `wecoza_notify_update_email`.

Provide the addresses by setting an environment variable, defining the constant in `wp-config.php`, or updating the option (e.g., with `wp option update`). A UI is also available under `WeCoza Dashboard → Notifications` inside wp-admin. Invalid emails are ignored.

Notifications are processed every five minutes using WordPress cron. Ensure regular traffic or an external cron ping keeps WordPress cron running; otherwise, schedule a server-side cron to request `wp-cron.php` periodically.

## Database Objects

Running the installer creates and maintains:

- Table `public.class_change_logs`
- Trigger function `public.log_class_change()`
- Trigger `classes_log_insert_update` on `public.classes`
- Index `class_change_logs_class_id_idx`

Update statements produce JSON diffs highlighting changed keys (`old` vs `new`), while inserts store the full row snapshot.

## Development Notes

- Source files follow a lightweight MVC structure under `includes/Controllers`, `includes/Models`, `includes/Services`, and `includes/Views`.
- The codebase targets strict types wherever possible; run `php -l` on individual files to lint.
- Sample schema SQL lives in `schema/` for reference when provisioning new environments.

## Troubleshooting

- **Missing `pdo_pgsql`:** Install/enable the extension for both CLI (`php.ini`) and web SAPI.
- **Trigger not firing:** Re-run `schema/class_change_trigger.sql` and verify triggers via `\d public.classes` within `psql`.

For further assistance, review `test.json` (example payload) when troubleshooting UI changes.
