# Phase 8: platform admin: job reports, approval for unverified companies, name lock, two-factor reset, chat reports, email suppression, announcements and scheduler watch (designed 2026-10-15, not coded)

Gaps found from the platform admin's point of view, against Mekari Talenta (help center articles 9518591606041: in-app support chat;
9324824732953: targeted announcements; 61590778970649: queue activity; 9096990286233: two-factor on promotion to admin) and against the job portals
of `docs/analysis/gap-analysis-round-2.md` (G1, G2, G6, G7, G20), chosen by the user from the advice of 2026-10-15. Talenta is a closed B2B
product without a public job marketplace, so the trust and safety pieces follow the job portals. Not taken now: an in-app help desk (an outside
help desk linked from the FAQ instead), a register of data subject requests and incidents (a written procedure instead), appeals (G8), avatar
moderation (G19), queue progress for users. Extends `phase-1-jobs.md` (moderation), `phase-1-company.md` (profile, verification),
`phase-2-chat.md`, `phase-8-company-admin.md` (two-factor), `phase-8-company-registrations.md`, `phase-4-dashboards.md` and the email templates.
Sections 1 to 4 are **before go-live**; section 6 is before the reset emails to the imported accounts; sections 5, 7 and 8 after.

**Read from the code (2026-10-15):** `jobs.require_approval` is off by default and publishing ignores the verification badge
(`PublishJob`, `ReopenJob`, `UpdateJob`); `CompanyVerificationBadge` looks at documents only, so a verified company may rename itself
(`PATCH company/profile` `name`); a job seeker cannot report a job or a message, nor block a thread (only the company closes one); emails are sent
by many senders through Laravel Mail with no bounce handling; the operations card shows failed and waiting queue jobs only; the console resets
admins' two-factor only (`identity:reset-admin-two-factor`).

## Decisions

- 2026-10-15: **signed-in job seekers report jobs**; enough distinct reporters put the job on a **hold column** (`job_jobs.report_hold_at`)
  until an admin decides; the reporter gets a short in-app notice when the report is reviewed (decided by the user).
- 2026-10-15: reports are stored **per context** (`job_reports`, `messaging_reports`) (schema decided by the user).
- 2026-10-15: jobs of an **unverified company always go to review**; **suspicious phrases**, an admin setting, flag a job and send it to review.
- 2026-10-15: a verified company's **name is locked**; a change goes through a **request in the app** with a document, decided by an admin
  (schema decided by the user).
- 2026-10-15: a console command resets a company member's two-factor, coded with `phase-8-company-admin.md` section 1.
- 2026-10-15: **chat reports** by both sides and a **block** by the job seeker.
- 2026-10-15: an **email suppression list** fed by hard bounces and complaints.
- 2026-10-15: **platform announcements** shown as a banner.
- 2026-10-15: a **scheduler watch** on the operations card with an email to an operations address (chosen by the user).

## 1. Job reports (before go-live)

- **Table** `job_reports`: `job_id` (FK cascade), `reporter_user_id` (plain column), `reason` (`fee_requested`, `fake_or_misleading`,
  `discriminatory`, `spam_or_duplicate`, `inappropriate`, `false_accessibility_claim` (`phase-8-job-discovery.md`), `other`; CHECK), `note` (≤1,000, required for `other`), `status` (`open`, `dismissed`,
  `actioned`), `decided_by_admin_id`, `decided_at`, timestamps. One `open` report per reporter and job (partial unique index).
- **Report:** `POST jobs/{id}/reports {reason, note?}` (job seekers with a verified email; the job `active`; 201; 409 `already-reported`;
  10 per hour). The public job page shows "Laporkan lowongan".
- **Hold:** when the open reports of a job come from at least `jobs.report_hold_threshold` distinct reporters (admin setting, default **3**, 0
  turns holds off), `job_jobs.report_hold_at` is set (once until released). A held job leaves the public list, the detail (404 as a job that is not
  live), alerts, recommendations and the company page; applying answers 409 `job-on-hold`; existing applications go on. The company sees
  "Sedang ditinjau admin" (`report_hold_at` on its job) and gets in-app `job.held`, never who reported or why.
- **Admin** (`jobs.manage`): `GET admin/job-reports` (per job: open count, reasons, first and last report, held or not; held first, then by count),
  `GET admin/jobs/{id}/reports`; `POST admin/jobs/{id}/reports/dismiss {note?}` closes the open reports as `dismissed` and clears the hold; the
  existing take-down closes them as `actioned` (the hold is cleared, the job is `taken_down`). Audit `job.reports_dismissed`, `job.hold_released`.
