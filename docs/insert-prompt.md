# LLM Prompt Template: Class INSERT Verification

## Purpose
- Use this template when a new class INSERT report is generated and we want an LLM to sanity-check the data before sign-off.
- The template guides the model to validate both technical integrity (field shape, data types, relationships) and business plausibility (schedule coherence, learner assignments, compliance hints).

## Recommended Inputs
- `insert_report_json`: Raw INSERT audit payload exactly as emitted (see `docs/example.json` for the schema).
- `business_rules`: Known expectations or edge cases for the specific class (can be empty, but populate as we mature the library).
- `contextual_notes` (optional): Anything unusual about the client, site, or rollout that the model should know.

## Suggested Checks To Seed The Model
- Confirm `class_id`, `client_id`, `site_id`, `class_code`, and `class_subject` exist and match known patterns.
- Ensure `created_at` ≤ `updated_at` and that the audit timestamp aligns with the change window.
- Compare `schedule_data.startDate`, `endDate`, `selectedDays`, and `perDayTimes` for logical consistency (e.g., end date after start date, times align with selected days, durations positive).
- Validate `original_start_date`, `delivery_date`, and `stop_restart_dates` against the schedule window.
- Look for empty or contradictory learner entries (duplicate IDs, missing levels where required, sets not matching `exam_learners`).
- Check booleans/flags (`seta_funded`, `exam_class`, `holiday_overrides`) for reasonableness and alignment with provided metadata.
- Flag any `null` or empty strings on fields that typically drive downstream processes (address, supervisors, agent assignments).

## Prompt Template
````text
You are a QA analyst validating that a newly inserted WeCoza class record is internally consistent and satisfies business expectations.

### Inputs
- INSERT report (authoritative source of truth):
```json
{insert_report_json}
```
- Business rules and expectations to enforce (may be partial):
```
{business_rules}
```
- Additional context (optional notes from the operator):
```
{contextual_notes}
```

### Tasks
1. **Technical integrity** – verify required fields, expected data types, JSON structures, and relational IDs. Highlight anything missing, malformed, or suspicious.
2. **Business plausibility** – check that the schedule, learner roster, staffing, and compliance-related fields “make sense” based on the provided rules or standard practice (e.g., schedule dates align, class duration matches the timetable, staffing looks realistic, learner statuses align with class state).
3. **Risk assessment** – classify each finding with a severity tag:
   - `Critical`: breaks the insert or will cause downstream failure.
   - `Major`: likely to cause incorrect behaviour or data drift soon.
   - `Minor`: inconsistent with expectations but low immediate risk.
   - `Info`: noteworthy observation or follow-up question.
4. Summarise whether the class INSERT should be accepted or needs remediation, and call out any clarifying questions for the operator.

### Response Format
```
Overall Verdict: <Pass | Fail | Needs Review> — one sentence justification.

Findings:
- [Severity] <short title>: <concise description referencing field/path>.
- ...

Follow-up Actions:
- (Only include when there are findings; suggest next steps or data owners.)

Questions for Operator:
- (Optional; list any information the LLM needs to confirm assumptions.)
```
```` 

## Usage Notes
- If `business_rules` is empty, instruct the model to propose likely checks based on patterns in the report and flag assumptions explicitly.
- When multiple reports are batched, run the template per class to keep verdicts atomic.
- Archive the LLM response with the INSERT report for traceability (e.g., `daily-updates/` entry).
