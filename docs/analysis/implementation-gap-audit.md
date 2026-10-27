# Implementation gap audit: API against the Workspace screens, per actor (2026-10-27)

`actor-gap-analysis.md` and `frontend-gap-analysis.md` (2026-10-19) compare the **designs** with what an actor needs. This audit compares the **code**: for each actor, which
things the API already does that no Workspace screen lets them do. It starts with the company admin, the recruiter, the hiring manager, the interviewer, the job seeker, the group admin with the approver, the psychologist with the institution lead, and the platform admin; the other actors follow in later rounds.

**Method (static, nothing was tried in a browser).** Every operation of `/company/*` and `/organization/*` in `packages/api/src/generated/schema.d.ts` was matched against
the calls in `apps/workspace/app` and `packages/ui/src` (a call counts when its path is written out as in `api.GET('/company/...')`; a call built at run time can be missed).
The `/me/*` account operations were matched the same way. Then the request bodies and the list filters of the operations that are used were compared field by field with
the forms and filter bars. Findings are an observation of the code on 2026-10-27, not a decision: each is **a gap to schedule**, or **waits** for something named.

Status: **Gap** = API coded, no screen; **Partial** = a screen exists but leaves out something the API takes; **Waits** = the API or the infrastructure is not ready;
**Deliberate** = left out on purpose.

## 1. Company admin

