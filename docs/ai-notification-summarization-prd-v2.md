# Product Requirements Document: AI-Powered Notification Summaries (v2)

## 1. Executive Summary
Introduce AI-generated summaries into WeCoza's class change notifications. Replace raw JSON payloads with concise, human-readable highlights while preserving the existing audit log. Summaries are produced by OpenAI models, obfuscating sensitive learner data before transmission, and surfaced via updated notification emails that retain a clear fallback path.

## 2. Current System Overview

### 2.1 Source of Truth
- **Table**: `public.class_change_logs`
- **Trigger**: `public.log_class_change()` captures INSERT/UPDATE events
- **Processor**: `includes/Services/NotificationProcessor.php` (cron hook every 5 minutes)
- **Email**: Plain text dumps of `diff`, `new_row`, `old_row`
- **Deduplication**: WordPress option `wecoza_last_notified_log_id`

### 2.2 New Column Baseline
Ship the schema with an `ai_summary` column in the initial release:
```sql
ALTER TABLE public.class_change_logs
ADD COLUMN ai_summary jsonb DEFAULT NULL;
```
Update `schema/` SQL snapshots and any ERD documentation so fresh environments include the column automatically.

### 2.3 Prompt Inputs
- **INSERT prompts**: `docs/insert-prompt.md`
- **UPDATE prompts**: `docs/update-prompt.md`
- **Reference payloads**: `docs/notification-*-example.json`

## 3. AI Summary Payload

### 3.1 JSON Contract
```json
{
  "summary": "AI-generated highlight of the class change.",
  "status": "success | failed | pending",
  "error_code": "openai_timeout | quota_exceeded | validation_failed | null",
  "error_message": "Sanitized error text, null on success.",
  "attempts": 2,
  "viewed": false,
  "viewed_at": null,
  "generated_at": "2025-10-27T10:30:00Z",
  "model": "gpt-4o-mini",
  "tokens_used": 150,
  "processing_time_ms": 2300
}
```

### 3.2 Status Rules
- `pending`: Row queued but not yet processed.
- `success`: `summary` populated; no retry required.
- `failed`: Attempts exhausted—retain `error_code`/`error_message` for triage.
- `viewed`, `viewed_at`: Toggle when an admin opens the enhanced notification view (future requirement; reserved now).

## 4. Data Preparation & Obfuscation
- Obfuscate learner names, IDs, and contact info before calling OpenAI; summaries use deterministic pseudonyms (`Learner A`, `Learner B`, etc.).
- Implement `includes/Services/AISummaryService/Traits/DataObfuscator.php` with utilities for masking (`John Doe` → `Learner A`, numbers → `XXX`).
- Maintain mappings only while building the email payload; the OpenAI request and stored summary never receive raw identifiers.
- Persist the sanitized summary (with pseudonyms) in `ai_summary.summary` to ensure no PII is retained post-processing.

## 5. Configuration & Secrets
- **Environment first**: Check `WECOZA_OPENAI_API_KEY` (or standard `OPENAI_API_KEY`) from `$_ENV`.
- **Fallback**: WordPress option `wecoza_openai_api_key` stored with `autoload = no`; UI masks value except for a last-4 preview.
- **Validation**: Non-empty, matches `/^sk-[\w]{20,}$/`.
- **Admin UI**: Update `includes/Admin/SettingsPage.php`:
  - Feature toggle `wecoza_ai_summaries_enabled` (checkbox)
  - API key field with mask + “Test Connection” button
  - Inline status indicator (last successful call timestamp)

## 6. High-Level Workflow
```
Class Change →
PostgreSQL Trigger →
class_change_logs (JSON + ai_summary.status="pending") →
Cron: NotificationProcessor::process() every 5 min →
Fetch up to 50 pending logs ordered by log_id →
Obfuscate payload →
AISummaryService::generateSummary() →
OpenAI API (GPT-4o Mini primary, GPT-3.5 Turbo fallback) →
Persist ai_summary (status + metadata) →
EmailBuilderPresenter renders enhanced template →
wp_mail() →
Advance wecoza_last_notified_log_id
```

## 7. Implementation Plan

### Phase 1 – Foundations (Week 1)
1. **Schema**: Add `ai_summary` to `schema/class_change_logs.sql`.
2. **Service Skeleton**: `includes/Services/AISummaryService.php`
   - Interfaces for `generateSummary`, `getMetrics`.
   - HTTP client abstraction (injectable for testing).
3. **Configuration**:
   - Extend `includes/Admin/SettingsPage.php`.
   - Add `includes/Support/OpenAIConfig.php` for env/option resolution.

### Phase 2 – Notification Integration (Week 2)
1. **NotificationProcessor** (`includes/Services/NotificationProcessor.php`):
   - Inject `AISummaryService`.
   - Limit each cron pass to 50 logs or 20 seconds.
   - Skip rows where `ai_summary.status === 'success'`.
   - Guard against overlapping runs using transient lock.
