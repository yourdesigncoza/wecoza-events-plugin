# Class Workflow & Notification Redesign — To Do

## 1. Data Layer (PostgreSQL)
- [ ] Finalize target Postgres schema for `class_tasks`, `class_task_logs`, and `notifications_outbox`, including constraints, indexes, and JSON payload columns.
- [ ] Draft migration SQL and add it under `schema/`, ensuring it plays well with existing `classes_schema.sql` conventions.
- [ ] Plan data retention and archival rules for the new tables (e.g., log pruning, outbox cleanup).

## 2. Workflow Template Definitions
- [ ] Create `config/workflows.php` with task template bundles mapped to core lifecycle events (`class_created`, `learners_loaded`, `agent_order_submitted`, etc.).
- [ ] Validate templates against representative class scenarios (new class, restart, manual events) to confirm due-date offsets and ownership roles make sense.
- [ ] Document how stakeholders can extend templates (new event types, manual triggers) without code changes.

## 3. Services & Infrastructure
- [ ] Implement a `WorkflowService` that listens to class events, instantiates task records, and orchestrates status transitions.
- [ ] Build repository/DAO classes for interacting with the new Postgres tables, mirroring existing `DatabaseService` patterns.
- [ ] Add a notification dispatcher service that queues reminders/confirmations into `notifications_outbox` and records audit entries.
- [ ] Schedule a WP-Cron job (or reuse existing cron) to process the outbox and update task/reminder state.

## 4. Producer Integrations (Classes Plugin)
- [ ] Emit standardized workflow events from class create/update/delete flows (`ClassController`, form handlers, manual triggers).
- [ ] Ensure event payloads include required metadata (class IDs, supervisors, agents, schedule data) for template rendering.
- [ ] Replace any legacy event/queue calls with the new workflow triggers.

## 5. User Experience
- [ ] Redesign the dashboard task tiles to consume the new task status data (`Open Task`, `Inform`, `Informed`, `Overdue`).
- [ ] Update the single-class view to list task progress, manual event shortcuts, and audit history.
- [ ] Provide admin tools to reassign task owners, adjust due dates, or mark exceptions (cancelled, skipped).

## 6. Transition & QA
- [ ] Plan migration scripts to import outstanding tasks from the legacy system (if data is available).
- [ ] Define manual regression scenarios per repository testing guidelines and add them to `docs/`.
- [ ] Coordinate stakeholder UAT focusing on the PDF workflow (pages 7–8) to confirm usability.
- [ ] Outline a rollback strategy in case the new workflow needs to be disabled post-launch.
