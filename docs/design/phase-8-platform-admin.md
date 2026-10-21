# Phase 8: platform admin: job reports, approval for unverified companies, name lock, two-factor reset, chat reports, email suppression, announcements and scheduler watch (designed 2026-10-15; sections 1 to 4 coded 2026-10-15, 5 to 8 coded 2026-10-18)

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
- 2026-10-18: the **admin account view** (`GET admin/users`, `users.view`) shows the suppression flag; the **mass reset email** is a console command, `identity:send-imported-password-resets` (decided by the user, see "Coded: sections 5 to 8").
- 2026-10-19: a **queue watch** next to the scheduler watch: an email on new failed jobs and on a stalled queue (FG16 of
  `docs/analysis/actor-gap-analysis.md`, chosen by the user; section 9, item #30).

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

## 9. Queue watch (designed 2026-10-19, item #30, before go-live)

The operations card already shows failed and waiting queue jobs; nobody is told when they pile up.

- **Checked by** `platform:check-scheduled-tasks` (every 5 minutes), through a `QueueHealth` port read from the `jobs` and `failed_jobs` tables.
- **Failed jobs:** an email to `platform.operations_email` when `failed_jobs` has rows newer than the last alert, **at most once an hour**,
  with the count per queue and job class and the newest exception's first line (no payload: it may hold personal data).
- **Stalled queue:** an email when the oldest available job of a queue (`available_at` passed, not reserved) waits more than **10 minutes**,
  once while it lasts, and once when it recovers. Covers dead workers (Supervisor stopped) and a full `bulk` queue.
- **State:** a new table `platform_queue_alerts` (`kind` `failed` or `stalled`, `queue`, `alerted_at`, `recovered_at`, `last_failed_job_id`;
  unique (`kind`, `queue`)); schema approved by the user 2026-10-19.
- **Card:** the operations card gains `queues: [{queue, waiting, oldest_wait_seconds, failed_last_24h, stalled}]`.
- Essential email in English, as the scheduler alert (a suppressed operations address still gets it). `docs/deployment-checklist.md` lists the
  setting next to the scheduler alert.

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

## Coded: sections 1 to 4 (2026-10-15)

Migration `000141` (`job_reports`, `job_jobs.report_hold_at`, `company_name_change_requests`). Section 4 was coded with `phase-8-company-admin.md`. Sections 5 to 8: see "Coded: sections 5 to 8".
Checked by the user. Interpretations, where the design left room:

- **Job reports (section 1).** `POST jobs/{id}/reports` answers 201 with the report (`id`, `job_id`, `reason`, `status`, `created_at`); it never says whether the job went on
  hold. The reporter is a job seeker with a verified email (403 `job-seeker-only`, 403 `email-not-verified`); the job must be live (a held job is not, so 404); 422
  `invalid-report` for a missing or too long note. The reporter is the plain column `reporter_user_id`; the admin and the account foreign keys follow the existing rule
  (`decided_by_admin_id` restricts, the job cascades). The hold counts **distinct reporters with an open report** (an erased reporter no longer counts) and is set in the
  same locked transaction as the report; the threshold is the global setting `jobs.report_hold_threshold` (0 to 100, not per company). `JobVisibility::isLive` and the
  public search skip a held job, so the page, the alerts, recommendations, saved jobs and the company page follow; `ApplyToJob` answers 409 `job-on-hold` (a new
  `JobDirectory::isOnHold`); applications already made go on. The company's job detail carries `report_hold_at`; its assignees get `job.held` (job id only).
- **Admin.** `GET admin/job-reports` lists jobs with open reports (held first, then most reported, then latest; `open_reports`, `reasons` as a map to counts, first and last
  time, `held`, `held_at`, the company); `GET admin/jobs/{id}/reports` lists every report of a job, newest first, with the reporter id and the note. `POST
  admin/jobs/{id}/reports/dismiss` closes the open reports as `dismissed` and lifts the hold (409 `no-open-reports` when there are none). A take-down closes them as
  `actioned` and clears the hold (`Job::takeDown`). Every reporter gets `report.reviewed` (job id only, never the outcome). Audit `job.reports_dismissed` (count) and
  `job.hold_released` (once, on either path, only when there was a hold). The queue card `jobs_queue` gains `job_reports {jobs_with_open_reports, held}`. The account
  erasure step `job-reports` clears the reporter and the note. The reports are not part of the personal data export (the export lists what the person owns; a report is the
  platform's record).
- **Review rule (section 2).** `JobReviewPolicy::required(companyId, content)` replaces the `jobs.require_approval` check in `PublishJob`, `ReopenJob` and `UpdateJob` (a core
  change of a live job): the company's approval setting, **or** `jobs.approval_for_unverified` (global, default on) while the company is not verified, **or** a flag.
  "Verified" is read from `company_companies.verified_at` through the new `CompanyDirectory::isVerified` (the public card is null for a hidden company, and a hidden
  company still publishes). The scanner reads the **title and the description** (the summary and the salary note do not exist yet; they join with
  `phase-8-job-discovery.md`). A phrase matches whatever the letter case, on word boundaries, with any run of spaces for a space; a contact flag is the first email
  address and the first phone number (Indonesian mobile or landline, or `+` international) of the description. The internal tenant exemption came with #5 (2026-10-16): an internal tenant counts as verified through its tenant link (`erp-organization.md`, coded section of part A).
  The unverified rule means every job is reviewed until at least one required document type exists (`docs/deployment-checklist.md` says so).
- **Admin view.** `GET admin/jobs/{id}` gains `company_verified` and `flags [{kind, value, field}]`; the list gains `flagged` (true or false in the review queue,
  `filter[status]=pending_review`, null in any other list). In the queue the flagged jobs come first: the whole queue is scanned in memory (it is short) and the page is
  ordered with the flagged ids first, so no column was added.
- **Name lock (section 3).** `PATCH company/profile` answers 409 `name-locked` when the name differs and the company is verified; the same name sent again is not a change.
  `POST company/name-change-requests` is multipart (`new_name`, `reason` 5 to 1,000, `file`): the name is trimmed and spaces folded, 2 to 255 characters and not equal to the
  current name ignoring case (422 `invalid-name-change` with `errors`); the file is a PDF by content, at most 10 MB (`invalid-document`, `file-too-large`); an
  **unverified company is refused with 409 `name-not-locked`** (it renames itself in the profile); 409 `name-change-pending` while one waits. Any member who holds
  `company.profile` may ask, list (newest 50) and cancel; a cancel is a decision (`decided_at` is set, no admin). The company never sees which admin decided.
- **Admin** (`companies.manage`): `GET admin/company-name-changes` (`filter[status]`; pending first, oldest first, then the decided newest first), `.../{id}` (the company,
  who asked, who decided, `has_document`), `.../{id}/document` (download only, audited as `company.name_change_document_viewed`), `.../approve` (the name changes; the badge,
  the slug and the other fields stay) and `.../reject {note}` (5 to 1,000). Both answer with the request afterwards; a request already decided is 409
  `name-change-not-pending`. The company admins get `company.name_change_approved` or `_rejected` (with the note); audit `company.name_change_requested`, `_cancelled`,
  `_approved` (from and to), `_rejected`; all four show in the company activity log (group `settings`). The queue card `companies_queue` gains `name_changes_waiting`.
  `company:purge-name-change-documents` (03:30 daily) deletes the file 90 days after the decision and keeps the request.
- **Tests.** `FeatureTestCase` wraps `Settings` so `jobs.approval_for_unverified` reads off unless a test sets `$unverifiedReview = true` (the companies of the older tests
  are unverified and their jobs must still go live).

## Coded: sections 5 to 8 (2026-10-18, #17; waits for the user's check)

Migrations `000166` (`platform_email_suppressions`), `000167` (`content_announcements`), `000168` (`platform_scheduled_task_runs`), `000169` (`messaging_reports`,
`messaging_conversations.blocked_by_seeker_at`). Two decisions the user took before coding, beyond the design: (1) the design said "the admin user and member views show the flag",
but the platform admin had no account view at all, so **`GET admin/users` (`q`, paginated) and `GET admin/users/{id}`** were added under a new permission `users.view`
(id, type, name, email, verified, status, locale, first and last sign-in, deletion request, `email_suppressed`, `email_suppression_reason`; opening one account is audited as `user.viewed`,
the list is not); (2) the import's reset email run did not exist in code, so the console command **`identity:send-imported-password-resets`** was written (below). Interpretations:

- **Email suppression (section 6).** The list is read through the shared port `EmailSuppressions::reasons(emails)` (Platform implements it; Identity reads it). The `MessageSending` listener
  (`DropSuppressedRecipients`) drops suppressed addresses from To, Cc and Bcc, cancels a message left with none, and matches whatever the letter case. A message carrying `X-Essential`
  (`EssentialEmail::headers()`) is sent as it is and the header is removed before sending. Essential: `VerificationCodeMail` (verification code and password reset), `AccountNoticeMail` (two-factor
  required and the internal-tenant notice, the only two uses), the member **suspension** notice (`MemberNoticeMail` with `company.member_suspended`; reactivation and ownership notices are not essential)
  and the scheduler alert to the operations address. Newsletters, digests, recommendations, reminders and chat emails are filtered. Webhook: `POST webhooks/email/{provider}` answers 404 until a parser
  is registered in the `EmailEventParsers` singleton (`EmailEventParser::provider()` and `parse(body, headers)`, throwing `InvalidWebhookSignature` for a bad signature, answered as 403
  `invalid-signature`); the body and headers go to the parser untouched, the webhook has no other limit than the baseline per-IP `api` limit. An address already listed keeps its first entry. Admin
  (`settings.manage`): `GET admin/email-suppressions` (`q`, paginated, newest first), `POST` (`email`, `details`; 409 `already-suppressed`), `DELETE .../{id}`; audit `platform.email_suppression_added`
  and `_removed` hold the entry id and reason, never the address. `GET me` gains `email_deliverable` (a new `CurrentUserResource`; the sign-in responses keep `UserResource`); a changed address starts clean
  because the list is keyed by the address.
- **Mass reset email.** `identity:send-imported-password-resets` (`--write` to send, default dry run; `--limit=200` emails per run; `--delay=1` second after each email). Target: accounts that are not
  psychologists, active, not anonymised, **without a password and without a social sign-in** (a Google-only account has no password too and must not get one). The dry run reports, over the whole pool:
  without a password, **suppressed (skipped)**, already issued a reset code (skipped), left to send. An account that was **ever** issued a password reset code (this command, an earlier run or its own
  request) is skipped, so a repeated run sends nothing twice and no new column was needed. The email is the normal password reset code (queue `critical`, essential, valid as long as the reset flow's
  code, 15 minutes by default), not a long-lived link: a person who misses the window asks again through "forgot password".
- **Chat reports and block (section 5).** `POST me/conversations/{id}/report` and `POST company/conversations/{id}/report` share one use case (the caller decides the side; a conversation the caller may not see is
  404); 201 with `{id, conversation_id, reason, status, created_at}`; 10 per hour per account (limiter `chat-report`, 429); 409 `already-reported` while the reporter's own report of that conversation is open
  (the other side may report); 422 `invalid-report` with `errors` (`other` needs a note, note at most 1,000, `message_id` must belong to the conversation). A closed or blocked conversation can be
  reported. The snapshot is the reported message and up to 10 earlier ones (without `message_id`: the latest 11, none marked), oldest first: `message_id`, `side` (`job_seeker`/`company`), `sent_at`, `text`
  (null once deleted), `attachments` (names only), `deleted`, `reported`. `reporter_user_id` and `message_id` are plain nullable columns (the reporter is cleared by the erasure, the message FK is `SET NULL`).
  **Block:** `POST`/`DELETE me/conversations/{id}/block` (job seeker only, idempotent, 403 `job-seeker-only` for others); `blocked` and `blocked_at` are in every conversation view of both sides. The company's
  sends, new messages into the pair and bulk sends stop with 409 `conversation-blocked` (bulk: skipped with that code). **Not restricted: the job seeker's own sending** (the design only stops the
  company); say so if a block should also stop the seeker writing. **Admin** (`messages.moderate`, new `MessagingPermissions`): `GET admin/message-reports` (`filter[status]`; open ones oldest first, then the
  decided newest first; no names or text), `GET .../{id}` (company, job seeker, note, snapshot, decision; never the live thread), `POST .../{id}/dismiss {note?}`, `POST .../{id}/action {note}` (note required,
  at most 1,000: 422 `invalid-decision`; 409 `report-decided`). The reporter gets `report.reviewed` with the conversation id only. Audit `messaging.report_dismissed` and `_actioned` hold the report id, reason
  and conversation id. `messaging:purge-report-snapshots` (04:15 daily) clears snapshots 180 days after the decision (open reports keep theirs); the erasure step `messaging-data` also clears the reporter, the note
  and the snapshot of the erased account's reports, and the snapshots of the reports about conversations where the erased user is the job seeker. Reading a snapshot is not audited (the design did not
  ask for it): consider `messaging.report_viewed` once legal question 16 is answered.