- **Reporter:** in-app `report.reviewed` when the report is closed, without the outcome.
- **Dashboard:** the admin queue card gains `job_reports` (jobs with open reports, held ones). The account erasure clears the reporter and the note.

## 2. Review for unverified companies and suspicious phrases (before go-live)

- **Internal tenants (decided 2026-10-15, `erp-governance.md` section 1.2):** a company linked to a legal entity counts as verified (the public card's
  `verified` is "verified or linked") and none of the triggers below applies to its jobs. The link column arrives with `erp-organization.md`; until
  it is coded every company is external and the rule below is unchanged.

- **Rule:** a publish, reopen or a content change of a live job needs review when `jobs.require_approval` is on for the company, **or** the
  company is not verified (read through the existing public company card, `verified`) and `jobs.approval_for_unverified` is on (admin setting,
  default **on**), **or** the job matches a suspicious phrase. With no active required document type nobody is verified, so every job is reviewed
  until the types exist; the moderation guide says so.
- **Phrases:** admin setting `jobs.suspicious_phrases` (text, one phrase per line, matched case-insensitively on word boundaries in the title,
  summary, description and salary note). Default: biaya pendaftaran, biaya administrasi, biaya pelatihan, biaya seragam, uang jaminan, deposit,
  transfer ke rekening, hanya via whatsapp. A phone number or an email address in the description is a flag too.
- **Moderation view:** `GET admin/jobs[/{id}]` gains `flags [{kind: phrase|contact, value, field}]` and `company_verified`; the pending queue lists
  flagged jobs first. The company sees only "Menunggu persetujuan admin", as today.

## 3. Name lock and name change requests (before go-live, G20)

- **Lock:** `PATCH company/profile` with a changed `name` answers 409 `name-locked` while the company is verified. Unverified companies change it
  freely; the slug stays changeable.
- **Table** `company_name_change_requests`: `company_id` (FK cascade), `requested_by_user_id`, `current_name`, `new_name`, `reason` (≤1,000),
  `document_path` (PDF checked by content, at most 10 MB, private disk), `status` (`pending`, `approved`, `rejected`, `cancelled`),
  `decided_by_admin_id`, `decided_at`, `decision_note`, timestamps. One `pending` per company (partial unique index).
- **Company** (`company.profile`): `POST company/name-change-requests` (multipart; 409 `name-change-pending`), `GET company/name-change-requests`,
  `POST .../{id}/cancel`.
- **Admin** (`companies.manage`): `GET admin/company-name-changes` (pending first), `GET .../{id}`, `GET .../{id}/document`, `POST .../{id}/approve`
  (the name changes, the badge stays) and `POST .../{id}/reject {note}`. In-app notice to the company admins either way; audit
  `company.name_change_approved` and `_rejected`. A queue card on the admin dashboard. Retention: the document is deleted 90 days after the decision.

## 4. Two-factor reset for company members (coded with `phase-8-company-admin.md` section 1)

- Console `identity:reset-user-two-factor {email}`: for a job seeker or company member account with a factor, removes the factor and recovery codes,
  revokes the tokens and writes audit `auth.two_factor_reset` (actor `console`); asks for confirmation. The panel endpoint stays in
  `phase-8-user-management.md`.

## 5. Chat reports and block

- **Table** `messaging_reports`: `conversation_id` (FK cascade), `message_id` (nullable), `reporter_side` (`job_seeker`, `company`),
  `reporter_user_id`, `reason` (`scam_or_fee`, `harassment`, `spam`, `inappropriate`, `other`), `note` (≤1,000), `snapshot` (JSON: the reported
  message and up to 10 earlier messages of the thread, text, sender side and time, attachment names only), `status` (`open`, `dismissed`,
  `actioned`), `decided_by_admin_id`, `decided_at`, `decision_note`, timestamps.
- **Report:** `POST me/conversations/{id}/report` and `POST company/conversations/{id}/report` `{reason, note?, message_id?}` (a party of the
  thread; 10 per hour; 409 `already-reported` while one of theirs is open). The job seeker's dialog offers "Blokir juga".
- **Block:** `POST`/`DELETE me/conversations/{id}/block` sets `messaging_conversations.blocked_by_seeker_at`; the company cannot send (409
  `conversation-blocked`, bulk messages skip with that code) and sees "Kandidat menutup percakapan"; the job seeker can still read and unblock.
- **Admin:** new permission `messages.moderate`; `GET admin/message-reports` (`filter[status]`), `GET .../{id}` (the snapshot, never the live
  thread), `POST .../{id}/dismiss {note?}`, `POST .../{id}/action {note}` (records the decision; suspending the account or the company uses the
  existing actions). Reporter notice `report.reviewed`. Snapshot cleared 180 days after the decision and by the account erasure.
