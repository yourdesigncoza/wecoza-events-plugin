# WECOZA Notifications Plugin PRD  
_Status: Draft_  
_Owner: TBD_ – _Engineering Lead: TBD_ – _Last updated: 2025‑xx‑xx_

---

## 1. Purpose & Background
WECOZA currently creates classes within `wecoza-classes-plugin`. After a class is created, several operational steps (learners, agent orders, schedule, materials, paperwork, eventual approval) must be tracked. Today, reminders/confirmations are inconsistent and embedded inside feature plugins. We need a **standalone WordPress plugin** that centralizes email notifications driven by the Create Class workflow so stakeholders remain aligned without touching the source `wecoza-classes-plugin`.

**Problem Statement**  
Supervisors, agents, and operations staff lack timely email updates on class tasks. Information is scattered across dashboards and ad hoc messages, causing delays and confusion.

**Vision**  
Deliver a decoupled notifications service that:
- Listens for standardized events emitted by feature plugins.
- Decides recipients/policies via configuration.
- Sends reliable, auditable email notifications (phase 1) with extensible channel support for the future.

---

## 2. Goals & Non-Goals
### Goals (MVP scope)
- Provide automated **email** reminders and confirmations for the Create Class workflow.
- Model the workflow steps (EVT-01…EVT-06 + EVT-01a) using an event-driven contract from `wecoza-classes-plugin`.
- Expose an admin UI for routing rules and templates.
- Maintain delivery logs with retry handling and idempotency.
- Deliver minimal in-app hooks (WP admin notices) for quick visibility.

### Non-Goals (Future Phases)
- SMS, WhatsApp, push notifications (_target Phase 3_).
- Deep template localization or per-client branding overrides (_future_).
- End-user preference center (opt-in/out) beyond basic unsubscribe handling for emails.

---

## 3. Target Users & Personas
- **Operations Coordinator (Primary actor)**: Creates classes, owns task completion.
- **Training Supervisor (Stakeholder)**: Needs confirmations and approvals.
- **Agents/Learners (Recipients)**: Receive confirmation emails when relevant steps complete.
- **Support Analyst (Admin)**: Monitors notification health and delivery logs.
- **Developers (Feature plugin owners)**: Emit events; rely on notification plugin for outbound messaging.

---

## 4. User Stories (Email Channel)
1. **Operations Coordinator** receives reminder emails when a class task is overdue (Load Learners, Agent Order, etc.).
2. **Supervisor** receives confirmation emails when tasks complete and sees an approval request when prerequisites are done.
3. **Supervisor** receives an email when a new class is created.
4. **Learners** receive an enrollment email once the class is approved.
5. **Agents** receive assignment confirmation when approved.
6. **Support Analyst** reviews delivery logs and retries failed sends.

---

## 5. Scope & Requirements

### Functional Requirements
- Accept events from `wecoza-classes-plugin` via `do_action('wec_event', $payload)`.
- Deduplicate using `idempotency_key`.
- Determine recipient lists by role (supervisor, responsible user, agents, learners, internal dashboards).
- Render email templates with placeholders for class metadata.
- Queue sends via Action Scheduler for async dispatch and retrying.
- Expose admin pages:
  - **Notification Rules**: map event → channel → recipients.
  - **Templates**: manage subject/body with merge tags.
  - **Delivery Log**: filter by class/event/status; manual retry.
- Store all outbound notifications in custom table/CPT with metadata and rendered content.
- Provide hooks/filters for future channel extension.

### Workflow Events (Phase 1 email coverage)
| Event Code | Trigger Source | Reminder Email To | Confirmation Email To | Notes |
|------------|----------------|-------------------|-----------------------|-------|
| `class.created` | Class creation form submit | n/a | Supervisor | include class metadata |
| `class.learners.pending` | Scheduler detects no learners | Responsible user | n/a | generated reminder event |
| `class.learners.loaded` | Learners uploaded | n/a | Supervisor (optional), Dashboard only | confirm tasks |
| `class.agent_order.pending` | Task open & due | Responsible user | n/a | remind to submit order |
| `class.agent_order.submitted` | Agent order completed | n/a | Supervisor | |
| `class.schedule.pending` | No schedule set | Responsible user | n/a | |
| `class.schedule.set` | Schedule saved | n/a | Supervisor | include dates |
| `class.material.delivery.pending` | Delivery not marked | Responsible user | n/a | |
| `class.material.delivery.confirmed` | Materials delivered | n/a | Supervisor | |
| `class.agent.paperwork.pending` | Paperwork outstanding | Responsible user | n/a | |
| `class.agent.paperwork.submitted` | Paperwork complete | n/a | Supervisor | |
| `class.approval.requested` | All prerequisites met | Supervisor | n/a | reminder to approve |
| `class.approved` | Supervisor approves | n/a | Supervisor (confirmation), Learners (enrollment), Agents (assignment) | attaches schedule |

_Note:_ `pending` reminders may be synthetic events emitted by scheduler instead of feature plugin.

### Templates (Email)
- `class_created_supervisor`
- `task_reminder_generic`
- `agent_order_submitted`
- `training_schedule_confirmed`
- `material_delivery_confirmed`
- `agent_paperwork_submitted`
- `class_approval_request`
- `class_approved_supervisor`
- `class_enrollment_confirmed` (learners)
- `agent_assignment_confirmed`

Each template supports merge tags:
- `{class_name}`, `{client_name}`, `{site_name}`, `{start_date}`, `{end_date}`, `{schedule_link}`, `{responsible_user_name}`, `{due_date}`, etc.

---

## 6. Technical Design

