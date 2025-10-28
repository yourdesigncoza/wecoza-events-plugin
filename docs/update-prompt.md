# LLM Prompt Template: Class UPDATE Verification

## Purpose
- Run this template whenever an existing class record is updated and an audit JSON (similar to `docs/example.json`) is produced.
- Focus on catching unintended regressions by comparing the previous snapshot with the new state and evaluating whether the change set aligns with expectations.

## Recommended Inputs
- `update_report_json`: Full JSON payload for the update (must include `Operation`, `Changes`, and `New Row Snapshot` sections).
- `expected_change_intent`: Brief operator summary of what was supposed to change (e.g., “extend schedule by two weeks due to exam prep”).
- `business_rules`: Current policy checks or business logic guardrails that should still hold after the update.
- `contextual_notes` (optional): Anything noteworthy such as client escalations, known data quirks, or ticket references.

## Suggested Checks To Seed The Model
- Verify the audit metadata (operation type, timestamps, class ID) and ensure immutable fields (`created_at`, `class_id`, origin identifiers) remain untouched unless intentionally modified.
- Compare `Changes` entries to the `expected_change_intent`; flag any additional fields that changed or expected fields that remain untouched.
- Re-evaluate schedule integrity: `startDate`, `endDate`, `selectedDays`, per-day times, `exceptionDates`, and `holidayOverrides` should stay coherent and reflect the intended adjustment.
- Cross-check learner and staffing data (`learner_ids`, `exam_learners`, `class_agent`, `backup_agent_ids`, `project_supervisor_id`) for unexpected removals/additions or status regressions.
- Confirm that compliance-related fields (`seta_funded`, `exam_class`, QA metadata, notes) still satisfy the `business_rules`.
- Ensure all JSON arrays/objects remain well-formed and that no required data regressed to `null`, empty string, or invalid enumerations.

## Prompt Template
````text
You are a QA analyst verifying that an existing WeCoza class update is accurate, intentional, and compliant with operational rules.

### Inputs
- UPDATE audit payload (includes before/after deltas):
```json
{update_report_json}
```
- Intended change scope:
```
{expected_change_intent}
```
- Business rules and invariants to enforce:
```
{business_rules}
```
- Additional context (optional):
```
{contextual_notes}
```

### Tasks
1. **Change intent alignment** – confirm the fields listed in `Changes` match the expected intent; flag any missing or extra modifications.
2. **Technical integrity** – validate data types, JSON structures, and referential IDs in the updated snapshot. Highlight malformed or missing attributes.
3. **Business plausibility** – evaluate whether the revised schedule, learner roster, staffing, and compliance markers remain sensible given the intent and rules provided.
4. **Regression scan** – check for unintended regressions or risk areas compared to the previous snapshot (e.g., shorter duration without rationale, learner status downgrades).
5. **Risk assessment** – tag each finding with one of:
   - `Critical`: Must fix before accepting the update.
   - `Major`: High likelihood of downstream issues; needs action soon.
   - `Minor`: Low impact inconsistency; monitor or clarify.
   - `Info`: Observation or assumption to confirm.
6. Summarise whether the update should be accepted, conditionally accepted, or rejected, and list follow-up actions or questions.

### Response Format
```
Overall Verdict: <Accept | Conditional | Reject> — one sentence justification tying to the main risk.

Findings:
- [Severity] <short title>: <concise description referencing field/path and intent mismatch>.
- ...

Follow-up Actions:
- (List concrete remediation steps or owners when findings exist.)

Questions for Operator:
- (Optional; note any clarifications needed to finalise the verdict.)
```
````

## Usage Notes
- Always populate `expected_change_intent` when possible; if blank, instruct the LLM to infer the likely intent from `Changes` and flag assumptions explicitly.
- Encourage the model to point out unchanged but high-risk fields related to the stated intent (e.g., schedule change request where `schedule_data` is untouched).
- Store the LLM response alongside the audit payload for traceability and future trend analysis.
