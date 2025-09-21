Class Status Shortcode

render_class_status registers the shortcode, merges attributes (including status) and renders a container that embeds the JSON-encoded params for AJAX refreshes (app/Controllers/ShortcodeController.php:101).
The server-side HTML inside the container is produced by get_class_status_content, which loops over the tasks returned from get_dashboard_tasks and builds the tiles (app/Controllers/ShortcodeController.php:137).

Status Parameter Behaviour

get_dashboard_tasks is the only place that interprets the status attribute; if it’s anything other than all, the query filters dashboard_status.task_status to that value (app/Controllers/ShortcodeController.php:332).
The very next condition unconditionally forces task_status = 'open' whenever show_completed="false" (the shortcode default), so any other status value collapses to an empty result unless you also set show_completed="true" (app/Controllers/ShortcodeController.php:338).
Because of that logic, practical combinations are:
status omitted or open → only open tasks (default behaviour).
status informed/completed/etc. → you must set show_completed="true" to see those tasks.
Documentation promises status="open|informed|overdue|all" (README.md:62 and docs/notifications_system_prd.md:688), but there’s no special handling for overdue; overdue highlighting is calculated from due dates, not from the status attribute. Any unsupported value just yields no rows.

Implications & Next Steps

If you expect to filter to completed/informed tasks while still hiding them by default, you’ll need to pass show_completed="true" or adjust get_dashboard_tasks to reconcile the two filters.
Consider aligning docs and data model: actual task statuses in this controller are open and informed, whereas the schema/events also mention pending/completed.
If you need an overdue filter, add explicit logic (e.g. due_date < NOW() alongside task_status='open') in get_dashboard_tasks.
Natural follow-ups: 1) decide the canonical status values and update either docs or code, 2) tweak the SQL so status and show_completed can co-exist without conflicts.