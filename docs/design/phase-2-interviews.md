# Phase 2, step 2: interview scheduling (R7b, designed and coded 2026-10-08, not committed)

Context: `Recruitment`. Spec in `decisions.md` (R7b): time, format online/onsite, location or link, interviewer; the job seeker confirms or proposes another time; a schedule is mandatory when
moving to `interview` (one by one); a bulk move to `interview` uses one base schedule and the system creates sequential slots (the bulk move itself is step 4, the slot rules are decided here).

## Confirmed by the user (2026-10-08)

- **One active schedule per application.** Changing the time cancels the old schedule and creates a new one; the history is kept. The job seeker is notified.
- **Rescheduling:** the job seeker confirms, or proposes another time at most 2 times. The recruiter accepts a proposal or sets a new time. After the limit the job seeker can only confirm or decline.
- **Bulk slots:** the recruiter gives a start time, a duration, a gap and the interviewer; slots are created one after another in the order of the selected applicants. A daily window
  (start and end of the working day) is part of the base schedule; a slot that would end after the window moves to the next working day.
- **Access:** same as the application detail (assigned recruiters and company admins); the interviewer is chosen among the members who may see the job. No answer from the job seeker leaves the
  schedule `pending` (no auto-confirm). A reminder email goes to the job seeker the day before a confirmed interview; the team gets it in-app (as coded; the team email is designed in `phase-8-interviewers.md`).

## Details (approved 2026-10-08)

- Table `recruitment_interviews`: `application_id`, `starts_at`, `ends_at`, `format` (online/onsite), `location` or `meeting_url` (one required by the format), `interviewer_id`, `status`
  (`pending`, `confirmed`, `declined`, `cancelled`, `completed`), `proposals_used`, `proposed_starts_at`, `created_by`, timestamps. Partial rule: one non-cancelled row per application.
- Moving an application to `interview` one by one requires a schedule in the same request (422 `schedule-required`). Times must be in the future; "working day" for bulk is Monday to Friday.
- Job seeker endpoints: confirm, decline, propose (`proposed_starts_at`); recruiter endpoints: create or replace, accept proposal, cancel, mark completed. Notifications: created, changed, confirmed, proposal, declined (email, plus in-app).

## Coded (2026-10-08)

- Migration `000081` (`recruitment_interviews`, with a unique `active_key` that holds the application id while the interview is pending or confirmed). It ran on the test database only; the real table needs the user's approval.
- Domain: `Interview` (transitions `confirm`, `decline`, `propose`, `acceptProposal`, `cancel`, `complete`), `InterviewStatus`, `InterviewFormat`, `InterviewSlotPlanner` (pure; the bulk move of step 4 will call it with Asia/Jakarta, a 09:00 to 17:00 window and Monday to Friday).
- Use cases: `ScheduleInterview` (create or replace, only while the application is in an interview stage), `ManageInterview` (accept proposal, cancel, complete), `RespondToInterview` (confirm, decline, propose), `ListApplicationInterviews`, `GetMyInterview`, `SendInterviewReminders`.
  `InterviewScheduler` is shared with `ProcessApplication::move`, which takes an optional `interview` and refuses a move to an interview stage without one unless the application already has an active interview.
- Endpoints: `GET company/applications/{id}/interviews`, `PUT .../interview` (201 new, 200 replaced; body `starts_at` ISO 8601 with offset or `Z`, `duration_minutes` 5 to 480, `format`, `location` or `meeting_url`, `interviewer_id`),
  `POST .../interview/accept-proposal|cancel|complete`; `GET me/applications/{id}/interview` (`data` null when none), `POST me/applications/{id}/interview/confirm|decline|propose {starts_at}`. The move request takes the same fields under `interview`.
- Errors: 422 `schedule-required`, `invalid-schedule`, `invalid-interview`, `invalid-interviewer`; 409 `not-in-interview-stage`, `no-active-interview`, `interview-not-pending`, `proposal-pending`, `proposal-limit`, `no-proposal`, `interview-not-active`, `interview-not-confirmed`.
- Choices made while coding (not asked): a proposal waits for the team, so the job seeker cannot confirm or propose again meanwhile (`proposal-pending`); accepting a proposal confirms the interview; declining is allowed while pending or confirmed;
  a rejection or a withdrawal cancels the active interview (the job seeker is told on rejection); moving out of an interview stage in any other way leaves the interview as it is; the team (interviewer and who scheduled it) is told **in-app only**, as for new applications, and
  only the job seeker gets emails (scheduled, rescheduled, cancelled, reminder), following the preference `application_update`; the job seeker sees the interviewer's name; the time in emails is Jakarta time; the reminder runs hourly and covers interviews starting within 24 hours;
  anonymising the applicant deletes their interviews.
- Tests: `InterviewsTest` (22), `InterviewTest` and `InterviewSlotPlannerTest` (unit). The existing move tests now send a schedule when they move to an interview stage.
