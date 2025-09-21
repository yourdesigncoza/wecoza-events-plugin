Goal: After every successful “class saved” (both create and update) in this plugin, push a notification event into the WECOZA Notifications plugin so it can write rows to the wecoza_events.dashboard_status table.

Context:
- The notifications plugin exposes a WordPress action hook `wecoza_event` that accepts an associative array describing the event. When you fire it with `event => 'class.created'` the notifications code (EventProcessor, CronService, PostgreSQLDatabaseService) will do the inserts/updates against wecoza_events.dashboard_status automatically.
- Both projects share the same PostgreSQL database. `public.classes` comes from schema/wecoza_db_bu_01.sql and `wecoza_events.dashboard_status` comes from schema/postgresql_events_schema.sql.
- We need to run this right after our existing class-save logic commits the data, but only if the save was successful.

Task:
1. Identify the point in our plugin where the AJAX/form handler finishes saving a class (the same flow that logs “=== CLASS SAVE AJAX START ===” in debug.log).
2. After the class record is persisted and we have a `$class_id`, gather enough metadata to feed the notifications system:
   - `class_name` or code (whatever we store for display),
   - `client_name` (or client ID if that’s all we have),
   - `site_name` / location,
   - `created_by` (current user’s display name or ID).
   
   Use fallbacks if a value is missing (e.g., empty string).
3. Fire the hook exactly once per save:

```php
if (function_exists('do_action')) {
    $actor_id   = get_current_user_id() ?: 0;
    $meta       = array(
        'class_name'  => $class_data['class_code'] ?? '',
        'client_name' => $class_data['client_name'] ?? '',
        'site_name'   => $class_data['site_address'] ?? '',
        'created_by'  => wp_get_current_user()->display_name ?? '',
        // add any other fields you want to surface
    );

    do_action('wecoza_event', array(
        'event'           => 'class.created',
        'class_id'        => (int) $class_id,
        'actor_id'        => $actor_id,
        'occurred_at'     => current_time('mysql'),
        'idempotency_key' => sprintf('class.created:%d:%s', $class_id, wp_generate_uuid4()),
        'metadata'        => $meta,
    ));
}
```

4. For existing classes that are being updated, decide whether to emit the same event or a different one (e.g., class.updated). If you keep class.created for updates, ensure the idempotency key always includes a unique UUID so the notifications plugin treats it as a new event.
5. If you prefer to manage the dashboard rows directly, you can swap the hook call with:

```php
$cron = new \WecozaNotifications\CronService();
$cron->create_dashboard_status($class_id, 'class_created', get_current_user_id() ?: 1);
```

…but the hook approach is cleaner because it keeps all dashboard logic inside the notifications plugin.

Deliverables:
- Modified class-save handler with the new hook call (or CronService call).
- Any guard clauses needed so we only fire the event when the notifications plugin is active (e.g., class_exists('\WecozaNotifications\CronService') or class_exists('\WecozaNotifications\Core')).
- Brief note in the other plugin’s README/inline comments explaining that we now emit notifications via wecoza_event.