2. **Email Presenter** (`includes/Views/Presenters/NotificationEmailPresenter.php`):
   - Render “AI Summary” block when available.
   - If `status !== 'success'`, fall back to raw JSON and include failure note.
3. **Template** (`includes/Views/event-tasks/email-summary.php`):
   - Introduce structured sections (summary, metadata, audit link).
   - Ensure HTML emails gracefully degrade to plain text.

### Phase 3 – Reliability & Obfuscation (Week 3)
1. **Retry Logic**: 3 attempts with exponential backoff (1s, 2s, 4s). Store `attempts`.
2. **Error Handling**: On failure, set `status = 'failed'`, preserve sanitized `error_message`.
3. **Obfuscation Module**: Reusable helper with unit tests.
4. **Metrics Logging**: Use `error_log` or WP logging hook (`do_action('wecoza_ai_summary_generated', …)`).

### Phase 4 – Rollout & QA (Week 4)
1. **Feature Flag**:
   - WP option `wecoza_ai_summaries_enabled`; default `false`.
   - Gradual rollout logic: hash `log_id % 10` → enable when value < rollout percentage.
2. **Monitoring**:
   - WP-CLI command `wp wecoza ai-summary status` for ops.
   - Dashboard widget (stretch): success/failure counts, average processing time.
3. **Documentation**:
   - Update `docs/` with admin guide and developer setup notes.

## 8. Operational Constraints
- **Cron Budget**: Hard cap 20 seconds; stop early if remaining time < 5s.
- **Batch Size**: `LIMIT 50` pending logs per run.
- **Timeouts**: 10s HTTP timeout per OpenAI request.
- **Duplicate Protection**: Acquire site transient `wecoza_ai_summary_lock` before processing; release after.
- **Idempotency**: If cron reprocesses a log with `status='success'`, skip to avoid duplicate API calls.

## 9. Rollout Strategy
1. **Stage**: Enable flag in staging; run manual cron `wp cron event run wecoza_events_process_notifications`.
2. **Percentage Rollout**: Start at 10% (`rollout_percentage = 10`), increase after monitoring.
3. **Override**: Admin UI includes “Force enable for testing” toggle (per environment stored in `wp_options`).
4. **Rollback**: Toggle feature flag off; emails revert to raw JSON. Existing `ai_summary` data retained for audit.

## 10. Metrics & Reporting
- **Clarification Requests**: Track weekly support tickets tagged `notification-clarification`; baseline = average of 4 weeks pre-launch. Goal: 80% reduction.
- **Success Rate**: Ratio of logs with `ai_summary.status='success'` vs total processed. Logged nightly.
- **Cost Monitoring**: Aggregate `tokens_used` × OpenAI pricing; include in WP-CLI status output.
- **Latency**: Record `processing_time_ms`; alert if median > 3 seconds.

## 11. Testing Requirements

### 11.1 Automated
- Unit tests for `AISummaryService` obfuscation, retry, and error mapping.
- Contract test for OpenAI client (mocked responses).
- Presenter snapshot tests (HTML + plain text).

### 11.2 Manual (per PR guidelines)
- Capture screenshot of updated admin settings panel.
- Manually trigger cron (`wp cron event run wecoza_events_process_notifications`) and attach resulting email copy.
- Verify `[wecoza_event_tasks]` shortcode still renders (no regression).
- Confirm fallback behaviour by forcing OpenAI failure (use invalid key) and documenting email output.

## 12. Security & Compliance
- Secrets never logged; redact before storing metrics.
- Obfuscation ensures no raw learner names/IDs leave the system.
- Enforce HTTPS, validate TLS certificates.
- Store only required summary metadata; original JSON audit trail remains untouched.

## 13. Dependencies & Tooling
- PHP 8.1+, cURL, JSON extensions.
- OpenAI API access with models `gpt-4o-mini` primary, `gpt-3.5-turbo` fallback.
- WordPress cron operational.
- Ability to run WP-CLI commands in target environment.

## 14. Scope Decisions
- **Admin surface**: Summaries remain email-only in v2; add backlog ticket for wp-admin visibility after launch.
- **Language support**: English-only prompts and responses for the initial release; capture translation demand during rollout metrics review.
- **Summary storage policy**: Persist only the sanitized summary produced with obfuscated identifiers; discard pseudonym mapping immediately after email rendering.

## 15. Acceptance Criteria
1. Every new `class_change_logs` row ends with `ai_summary.status` in `{success, failed}` (no lingering `pending` after cron).
2. AI summaries appear at the top of notification emails when flag enabled; otherwise, emails match legacy format.
3. Admin settings show feature toggle, masked API key, and last test result.
4. Manual QA evidence attached to release PR (admin screenshot, sample email, cron command output, shortcode rendering).
5. WP-CLI status command reports success/failure counts for the last 24 hours.

This v2 PRD captures the engineering tasks, operational safeguards, and QA steps needed to deliver AI-assisted notification summaries without overwriting the original specification.
