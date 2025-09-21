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


• I'll clarify that the shortcode looks for a row in wecoza_events.dashboard_status with task_type
  'class_created' and task_status 'open' by default, referencing the event processing pipeline
  that inserts a completed status instead—which means showing the notification requires either
  show_completed="true" or adjusting the task status. I'll include file paths and line numbers like
  app/Controllers/ShortcodeController.php:334 and config/events.php:12 to ground the explanation
  clearly.

> Class Created Trigger

  - The shortcode renders whatever get_dashboard_tasks() pulls from the
  wecoza_events.dashboard_status table; by default it filters to rows where task_status =
  'open' unless you pass show_completed="true" in the shortcode attributes (app/Controllers/
  ShortcodeController.php:334, app/Controllers/ShortcodeController.php:351).
  - A “new class created” notification is only produced when an incoming event with name
  class.created is processed; the event configuration maps that to task_type = 'class_created' and
  task_status = 'completed' for the dashboard update (config/events.php:12, config/events.php:18).
  - When that event is handled, EventProcessor::update_dashboard_status() either inserts or updates
  the matching row in dashboard_status with the class ID, task type, status, actor, timestamps, and
  any metadata (app/Services/EventProcessor.php:205, app/Services/EventProcessor.php:225).
  - Because the status is written as completed, the tile stays hidden in the default shortcode
  output; it only becomes visible if you opt in to completed entries (e.g. [wecoza_class_status
  show_completed="true"]) or if some other process changes task_status to open.