### Architecture
- **Standalone plugin** `wecoza-notifications-plugin`.
- Hooks into `init` to register custom tables/admin pages.
- Subscribes to `wec_event` action to enqueue notifications.
- Uses Action Scheduler for async job queueing (dependency required).
- Renders email via WP mailer (supports HTML + text fallback).
- Maintains data storage for notifications and templates.

### Data Model
- `wec_notifications_outbox` table:
  - `id`, `event_name`, `idempotency_key`, `class_id`, `payload_json`, `channel`, `recipient_type`, `recipient_id/email`, `template`, `status` (`queued`, `sent`, `failed`), `attempts`, `last_error`, `created_at`, `sent_at`.
- Template storage:
  - Optionally WP options + files or CPT `wec_notification_template`.
- Policy configuration:
  - Option `wec_notification_policy` (structured array) or dedicated settings table.

### Event Contract (Producer: `wecoza-classes-plugin`)
Sample payload:
```php
do_action('wec_event', [
  'event'           => 'class.schedule.set',
  'class_id'        => 123,
  'actor_id'        => get_current_user_id(),
  'occurred_at'     => current_time('mysql'),
  'metadata'        => [
    'class_name'   => 'Sales Bootcamp 101',
    'client_name'  => 'Acme',
    'site_name'    => 'Johannesburg',
    'start_date'   => '2025-03-01',
    'end_date'     => '2025-03-04',
    'schedule_url' => 'https://...',
    'responsible_user_id' => 45,
    'supervisor_id'       => 12,
    'learner_ids'         => [33, 34],
    'agent_ids'           => [50, 51],
    'due_date'            => '2025-02-20'
  ],
  'idempotency_key' => 'class.schedule.set:123'
]);
```
The notifications plugin validates payload schema; missing fields raise logged errors.

### Scheduler & Reminders
- Nightly cron identifies tasks near/over due by checking `metadata['due_date']` and class status from classes plugin or stored state.
- Emits `...pending` events to send reminder emails (once per 24h per task by default).
- Configurable thresholds per task.

### Extensibility
- Filters:
  - `wec_notifications_policy` to alter routing.
  - `wec_notifications_channels` to register new channels.
- Actions:
  - `wec_notification_sent` after successful send.
  - `wec_notification_failed` on final failure.

---

## 7. UX / Admin Screens
1. **Notifications → Rules**
   - Table (event, template, recipients, status).
   - Toggle enable/disable per event.
2. **Notifications → Templates**
   - List of templates with preview/edit.
   - Editor with merge tag helper sidebar.
3. **Notifications → Delivery Log**
   - Filter by status, event, class.
   - View payload & rendered content (for audit).
   - Retry button.

Design should follow existing WP admin UI patterns; no custom frameworks required.

---

## 8. Security, Compliance & Audit
- Resolve recipients at send time using user IDs to avoid stale emails.
- Log rendered email content hash + metadata for audit.
- Include unsubscribe link for learner/agent emails (if required by policy).
- Ensure capability checks: only admins/managers can view logs/templates.
- Rate limiting / throttle to prevent spamming (per recipient).
- Store minimal PII; rely on user IDs rather than raw emails when possible.

---

## 9. Dependencies & Assumptions
- `wecoza-classes-plugin` emits the agreed events with required metadata.
- Action Scheduler available (bundled or installed).
- WP Cron enabled.
- Email transport configured (SMTP/3rd-party). Out of scope for this PRD.
- Stakeholders agree to standardized templates.

Open assumptions to validate:
1. Single supervisor per class? (affects routing).
2. Responsible user always identifiable and email-able?
3. Are agent/learner emails mandatory for approval stage?

---

## 10. Success Metrics
- ≥95% of Create Class emails delivered successfully (Action Scheduler logs).
- Reduction in late tasks (baseline TBD) post launch.
- Support queue reduction for “Has this been done?” queries (qualitative).
- Admins can audit notification delivery without developer support.

---

## 11. Rollout Plan
1. **Phase 0** – Align event contract with `wecoza-classes-plugin`.
2. **Phase 1 (MVP)** – Implement plugin with email channel, templates for core events, dashboard admin.
3. QA/Test in staging; verify events triggered by sample class creation flow.
4. Monitor logs; instrument metrics (fail rate).
5. Phase 2 – Add reminder scheduler for overdue tasks.
6. Phase 3 – Introduce additional channels/templates per customer branding.

---

## 12. Risks & Mitigations
- **Incomplete Event Payloads** → Provide validation + debug logging; contract testing between plugins.
- **Email deliverability issues** → Use Action Scheduler retries, surface failures in log, ensure SMTP configured.
- **Template drift** → Centralized editor with versioning (store last modifier/timestamp).
- **User preference conflicts** → Basic opt-out handling per recipient type as fallback.

---

## 13. Open Questions
1. Should reminders respect business hours / time zone logic?
2. Do supervisors need daily digests or real-time only?
3. Are agent/learner email templates brand-specific per client?
4. Do we need API endpoints for external systems to query notification status?
5. Should notifications mark related dashboard tiles automatically (two-way sync) or rely on classes plugin?

---

_Appendix: Event-to-Template JSON Example_
```json
{
  "event": "class.approved",
  "policy": [
    {
      "type": "confirmation",
      "recipient": "supervisor",
      "channel": "email",
      "template": "class_approved_supervisor"
    },
    {
      "type": "confirmation",
      "recipient": "learner_ids",
      "channel": "email",
      "template": "class_enrollment_confirmed"
    },
    {
      "type": "confirmation",
      "recipient": "agent_ids",
      "channel": "email",
      "template": "agent_assignment_confirmed"
    }
  ]
}
```
