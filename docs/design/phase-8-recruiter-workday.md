# Phase 8: the recruiter's task list, time in stage, team notification preferences and daily digest, other applications and the received email (designed 2026-10-14; sections 1, 2 and 4 coded 2026-10-17)

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
- 2026-10-17: **coded in parts** (chosen by the user): sections 2, 4 and 1 first (the task list without `unscheduled_interviews`, which waits for the interview calendar #10); sections 3 and 5 follow.
- 2026-10-17: section 3 **part A coded** (audiences and the daily digest); the event emails (`team_interview_changes`, `team_offer_answers`, `team_new_applications`, `team_scoring`, `provider_reviews`) follow with the code that sends them. The existing `approval_task` and `approval_update` are for company members, not job seekers (chosen by the user; corrects section 3's "existing keys are job_seeker").

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
| `team_scoring` | off | a test submitted where the member is an assessor (the scorecard emails moved to `team_scorecard_reminders`, `phase-8-interviewers.md`) |

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

## Coded: sections 2, 4 and 1 (2026-10-17)

Migration `000153` (`recruitment_applications.stage_entered_at`, default now, filled from the latest stage history entry or the apply time). Sections 3 (team preferences and the digest) and 5 (the received email) are not coded:
they wait for the interview calendar (#10: message defaults, the logo, `interview_state`). Interpretations, where the design left room:

- **Time in stage.** `stage_entered_at` is written with every stage change: apply, move, reject, withdraw, hire, the merge when a stage is deleted, and the legacy import (the apply time, or the time of the system move).
  `days_in_stage` is whole Jakarta days (`StageClock`); `stale` is true in `applied` or `screening` when the days are **more than** the limit. The list of a job (`company/jobs/{id}/applications`, the pipeline
  rows) and the application detail carry `stage_entered_at`, `days_in_stage` and `stale`; the list takes `filter[stale]` and `sort` `days_in_stage` (fewest days first) or `-days_in_stage` (longest wait first).
  The company setting `recruitment.stale_after_days` (company admin, default 7, 1 to 60) is on the company settings page.
- **Other applications.** The detail has `other_applications` (the person's other applications to this company, newest first, at most 10, each with its job title and stage; withdrawn and rejected ones included) and
  `others_hidden` (those on jobs the member may not see: a member with `jobs.view_all` sees every job, anyone else the assigned ones). The list rows have `other_applications_count`: the active ones (not rejected or
  withdrawn) among the jobs the member may see. An anonymised applicant (no snapshot) shows none.
- **Task list.** `GET company/tasks` answers `{generated_at, items: [{key, count, filter}]}`, kept for 60 seconds per member. The Dashboard context composes it; every context registers its own items in
  `TaskProviders` (Shared, like the dashboard cards): Recruitment `stale_applications`, `my_scorecards`, `offers_waiting`, `offers_expiring`; Assessment `my_scoring`; Job `jobs_closing`, `jobs_draft`,
  `jobs_without_applicants`. An item the member lacks the permission for is left out; counts cover the jobs the member may see. `my_scorecards` counts the interviews where the member is the interviewer or a
  participant, the interview is confirmed or completed and has started, the application is not final, and their scorecard is not sent. `my_scoring` counts submitted tests whose result waits for scoring and
  names the member as an assessor (the assessor is assigned to the invitation, so the job scope does not apply). `offers_expiring` is the offers expiring within 3 days. `jobs_closing` is active jobs with a
  deadline from today to 7 days ahead; `jobs_without_applicants` is active jobs published 7 or more days ago.
- **Not coded: `unscheduled_interviews`** (`interview_state` of #10 exists since 2026-10-17; the item is not added yet).
- **Finding (reported to the user, 2026-10-17):** the lists the `filter` of an item points to do **not exist yet**: `company/applications` (the list across jobs, #19 applicant search), `company/interviews` and
  `company/assessment/invitations` have no list endpoint. The items still carry the filter text of this design; the front end links it once the list exists. On the `company/jobs` list the filters
  `filter[deadline_to]` (a day: deadline on or before it) and `filter[applications]=0` were added now, so the three job items already open a real list.


## Coded: section 5, the received email (2026-10-17)

With #10 section 6 (migration `000157`: `company_message_defaults`). New template `recruitment.application_received` (job, company, the Jakarta date, the company's `applied` message, the link to the application page from `JOB_SEEKER_APPLICATION_URL`, empty when unset), sent by the queued listener
`EmailApplicantOfReceiptListener` on `ApplicationSubmitted` by the preference `application_update`; no in-app notice, no attachment. It shows the company logo and the fixed anti-scam line like the interview, offer and test emails. Section 3 is not coded. The sender of offer emails was generalised
for it: `ApplicantEmailSender`, `MailApplicantSender`, `ApplicantMail` (they were the offer ones).

## Coded: section 3 part A, audiences and the daily digest (2026-10-17)

No migration. `NotificationPreferenceDefinition` has `audiences` (`job_seeker` by default; `company_member`, `psychologist`); `GET me/notification-preferences` lists only the caller's kind and each row carries `audiences`;
`PUT` of a key outside the caller's audience answers `unknown-notification-preference`. `approval_task` and `approval_update` are `company_member` (they were shown to job seekers by mistake). Key `team_daily_digest` (company member, email, on).

- **Digest.** `company:send-team-digests` (Dashboard context, 07:00 Jakarta, Monday to Saturday): for every active company, each member with the preference on, an active account and a membership in that company gets one queued email, template `team.daily_digest`
  (variables `name`, `company`, `date`, `summary`). Lines: new applications of the last 24 hours on the jobs **assigned** to the member, every task item with a count above zero (section 1, same use case), and the member's own interviews of the day
  (calendar with `mine`, from `applications.view` only). Nothing is sent when no line is left. A member whose digest fails with an access or rule error is skipped. Suspended members are left out by the preference port.
- **Links.** New `.env` value `COMPANY_APP_URL` (`app.company_app_url`); a line ends with `: <url>/<path>` where the path is the task item's `filter` (or `company/applications/{id}` for an interview). Empty: no links. Variable values are Markdown-escaped, so a link is plain text, not a Markdown link.
  The lists those paths name do not exist yet (finding above), so the front end maps them.
- **Opt-out deferred (decided by the user, 2026-10-17):** the one-click opt-out is not coded now.
  (Original note:) the one-click opt-out. The design says the team emails share "the one-click opt-out of the existing emails", but no existing email has one (only the newsletter unsubscribes); it needs a signed-token endpoint and is **waiting for the user's decision**.

## Coded: section 3 part B, the event emails (2026-10-17)

No migration. Preferences (company member, email): `team_interview_changes` (on), `team_offer_answers` (on), `team_new_applications` (off), `team_scoring` (off). Templates `team.interview_changed`, `team.offer_answered`, `team.application_received` (Recruitment) and `team.test_submitted` (Assessment).
All hold the job title and, when `COMPANY_APPLICATION_URL` is set, a link to the application, and **never the candidate's name** (the safe reading of "only where the member may see the application"); the existing withdrawal email keeps the name.

- **Interview answers** (confirmed, proposed another time, declined): to the interviewer and the member who scheduled it. The email of a withdrawal that cancels an interview (`recruitment.interview_cancelled_team`) now follows `team_interview_changes` too.
- **Offer answers:** to the member who sent the offer. **New application:** to the job's notification recipients, without hiring managers who do not see it yet. **Test submitted:** to the assessors only (not the sender), `team_scoring`.
- A suspended member (preference port) and an inactive account get none. Sent by queued listeners (`EmailTeamOfEventsListener`, `NotifyOfAttemptSubmissionListener::email`) after the change; a failure never undoes it.
- **Not coded:** `provider_reviews` (waits for #23, provider tools), `team_scorecard_reminders` (#11), the one-click opt-out (deferred).
