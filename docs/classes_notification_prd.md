# WECOZA Post-Class Creation Tasks & Notifications

## Introduction
When a **new class** is created, it triggers a series of **events** (tasks) that must be completed in order for the class to run successfully. Not every class will follow the exact same sequence, but most share a common workflow:

* **Load learners**
* **Submit agent order**
* **Set the training schedule**
* **Deliver materials**
* **Complete agent paperwork**

The notification system manages this workflow by guiding users and informing stakeholders.

### Two Types of Notifications

1. **Reminders (Action Required)**

   * Sent to the responsible user.
   * Purpose: *Tell the user what still needs to be done.*
   * Example: “You need to load learners for this class,” or “Submit the agent order.”

2. **Confirmations (Action Completed)**

   * Sent to supervisors, learners, or agents once a task is complete.
   * Purpose: *Inform others that the step has been finished.*
   * Example: “Learners have been loaded,” or “Agent order has been submitted.”

### Why Both Are Needed

* **Reminders** make sure tasks are not forgotten.
* **Confirmations** prevent confusion and unnecessary questions like “Has this been done yet?” by providing clear status updates to everyone involved.

### Dashboard Integration

On the system dashboard, each event/task is represented as a tile that shows:

* **Pending (Reminder active)** – e.g., “Learners still need to be loaded.”
* **Complete (Confirmation sent)** – e.g., “Agent order submitted on \[date].”

This creates an auditable trail where everyone can see both what still needs attention and what has already been resolved.

# Overview
This document describes the **fixed sequence of tasks** and the **two-phase notification model (Reminders & Confirmations)** that apply after a new class has been created in the WECOZA system.  
It also maps each EVT step to its **Dashboard Task Status** (Open Task vs Informed) and **Notification Status** (Reminder vs Confirmation).

---

## EVT-01 — Create New Class
- **Task:** Create the class container.
- **Dashboard Status:** ✅ Informed (task marked as complete at submission).
- **Notification Status:**
  - Confirmation → Supervisor only ("New class created: {class_name}/{client}").
  - No reminder.

---

## EVT-02 — Load Learners
- **Task:** Load learner roster into the class.
- **Dashboard Status:** ✅ Inform (dashboard shows learner count when task complete).
- **Notification Status:**
  - Reminder → User: "Reminder: Learners must be loaded before the class can move forward."
  - Confirmation → Internal only (dashboard marks task complete).

---

## EVT-03 — Agent Order
- **Task:** Submit and confirm the Agent Order.
- **Dashboard Status:** ✅ Inform (once order is submitted).
- **Notification Status:**
  - Reminder → User: "Submit Agent Order for Class {class_name}."
  - Confirmation → Supervisor: "Agent Order submitted for Class {class_name}."

---

## EVT-04 — Training Schedule
- **Task:** Set and confirm training schedule.
- **Dashboard Status:** ⬜ Open Task (pending until completed).
- **Notification Status:**
  - Reminder → User: "Set training schedule for Class {class_name}."
  - Confirmation → Supervisor: "Training schedule confirmed for Class {class_name}."

---

## EVT-05 — Material Delivery
- **Task:** Arrange and confirm delivery of training materials.
- **Dashboard Status:** ⬜ Open Task (pending until completed).
- **Notification Status:**
  - Reminder → User: "Arrange material delivery for Class {class_name}."
  - Confirmation → Supervisor: "Material delivery completed for Class {class_name}."

---

## EVT-06 — Agent Paperwork
- **Task:** Prepare and submit required agent paperwork.
- **Dashboard Status:** ⬜ Open Task (pending until completed).
- **Notification Status:**
  - Reminder → User: "Complete agent paperwork for Class {class_name}."
  - Confirmation → Supervisor: "Agent paperwork submitted for Class {class_name}."

---

