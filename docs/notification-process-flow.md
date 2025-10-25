# WeCoza Class Notification Workflow

This document summarises how a class INSERT or UPDATE propagates through the system and results in an email notification.

## 1. Database Layer
- **Trigger:** `classes_log_insert_update` attaches to `public.classes` (`schema/wecoza_db_schema_bu_oct_22.sql`).
- **Function:** `public.log_class_change()` runs after each INSERT or UPDATE.
  - Captures `NEW` and (for updates) `OLD` rows as JSON.
  - Computes a diff for updated columns.
  - Inserts an audit row into `public.class_change_logs`.
  - Emits a `pg_notify('class_change_channel', …)` payload for realtime listeners.

## 2. Audit Storage
- Table `public.class_change_logs` stores the audit rows (`log_id`, `class_id`, `operation`, `changed_at`, `new_row`, `old_row`, `diff`, `tasks`).
- Additional indexes on `changed_at`, `class_id`, and `diff` support efficient lookups.

## 3. WordPress Cron Scheduling
- Plugin activation registers a custom 5-minute schedule (`wecoza_events_five_minutes`).
- Activation schedules the first run (`time() + 60` seconds).
- Deactivation clears the hook to avoid duplicate events.

## 4. Notification Processing
- Cron hook `wecoza_events_process_notifications` invokes `NotificationProcessor::boot()->process()`.
- `NotificationProcessor`:
  1. Reads the last processed `log_id` from the `wecoza_last_notified_log_id` option.
  2. Fetches newer entries from `class_change_logs`.
  3. Resolves recipients per operation (`INSERT`, `UPDATE`) by reading WordPress options (`wecoza_notification_class_created`, `wecoza_notification_class_updated`).
  4. Builds a plaintext email summarising metadata, diff, and new-row snapshot.
  5. Sends via `wp_mail()` and logs success/failure.
  6. Updates `wecoza_last_notified_log_id` to the highest processed row to prevent duplicates.

## 5. Manual Testing Checklist
1. Ensure INSERT and UPDATE notification email addresses are configured in WordPress options (`wecoza_notification_class_created`, `wecoza_notification_class_updated`).
2. Perform a class insert/update; confirm a new row appears in `public.class_change_logs`.
3. Trigger the cron job manually with `wp cron event run wecoza_events_process_notifications`.
4. Review `wp-content/debug.log` for processor logs and confirm receipt of email.
