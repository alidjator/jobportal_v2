# Phase 8: hiring managers: a role on the job, a review permission, a stage boundary and shortlist reviews (designed 2026-10-14, not coded)

Gaps found from the hiring manager's point of view and against Mekari Talenta (help center articles 47603513135385 and 34576595122073: a "User"
account role acting as hiring manager or interviewer, and a hiring team per job listing with Recruiter, Hiring Manager and Interviewer), chosen by
the user from the advice of 2026-10-14. Talenta has no shortlist review; ours goes further. Not taken now: a hire decision and offer approval by the
hiring manager (on request), requisitions (on request). Extends `phase-8-company-permissions.md` (catalog, presets, scope), `phase-1-jobs.md`
(assignees), `phase-2-notes.md` (notes, ratings), `phase-1-pipeline.md` and `phase-8-recruiter-workday.md` (tasks, team preferences). Coded in a
coding session, after the recruiter workday.

## Decisions

- 2026-10-14: each assignee of a job has a **role on the job**: `recruiter` or `hiring_manager` (chosen by the user).
- 2026-10-14: a **new permission `applications.review`** for ratings, notes and review answers, required by `applications.manage`; a fourth preset
  **Hiring Manager** (decided by the user).
- 2026-10-14: a hiring manager sees an application only **from a stage boundary** set per job (default: the company's first interview stage) or
  when it is sent for review, recorded in a **marker column** that is never cleared (schema decided by the user).
- 2026-10-14: **shortlist reviews**: a recruiter asks the job's hiring managers to review chosen applications by a due date; each answers
  **Lanjut / Tidak / Mungkin** per candidate (reason required for Tidak); answers never move an application; a hiring manager sees the others'
  answers only after sending their own; reminders the day before the due date and a notice to the requester when it passes (chosen by the user).

## 1. Role on the job and the review permission

- **Storage:** `job_assignments.role` (`recruiter` default, `hiring_manager`; CHECK). `PUT company/jobs/{id}/assignees` takes
  `[{user_id, role}]`; at least one assignee as today. The job detail, the job list's `assignees` and the member's job list show the role.
- **Catalog:** `applications.review` (group Applicants): rate, write and edit notes, answer reviews. Requires `applications.view`
  (`permission-requires-view`); `applications.manage` now requires `applications.review` (422 `permission-requires-review`; the front end ticks it).
  Note deletion stays with the author or a company admin. Requesting a review needs `applications.manage`. The catalog has 21 permissions.
- **Presets:** Recruiter gains `applications.review`. New **Hiring Manager**: `applications.view`, `applications.review`, `interviews.manage`
  (no move, reject, offer, export or tests). Interviewer unchanged.
- **Migration:** every member and pending invitation holding `applications.manage` gets `applications.review`, so nobody loses access.
- **Notifications:** `JobDirectory::notificationRecipients` returns the role; a hiring-manager assignee gets an application event only for an
  application they may see (section 2), so never `application.received`.

## 2. Stage boundary

- **Who is bound:** a member whose assignment on the job has role `hiring_manager`, unless they hold `jobs.view_all` or are a company admin.
  Others keep the job-wide scope of today.
- **Job setting:** `job_jobs.hiring_manager_from_stage_id` (nullable; null = the company's first stage of type `interview`), an active stage
  of the company checked through the Recruitment stage port (422 `invalid-stage`), set in the job editor (C4) by `jobs.create`.
- **Marker:** `recruitment_applications.hiring_manager_visible_at`, set (once, never cleared) when the application enters a stage at or after the
  boundary in the company's stage order (`hired` counts; `rejected` and `withdrawn` do not), is sent for review (section 3), or a hiring manager is
  added to one of its interview panels. A later rejection keeps it visible. Changing the boundary or the stage order sets the marker on the job's
  active applications that now qualify (never clears). The migration fills it from the stage history with the default boundary.
- **Where it applies:** every team read of applications: the pipeline and its stage counts, the list across jobs, the application detail, resume
  and files (404 otherwise), interviews, the calendar, assessment results, conversations about the application, `other_applications`, the task
  counts and the applicant search. The rule sits in `CompanyApplicationAccess` and one list condition in Recruitment; Assessment, Messaging and
  the calendar ask through the existing `TeamApplications` port.
- Front end: the pipeline shows a bound hiring manager only the stages from the boundary, with "Lamaran lain dikirim oleh recruiter bila perlu
  ditinjau".

## 3. Shortlist reviews

### Storage

- `recruitment_review_requests`: `company_id`, `job_id`, `requested_by_user_id`, `message` (≤1,000, plain text), `due_on` (date, Jakarta),
  `status` (`open`, `complete`, `closed`), `overdue_notified_at`, `reminded_at`, timestamps.
- `recruitment_review_request_items` (`request_id`, `application_id`, unique pair) and `recruitment_review_request_reviewers` (`request_id`,
  `user_id`, unique pair).
- `recruitment_application_reviews`: `request_id`, `application_id`, `reviewer_user_id`, `decision` (`advance`, `decline`, `maybe`), `reason`
  (≤1,000, nullable), `answered_at`, `updated_at`; unique triple.

### API

| Endpoint | Rule |
|---|---|
| `POST company/jobs/{id}/review-requests` | `applications.manage`; `{application_ids (1 to 50, active applications of the job), reviewer_ids? (hiring-manager assignees of the job; default all of them), due_on (today to 30 days), message?}`; 422 `invalid-reviewers` (none, or not a hiring manager of the job). Sets the marker on the items. |
| `GET company/jobs/{id}/review-requests` | the job's requests with per-item, per-reviewer status (requester's view) |
| `GET company/review-requests` | the caller's requests as a reviewer, `filter[state]=pending|answered`, newest due first |
| `GET company/review-requests/{id}` | the compact review page: message, due date, and per candidate a card (name, current stage, latest position, education, experience, expected salary, must-have screening answers, interview scorecard summary and assessment summary when visible, resume link), the caller's answer, and the others' answers under the lock |
| `PUT company/review-requests/{id}/applications/{applicationId}/review` | reviewer with `applications.review`; `{decision, reason?}`; `reason` required for `decline` (422 `reason-required`); changeable while the request is not `closed` |
| `POST company/review-requests/{id}/close` | the requester or `applications.manage`; ends a request early |

- **Lock:** a reviewer sees the other reviewers' answers on an item after sending their own; the requester and members not among the reviewers
  see all. Same rule on the application detail.
- **Status:** `complete` when every reviewer answered every active item; an item whose application became final or withdrawn needs no answer and
  shows its stage.
- **Shown elsewhere:** the application detail gains `reviews[]` (request, reviewer, decision, reason, answered at; under the lock) and pipeline
  rows gain `review_summary {advance, decline, maybe, pending}`. Answers never move an application.

### Notifications and tasks

- **Requested:** in-app `review.requested` and email `team.review_requested` to each reviewer (preference `team_reviews`, new, on by default;
  `phase-8-recruiter-workday.md` section 3), with the job, the count, the due date, the message and a link to the review page.
- **Reminder:** the day before `due_on` at 08:00 Jakarta, in-app and email `team.review_reminder` to reviewers with pending items (once,
  `reminded_at`).
- **Overdue:** after `due_on`, in-app `review.overdue` to the requester (once, `overdue_notified_at`); the request shows `overdue`.
- **Complete:** in-app `review.completed` to the requester.
- **Tasks** (`GET company/tasks`): `my_reviews` (pending items of the caller as a reviewer) and `reviews_overdue` (the caller's overdue requests).
- **Erasure:** the account erasure and the retention of `phase-8-company-admin.md` clear the reasons of reviews about the applicant; the
  decisions stay. `docs/legal-review.md` question 14 lists review reasons with the team notes.

## Changes in short

| Piece | Change |
|---|---|
| Migration | `job_assignments.role`; `job_jobs.hiring_manager_from_stage_id`; `recruitment_applications.hiring_manager_visible_at` (filled from history); `recruitment_review_requests`, `_items`, `_reviewers`, `recruitment_application_reviews`; `applications.review` rows for holders of `applications.manage` |
| Catalog | `applications.review`; preset Hiring Manager; Recruiter gains review |
| API | roles in `PUT company/jobs/{id}/assignees`; `hiring_manager_from_stage_id` on the job; review requests (create, list per job, my list, detail, answer, close); `reviews[]` and `review_summary` |
| Preferences | `team_reviews` |
| Scheduler | review reminders and overdue notices in the hourly recruitment task |
| Notifications | `review.requested` (in-app, email `team.review_requested`), `team.review_reminder`, `review.overdue`, `review.completed` |
| Tasks | `my_reviews`, `reviews_overdue` |
| Errors | 422 `permission-requires-review`, `invalid-reviewers`, `reason-required`, `invalid-stage` |
| Front end | roles in the hiring team of the job editor (C4) with the stage boundary; the Hiring Manager preset in the invitation and edit dialogs (C22); "Minta review" in the pipeline selection (C6); the review page (new C28) with the answer buttons; reviews on the application detail and cards |
