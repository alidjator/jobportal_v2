# Phase 8: hiring managers: a role on the job, a review permission, a stage boundary and shortlist reviews (designed 2026-10-14; sections 1 and 2 coded 2026-10-17, section 3 in part)

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

## Coded: sections 1 and 2 (2026-10-17)

Migration `000154`: `job_assignments.role`, `job_jobs.hiring_manager_from_stage_id`, `recruitment_applications.hiring_manager_visible_at` (filled from the stage history with the default boundary) and `applications.review`
for every member and pending invitation that held `applications.manage`. The team preference `team_reviews` that section 3 needs now exists in the recruiter workday section 3 (part B below).
Interpretations, where the design left room:

- **Role on the job.** `PUT company/jobs/{id}/assignees` takes `assignees: [{user_id, role?}]` (role `recruiter` by default) **or** the old `user_ids`, where an assignee that stays keeps its role (a new one is a recruiter); sending both is a 422.
  The assignee rows (`GET company/jobs/{id}/assignees`, the job detail) keep `role` (the role in the company) and gain `job_role`. A change of role is audited in `job.assignees_changed` (`roles_changed`). The company job list has only `assignees_count`,
  and the member's job list (`AssignedJob`) has no role yet.
- **Permission.** Catalog of 28; `requires_review` in the catalog answer; `applications.manage` without `applications.review` is a 422 `permission-requires-review`. Ratings and notes (add, edit, delete) now need `applications.review`; the preset Hiring Manager
  is `applications.view`, `applications.review`, `interviews.manage`, and Recruiter gains review.
- **Boundary.** Its own endpoint, `PUT company/jobs/{id}/hiring-manager-boundary` with `{stage_id}` (`present`; null returns to the default), by `jobs.create`, in any status of the job (the design said "in the job editor"; the job content freezes after
  publication, the boundary must not). A stage must belong to the company and not be `rejected` or `withdrawn` (422 `invalid-stage`). The job detail shows `hiring_manager_from_stage_id` (the raw value). A boundary stage that was deleted counts as "none".
- **Marker.** `HiringManagerVisibility` (Recruitment) sets `hiring_manager_visible_at` when an application is submitted, moved, hired or merged into another stage (a stage deleted), when stages are reordered or deleted (the whole company is
  re-evaluated), when the boundary of a job changes (the job), and when an interview is scheduled or its panel replaced with a hiring manager of the job as the interviewer or on the panel. It never clears. The legacy import sets it for applications at an
  interview, offer or hired stage.
- **Who is bound.** A member assigned as a hiring manager on a job and without `jobs.view_all` (a company admin always has it). On every other job the member keeps the assignee scope. `HiringManagerScope` is the one rule; `CompanyApplicationAccess` (detail, resume,
  every `TeamApplications` caller: interviews, assessment, notes, ratings, move) answers 404, `ListJobApplications` and the export list only marked applications and count only those per stage, `other_applications` hides unmarked ones (`others_hidden`),
  and the dashboard cards and the task counts of Recruitment (`stale_applications`, `my_scorecards`, `offers_*`) leave them out through `boundJobIds` of `DashboardScope` and `TaskScope`.
- **Notifications.** `application.received` goes to the job's recipients except its bound hiring managers.
- **Not covered yet:** conversations about an application (a hiring manager has no `messages.send`) and the applicant search (#19, not coded) must use the same rule when they read applications of a bound job.


## Coded: section 3, part A (2026-10-17, migration `000162`)

- **Tables:** `recruitment_review_requests`, `_items`, `_reviewers` and `recruitment_application_reviews` as designed (checks on `status` and `decision`, unique pairs and triple).
  Domain: `ReviewRequest`, `ReviewAnswer` (a `decline` needs a reason), `ReviewDecision`, `ReviewRequestStatus`, port `ReviewRequestRepository`.
- **Create** `POST company/jobs/{id}/review-requests` (`CreateReviewRequest`, `applications.manage` and the job): `application_ids` (1 to 50, active applications of the job that the requester may see, else 422
  `invalid-applications`), `reviewer_ids?` (hiring-manager assignees of the job, default all; none or a stranger 422 `invalid-reviewers`), `due_on` (a Jakarta day from today to 30 days ahead, else 422
  `invalid-due-date`), `message?`. It sets the visibility marker on every item (`HiringManagerVisibility::showForReview`, never cleared). 201 with the request as the requester sees it.
- **Lists:** `GET company/jobs/{id}/review-requests` (the requester's view with every answer; `applications.manage`, newest first, paginated) and `GET company/review-requests` (the caller as a reviewer,
  `applications.review`, latest due first, paginated, `filter[state]=pending|answered`: `pending` is an open request with an active application the caller has not answered).
- **Page:** `GET company/review-requests/{id}` (`GetReviewRequest`: a reviewer, who made it, or `applications.manage` with the job; anyone else 404) adds a `card` per candidate: name, headline, city,
  experience level, latest experience, latest education, expected salary, `meets_must_have`, the screening answers, `interview_result` and `has_resume` (the resume is `company/applications/{id}/resume`).
- **Answer:** `PUT company/review-requests/{id}/applications/{applicationId}/review` `{decision, reason?}` (`AnswerReview`, a reviewer with `applications.review`; 422 `reason-required`; 409 `review-request-closed`,
  `application-final`; 404 for a stranger or another application). Changeable until the request is closed; it never moves the application. The request becomes `complete` when every reviewer answered every
  active application. **Lock:** an item's `locked` is true while the reviewer has not answered it: the others' `decision` and `reason` are null (`answered` still shows); the requester sees all.
- **Close:** `POST company/review-requests/{id}/close` (who made it or `applications.manage`; 204, also when already closed; answers stay).
- **Not coded (part B):** the notifications and emails (`review.requested`, `team.review_requested`, the reminder, `review.overdue`, `review.completed`), the preference `team_reviews`, the scheduler step, the tasks
  `my_reviews` and `reviews_overdue`, `reviews[]` on the application detail, `review_summary` on the pipeline rows, the erasure of reasons, and the assessment summary on the card.
