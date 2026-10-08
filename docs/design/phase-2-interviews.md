# Phase 2, step 2: interview scheduling (R7b, designed 2026-10-08, not coded)

Context: `Recruitment`. Spec in `decisions.md` (R7b): time, format online/onsite, location or link, interviewer; the job seeker confirms or proposes another time; a schedule is mandatory when
moving to `interview` (one by one); a bulk move to `interview` uses one base schedule and the system creates sequential slots (the bulk move itself is step 4, the slot rules are decided here).

## Confirmed by the user (2026-10-08)

- **One active schedule per application.** Changing the time cancels the old schedule and creates a new one; the history is kept. The job seeker is notified.
- **Rescheduling:** the job seeker confirms, or proposes another time at most 2 times. The recruiter accepts a proposal or sets a new time. After the limit the job seeker can only confirm or decline.
- **Bulk slots:** the recruiter gives a start time, a duration, a gap and the interviewer; slots are created one after another in the order of the selected applicants. A daily window
  (start and end of the working day) is part of the base schedule; a slot that would end after the window moves to the next working day.
- **Access:** same as the application detail (assigned recruiters and company admins); the interviewer is chosen among the members who may see the job. No answer from the job seeker leaves the
  schedule `pending` (no auto-confirm). A reminder email goes to the job seeker and the interviewer the day before a confirmed interview.

## Proposed details (to confirm)

- Table `recruitment_interviews`: `application_id`, `starts_at`, `ends_at`, `format` (online/onsite), `location` or `meeting_url` (one required by the format), `interviewer_id`, `status`
  (`pending`, `confirmed`, `declined`, `cancelled`, `completed`), `proposals_used`, `proposed_starts_at`, `created_by`, timestamps. Partial rule: one non-cancelled row per application.
- Moving an application to `interview` one by one requires a schedule in the same request (422 `schedule-required`). Times must be in the future; "working day" for bulk is Monday to Friday.
- Job seeker endpoints: confirm, decline, propose (`proposed_starts_at`); recruiter endpoints: create or replace, accept proposal, cancel, mark completed. Notifications: created, changed, confirmed, proposal, declined (email, plus in-app).