## EVT-01a — Supervisor Approval (New Explicit Step)
- **Task:** Supervisor approves the class once prerequisites are complete.
- **Dashboard Status:** ⬜ Approval Pending (becomes ✅ Informed when approved).
- **Notification Status:**
  - Reminder → Supervisor dashboard shows "Approval Pending".
  - Confirmations on approval:
    - Supervisor (self-confirm): "Class approved."
    - Learners: Enrollment notification with class metadata (start/end dates, site, schedule, docs).
    - Agents: Assignment notification with metadata (class, site, schedule, roster).

---

## Dashboard Model
- **Open Task (⬜)** = Reminder active, task not yet complete.  
- **Inform/ Informed (✅)** = Confirmation sent, task marked complete.  

This dual model ensures:
1. Users are reminded of pending responsibilities.  
2. Supervisors, learners, and agents receive clear confirmation when steps are complete.  

---

## Example Flow (with Dashboard Mapping)
1. EVT-01 Create Class → ✅ Informed (Supervisor confirmation).  
2. EVT-02 Load Learners → ✅ Inform (user reminder + dashboard confirmation).  
3. EVT-03 Agent Order → ✅ Inform (reminder + Supervisor confirmation).  
4. EVT-04 Training Schedule → ⬜ Open Task (reminder pending).  
5. EVT-05 Material Delivery → ⬜ Open Task (reminder pending).  
6. EVT-06 Agent Paperwork → ⬜ Open Task (reminder pending).  
7. EVT-01a Supervisor Approval → ⬜ Approval Pending → ✅ Informed (Supervisor, Learners, Agents notified).  

---



**build a single “Notifications Core” plugin** and let each feature plugin (Create Class, New Learners, Exams, etc.) just **emit events**. Don’t bury notification logic inside each feature plugin.

Here’s why—and how to do it cleanly in WordPress.

# Why a separate Notifications Core plugin?

**Pros**

* **Decoupling:** Feature plugins stay focused on their domain; notifications evolve without touching them.
* **Consistency:** One place for reminder vs confirmation rules, throttling, templates, and audit logs.
* **Reusability:** New modules (e.g., QA Visits, Collections) get notifications “for free” by emitting the same events.
* **Observability:** Unified delivery logs, retries, and metrics (what was sent, to whom, when, success/failure).
* **Policy control:** Switch channels (email/SMS/WhatsApp/in-app) or change copy without redeploying 5 plugins.
* **Safety:** Centralize idempotency and de-duplication so users don’t get spammed by accidental double-fires.

**Cons**

* Adds one more plugin to your stack; you’ll define a minimal “event contract” that all modules must follow.
* Slightly more up-front design (interfaces, event schema).

Given your app has multiple flows (Create Class, Load Learners, Agent Order, etc.) and a dual notification model (Reminders vs Confirmations), the **centralized approach wins** long-term.

# Recommended architecture

## 1) Event bus (WP-style)

Feature plugins **emit events**; the Notifications Core **subscribes**.

```php
// In Create Class plugin (producer)
do_action('wec_event', [
  'event'        => 'class.created',      // EVT-01
  'class_id'     => $class_id,
  'actor_id'     => get_current_user_id(),
  'occurred_at'  => current_time('mysql'),
  'metadata'     => [/* client/site/schedule… */],
  'idempotency_key' => "class.created:$class_id"
]);

// In Notifications Core plugin (consumer)
add_action('wec_event', 'wec_notifications_dispatch', 10, 1);
```

**Events you’ll fire (examples)**

* `class.created` (EVT-01)
* `class.learners.loaded` (EVT-02 confirm)
* `class.agent_order.submitted` (EVT-03 confirm)
* `class.schedule.set` (EVT-04 confirm)
* `class.material.delivery.confirmed` (EVT-05 confirm)
* `class.agent.paperwork.submitted` (EVT-06 confirm)
* `class.approved` (EVT-01a → learners/agents/supervisor confirmations)
* `task.reminder.due` (system-generated reminders via scheduler)

## 2) Routing & policy (Reminders vs Confirmations)

Inside Notifications Core, maintain a **policy map** that decides **who** gets **what** via **which channel** per event.