- **Announcements (section 7).** `Announcement` (Content): title at most 120, body 500, `link_url` https (500), audience, level, window; the form request checks the shapes (422), the domain checks that the
  window ends after it starts and is at most 90 days (422 `invalid-announcement`). Admin (`announcements.manage`): list (paginated, newest start first, with `status` `scheduled`/`live`/`ended`), show, create, replace
  (`PUT`), delete; audit `content.announcement_created`, `_updated`, `_deleted` hold the id, audience and level, never the text. `GET announcements` (no sign-in): the live banners, important first then newest
  start, at most 20: `id, title, body, link_url, level, starts_at, ends_at`. The reader is the owner of the `user` token (job seeker or company member adds their audience; anonymous, psychologists and admins
  see `all`). Cached 60 seconds per audience (plain arrays in the cache): a new, changed or deleted banner reaches the public within a minute, no flush on write.
- **Scheduler watch (section 8).** Task name: `ScheduledTaskName::of` (the artisan command; a closure task would use its `->name()`; no call site changed); a test checks they are distinct and none is `unnamed-`.
  Listeners fill `platform_scheduled_task_runs` from `ScheduledTaskStarting`, `Finished` and `Failed` (a command exiting with a failure code raises both a finish and a failure event: only the failure counts;
  a failure message is `Class: message`, 1,000 characters; a problem in the recording is logged and never stops the scheduler). The **heartbeat** is a new hidden command `platform:record-scheduler-tick`
  that does nothing every minute: `last_tick_at` is its last finish. **Late**: no finish within two intervals plus 5 minutes counted from the last finish (or first start if it never finished); the interval is
  the longest gap among the next 8 runs of the expression (so a weekday task is not late over a weekend). Rows of tasks no longer scheduled are ignored and deleted by the check. The operations card gains
  `scheduler {last_tick_at, tasks [{task, last_finished_at, late, failing}]}`, late and failing first. `platform:check-scheduled-tasks` (every 5 minutes) emails the new setting
  `platform.operations_email` (empty = nothing): one email per check for the tasks that turned late or failing (task, reasons, last failure), one for the tasks that recovered; with no address nothing is marked, so
  an alert goes out once an address is set while the problem lasts. The alert is an essential email (a suppressed operations address still gets it) in English, templates `platform.scheduler_alert` and
  `platform.scheduler_recovered`. `GET health/scheduler` (no sign-in) answers `{status: ok}` 200 while the heartbeat is under 5 minutes old, else `{status: down}` 503, and nothing else.