| # | What | API | Status | Effect |
|---|---|---|---|---|
| A1 | Screening questions of a job | `GET`/`PUT company/jobs/{id}/screening-questions` | **Done 2026-10-27** (tab Screening of the job: `JobScreeningTab`, `JobScreeningDialog`) | The bank (C18) can be filled, but no job can use it: applicants never get screening questions. |
| A2 | Ask colleagues to review candidates | `GET`/`POST company/jobs/{id}/review-requests` | **Done 2026-10-27** ("Minta review" in the selection of the pipeline) | The inbox of the reviewers exists; nobody can send the request. |
| A3 | Notification preferences (daily digest, team emails) | `GET`/`PUT me/notification-preferences[/{key}/{channel}]` | **Done 2026-10-27** (tab Notifikasi of the account page) | No member can switch the digest or the team emails off. |
| A4 | Default test per assessment stage, with the advance rule (#22) | `company/jobs/{id}/assessments` (`GET`, `PUT`, `DELETE`) | **Done 2026-10-27** (tab Stages: `JobStagesTab`, `JobDefaultDialog`) | The automation of sending a test on entering a stage cannot be set up. |
| A5 | Interview kit per stage of a job | `company/jobs/{id}/interview-kits` | **Done 2026-10-27** (tab Stages) | A kit is chosen only when scheduling, never fixed per stage. |
| A6 | Stage boundary of a hiring manager | `PUT company/jobs/{id}/hiring-manager-boundary` | **Done 2026-10-27** (the stage choice in `JobTeamDialog`) | A hiring manager always sees applicants from the default stage. |
| A7 | Message many applicants | `POST company/jobs/{id}/applications/bulk-message` | Gap | The bulk actions are move and reject only. |
| A8 | Delete a draft job | `DELETE company/jobs/{id}` | **Done 2026-10-27** (action "Hapus draf" of the job page) | A wrong draft can only be closed or duplicated. |
| A9 | Summary of what the karir.pkp import carried | `GET company/import-summary`, `POST .../dismiss` | Gap | A migrated company admin sees nothing of the import; matters at go-live. |
| A10 | Preview of the email before it is sent | `POST company/applications/{id}/email-preview` | Gap | Interview, test and offer emails go out unseen. |
| A11 | Own account: photo, change email, delete account, export data | `me/avatar`, `me/email-change`, `me/deletion`, `me/data-export` | **Partly done 2026-10-27**: email change and account deletion are in the tab Akun. A photo has no place in the Workspace yet, and the data export is for job seekers only in the API (`job-seeker-only`): a company member has no export | The account page has information, password and security only; the erasure and export rights apply to company members too. |
| A12 | Announcement banner (decided for Workspace in `frontend.md` 11.1) | `GET announcements` | Gap | Only Career shows it. |
| A13 | Assign people to a job; assign a member to jobs | `PUT company/jobs/{id}/assignees`, `POST company/members/{id}/jobs` | **Done 2026-10-27** (`JobTeamDialog`, `TeamAssignJobsDialog`) | The job team dialog reads `GET company/members`, which only a company admin may read; a non-admin with `jobs.assign` gets an error (**fixed 2026-10-27**: `GET company/members/options`). |
| A14 | Interview recordings, calls, attendance, video and voice rooms | `company/interviews/{id}/...`, `.../interview/video|voice/...` | Waits | Step 6: Janus, coturn and Asterisk. |
| A15 | Assessment providers of the group (E16) | `organization/assessment-*` | Waits | API #24 section 8 is not coded. |

API-side gaps seen from the company admin: no report of work per member (the member activity log and the analytics "fill" report per assignee are the nearest); the team
list shows no jobs per member; no way to list the jobs without an assignee; no member picker for a non-admin who holds `jobs.assign` (**done 2026-10-27**, `GET company/members/options`); the team list now shows the jobs per member (`assigned_job_count`, done 2026-10-27); a job always keeps at least one assignee, so there are no jobs without one to list.

## 2. Recruiter

The Recruiter preset holds `jobs.create`, `jobs.publish`, `applications.view|manage|review|export`, `interviews.manage|participate`, `offers.manage`, `assessments.send`,
`candidates.search`, `talent_pool.manage`, `messages.send` and `interview_kits.manage`. A recruiter sees the jobs they are assigned to (the creator of a job is assigned
at once).

| # | What | API | Status | Effect for a recruiter |
|---|---|---|---|---|
| R1 | Screening questions of a job (A1) | `company/jobs/{id}/screening-questions` | **Done 2026-10-27** (A1) | They create jobs (`jobs.create`) but cannot attach questions to them. |
| R2 | Ask colleagues to review (A2) | `POST company/jobs/{id}/review-requests` (`reviewer_ids`) | **Done 2026-10-27** (A2) | The shortlist step with a hiring manager cannot start from the Workspace. |
| R3 | Message many applicants (A7) | `POST company/jobs/{id}/applications/bulk-message` | Gap | A rejection or an invitation to many is one message at a time. |
| R4 | Email preview (A10) | `POST company/applications/{id}/email-preview` | Gap | Interview, test and offer emails go out unseen. |
| R5 | Interview kit and default test per stage of a job (A4, A5) | `company/jobs/{id}/interview-kits`, `.../assessments` (`due_days`) | **Done 2026-10-27** (A4, A5) | The job editor ends at the content; the stage setup of a job is left to the scheduling dialog. |
| R6 | Delete a draft job (A8) | `DELETE company/jobs/{id}` | **Done 2026-10-27** (A8) | |
| R7 | Applicant list filters | `filter[education_min]`, `filter[experience_min]`, `filter[experience_max]`, `filter[stage_type]` | Partial | The API filters by education level, experience range and stage type; the filter bar offers none of them. |
| R8 | Job list filters | `filter[published_from]`, `filter[published_to]`, `filter[deadline_from]`, `filter[created_by]` | Partial | No date range and no "created by" in the job list. |
| R9 | Invite a person of the talent pool to one job | `CreateInvitationRequest.talent_pool_entry_id` | Partial | The bulk dialog is used; a single invitation from an entry is not. |
| R10 | Notification preferences (A3) | `me/notification-preferences` | **Done 2026-10-27** (A3) | A recruiter cannot choose which of the digest and event emails they receive. |
| R11 | Own account (A11) | `me/*` | **Partly done 2026-10-27** (A11) | |
| R12 | Candidate search by age, religion, nationality | `filter[age_min]`, `filter[age_max]`, `filter[religion_id]`, `filter[nationality_id]` | Deliberate | Sensitive filters stay off until the legal review (`docs/legal-review.md`). |
| R13 | Undo a rejection; reject the rest when a job closes | (none) | Waits | Designed in #14 and #15, no endpoint yet. |
| R14 | Add a candidate by hand (walk-in, referral) | (none) | Waits | Designed in #31. |
| R15 | Reference check, background check, MCU as steps | (none) | Waits | Designed in #32. |
| R16 | Video and voice rooms, recordings | `.../interview/video|voice/...` | Waits | Step 6 (Janus, coturn, Asterisk). |

What the recruiter does have end to end in the Workspace: jobs (create, edit, preview, publish, close, reopen, fill, clone), the pipeline as a table and a board with
filters, bulk move and reject, notes, ratings, export, interviews (schedule with a panel, confirm, propose a time, no-show, complete, calendar and `.ics`), assessments
(send, results, scoring), offers (make, revise, withdraw, hire), candidate search and invitations, the talent pool, conversations, the review inbox, the calendar and the
task cards.

Order of work suggested for A and R together: (1) the job editor steps (A1, A4, A5, A6, A8 = R1, R5, R6); (2) review requests and bulk message (A2, A7 = R2, R3);
(3) notification preferences and the account page (A3, A11 = R10, R11); (4) the import summary before go-live (A9); (5) the email preview (A10 = R4) and the missing list
filters (R7, R8).

## 3. Hiring manager

The Hiring manager preset holds `applications.view`, `applications.review`, `interviews.manage` and `interviews.participate`. The role is set per job (`job_role`), and on such
a job the member sees applicants only from the stage boundary on. The menu shows Wawancara saya, Review kandidat, Penilaian tes saya, Lowongan, Semua pelamar and
the calendar; the requisition and plan pages need the `workforce.*` permissions, and the approval pages appear when an approval waits.

| # | What | API / where | Status | Effect for a hiring manager |
|---|---|---|---|---|
| H1 | Requests to review reach them | `POST company/jobs/{id}/review-requests` (A2, R2) | **Done 2026-10-27** (A2) | The review inbox and the answer page exist (Lanjut, Tidak, Mungkin, close), but no recruiter can send a request from the Workspace, so the inbox stays empty. |
| H2 | Stage boundary | `PUT company/jobs/{id}/hiring-manager-boundary` (A6) | Gap | The boundary cannot be set, and the pipeline does not say that it hides the earlier stages (only the count of other applications by recruiters is explained). |
| H3 | **"Approvals waiting" on the dashboard** | task key `approvals_waiting` (`company/approvals?as=approver`) | **Fixed 2026-10-27** (see G1) | The task list of the dashboard has no text for the key (it would show `talent.dashboard.tasks.approvals_waiting`) and `utils/link-entry.ts` has no link for it, so the row has a count and opens nothing. It hits every approver, not only a hiring manager. The coverage test lists the keys by hand, so a key added after 2026-10-19 slipped through. |
| H4 | Create and track requisitions | `company/requisitions` (E5) | **Done 2026-10-27** (preset) | The design lets a hiring manager create, submit, amend and track a requisition, but the Hiring manager preset has no `workforce.requisitions.view` or `.create`: the menu entry is hidden until a company admin ticks them one by one. The permission is decided by the user; see the point below. |
| H5 | Plan of the unit | `company/workforce/plans` (E4) | **Done 2026-10-27** (preset) | `workforce.plans.view` is now in the preset. |
| H6 | Hires and join dates of a requisition | (none) | Waits | API gap FG13: the requisition links to its jobs but there is no rollup of hires. |
| H7 | Assessment summary on the review card | (none) | Waits | Designed in #23, not coded. |
| H8 | Notification preferences (A3) | `me/notification-preferences` | **Done 2026-10-27** (A3) | Reminders of reviews and interviews cannot be tuned. |
| H9 | Own account (A11) | `me/*` | **Partly done 2026-10-27** (A11) | |

Done and checked in the code: the interview scorecard and the results comparison of a job (`interview-results`), the answer page of a review with its decision, the
read-only board (no moves without `applications.manage`), the tabs of an application gated by `applications.review`, and the approval inbox (approve, reject, return,
replace, delegate).

Points for the user:

1. **Presets and requisitions (H4, H5).** Should the Hiring manager preset include `workforce.requisitions.view` and `workforce.requisitions.create` (and `workforce.plans.view`
   for the plan of their unit)? The design of the requisition names the hiring manager as the person who creates it. Changing the preset touches `MemberPreset` and the invitation
   dialog only; existing members keep what they hold.
2. **H3 is a small fix** on the front end (a label and a link entry for `approvals_waiting`) and a test that reads the keys from the API instead of a hand-written list.

## 4. Interviewer

The Interviewer preset holds `interviews.participate` alone. The member reads the application of an interview they sit on (reduced: no ratings, no other applications, the
résumé and the questions included), answers the invitation, fills the scorecard and sees the scorecards of the others after sending their own (the lock rule). The menu
shows Wawancara saya, Penilaian tes saya (only when named an assessor) and Kalender.

| # | What | Where | Status | Effect for an interviewer |
|---|---|---|---|---|
| I1 | **Calendar** | menu entry `calendar` allows `interviews.participate`; `GET company/calendar` (`GetCompanyCalendar`) and the page `/kalender` needed `applications.view` | **Fixed 2026-10-27** (option a: the API and the page admit a panel seat, with the interviews they sit on only) | The entry shows in the menu and opens a refusal. `frontend.md` C26 says "`applications.view` or a panel seat", so the API and the page do not follow the design. |
| I2 | Answer the invitation, scorecard, questions of the kit, `.ics` | `company/interviews/{id}/rsvp`, `.../scorecard`, `.../scorecards`, `.../questions`, `.../calendar.ics` | Done | All used by `/wawancara` and `/wawancara/{id}`. |
| I3 | Join the video or voice room | `company/applications/{id}/interview/video|voice/...` | Waits | Step 6: Janus, coturn, Asterisk. Until then an interview of this format has only the link the scheduler typed. |
| I4 | Notification preferences (A3) | `me/notification-preferences` | **Done 2026-10-27** (A3) | The reminder and the change emails of an interview cannot be tuned. |
| I5 | Own account (A11) | `me/*` | **Partly done 2026-10-27** (A11) | |
| I6 | Briefing page for the interviewer; telling them the outcome | (none) | Deliberate | Not taken (`phase-8-interviewers.md`). |
| I7 | Offer another time instead of declining | (none) | Deliberate | Not taken (interviewer availability, G12): the interviewer declines with a note and the scheduler gets the task `panel_declined`. |

Points for the user:

1. **I1.** Which way: (a) the API lets a panel member read the calendar and the page follows, with only the interviews they sit on (the design of C26), or (b) the entry
   leaves the interviewer's menu and "Wawancara saya" stays the one place. (a) is a change in `GetCompanyCalendar` and its tests, and the page access; (b) is one line in `utils/menu.ts`.

## 5. Job seeker (Career, `apps/public`)

Career is built in steps (`frontend.md` section 13, step 4 and 5), so most of what follows is known as "left". This round checks it **against the Workspace that already
exists**: a gap counts as a **broken chain** when a company member can already do something in the Workspace that the job seeker cannot receive or answer in Career. The
same method: every public and `/me/*` operation of `schema.d.ts` against `apps/public/app`, `apps/public/server` and `packages/ui/src` (93 operations are not called there).

Built: home, job search and detail (apply, save, share, report, similar jobs), company profile and its reviews, about, legal documents and the terms gate, sign in, register,
Google, password reset, email verification, the blocked-account page, the dashboard, the profile (basics, experiences, educations, skills, languages, links, photo),
resumes and the CV generator, the application list and detail (stage history, withdraw, talent pool consent, interview confirm, decline, propose, recording consent
withdrawal, offer with the PDF letter, accept, decline).

The menu already lists ten more pages; each opens "Segera hadir" (`pages/me/[...slug].vue`): saved jobs, job alerts, invitations, agenda, assessments, messages, notifications,
reviews, training, settings.

| # | What | API | Broken chain with | Status |
|---|---|---|---|---|
| K1 | **Tests** (J7): see an invitation, start, answer, draw, record a video answer, submit, read the explanation | `me/assessment/invitations/...` (17 operations) | The Workspace sends tests (stage and manual send) and psychologists sign reports for them | Gap (step 5) |
| K2 | **Messages** (J11): read, reply, attachments, block, report | `me/conversations`, `me/messages` | The Workspace sends and reads messages (C14) | Gap (step 5) |
| K3 | **Invitations to apply** (J8) | `me/invitations[/{id}]`, `.../decline` | The Workspace invites candidates one by one and in bulk | Gap (step 5) |
| K4 | **Notifications** (J12): the list, read, read all, delete, unread count, preferences | `me/notifications`, `me/notification-preferences` | Every in-app notice and email of the platform; the menu shows a badge that opens nothing | Gap (step 5) |
| K5 | **Campaign landing** (P7) | `GET campaigns/{code}` | Campaign links and QR codes of the Workspace (`CAMPAIGN_REGISTRATION_URL`) lead to a page that does not exist | Gap (step 5) |
| K6 | **Newsletter** confirm and unsubscribe (P10) | `POST newsletter/confirm`, `.../unsubscribe[/{token}]` | The emails carry these links; an unsubscribe link that does not work is a compliance problem | Gap (step 5) |
| K7 | **Settings** (J15): password, email change, two-factor and recovery codes, data export, account deletion, the summary of the import, leaving every talent pool | `me/password`, `me/email-change`, `me/two-factor/...`, `me/data-export`, `me/deletion`, `me/import-summary`, `DELETE me/talent-pool-memberships` | The rights of access and erasure (UU PDP); a job seeker cannot change a password or set two-factor today | Gap (step 5) |
| K8 | **Saved jobs** (J9) list | `GET me/saved-jobs` | The save button works, the list does not exist | Gap |
| K9 | **Job alerts** (J10) | `me/job-alerts[/{id}]`, `.../pause`, `.../resume`, `.../jobs` | The platform sends alert emails the job seeker cannot manage | Gap |
| K10 | **My reviews** (J13) | `me/reviews/{id}` | A review written on a company page cannot be edited or deleted | Gap |
| K11 | Salary insight (P5), training (P6, J14), FAQ (P9) | `salary-insight`, `training/courses`, `me/training/courses`, `faq` | Job detail links to the salary insight | Gap (step 5) |
| K12 | Who viewed my profile on the dashboard | `GET me/profile/views` | Companies' profile reads are recorded | Gap |
| K13 | Video interview room (J6) | `me/applications/{id}/interview/video/lobby`, `.../join` | | Waits (step 6) |
| K14 | Agenda and "Perlu tindakan" (J16, J1) | `me/agenda`, `me/tasks` | | Waits: no endpoint yet (#15) |
| K15 | Open an invitation from a company by email without an account, and claim it (P15) | `email-invitations/{token}` | | Waits: #31 |

The order that follows the broken chains: K2 and K3 (the company already talks to the job seeker), then K1 (tests), K4, K6 and K7 (compliance), K5 (campaigns), and the rest.
No code of this round touches Career; the Workspace items of sections 1 to 4 do not depend on it.

## 6. Group admin and approver (Workspace, internal tenants)

This is the part of the Workspace built most recently (steps 5 and 7, E1 to E15), and the check shows it: every operation of `/organization/*`, of the approvals
(`company/approvals`, `approval-flows`, `approval-delegations`) and of the workforce plans and requisitions is called by a screen, and the request bodies and the list filters
of those screens leave out almost nothing. Group structure (legal entities, units, positions with versions, grades, cost centres), group members and roles, settings, activity
log, support access, approval flows with the diagram editor and the tester, the approvals inbox with approve, reject, return and replace, delegations, plans as a grid,
requisitions with amendments and jobs, and the reads across tenants are all there.

| # | What | Where | Status | Effect |
|---|---|---|---|---|
| G1 | **"Approvals waiting" on the dashboard** (H3) | task `approvals_waiting`, `utils/link-entry.ts`, `talent.dashboard.tasks.*` | **Fixed 2026-10-27** (label in both languages, link to `/persetujuan`, coverage test of labels) | The row shows the raw key and opens nothing; hits every approver and delegate. The menu badge of the inbox does work. |
| G2 | Filters of the read-only screens across tenants | `organization/read/jobs`: `filter[published_from|to]`, `filter[deadline_from]`, `filter[created_by]`; `organization/read/jobs/{id}/applications`: `filter[education_min]`, `filter[experience_min|max]`, `filter[stage_type]` | Partial | Same as R7 and R8: the API filters, the filter bars do not offer them. |
| G3 | Notification preferences (A3) | `me/notification-preferences` | **Done 2026-10-27** (A3) | An approver cannot tune the approval reminders. |
| G4 | Own account (A11) | `me/*` | **Partly done 2026-10-27** (A11) | |
| G5 | Subjects an approval flow can govern | `workforce.plan`, `workforce.requisition`, `workforce.requisition_amendment` only | Waits | An offer or a hire cannot be sent through an approval for an internal tenant (#12, "approval needs a design"). |
| G6 | Assessment providers of the group (E16) | `organization/assessment-*` | Waits | API #24 section 8. |
| G7 | Approve from an email or a notification | (none) | Waits | FG15, P3: the approver decides inside the Workspace only. |
| G8 | Group talent pool | (none) | Waits | FG14, P2: talent pools are per tenant. |
| G9 | Hand-off of a hire to the Employee record | (none) | Waits | #13, needs a design session. |
| G10 | Hires and join dates per requisition (H6) | (none) | Waits | FG13. |

Nothing of this round blocks an existing flow except G1 (a count with no label). G1 is one label, one link-entry row and a coverage test that reads the keys from the API.

## 7. Psychologist and institution lead (Portal Mitra)

**No screen exists.** `apps/console/src` has the dashboard, companies, jobs, the portal brand and the account pages; Portal Mitra (`/mitra`, M1 to M8 of `frontend.md` 9.2) is not started, and
none of the 40 `/provider/*` operations of `schema.d.ts` is called anywhere. The API behind M1 to M6 is coded (login with two-factor, invitation, tests with sections and
questions, norms, report and explanation templates, auto issue, the review queue, the draft, drawings, edit, sign, mark reviewed, revise), and so are the claim, release, candidate
context and norm groups added on 2026-10-27 (`phase-8-provider-tools.md`, sections 1 to 3; `schema.d.ts` does not know them until `pnpm api:refresh`).

| # | What | Status | Effect |
|---|---|---|---|
| S1 | **The whole portal** (M1 to M6: invitation, sign in, test editor, norms and templates, review queue and report, account) | Gap (step 7) | A psychologist can do nothing in a browser. The invitation email carries `PSYCHOLOGIST_INVITATION_URL` (`console.satu.solutions/mitra/undangan/{token}`), a page that does not exist, so an invited psychologist cannot even accept. |
| S2 | The platform admin's side: register a provider, invite and manage its psychologists, grant batteries, library | Gap (Console, see the platform admin round) | Without it nothing creates the providers the portal would serve. |
| S3 | Account security of a psychologist | Gap in the API | No password change and no recovery-code endpoint for the psychologist account (FEG13): M6 would show none until one is designed. |
| S4 | Report register (M7), institution profile and team (M8), licences and signature (M6 additions) | Waits | #23 section 8 and #24 are designed, not coded. |
| S5 | Recommendation on a report (**API coded 2026-10-27**, standard-score rule), notifications for psychologists, example report, public verification of a report (P14) | Waits | #23 sections 5 to 7 are not coded; the recommendation has no screen until Portal Mitra. |
| S6 | Self-management of the team by an institution lead, licence renewals with the document, institution identity and signature on the report, revising a signed report, review targets, questions about a report | Waits | #24 sections 1 to 7. |
| S7 | In-house psychologists of a group (private providers, E16) | Waits | #24 section 8, after the rest of #24. |

What a company sees of all this is unaffected: the Workspace already sends tests, shows that a report awaits the psychologist, and downloads the signed report, but a
psychological report is never signed because nobody can reach the review queue from a screen. That is acceptable until the first partner institution (`phase-8-provider-tools.md`:
"needed before the first partner institution, not for the go-live"); the audit records it so that Portal Mitra is scheduled with #23 and #24, in this order: M1 to M6, the Console
provider pages (S2), then M7, M8 and E16 as their APIs land.

## 8. Platform admin (Console, `apps/console`)

Built: sign in with two-factor (D1), the dashboard with the operations and activation cards (D2), companies (list, detail, status, owner, invitation; D4), verification
documents (D5), job moderation (list, detail, approve, reject, take down, restore; D7), the account (D20) and the brand of the Career site (`admin/portal`, not in the page map).
**194 of the 226 `/admin/*` operations of `schema.d.ts` are not called by any screen.** Console step 5 (`frontend.md` 13: D3, D6, D8 to D17, D21, D23 to D26) is not started.

The gaps that **break a chain**, because someone else already does their part in the Workspace or Career and the item waits for an admin:

| # | What | API (unused) | Waits for the admin | Status |
|---|---|---|---|---|
| P1 | Review moderation (D8): reviews, replies, reports of reviews | `admin/reviews`, `admin/review-replies`, `admin/review-reports` (11) | Reviews that job seekers write and companies answer | Gap (step 5) |
| P2 | Reports of jobs and of messages (D23); the reports of one job | `admin/job-reports`, `admin/jobs/{id}/reports[/dismiss]`, `admin/message-reports[/{id}]`, `.../dismiss`, `.../action` | A job on a report hold ("Sedang ditinjau admin") takes no application until decided; a reported message waits | Gap (step 5); the job side of D7 is built, its reports are not |
| P3 | Company name changes (D24) | `admin/company-name-changes` (5) | A company admin asks for a new name in the Workspace | Gap (step 5) |
| P4 | Company setting overrides (D6) | `admin/companies/{id}/settings[/{key}]` | Features gated by a company setting, for example `assessment.psychological_enabled` | Gap (step 5) |
| P5 | Settings of the platform (D16) and the audit log (D17) | `admin/settings` (3), `admin/audit-logs` | Every setting of the go-live list (`docs/deployment-checklist.md`) is set by the API or a command today | Gap (step 5) |
| P6 | Legal documents (D12) | `admin/legal-documents` (6) | The terms gate of both applications reads the published versions | Gap (step 5) |
| P7 | Groups and tenants (D27); support reads (D28) | `admin/groups` (10), `admin/support` (12) | An ERP group cannot be created or linked to a tenant from a screen (step 7) | Gap (step 7) |
| P8 | **Assessment library and psychologist providers** (D18, D19) | `admin/assessment/*` (37) | Providers, psychologists, batteries and grants that Portal Mitra and the company catalogue depend on | Gap, **in no coding step**: D18 and D19 are in the page map (`frontend.md` 9.1) but `13` lists neither in a step; schedule them with Portal Mitra (S1, S2) |
| P9 | Content and marketing: announcements (D26), newsletter (D10), FAQ (D11), campaigns (D9), email templates (D13), CV templates (D14), undelivered email (D25) | the matching `admin/*` operations | The announcement banner, the newsletter links, the FAQ page and the emails read what is created here | Gap (step 5) |
| P10 | Reference data (D15): skills, levels, categories, masters | `admin/skills`, `admin/job-*`, `admin/education-levels` and the rest (3 each) | The pickers of every form read the masters | Gap (step 5) |
| P11 | Users (D21), analytics (D3), the activation report | `admin/users` (7), `admin/analytics/{report}`, `admin/activation/report` | Support and the go-live activation plan | Gap (step 5) |

The admin's own tools exist in code (artisan commands, the API) for all of these, which is why the go-live list can be followed today without the screens; the screens are what
makes the platform operable by someone who is not the developer.

## 9. Order of work across the actors

1. **Workspace, small and safe:** G1/H3 (approvals task label and link), I1 (calendar for the interviewer), then the job editor steps and the review request (A1 to A8, R1 to R6).
2. **Career chains** (K2, K3, K1, K4, K6, K7, K5): the company already talks to the job seeker.
3. **Console step 5**, in the order of the waiting chains: P3 (name changes), P2 (reports), P1 (reviews), P4 and P5 (settings), P6 (legal documents), then the content pages (P9, P10, P11).
4. **Portal Mitra with #23 and #24** (S1, S2 and P8 together, then M7, M8, E16).
5. Notification preferences and the account pages (A3, A11) in every application, once.