```php
$policy = [
  'class.created' => [
    ['type'=>'confirmation', 'to'=>'supervisor', 'channels'=>['inapp','email'], 'template'=>'class_created']
  ],
  'class.learners.loaded' => [
    ['type'=>'confirmation', 'to'=>'internal_dashboard', 'channels'=>['inapp'], 'template'=>'learners_loaded']
  ],
  'task.reminder.due' => [
    ['type'=>'reminder', 'to'=>'responsible_user', 'channels'=>['inapp','email'], 'template'=>'task_reminder']
  ],
  'class.approved' => [
    ['type'=>'confirmation', 'to'=>'supervisor', 'channels'=>['inapp','email'], 'template'=>'class_approved_supervisor'],
    ['type'=>'confirmation', 'to'=>'all_learners', 'channels'=>['email'], 'template'=>'enrollment_confirmed'],
    ['type'=>'confirmation', 'to'=>'assigned_agents', 'channels'=>['email'], 'template'=>'agent_assigned']
  ],
];
```

## 3) Templates & localization

* Store templates in the Notifications Core (e.g., `/templates/email/*.php` or Twig via Timber).
* Template variables come from `metadata` on the event (class/site/schedule links).
* Support brand overrides (child theme or options) without changing code.

## 4) Delivery & retries

* Use **Action Scheduler** (battle-tested WP job queue) for async sends + retries.
* Persist a **notifications\_outbox** table (or CPT) with:

  * `id`, `event_name`, `idempotency_key`, `payload`, `recipient`, `channel`, `status`, `attempts`, `last_error`, `sent_at`.
* Implement **idempotency**: drop duplicates with same `idempotency_key`.

## 5) Scheduler for reminders

* Notifications Core runs a cron (Action Scheduler) that:

  * Scans tasks with approaching/overdue **Due Date**,
  * Emits `task.reminder.due` events for the responsible user (e.g., Training Schedule still open),
  * Respects throttle windows (e.g., one reminder per task per day).

## 6) Admin UX (centralized)

* **Notification Rules** page: who/what/when per event.
* **Templates** editor: subject/body previews, variable hints.
* **Delivery Log**: filter by class, recipient, channel, status; re-queue failed deliveries.
* **Mute / Snooze** per task or per class (handy for noisy projects).

## 7) Minimal contract for producers (your feature plugins)

* They **do not send notifications** directly.
* They only:

  1. **Emit** a `wec_event` with a normalized payload,
  2. **Update** task state (Open → Done) and Due Dates (for reminder engine),
  3. Provide links/metadata for templates (schedule link, docs, contact names).

## 8) Data you’ll need on events (payload)

* `class_id`, `client_id`, `site_id`
* `subject`, `start_date`, `end_date`, `schedule_link`
* `supervisor_id`, `agent_ids`, `learner_ids` (IDs only; Notifications Core resolves emails)
* `responsible_user_id` (for reminders)
* `due_date` (for reminder scheduling on task events)
* `idempotency_key`

## 9) Security & compliance

* Resolve recipients by role at **send time** (RBAC) to avoid stale emails.
* Record exact rendered content (hash or blob) for audit.
* Respect opt-outs per channel (esp. SMS/WhatsApp).

## 10) Migration path (pragmatic)

* **Phase 1 (fast):** Stand up Notifications Core; wire only EVT-01 and EVT-01a. Keep templates simple (email + in-app).
* **Phase 2:** Add EVT-02 … EVT-06 confirmations and the **reminder scheduler**.
* **Phase 3:** Add SMS/WhatsApp channels and per-client template overrides.

---

## When would I embed notifications inside a feature plugin?

Only for **one-off, internal to that plugin** actions that will never be reused elsewhere. Your flows (classes, learners, exams, QA, collections) are cross-cutting and long-lived—so embedding would create duplication and drift.

---

### Bottom line

Create a **Notifications Core** plugin (or `mu-plugin`) that acts as your **event bus + policy engine + template/delivery service**. Feature plugins just **emit events** and **update task state**. This gives you clean boundaries, easier maintenance, consistent UX, and a single place to tune reminders vs confirmations as the business rules evolve.
