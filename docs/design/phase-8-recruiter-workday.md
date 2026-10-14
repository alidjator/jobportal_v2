# Phase 8: the recruiter's task list, time in stage, team notification preferences and daily digest, other applications and the received email (designed 2026-10-14, not coded)

Gaps found from the recruiter's point of view and against Mekari Talenta's Home page (help center article 34567830638361: new candidates,
unscheduled interviews, unanswered offers, jobs closing, drafts, jobs without applicants) and email templates (61789018343449: an "incoming"
scenario), chosen by the user from the advice of 2026-10-14. Not taken now: saved filters (after the applicant search is coded; the browser may keep
them meanwhile), a full per-job email template library and a job widget for company websites. Extends `phase-1-pipeline.md`, `phase-4-dashboards.md`,
`phase-8-interview-calendar.md` (message defaults) and the notification preferences. Coded in a coding session.

## Decisions

- 2026-10-14: a **task list** for each member, counting what waits for them (chosen by the user).
- 2026-10-14: **time in stage** on every application, with a "stale" mark (chosen by the user).
- 2026-10-14: **notification preferences for team members** and a **daily email digest**; this settles the "no team preference yet" notes of
  `phase-8-withdrawals.md` and `phase-8-provider-tools.md` (chosen by the user).
- 2026-10-14: an application shows the person's **other applications** to the company (chosen by the user).
- 2026-10-14: an **"application received" email**, with the company's own text when set; no attachment (chosen by the user).

## 1. Task list

- `GET company/tasks` for a member of an active company. Each item: `{key, count, filter}` where `filter` is the query to open the matching list;
  an item the member may not use (missing permission) is left out; counts cover only the jobs the member may see.

| Key | Counts | Opens | Needs |
|---|---|---|---|
| `stale_applications` | active applications past the stale limit in `applied` or `screening` (section 2) | `company/applications?filter[stale]=1` | `applications.view` |
| `unscheduled_interviews` | applications in an interview stage with `interview_state=not_scheduled` | the pipeline filter of the calendar design | `interviews.manage` |
| `my_scorecards` | interviews where the member is on the panel, scoring is open and their scorecard is not sent | `company/interviews?filter[my_scorecard]=pending` (new filter) | panel membership |
| `my_scoring` | submitted tests with long-text answers waiting for the member as an assessor | `company/assessment/invitations?filter[my_scoring]=pending` (new filter) | assessor |
| `offers_waiting` | offers sent and not answered | `filter[state]=offer_pending` | `offers.manage` |
| `offers_expiring` | of those, expiring within 3 days | same, `filter[offer_expiring]=1` | `offers.manage` |
| `jobs_closing` | active jobs whose deadline is within 7 days | `company/jobs?filter[deadline_to]=…` | — |
| `jobs_draft` | drafts created by the member, or all for a company admin | `company/jobs?filter[status]=draft` | `jobs.create` |
| `jobs_without_applicants` | active jobs published at least 7 days ago with no application | `company/jobs?filter[applications]=0` (new filter) | — |

- Computed live, one grouped query per item, cached 60 seconds per member. The company dashboard (C1) shows it as the first card ("Perlu
  tindakan"); the existing count cards stay.

## 2. Time in stage

- New column `recruitment_applications.stage_entered_at`, set on every move into a stage (apply, move, reject, withdraw, hire) in the same write;
  the migration fills it from the latest stage history entry.
- Pipeline rows, the list across jobs and the application detail gain `stage_entered_at` and `days_in_stage` (whole Jakarta days).
- **Stale:** an active application whose `days_in_stage` is above the company setting `recruitment.stale_after_days` (company admin, default 7, 1
  to 60), only in `applied` and `screening` (later stages have their own clocks: interviews, tests and offers). `filter[stale]=1` on both lists;
  rows carry `stale: true`. The front end shows "12 hari di tahap ini" in amber when stale.
- Sort `days_in_stage` on both lists.

## 3. Team notification preferences and the daily digest

### Preferences

- `NotificationPreferenceDefinition` gains `audiences` (`job_seeker`, `company_member`, `psychologist`); `GET me/notification-preferences` lists
  only the caller's. Existing keys are `job_seeker`.
- New keys for company members (email channel; in-app always stays):

| Key | Default | Covers |
|---|---|---|
| `team_daily_digest` | on | the digest below |
| `team_interview_changes` | on | a job seeker confirmed, declined or proposed a new time; an interview cancelled by a withdrawal (`phase-8-withdrawals.md`) |
| `team_offer_answers` | on | an offer accepted or declined |
| `team_new_applications` | off | each new application to a job assigned to the member (the digest counts them anyway) |
| `team_scoring` | off | a test submitted where the member is an assessor; a scorecard reminder |

- New key for psychologists: `provider_reviews` (on), the waiting-reviews email of `phase-8-provider-tools.md`; the licence emails stay
  always on (they decide whether reports can be signed).
- Each team email is a platform template (`team.*`) with the one-click opt-out of the existing emails; the content holds ids and job titles and the
  candidate's name only where the member may see the application.

### Daily digest

- `company:send-team-digests` at **07:00 Jakarta**, Monday to Saturday: for each active member with `team_daily_digest` on and a verified email,
  the task list of section 1 plus the counts of the last 24 hours (new applications on their jobs, interviews today with times). **Nothing is sent
  when every count is zero.** One email per member and company (a member of two companies gets two).
- Template `team.daily_digest`; each line links to the matching list in the company app.

## 4. Other applications of the same person

- The application detail gains `other_applications`: the person's other applications to **this company**, newest first, at most 10:
  `{id, job {id, title}, stage {type, name}, applied_at}` for those the member may see, and `others_hidden` (a count) for those on jobs the member
  may not see. Withdrawn and final ones are included (marked by their stage).
- Pipeline rows gain `other_applications_count` (active ones only); the card shows "+2 lamaran lain".
- An anonymised applicant shows none.

## 5. "Application received" email

- New email `recruitment.application_received` to the job seeker after applying (by the preference `application_update`), and no in-app notice
  (the apply answer already confirms it). Content: job title, company, the date, a link to the application page, the anti-scam line and the
  company logo of the calendar design section 6.
- `company/message-defaults` gains `applied` (at most 1,000 characters, plain text, the link rule): when set, it is added to the email as the
  company's message. No attachment.
- Sent after the commit by a queued listener on `ApplicationSubmitted`, like the assignee notice; a failure never undoes an application.

## Changes in short

| Piece | Change |
|---|---|
| Migration | `recruitment_applications.stage_entered_at` (filled from history) |
| Settings | `recruitment.stale_after_days` (company) |
| API | `GET company/tasks`; `stage_entered_at`, `days_in_stage`, `stale`, `filter[stale]`, sort `days_in_stage`; `filter[my_scorecard]`, `filter[my_scoring]`, `filter[applications]=0`, `filter[offer_expiring]`; `other_applications`, `others_hidden`, `other_applications_count`; `applied` in `company/message-defaults`; `audiences` on the preference list |
| Preferences | `team_daily_digest`, `team_interview_changes`, `team_offer_answers`, `team_new_applications`, `team_scoring`, `provider_reviews` |
| Scheduler | `company:send-team-digests` (07:00 Jakarta, Monday to Saturday) |
| Emails | `team.daily_digest` and the `team.*` templates of the keys above; `recruitment.application_received` |
| Front end | "Perlu tindakan" card (C1); time in stage and stale mark on the pipeline (C5, C6); other applications on the application detail; team preferences in the account settings; "Pesan lamaran diterima" in the message defaults |