- `docs/legal-review.md` question 16: admins read reported message excerpts.

## 6. Email suppression (before the reset emails to the imported accounts)

- **Table** `platform_email_suppressions`: `email` (lower case, unique), `reason` (`hard_bounce`, `complaint`, `manual`), `source`, `details`
  (≤1,000), `created_at`.
- **Intake:** `POST webhooks/email/{provider}` with the provider's signature check, through an `EmailEventParser` per provider, registered when the
  SMTP provider is chosen (none until then: 404). Hard bounces and complaints are stored; soft bounces are ignored.
- **Enforcement:** one listener on Laravel's `MessageSending` drops suppressed recipients and cancels a message left without any, unless the message
  carries the header `X-Essential: 1` (email verification code, password reset, two-factor required, account suspension). Newsletters too.
- **Admin** (`settings.manage`): `GET admin/email-suppressions` (`q`), `POST` (manual), `DELETE .../{id}`; audited. The admin user and member
  views show the flag.
- **Owner:** `GET me` gains `email_deliverable`; the front end asks the person to check or change the address. A changed address starts clean.
- The import's reset email run reports how many addresses are suppressed in its dry run.

## 7. Platform announcements

- **Table** `content_announcements`: `title` (≤120), `body` (≤500, plain text), `link_url` (https, nullable), `audience` (`all`, `job_seekers`,
  `company_members`), `level` (`info`, `important`), `starts_at`, `ends_at` (at most 90 days later), `created_by_admin_id`, timestamps.
- **Admin:** `admin/announcements` (CRUD) under a new permission `announcements.manage`; audited.
- **Read:** `GET announcements` answers the live ones for the caller (anonymous: `all`; job seekers and company members add their audience),
  cached 60 seconds per audience. Front end: a banner on the public site and the dashboards; `info` can be closed (remembered in the browser),
  `important` shows again on each visit. No email (the newsletter is for that).

## 8. Scheduler watch

- **Table** `platform_scheduled_task_runs`: `task` (unique, the task's `name()`), `expression`, `last_started_at`, `last_finished_at`,
  `last_failed_at`, `last_failure` (≤1,000), `consecutive_failures`, `alerted_at`. Every scheduled task gets a stable name (a test checks it).
  Filled by listeners on the scheduled task events.
- **Late:** no finish within two intervals of its expression plus 5 minutes. The operations card gains `scheduler {last_tick_at, tasks: [{task,
  last_finished_at, late, failing}]}`, late and failing first.
- **Alert:** `platform:check-scheduled-tasks` every 5 minutes emails `platform.operations_email` (admin setting, empty = no email) once when a task
  turns late or failing and once when it recovers (`alerted_at`).
- **Dead scheduler:** `GET health/scheduler` (no sign-in, no data) answers 200 when the last tick is within 5 minutes, else 503, for an outside uptime
  monitor; `docs/deployment-checklist.md` gains that monitor.

## Changes in short

| Piece | Change |
|---|---|
| Migration | `job_reports`; `job_jobs.report_hold_at`; `company_name_change_requests`; `messaging_reports`; `messaging_conversations.blocked_by_seeker_at`; `platform_email_suppressions`; `content_announcements`; `platform_scheduled_task_runs` |
| Settings | `jobs.report_hold_threshold` (3), `jobs.approval_for_unverified` (on), `jobs.suspicious_phrases`, `platform.operations_email` |
| Permissions (admin) | `messages.moderate`, `announcements.manage` |
| API | job reports and hold; `flags`, `company_verified` in moderation; name lock and name change requests; chat reports and block; email suppressions, webhook, `email_deliverable`; `GET announcements` and admin CRUD; scheduler on the operations card; `GET health/scheduler` |
| Console | `identity:reset-user-two-factor`; `platform:check-scheduled-tasks` |
| Notifications | `job.held`, `report.reviewed`, name change decided (in-app); scheduler alert (email) |
| Audit | `job.reports_dismissed`, `job.hold_released`, `company.name_change_approved`, `_rejected`, suppression and announcement changes, `auth.two_factor_reset` from the console |
| Errors | 409 `already-reported`, `job-on-hold`, `name-locked`, `name-change-pending`, `conversation-blocked` |
| Legal | question 16 (reported message excerpts) |
| Front end | "Laporkan lowongan"; report and block in chat; name change request in the company profile; admin pages D23 Laporan (jobs, messages), D24 Perubahan nama, D25 Email tidak terkirim, D26 Pengumuman; moderation flags; operations card; announcement banner |