- **Not coded / for later.** The front end pages. A parser for the chosen SMTP provider (`EmailEventParser`) and its webhook secret wait for the SMTP provider. The one-click opt-out is unrelated.
- **Docs.** `ApiTags` gained the group `Operations` (webhooks and health) and patterns for the new admin routes; `docs/api/error-catalog.md` regenerated.

## Coded: status counts for the Console tabs (2026-10-21, asked by the front end; waits for the user's check)

`GET admin/jobs/status-counts` (`jobs.manage`, `filter[q]`) answers `{data: {all, draft, pending_review, active, closed, expired, rejected, taken_down}}`; `GET admin/companies/status-counts`
(`companies.manage`, `filter[q]`, `filter[verified]`) answers `{data: {all, active, suspended}}`. The front end chose separate endpoints over counts in the list `meta` (option B). Interpretations: one
`GROUP BY status` query each (`AdminJobReader::countByStatus`, `AdminCompanyReader::countByStatus`), applying the same private filter method as the list, so the search matches exactly what the list matches
(a job also matches by its company name); a status without a row is 0; `all` is the sum, honours the search and `verified` but never a status; `filter[status]` and any other filter is refused with 422.
Use cases `CountJobsByStatusForAdmin` and `CountCompaniesByStatus`; no migration; routes registered before `{id}`.
