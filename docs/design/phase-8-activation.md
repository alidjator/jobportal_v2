# Phase 8: activation of the imported karir.pkp users and companies (designed 2026-10-20)

Design only; no code. Source: `docs/analysis/multi-persona-gap-analysis.md` section 5.6, decisions 1, 2, 5, 6, 8, 12, 13, 14 and 15 (gaps MG-P01, MG-P02,
MG-A05, MG-A06, MG-A07, MG-A09, MG-B01, MG-B02, MG-B06, MG-B07, MG-C03, MG-C04). The legacy import (`phase-1-migration.md`) is coded and rehearsed; this
design is what must exist **before the final run**: the import is treated as a user journey, not a data task. Plans and billing stay out of scope.

## Decisions

One line per decision; the detail is in the section named. If they disagree, the detail section wins. "User" = answered by the user in this session
(2026-10-20); "default" = proposed by the design and approved by the user (section 16, defaults a to j).

- 2026-10-20 One **activation email** for every active, verified imported account, **without a code**, with a link to a new landing page (A8 for job seekers, B6 for the back office); two variants (has a password / has none); the old command `identity:send-imported-password-resets` is replaced (user; [section 3](#3-the-activation-email-the-landing-pages-and-the-command)).
- 2026-10-20 Order: pre-freeze notice **14 days and 3 days** before the freeze; company owners activated first, job seekers **3 working days** later; job seekers in a **pilot of 200, then 500 a day**, with a stop rule at more than 2% bounces or 0.1% complaints; verified addresses first, unverified ones last; **one reminder after 7 days** (user; [section 2](#2-order-and-calendar)).
- 2026-10-20 Drafts of the notice, the banners, the newsletter, the activation and reminder emails and the page texts are in [Appendix A](#appendix-a-drafts-for-review); **Claude drafts, the user reviews** (user, decision 14).
- 2026-10-20 **Hold rule:** an imported account that has not signed in since the import gets **no scheduled email** (recommendations, profile reminders, team digests, unread-message emails, job alerts, expiry notices) and **no newsletter-style email**; emails caused by a person (interview invitation, offer, rejection, verification, reset) still go, in-app notifications are not held; held mail is skipped, not queued (user; [section 4](#4-the-hold-on-scheduled-emails)).
- 2026-10-20 New column **`identity_users.imported_at`** marks imported accounts; the other contexts ask through a Shared port, the id map stays removable (user; [section 4](#4-the-hold-on-scheduled-emails), [section 11](#11-schema)).
- 2026-10-20 **Import summary** at the first sign-in: a dismissable card on J1 and C1 plus an archive page (J19, C33), kept **90 days from the first sign-in**, counts per kind only (user; [section 5](#5-the-first-sign-in-import-summary)).
- 2026-10-20 **Label "Dipindahkan dari karir.pkp"** on both sides (J5, C6, C7); an imported application is **left out of the stale mark and of time in stage until its stage is first changed**, backed by a new column `recruitment_applications.migrated_at` (user; [section 6](#6-the-label-and-the-stale-rule)).
- 2026-10-20 **Self-service email change** (J15, C25): a code to the new address, confirmed with the code **and the password** (accounts without a password also give a code sent to the old address), notice to the old address, company domain limit applies (user; [section 7](#7-email-change-and-the-admin-correction)).
- 2026-10-20 **Admin email correction** under `users.manage`, after the written identity check (two proofs on different channels, no ID photo), audited, both addresses told, every token revoked, one admin (user; [section 7](#7-email-change-and-the-admin-correction), [Appendix B](#appendix-b-identity-check-procedure)).
- 2026-10-20 **Two-factor reset by the platform admin** for company members **and job seekers**, before go-live, with the same written check; psychologists stay with D19 (user; [section 8](#8-two-factor-reset-by-the-platform-admin)).
- 2026-10-20 Three **new importers**: saved jobs, job-alert opt-ins (one daily alert per account; switch off becomes paused), saved candidates (a private company list: name, category and date only, no contact actions; contact opens when the candidate is discoverable or has a consenting application to that company); the **profile-view log is not imported**; chat history is not imported (user; [section 9](#9-the-importers)).
- 2026-10-20 **Activation report** (D29, `GET admin/activation/report`): imported, eligible, emails sent, bounced, first sign-ins, at day 30, 60 and 90 from the first activation email of each group, **no target** (user; [section 10](#10-the-activation-report)).
- 2026-10-20 New settings `platform.support_email` and `platform.support_whatsapp`, to be filled before the first batch; the send command refuses to run while the e-mail is empty (user; [section 14](#14-settings-templates-permissions-and-errors)).
- 2026-10-20 Correction of an assumption of the gap analysis (MG-P02): the importer **already** sets `expired` on an imported job whose deadline has passed (`JobsImporter`, note `deadline-passed-expired`), so there is no burst of expiry notices at the first `job:expire-due` run ([section 4](#4-the-hold-on-scheduled-emails)).

## 1. Facts this design rests on

Read from the code, the schema and the rehearsal report of 2026-10-13 (`20261013-081834-all-write.json`, counts only; the final run uses a new copy and its numbers will differ).

- **Scale (rehearsal):** 3,719 accounts, 3,710 profiles, **2 companies**, 85 jobs, 1,657 applications. 841 accounts have **no password** (the legacy hash was not bcrypt); the other 2,878 keep a bcrypt hash and **can sign in at once** with the old password. The company side is two owners: they can be called one by one.
- **The existing reset command** (`SendImportedPasswordResets`) emails a six-digit **code that lives 15 minutes** (`VerificationCode::LIFETIME`) and only to the accounts without a password; a batch email read hours later is dead on arrival, and the 2,878 other accounts are told nothing. This is why section 3 replaces it.
- A3 is "a code, not a link" (`frontend.md` A3): the activation email therefore carries a link to a page that **requests** the code when the user clicks, never a code.
- `identity_users.first_login_at` exists (set at the first sign-in; the profile reminders already wait for it). `identity_users` has no imported marker; `legacyimport_id_map` has no foreign keys and "is removable when everything is done".
- Terms are not carried: the A5/B3 terms gate runs at the first sign-in. Company admins need TOTP (forced enrolment on B1 at the first sign-in). Discoverability starts off.
- The company-side two-factor reset by another company admin exists (`POST company/members/{userId}/reset-two-factor`), the console `identity:reset-user-two-factor <email>` exists, the psychologist reset exists (D19); the platform admin's API for company members does not (MG-B01).
- Notification preferences are asked through one Shared port (`NotificationPreferences::isEnabled`, `enabledAmong`), used by the scheduled senders; the `EssentialEmail` marker already lets a message pass the suppression list.

## 2. Order and calendar

T = the freeze day (the day of the final import). Working days are Jakarta working days. Nothing here is automatic except the send commands, which the user runs (`CLAUDE.md`: production writes need approval each time).

| When | What | Who |
|---|---|---|
| T-14 | **Notice 1** on karir.pkp: banner and newsletter (drafts A1, A3). The two companies are called, then emailed (draft A8). Both sides are told that chat history and the profile-view log do not move. | karir.pkp maintainers (copy by Claude, reviewed by the user) |
| T-3 | **Notice 2**: banner and short newsletter (A2, A4). | same |
| T-1 | Checks: SMTP provider and its bounce webhook working; SPF, DKIM and DMARC of the sender domain; `platform.support_email` filled; test activation sent to staff-owned imported accounts with `--emails=<file>`; all four parts of section 15 rehearsed on a fresh copy. | user |
| T | Freeze (maintenance page A5), new copy of the database and files, `legacy:import --all` dry run, then `--write`; the after-import steps of `deployment-checklist.md` section 3; the summaries are written by the same run. The new site is open at once: an account with a usable old password can sign in before any email; that sign-in releases the hold. | user |
| T+1 | **Company owners:** activation email (variant `company`, B6) and a phone call; they enrol TOTP, accept the terms, read the company summary (C1). Platform admins help with the reset of two-factor (section 8) and the email correction (section 7) if needed. | user, platform admin |
| T+1 working day + 3 | Setting `identity.activation_job_seekers_not_before` (a date; the job-seeker command refuses earlier) opens. **Pilot of 200** job seekers, after the staff test in T-1. | user |
| next working day | Read the activation report (D29): bounces within 2% and complaints within 0.1% of the pilot, else stop and look. Then **500 a day**, verified addresses first (about 7 working days for 3,500); accounts whose address was never verified last, only if the earlier batches stayed within the limits. | user |
| first email + 7 days | **One reminder** (`--reminders`) to those who have not signed in, same batch sizes and stop rule; nothing further is sent to them (hold rule). | user |
| first email + 30, 60, 90 | The report shows the milestones (section 10). | admin |

Deactivated accounts, suppressed addresses and accounts whose email was never verified (until their turn) are never part of a batch in the first pass; each is counted in the report.
Newsletter issues of the new platform are sent by an admin by hand (`phase-4-newsletter.md`); none is sent to imported subscribers before the activation of their group is finished (operating rule, part of the checklist).

## 3. The activation email, the landing pages and the command

**Email.** Template keys `activation.job_seeker` (variants `has_password` and `no_password`), `activation.company`, `activation.reminder`, in `id` and `en`, editable in D13 like the other templates, marked `EssentialEmail` (they must pass the suppression listener only for addresses that are **not** suppressed: the command already skips suppressed addresses and counts them). The email never contains a code or a password and never asks for either. Texts: Appendix A.

**Landing pages.** The email links to a page that explains and starts the reset:

- **A8** `/aktifkan-akun` on the public site (job seekers): short explanation, two choices: "Masuk" (A1) and "Atur kata sandi baru" (an email field, then `POST auth/password/forgot`, then the existing reset step A3). It cannot tell which variant the reader has (no account-existence leak), so both choices show for everyone. A1 and A3 get a one-line pointer "Pernah memakai karir.pkp? Aktifkan akun" (static text).
- **B6** `/aktifkan-akun` in the back office (company owners): the same, on B1/B2, with the line that an authenticator app is needed for the first sign-in.

**Command `identity:send-activation-emails`** (replaces `identity:send-imported-password-resets`; its use case, report class and tests are removed, the checklist item is rewritten):

- Options: `--audience=company|job_seeker` (required), `--limit` (default 200; 500 for the daily batches is typed by the user), `--delay` (as today), `--pilot` (a label for the first batch), `--reminders` (the reminder pass), `--emails=<path>` (only the addresses in the file, for the staff test), `--write` (without it a dry run that only reports); the confirmation prompt stays.
- Selects `imported_at IS NOT NULL AND first_login_at IS NULL`, status `active`, address not suppressed, no earlier row of the same kind in `identity_activation_emails`. Verified addresses first; unverified ones only when the previous batch was within the limits. A reminder selects accounts whose activation email is at least `identity.activation_reminder_days` (7) old, still without a first sign-in and without a reminder.
- **Stop rule** (checked before every `--write`): for the previous batch, bounces (suppressions with reason bounce) above `identity.activation_bounce_limit_percent` (2) or complaints above `identity.activation_complaint_limit_percent` (0.1) refuse the run with the numbers; lowering the settings is the user's call.
- **Guards:** refuses while `platform.support_email` is empty; for `job_seeker` refuses before `identity.activation_job_seekers_not_before`. The dry run prints how many company owners have signed in.
- Sends through the existing `VerificationCodeSender`-style mailer (no queue, paced by `--delay`); a row is written per email in `identity_activation_emails` (user id, kind, variant, batch label, sent time).
- Output: the counts table (eligible, suppressed, unverified held back, deactivated, already sent, sent in this run).

**Per-user actions for support** (D21, `users.manage`): resend the activation email (kind `manual`), send a password reset code, resend the verification code (section 12).

## 4. The hold on scheduled emails

Rule (decision 15): an account with `imported_at` set and `first_login_at` empty is **held**.

- **Mechanism.** `NotificationPreferenceDefinition` gains a flag `scheduled` (default false). `StoredNotificationPreferences::isEnabled` and `enabledAmong` answer **false for the `email` channel** when the key is `scheduled` and the account is held; the answer comes from a new Shared port `AccountActivation` (`heldAmong(list<int> userIds): list<int>`, implemented in Identity, one read per page like `enabledAmong`). Scheduled keys: the matching recommendations, the profile reminders, the job-alert digests, the unread-message email, the team daily digest. The job expiry notice (`NotifyAssigneesOfExpiry`) does not use preferences; it asks `AccountActivation` directly.
- **Not held:** emails caused by a person or by a security action (application, interview, offer and rejection emails, invitations, verification, reset, activation itself); in-app notifications; push.
- **Skipped, not queued.** A held email is simply not sent; nothing is stored for later, so the first sign-in does not release a backlog. Recommendations, digests and unread mail come again at their next run; the profile reminders count from `first_login_at`; the job-alert cursor is moved to the first sign-in by a listener (section 9), so the first digest holds only jobs published after it.
- **Release:** the first successful sign-in (`first_login_at` set); the terms gate does not delay it.
- **Correction of the gap analysis:** `JobsImporter` already imports a job whose deadline has passed as `expired`; the first `job:expire-due` run only closes jobs whose deadline falls after the import, a handful at most, and their email notices are held until the owner signs in.
- **Newsletter:** an issue is sent by an admin on purpose, so it is not a scheduled email and is not held (operating rule in section 2).

## 5. The first-sign-in import summary

**What it is.** A short list of what the import did and did not carry, for the account's owner. The summary is written by the importers during the final run (not rebuilt from the JSON report, which holds legacy ids only): each importer that skips, empties or defaults something reports it to a recorder keyed by the new user or company. Stored as counts per code, no new personal content.

**Storage** (section 11): `jobseeker_import_summaries` (one row per imported job seeker) and `company_import_summaries` (one row per imported company); `items` is a JSON list of `{code, count?, detail?}`. Static notes that are true for every account (terms, discoverability, chat not imported, team not carried) are **not stored**: the API adds them from a catalog per kind, so a copy change needs no re-import.

**Codes, job seeker (stored):** `resume_skipped` (count; detail: `not_pdf`, `failed_scan`, `too_large`, `file_missing`, `over_limit` counts), `legacy_cv_not_carried`, `avatar_skipped`, `skills_skipped`, `languages_defaulted` (count; level set to conversational), `experience_skipped`, `education_skipped`, `education_year_assumed`, `gpa_emptied`, `links_skipped`, `applications_imported` (count, `withdrawn_duplicates`, `no_resume`), `saved_jobs_imported` (count, `unavailable`), `job_alert_imported` (roles, paused), `saved_by_companies` (count of private company lists that hold the person, section 9), `password_reset_needed`.
**Codes, company (stored):** `jobs_imported` (total and per status), `jobs_draft_incomplete`, `jobs_closed_apply_outside`, `pending_edits_skipped`, `screening_questions_skipped`, `screening_questions_over_limit`, `applications_imported` (count, `group_unmapped`, `withdrawn_duplicates`), `saved_candidates_imported`, `verified_badge_kept`.
**Static notes (catalog):** job seeker: terms again, discoverability off (link to the switches, MG-A04), languages' level, chat and profile-view history not imported, hours of the activation email. Company: terms again, two-factor enrolment, no verification documents, contact fields empty, team members not carried, applicants' screening answers not carried (never stored in the legacy), imported applications explained (label of section 6), saved candidates as a private list (section 9).

**API.** `GET me/import-summary` (job seeker) and `GET company/import-summary` (company admin): `{kind, imported_at, dismissed_at, items[]}`; 404 `no-import-summary` when the account has none or it has been purged. `POST me/import-summary/dismiss` and `POST company/import-summary/dismiss`, 204. `GET me` gains `imported_from_legacy: bool`.
**Retention (user):** the summary stays readable **90 days from the first sign-in** (`identity.import_summary_retention_days`, an admin setting), then the nightly commands `job-seeker:purge-import-summaries` and `company:purge-import-summaries` delete the rows (the retention reads `first_login_at` through a port; for a company, the first sign-in of its owner). Anonymising the account deletes its row at once (the erasure listener of each context); the data export (J15) includes the summary while it exists.
**Front end:** J1 and C1 show the card at the first visit after the sign-in; closing it dismisses it; J19 `/me/pemindahan` and C33 `/perusahaan/pemindahan` keep the full list (links from J15 and C25).

## 6. The label and the stale rule

- **Label.** `migrated: bool` and `migrated_at` on the application resources of **both** sides (job seeker list and detail; company row and detail). J5, C6 and C7 show a badge "Dipindahkan dari karir.pkp" and, on the detail, one explanation: the history has two entries and the profile snapshot was built at the import (Appendix A, text A12). The "viewed" time stays empty.
- **Column.** `recruitment_applications.migrated_at` (nullable timestamp, partial index where not null) becomes the queryable flag; the migration backfills it from `profile_snapshot->>'migrated_at'` for the rehearsal databases; `ApplicationsImporter` sets it (the snapshot keeps its own `migrated` keys). It also serves decision 9 (reports count only applications created after the cut-over date; designed in its own item).
- **Stale rule (user).** An application is **untouched** while `migrated_at IS NOT NULL AND stage_entered_at <= migrated_at`. An untouched imported application is **not stale** (`StaleApplicationsTask`, the `stale` mark and filter of the lists, `TeamDigestLines`) and its time in stage shows "—" with the label; after the first stage change it is counted like any other from that change. One shared predicate in Recruitment (`UntouchedImport`) is used by all four places, so they cannot disagree.

## 7. Email change and the admin correction

**Self-service (J15 for job seekers, C25 for company members).**

- `POST me/email-change` `{email}` (session required): always **202**, whether or not the address is known (no account existence leak). A free, valid address gets a six-digit code (15 minutes, five attempts, same generator and lifetime as the other codes); an address that belongs to another account gets only a notice "someone tried to use your address", no code. Throttle: 3 requests an hour per account.
- `POST me/email-change/confirm` `{code, password}`; an account **without a password** (Google only) gives `{code, old_code}` where `old_code` is a second code sent to the **current** address at the request. 204 on success; 422 `email-change-code-invalid`, 410 `email-change-expired` (also when no request is pending), 422 `password-invalid`.
- `DELETE me/email-change` cancels the pending request, 204. `GET me` carries `pending_email_change: {email_masked, expires_at} | null`.
- On success: the email changes and is marked verified (the code proved the mailbox), every other session is signed out (the current one stays), the **old address gets the notice** `account.email_changed` (an essential email with the support contact, so a hijack can be reported). One change is allowed per 24 hours.
- **Company members:** the company's email-domain limit (`company/team/email-domains`), when on, applies to the new address (422 `email-domain-not-allowed`).
- **Undeliverable addresses:** a new address on the suppression list is 422 `email-undeliverable` (the code could not arrive).

**Admin correction (D21).** `PUT admin/users/{id}/email` `{email, reason, identity_check}`; permission `users.manage`; job seekers and company members only (never psychologists or admins: 409 `account-not-correctable`).

- `reason` and `identity_check` are required (5 to 1000 characters each): `identity_check` records which two proofs were seen (Appendix B). The company domain limit does **not** apply (as for an invitation by a platform admin). A taken address is 409 `email-in-use`; a suppressed one 422 `email-undeliverable`.
- Effects, in one transaction: the email changes and is marked **unverified**; every token of the account is revoked; a pending self-service change is dropped; audit `account.email_corrected` (admin, IP, reason, identity check, old and new address masked); after commit the **old address** gets `account.email_corrected_old` ("your sign-in email was changed by the platform team; contact support if this was not you") and the **new address** gets `account.email_corrected_new` with the link to A8/B6, where the user sets a password with a code sent there (which also verifies the address).
- One admin acts alone (user); the audit and a weekly list of corrections and resets for the super admin (a report filter of D21, section 12) are the control.

## 8. Two-factor reset by the platform admin

`POST admin/users/{id}/reset-two-factor` `{reason, identity_check}`; permission `users.manage`; for **company members and job seekers** (user); psychologists remain on `admin/assessment/psychologists/{id}/reset-two-factor`, admins on the console.

- Reuses the Identity use case behind `identity:reset-user-two-factor` (the secret, recovery codes and pending challenges are removed; the next sign-in asks for enrolment when it is required for the account, so a company admin enrols again on B1; a job seeker's factor is simply off). Every token is revoked in the same transaction.
- Audit `account.two_factor_reset` (admin, IP, reason, identity check). The user gets the notice `account.two_factor_reset` (essential email, and the in-app notice `company.two_factor_reset` that already exists for company members). A job seeker's email carries the support contact.
- **Before go-live (user):** this closes MG-B01, so a lost authenticator of the single owner of an imported company is a five-minute task for the platform admin and not the three-step workaround of the gap analysis. The written check is Appendix B.
- D21 shows when the account is the **only company admin** of its company, so the admin knows the stakes.

## 9. The importers

Three new importers in `src/LegacyImport/Application`, same pattern as the others (idempotent through `legacyimport_id_map`, `--dry-run`, JSON report with legacy ids and reason codes, one writer class per entity in Infrastructure, tests through the real API). New entities of the id map: `saved_job`, `job_alert`, `talent_category`, `saved_candidate`. They run after `applications`, in this order, and write the summary items of section 5. `legacy:import --all` includes them.

**Changes in the existing importers:** `AccountsImporter` sets `imported_at`; `ApplicationsImporter` sets `migrated_at`; every importer that skips or defaults a row calls the summary recorder.

**Saved jobs** (`bookmark_candidate_job` to `job_saved_jobs`): `saved_at` = the legacy `created_at`; a bookmark of a job that was not imported (apply-outside copies are imported as `closed`; pending-edit copies and rows with `job_id` NULL are not) is skipped and reported; a job that exists but is not live is kept and shows `available: false` as the feature already does; duplicates collapse on the unique key; the limit of 200 is not applied (the legacy data is migrated as it is).

**Job-alert opt-ins** (`candidate_job_alerts` plus the account switch `candidates.received_job_alert`, the opt-in; the other `*_alert` flags are not carried): **one alert per account** (user) with the legacy roles as the `role_id` criteria (roles that match no new master are reported), frequency **`daily`**, email on, name "Dari karir.pkp"; switch on = active, switch off but roles present = **paused** (nothing is lost, the owner can resume); `last_run_at` = the import time. A listener on the first sign-in sets `last_run_at` to that moment for the account's imported alerts, so the first digest holds only newer jobs.

**Saved candidates** (`company_bookmark_categories` to `talentpool_categories`, `bookmark_company` plus `bookmark_company_category` to `talentpool_entries`):

- Entries get the new `source` value `legacy` (the check constraint is widened). A candidate whose account was not imported is skipped and reported; a candidate who is not findable is **not** skipped: the entry is a **private list**.
- **What the company sees (user):** name, category and saved date, with the label "Dipindahkan dari karir.pkp"; **no profile, headline, email, phone, note field content from the candidate, message button or invitation** (single or bulk). Contact and invitations open when the candidate is **findable** (discoverable) **or** has an application with talent-pool consent to that company, the rules the pool already has; until then the actions answer the existing 409 `talent-pool-consent-missing`.
- The name comes through `CandidateDirectory` (a new method that returns only the display name for a given list of ids; the entry of a candidate whose account is deactivated or anonymised stays hidden as today).
- **Retention:** an entry whose only basis is the legacy one is deleted by the nightly pool command 24 months after the import.
- **Transparency for the candidate:** the job-seeker summary says how many companies hold them in a private list (`saved_by_companies`), and J15 gets one action "Hapus saya dari daftar perusahaan" (`DELETE me/talent-pool-memberships?source=legacy`, 204) that deletes those entries.
- The legacy category names are kept (at most 30 per company: over that, the excess entries land uncategorised and the count is reported).

**Not imported (user):** the messenger history (decision 1), the profile-view log (`candidate_cv_views`), `bookmark_candidate_company` (followed companies, frozen feature J18). Both sides are told in the pre-freeze notice and in the static notes of the summary.

## 10. The activation report

- **Endpoint:** `GET admin/activation/report?audience=job_seeker|company_member` (permission `users.view`), live from `identity_users`, `identity_activation_emails` and `email_suppressions`; no snapshots.
- **Response:** `imported`, `deactivated`, `unverified`, `suppressed`, `eligible`; `activation: {first_sent_at, sent, reminders_sent, bounced, complaints}`; `signed_in: {count, percent_of_sent, percent_of_imported}`; `milestones: [{day: 30|60|90, date, reached, signed_in, percent_of_sent}]` computed from `first_sent_at` of the audience (user: day 0 is the first activation email; no target); `batches: [{batch, sent_at, sent, bounced, complaints, signed_in}]`.
- A company counts as activated when its owner has signed in (the audience `company_member` is the owners). The page also lists the companies not yet signed in by name (a company name is not personal data) so they can be called.
- **Admin page D29** `/aktivasi` (menu "Pengguna"), and a card on D2 with the two sign-in percentages; the console `identity:activation-report` prints the same table. A target can be added later as settings.

## 11. Schema

Next migration numbers follow `000176`. The user runs the test-database migration, then the real one (`CLAUDE.md`); each is new.

| Table / column | Context | Detail |
|---|---|---|
| `identity_users.imported_at` | Identity | nullable timestamp; partial index `(imported_at) WHERE imported_at IS NOT NULL`; set by `AccountsImporter`; the migration backfills the rehearsal databases from `legacyimport_id_map` (entity `user`) |
| `identity_activation_emails` | Identity | `id`, `user_id` (indexed, **no foreign key**: it is a delivery log), `kind` (`activation`, `reminder`, `manual`), `variant` (`has_password`, `no_password`, `company`), `batch` varchar(40), `sent_at`; unique `(user_id, kind)` except `manual` (check: unique partial index for the two automatic kinds); index `(batch)` |
| `identity_email_changes` | Identity | `id`, `user_id` RESTRICT foreign key, unique (one pending per user), `new_email`, `new_code_hash`, `old_code_hash` nullable, `attempts`, `expires_at`, `created_at`; deleted by the account eraser and when confirmed or cancelled |
| `jobseeker_import_summaries` | JobSeeker | `user_id` unique RESTRICT, `items` jsonb, `created_at`, `dismissed_at` |
| `company_import_summaries` | Company | `company_id` unique RESTRICT, `items` jsonb, `created_at`, `dismissed_at`, `dismissed_by_user_id` (RESTRICT) |
| `recruitment_applications.migrated_at` | Recruitment | nullable timestamp; partial index where not null; backfill from the snapshot |
| `talentpool_entries.source` | TalentPool | the check constraint gains `legacy` |
| `notification` catalog flag `scheduled` | Notification (code, not schema) | `NotificationPreferenceDefinition::$scheduled` |

`CrossContextForeignKeysTest` lists the new keys; the data eraser of each context and `SettingsTest` are updated. Nothing here touches the production karir.pkp database; the legacy schema read for the new importers was `SHOW CREATE TABLE` only (no data).

## 12. API

| Endpoint | Who | Notes |
|---|---|---|
| `GET me/import-summary`, `POST me/import-summary/dismiss` | job seeker | section 5 |
| `GET company/import-summary`, `POST company/import-summary/dismiss` | company admin | section 5 |
| `GET me` adds `imported_from_legacy`, `pending_email_change` | any | |
| `POST me/email-change`, `POST me/email-change/confirm`, `DELETE me/email-change` | any signed-in account | section 7; limiter `email-change` |
| `DELETE me/talent-pool-memberships?source=legacy` | job seeker | section 9 |
| Application resources: `migrated`, `migrated_at` | job seeker, company | section 6; stale and time-in-stage rule |
| `PUT admin/users/{id}/email` | `users.manage` | section 7 |
| `POST admin/users/{id}/reset-two-factor` | `users.manage` | section 8 |
| `POST admin/users/{id}/activation-email`, `POST admin/users/{id}/password-reset`, `POST admin/users/{id}/resend-verification` | `users.manage` | support actions, audited, rate limited, 409 when the account is not active |
| `GET admin/users` filter `filter[imported]`, `filter[signed_in]`; the detail adds `imported_at`, `activation` (emails sent) and `only_company_admin` | `users.view` | |
| `GET admin/activation/report` | `users.view` | section 10 |
| Console: `identity:send-activation-emails`, `identity:activation-report`, `job-seeker:purge-import-summaries`, `company:purge-import-summaries` | user / scheduler | the purge commands are scheduled nightly |

After every route or response change run `php artisan scramble:clear` and then `scramble:cache` (mandatory rule). New error codes for `docs/api/error-catalog.md`: `no-import-summary`, `email-change-code-invalid`, `email-change-expired`, `email-undeliverable`, `email-in-use`, `account-not-correctable`, `password-invalid` (when not already present), `activation-support-contact-missing` (console only).

## 13. Page changes for `frontend.md`

New pages take the next number in their series (`frontend.md` 7 to 9); "#N" marks designed-not-coded.

| Page | Change |
|---|---|
| **A8** (new) | Public "Aktifkan akun" `/aktifkan-akun`: explanation, "Masuk" and "Atur kata sandi baru" (`POST auth/password/forgot`); target of `{ACTIVATION_URL}` of the job-seeker activation email |
| **B6** (new) | Back-office "Aktifkan akun" `/aktifkan-akun` for company owners, plus the line about the authenticator app |
| A1, A3, B1, B2 | One static line "Pernah memakai karir.pkp? Aktifkan akun" linking to A8/B6 |
| **J19** (new) | "Riwayat pemindahan" `/me/pemindahan` (`GET me/import-summary`); linked from J15 and from the J1 card |
| J1 | Card "Ringkasan pemindahan" at the first visit (dismissable); profile completeness card as today |
| J5 | Label "Dipindahkan dari karir.pkp" on imported applications; explanation on the detail; time in stage "—" |
| J9, J10 | Imported saved jobs and the imported daily alert ("Dari karir.pkp") appear as normal rows; unavailable saved jobs as designed |
| J15 | **Email change** card (new email, code, password; pending state; cancel); "Hapus saya dari daftar perusahaan" action (section 9) |
| **C33** (new) | "Riwayat pemindahan" `/perusahaan/pemindahan` (`GET company/import-summary`), company admins; linked from C1 and C25 |
| C1 | Card "Ringkasan pemindahan" for the company admin at the first visit (dismissable) |
| C6, C7 | Label, explanation, "—" for time in stage, no stale mark while untouched |
| C13 | Entries of source `legacy`: name, category, date, label; contact actions disabled with the reason; bulk invite skips them |
| C25 | **Email change** card (same as J15); the domain-limit error is shown |
| D2 | Card with the two first-sign-in percentages |
| D21 | Actions: correct email, reset two-factor, resend activation, send password reset, resend verification (each asks for a reason and, where required, the identity check); badge "only company admin"; filters `imported`, `signed_in` |
| **D29** (new) | "Aktivasi" `/aktivasi` (`GET admin/activation/report`; menu group Pengguna; `users.view`) |

## 14. Settings, templates, permissions and errors

- **Settings** (admin editable, `SettingsTest` lists each): `platform.support_email`, `platform.support_whatsapp` (optional), `identity.activation_reminder_days` (7), `identity.activation_bounce_limit_percent` (2), `identity.activation_complaint_limit_percent` (0.1), `identity.activation_job_seekers_not_before` (a date, empty = refuse), `identity.import_summary_retention_days` (90).
- **Email templates** (id and en, in the catalog and editable in D13): `activation.job_seeker`, `activation.company`, `activation.reminder`, `account.email_change_code`, `account.email_changed`, `account.email_corrected_old`, `account.email_corrected_new`, `account.two_factor_reset`; `EmailTemplatesTest` lists them. The activation and security emails are essential (`EssentialEmail`).
- **Notification catalog:** the in-app notice `account.email_changed` is not needed (the old address is told by email); `company.two_factor_reset` is reused for admin resets.
- **Permissions:** no new permission; `users.manage` (designed in `phase-8-user-management.md`, now also before go-live, together with its suspend and reactivate endpoints) and `users.view`. `identity:sync-permissions` gives `users.manage` to `super_admin`.
- **Link settings** (`frontend.md` 12.2): `JOB_SEEKER_ACTIVATION_URL` (A8), `COMPANY_ACTIVATION_URL` (B6), added to `.env.example`.

## 15. Coding order and tests

Parts are independent enough to code one by one; **parts 1, 3, 4 and 6 change the import, so a second rehearsal** (a new database from `jobportal_v2_rehearsal` steps in `docs/HANDOFF.md`) is needed after them and before the freeze. Parts 2, 5 and 7 are needed before go-live but do not change the data.

1. **Marker and hold:** `imported_at`, `AccountActivation` port, the `scheduled` flag, the expiry notice check, the first-sign-in listener for alerts. Tests: held and released account per scheduled key, a transactional email still sent, the importer sets the marker.
2. **Activation email, command and pages' API:** `identity_activation_emails`, templates, settings, the command with dry run, stop rule, guards, reminders and the staff test list; the old command removed. Tests: batch selection (verified first, suppressed, deactivated, already sent), stop rule, guards, reminder timing, both variants.
3. **Summaries:** the recorder, the two tables and endpoints, the static catalog, purge commands, erasure, export; importers report. Tests: items per skip reason, retention, dismiss, ownership, 404.
4. **Label and stale rule:** `migrated_at`, resources, `UntouchedImport` in the four places. Tests: label on both sides, stale exemption until the first move, the move starts the clock.
5. **Email change and admin actions:** `identity_email_changes`, the self-service endpoints, admin correction, two-factor reset, support actions, audit, notices. Tests: neutral 202, code attempts, expiry, Google-only second code, domain limit, revoke of tokens, audit, last-admin flag, authorization.
6. **New importers:** saved jobs, job alerts, saved candidates (with the `legacy` source rules in TalentPool and the `me/talent-pool-memberships` delete). Tests through the real API, idempotent re-run, unavailable jobs, paused alerts, contact actions refused.
7. **Report and D29 API:** the report use case, filters on `admin/users`, console report, D2 card data.

Checks are the user's step after each part (`CLAUDE.md`); `composer check` runs once after part 7. The front end needs A8, B6, J19, C33, D29 and the card and label changes of section 13; the copy of Appendix A is the starting text of those pages.

## 16. Questions and answers

Asked and answered by the user on 2026-10-20, in Indonesian; the recommendation was chosen each time.

| # | Question | Answer |
|---|---|---|
| 1 | Activation email: which accounts and what content (finding: the old code lives 15 minutes and only 841 of 3,719 accounts got it) | One email without a code for every active verified account, two variants, link to a landing page (section 3) |
| 2 | Pre-freeze notice timing and channels | 14 days and 3 days; banner and newsletter; companies called (section 2) |
| 3 | Lead time of the company owners | 3 working days |
| 4 | Batch size and schedule | Pilot 200, then 500 a day, stop rule 2% bounces / 0.1% complaints |
| 5 | Unverified addresses and reminders | Verified first, unverified last, one reminder after 7 days |
| 6 | Scope of the hold rule | Scheduled emails only; person-caused emails and in-app notices are not held |
| 7 | Form and lifetime of the first-sign-in summary | Dismissable card plus archive page, 90 days from the first sign-in |
| 8 | Stale mark and time in stage of imported applications | Excluded until the stage is first changed |
| 9 | How an account is known as imported (schema assumption) | New column `identity_users.imported_at` |
| 10 | Where the password is asked in the email change; Google-only accounts | At the confirmation; Google-only accounts add a code to the old address |
| 11 | Company domain limit on a self-service email change | Applies; the platform admin may bypass it |
| 12 | Who gets the admin two-factor reset | Company members and job seekers |
| 13 | Two admins for a correction or reset | No: one admin, reason and identity-check note required |
| 14 | Job-alert mapping | One daily alert per account; switch off becomes paused |
| 15 | What a company sees of a legacy saved candidate | Name, category, saved date only |
| 16 | Import the profile-view log | No |
| 17 | The written identity check | Two proofs on different channels, no ID photo (Appendix B) |
| 18 | Day 0 and target of the 30/60/90 report | First activation email; no target |
| 19 | Support contact for the texts | Two new admin settings, filled before the first batch |
| 20 | Defaults a to j chosen by the design without asking (listed below) | Approved as they are |
| 21 | Review of the drafts of Appendix A and B | Approved as they are; the placeholders are filled at sending |

**Defaults chosen without asking** (approved by the user on 2026-10-20, row 20):

a. The email-change request always answers 202 and an address that belongs to another account is told by email, never in the response (no account-existence leak).
b. The held email is skipped and not queued; the alert cursor moves to the first sign-in.
c. Summaries hold counts per code and static notes come from a catalog; no personal content is stored.
d. A job seeker can remove themselves from the legacy private lists of companies (`DELETE me/talent-pool-memberships?source=legacy`) and is told how many there are.
e. Legacy private-list entries are deleted 24 months after the import when nothing else gives them a basis.
f. `identity_activation_emails` has no foreign key (a delivery log, as the project rule says); the other new tables have RESTRICT keys.
g. Resend of the activation email, a password reset code and the verification code by an admin are in scope with the correction (MG-C04, API need of 5.4).
h. The report is computed live; no snapshot tables.
i. The expiry notice and the activation email count as essential for the suppression listener, except that suppressed addresses are skipped by the command.
j. The activation and security emails need a line in the legal review (`docs/legal-review.md`): the legal basis of an email to a person who has not accepted the new terms (a service notice about a change of the controller's platform). Add the question before the legal review is sent.

## 17. Not in this design

Decisions 3, 4, 7, 9, 10, 11, 16 and 17 of section 5.6 (job snapshot copy, analytics, one company per account, reports after the cut-over, approval escalation, merge of reference items, deletion and suspension listeners) are item-by-item designs; only the `migrated_at` column of section 6 is shared with decision 9. The legal review, the SMTP provider, the final domain and the freeze date stay with the user (`docs/HANDOFF.md`, "Items waiting for the user"). The texts of Appendix A contain placeholders for the freeze date, the site addresses, the sender and the support contact.

## Open points

None. The user answered every question, approved the defaults a to j and the drafts (2026-10-20). Waiting for the user, outside this design: the freeze date, the SMTP provider, the final domain and sender address, the values of the support settings, and the legal review (point j of section 16).

## Coded

### Part 1: marker and hold (2026-10-20, branch `phase-8-activation`)

Migration `000177_add_imported_at_to_identity_users` (new; backfills `imported_at` from `legacyimport_id_map`, entity `user`). Interpretations made while coding:

- **First-sign-in signal.** `User::recordSignIn` now returns true on the first sign-in, and `SignedIn` carries `firstSignIn` (default false), set by the five user sign-in paths (password, two-factor, Google, cancelled deletion, company member). Registration does not set it: a new account has no imported alerts. The alert listener (`StartAlertsWhenUserSignsInFirst`, queued) moves the cursor of **every** alert of the account forward to that moment; no "imported" flag is needed, because an account cannot create an alert before its first sign-in. The cursor only moves forward.
- **Only the `email` channel is held.** Push is left alone (a device is registered by signing in). `isEnabled` and `enabledAmong` check `AccountActivation` only for a `scheduled` key.
- **Scheduled keys:** `recommendation`, `profile_reminder`, `job_alert` (digests and immediate alerts), `chat_message` (unread email), `team_daily_digest`. Other keys are unchanged.
- **Expiry notice: no check added.** `NotifyAssigneesOfExpiry` writes an in-app notification only and no email exists for it, and in-app notifications are never held (section 4), so nothing was changed there. Section 4 says it "asks `AccountActivation` directly"; if an expiry email is added later, it must ask.
- The importer sets `imported_at` to the import time (the clock of the run). The domain `User` does not carry the field yet (only the model and a repository read, `findHeldIds`); the admin resources of part 7 add it.
- Needs a second rehearsal together with parts 3, 4 and 6 (the account importer changed).

### Part 4: label and stale rule (2026-10-20)

Migration `000180_add_migrated_at_to_recruitment_applications` (new; partial index, backfilled from the profile snapshot). `ApplicationsImporter` sets `migrated_at` to the import time, so this part needs the second rehearsal (with parts 1, 3 and 6). Interpretations made while coding:

- **One rule, two forms.** `UntouchedImport::applies(migratedAt, stageEnteredAt)` (Recruitment Application) and `UntouchedImportScope::exclude` (the SQL form, Infrastructure) say "imported and `stage_entered_at <= migrated_at`"; a unit test and the feature test keep them together. The four places: the stale **mark** on the list rows (`toRow`), the stale **filter** (`filter[stale]`), the `stale_applications` **task count** (which also feeds the team digest line) and the stale mark of the **detail** (`GetCompanyApplication`).
- **Time in stage.** `days_in_stage` is `null` (not a number) on an untouched import, on the list and on the detail; `stage_entered_at` keeps its value. Sorting by `days_in_stage` still uses `stage_entered_at` (an untouched import sorts by its import-time stage entry); the design asks nothing more.
- **Label.** `migrated` and `migrated_at` on the company list row and detail, and on the job seeker list row and detail. They stay after the first move; only the stale rule and the "—" end. The existing `applicant.migrated` of the snapshot is unchanged.
- The explanation text (A12) is the client's; no server text was added. Other places that read `stage_entered_at` (the list sort, the stage-time of the history) were left as they are.

### Part 3: import summaries (2026-10-20)

Migration `000179_create_import_summaries` (new; `jobseeker_import_summaries`, `company_import_summaries`). The importers changed, so this part needs the second rehearsal (with parts 1, 4 and 6). Interpretations made while coding:

- **Recorder.** `ImportSummaryRecorder` (in `ImportContext`) collects counts per code (and per reason) keyed by the new user or company id while the importers run; `ImportRunner` writes them once at the end of a **real** run through `ImportSummaryFlusher` and two writers (`DatabaseJobSeekerSummaryWriter`, `DatabaseCompanySummaryWriter`, one per entity). A code replaces the stored item with the same code and other stored items stay, so a repeated run does not count twice (a row re-reported as skipped gives the same count; a re-run that imports nothing new reports nothing and changes nothing). Every imported job seeker and every imported company gets a row, also with no items, so the static notes always show.
- **Codes written now** (job seeker): `password_reset_needed`, `resume_skipped` (detail = the reason code of the report: `file-missing`, `too-large`, `not-a-pdf`, `files-root-not-configured`), `avatar_skipped` (detail = reason), `skills_skipped`, `languages_skipped` (added: a language with no match; the design lists only `languages_defaulted`), `languages_defaulted`, `experience_skipped`, `education_skipped` (detail = reason), `gpa_emptied`, `links_skipped` (a link that is empty or too long; a link of an account without a profile is not reported), `applications_imported` (count, detail `withdrawn_duplicates` and `no_resume`). A duplicate skill, language or other list row is **not** reported (nothing is lost). Company: `jobs_imported` (detail = the status the job got), `jobs_draft_incomplete` (detail = the missing field), `jobs_closed_apply_outside`, `pending_edits_skipped`, `screening_questions_skipped` (detail = reason), `screening_questions_over_limit`, `applications_imported` (count, detail `withdrawn_duplicates`, `group_unmapped`), `verified_badge_kept`.
- **Not written:** `legacy_cv_not_carried` and `education_year_assumed` (no such event exists in the importers: an unreadable year skips the row); `saved_jobs_imported`, `job_alert_imported`, `saved_by_companies`, `saved_candidates_imported` come with the new importers of part 6.
- **Static notes** are codes, not text (like the notifications: the client writes and translates): `terms_again`, `discoverability_off`, `language_level_default`, `chat_history_not_imported`, `profile_views_not_imported`, `activation_email_timing` for job seekers; `terms_again`, `two_factor_enrolment`, `no_verification_documents`, `contact_fields_empty`, `team_members_not_carried`, `screening_answers_not_carried`, `imported_applications_explained`, `saved_candidates_private_list` for companies. Items carry the same codes with counts; the resource is `{kind, imported_at, dismissed_at, items, notes}` (`imported_at` is when the summary was written).
- **Retention** (`identity.import_summary_retention_days`, 90, 7 to 730): the nightly purges (`job-seeker:purge-import-summaries` 03:30 Jakarta, `company:purge-import-summaries` 03:45) read the first sign-in through a new method `FirstSignIns::firstSignedInAt`; a job seeker's from their own, a company's from its **owner** (a company without an owner, or an owner who never signed in, keeps its summary).
- **Erasure and export.** Anonymising a job seeker deletes the row (`EraseJobSeekerData`); the data export has a part `import_summary`. The company summary is not touched by an account erasure (it belongs to the company; `dismissed_by_user_id` keeps pointing at the anonymised account).
- **API.** `GET me/import-summary`, `POST me/import-summary/dismiss` (job seekers), `GET company/import-summary`, `POST company/import-summary/dismiss` (company admins, active company): 404 `no-import-summary`; dismissing twice keeps the first time (and the first member). `GET me` has `imported_from_legacy` (the domain `User` now carries `importedAt`). Error catalog regenerated (it also gained the five `activation-*` codes of part 2).

### Part 2: activation email, command and settings (2026-10-20)

Migration `000178_create_identity_activation_emails` (new). The old command `identity:send-imported-password-resets`, its use case, report, test and `UserRepository::findWithoutPassword` are removed; checklist section 3 and `HANDOFF.md` are rewritten (the history in `phase-8-platform-admin.md` still names the old command). No endpoint: the support actions (resend activation, password reset, resend verification) belong to part 5. Interpretations made while coding:

- **Four template keys, not three:** `activation.job_seeker` (old password works), `activation.job_seeker_no_password`, `activation.company`, `activation.reminder`; a template has one body, so the two job-seeker variants are two keys. The company template shows `{{support_contact}}` (support address, plus the WhatsApp number when set) in place of the two placeholders of the draft; the reminder is the same text for every audience.
- **Settings:** `identity.activation_reminder_days` is an integer (1 to 60); the two limits and the date are **text** settings (there is no decimal or date type): `activation_bounce_limit_percent` (`2`), `activation_complaint_limit_percent` (`0.1`), `activation_job_seekers_not_before` (`YYYY-MM-DD`, empty = refuse). A non-numeric limit refuses a real run (`activation-limit-invalid`). `platform.support_email` and `platform.support_whatsapp` are registered by Platform. `identity.import_summary_retention_days` comes with part 3.
- **Links:** `.env` `JOB_SEEKER_ACTIVATION_URL`, `COMPANY_ACTIVATION_URL` and a third one the draft needs, `JOB_SEEKER_SIGN_IN_URL` (`{SIGN_IN_URL}` of A6). A real run is refused while the one it needs is empty (`activation-url-missing`).
- **Guards apply to a real run; a dry run reports them** ("A real run would be refused: ...") and still counts. The date guard and the stop rule are skipped for the staff test. The stop rule looks at the **latest automatic batch of the same audience** (activation or reminder; staff tests are ignored): bounces (`hard_bounce`) and complaints among the suppressed addresses of its accounts, as a percentage of the accounts it went to, strictly above the limit refuses.
- **Selection and order:** imported, never signed in, not anonymised, then split into suppressed, deactivated or suspended, already sent (same kind), not due (reminder: no activation email, or younger than the reminder days), and sendable. **Unverified addresses are sent only when no verified one is left** (so a batch never mixes them); the report counts them as held back meanwhile. Order is by user id.
- **Batch label:** `batch|pilot|reminder|staff-test` + audience + run time (`--pilot` is a flag, not a name). **Staff test:** `--emails=<file>` (one address per line) logs kind `manual` rows, which do not use up the real activation and may be repeated.
- **Sending:** the email is queued like the other account notices (`AccountNoticeMail`, `default` queue), not sent inline; `--delay` paces the loop. A row is reserved in the log first (unique partial index on `activation` and `reminder`), released if handing it to the mailer fails.
- The company name comes through `CompanyMembershipResolver` and `CompanyDirectory`; an owner without a membership gets an empty name.
- The log keeps its rows when an account is anonymised (ids only, no personal data); `CrossContextForeignKeysTest` lists it among the tables without a foreign key.

### Fix MG-A08 / MG-B05: withdrawal by the system (2026-10-20, tied to the migrated applications MG-A09/MG-B06)

`withdrawal.by` of `GET me/applications/{id}` and `GET company/applications/{id}` now returns `applicant`, `recruiter` or `system` (it mapped every non-recruiter to `applicant`, so the older duplicates the import withdraws read as "the candidate withdrew"). On the company side the `note` of a system move is the history entry's note, never the applicant's withdrawn note; `actor` stays null for it. Test: `TeamWithdrawalTest`. API docs cache cleared and rebuilt.

## Appendix A: drafts for review

Indonesian is the language of these texts (the project writes everything else in English); each draft has a one-line English gloss. Placeholders: `{FREEZE_DATE}`, `{NEW_SITE_URL}`, `{SIGN_IN_URL}`, `{ACTIVATION_URL}`, `{SENDER_ADDRESS}`, `{SUPPORT_EMAIL}`, `{SUPPORT_WHATSAPP}`, `{REMINDER_DAYS}`, `{NOTICE_URL}`, `{COMPANY_NAME}`, `{NAME}`. The tone is plain and calm: the sender and the address are named, nothing is urgent, no password or code is asked for by email.

### A1. Banner on karir.pkp, 14 days before (gloss: the move is announced, with what does not move)

> **karir.pkp akan pindah ke ONESIS Career pada {FREEZE_DATE}.** Akun, profil, CV, lamaran, lowongan, lowongan tersimpan, dan pengaturan job alert Anda ikut dipindahkan. Riwayat pesan (chat) dan riwayat siapa melihat profil **tidak** ikut dipindahkan. Anda tidak perlu melakukan apa pun sekarang; kami akan mengirim email dari {SENDER_ADDRESS}. [Selengkapnya]({NOTICE_URL})

### A2. Banner, 3 days before (gloss: reminder, same facts, shorter)

> **Tiga hari lagi:** pada {FREEZE_DATE} karir.pkp pindah ke ONESIS Career dan situs ini hanya dapat dibaca. Simpan dulu percakapan chat yang masih penting, karena riwayat chat tidak ikut dipindahkan. Email dari {SENDER_ADDRESS} akan memandu Anda masuk. [Selengkapnya]({NOTICE_URL})

### A3. Newsletter, 14 days before (gloss: the full explanation for job seekers and companies)

Subject: `karir.pkp pindah ke ONESIS Career pada {FREEZE_DATE}`

> Halo,
>
> Pada **{FREEZE_DATE}** karir.pkp pindah ke rumah baru, **ONESIS Career** ({NEW_SITE_URL}). Tampilannya baru, pencarian lowongan lebih lengkap, dan proses melamar lebih jelas bagi pencari kerja maupun perusahaan.
>
> **Yang ikut pindah:** akun dan profil Anda (pengalaman, pendidikan, keahlian, bahasa, tautan), CV yang berupa berkas PDF, lamaran yang pernah Anda kirim, lowongan tersimpan, dan pilihan job alert Anda. Untuk perusahaan: profil perusahaan, lowongan beserta pelamarnya, dan daftar kandidat tersimpan.
>
> **Yang tidak ikut pindah:** riwayat pesan (chat) dengan perekrut atau kandidat, serta riwayat siapa yang melihat profil. Jawaban pelamar atas pertanyaan seleksi tidak pernah tersimpan di karir.pkp, sehingga tidak dapat dipindahkan. Lowongan yang melamar lewat email atau tautan luar dipindahkan sebagai lowongan *ditutup*; perusahaan dapat membukanya kembali.
>
> **Yang perlu Anda lakukan:** tidak ada sekarang. Setelah pemindahan, Anda akan menerima **satu email** dari {SENDER_ADDRESS} berisi cara masuk. Sebagian akun perlu mengatur kata sandi baru; Anda juga akan diminta menyetujui ketentuan penggunaan yang baru, dan profil Anda tidak terlihat oleh perusahaan sampai Anda sendiri mengaktifkannya.
>
> **Keamanan:** kami tidak pernah meminta kata sandi atau kode Anda lewat email, WhatsApp, atau telepon. Email resmi kami hanya berasal dari {SENDER_ADDRESS}.
>
> Pertanyaan? Hubungi {SUPPORT_EMAIL}.
>
> Tim ONESIS Career

### A4. Newsletter, 3 days before (gloss: short reminder)

Subject: `Tiga hari lagi: karir.pkp pindah ke ONESIS Career`

> Halo, pada **{FREEZE_DATE}** karir.pkp pindah ke ONESIS Career ({NEW_SITE_URL}). Mulai hari itu situs lama hanya dapat dibaca. Simpan lebih dulu percakapan chat yang masih Anda butuhkan: riwayat chat tidak ikut dipindahkan. Anda akan menerima satu email dari {SENDER_ADDRESS} untuk masuk; kami tidak pernah meminta kata sandi atau kode lewat email. Bantuan: {SUPPORT_EMAIL}.

### A5. Maintenance page on karir.pkp at the freeze (gloss: what happens now and when to expect the email)

> **karir.pkp sedang dipindahkan ke ONESIS Career.** Situs ini hanya dapat dibaca. Email berisi cara masuk akan dikirim dari {SENDER_ADDRESS} dalam beberapa hari ke depan; lowongan sudah dapat dilihat di {NEW_SITE_URL}. Bantuan: {SUPPORT_EMAIL}.

### A6. Activation email, job seeker with a password (variant `has_password`)

Subject: `Akun karir.pkp Anda kini ada di ONESIS Career`

> Halo {NAME},
>
> Seperti kami kabarkan, karir.pkp sudah pindah ke **ONESIS Career**. Akun Anda dengan email ini sudah dipindahkan beserta profil, lamaran, lowongan tersimpan, dan pilihan job alert Anda.
>
> **Masuk:** buka {SIGN_IN_URL} dan masuk dengan email ini dan kata sandi lama Anda. Jika kata sandi lama tidak berhasil, atur kata sandi baru di {ACTIVATION_URL}.
>
> Saat pertama masuk, Anda akan menyetujui ketentuan penggunaan yang baru dan melihat ringkasan apa yang sudah dan belum dipindahkan. Profil Anda **tidak terlihat** oleh perusahaan sampai Anda sendiri mengaktifkannya di pengaturan.
>
> Sampai Anda masuk, kami tidak mengirim email rekomendasi atau pengingat lain; Anda hanya akan menerima satu pengingat jika belum masuk dalam {REMINDER_DAYS} hari.
>
> Kami tidak pernah meminta kata sandi atau kode Anda lewat email, WhatsApp, atau telepon. Tidak merasa mendaftar di karir.pkp? Abaikan email ini. Bantuan: {SUPPORT_EMAIL}.
>
> Tim ONESIS Career

### A7. Activation email, job seeker without a password (variant `no_password`)

Subject: `Aktifkan akun karir.pkp Anda di ONESIS Career`

> Halo {NAME},
>
> karir.pkp sudah pindah ke **ONESIS Career**. Akun Anda dengan email ini sudah dipindahkan beserta profil, lamaran, lowongan tersimpan, dan pilihan job alert Anda. Karena alasan teknis kata sandi lama tidak dapat dibawa, Anda perlu **mengatur kata sandi baru** satu kali.
>
> **Aktifkan akun:** buka {ACTIVATION_URL}, masukkan email ini, lalu pilih "Atur kata sandi baru". Kami akan mengirim kode 6 digit ke email ini pada saat itu (berlaku 15 menit), jadi Anda dapat membuka tautan kapan saja.
>
> Saat pertama masuk, Anda akan menyetujui ketentuan penggunaan yang baru dan melihat ringkasan apa yang sudah dan belum dipindahkan. Profil Anda **tidak terlihat** oleh perusahaan sampai Anda sendiri mengaktifkannya.
>
> Kami tidak pernah meminta kata sandi atau kode Anda lewat email, WhatsApp, atau telepon. Tidak merasa mendaftar di karir.pkp? Abaikan email ini. Bantuan: {SUPPORT_EMAIL}.
>
> Tim ONESIS Career

### A8. Company: personal message and activation email (variant `company`)

Call script (gloss: the owner hears it from a person first): "Selamat pagi, saya dari tim ONESIS Career. Akun karir.pkp {COMPANY_NAME} sudah dipindahkan. Hari ini Anda akan menerima email dari {SENDER_ADDRESS} berisi cara masuk. Siapkan aplikasi authenticator di ponsel; untuk akun perusahaan, masuk pertama memerlukannya. Kami akan membantu bila ada kendala."

Subject: `Akun {COMPANY_NAME} sudah ada di ONESIS Career`

> Halo {NAME},
>
> Akun karir.pkp **{COMPANY_NAME}** sudah dipindahkan ke **ONESIS Career**: profil perusahaan, lowongan beserta pelamarnya, dan kandidat tersimpan.
>
> **Yang perlu Anda siapkan:** aplikasi authenticator di ponsel (untuk verifikasi dua langkah, wajib bagi admin perusahaan) dan email ini.
>
> **Langkah:** buka {ACTIVATION_URL}, atur kata sandi baru atau masuk dengan kata sandi lama bila masih berlaku, daftarkan authenticator, lalu setujui ketentuan penggunaan. Anda akan melihat ringkasan pemindahan: lowongan yang menjadi draf karena data belum lengkap, lowongan yang ditutup karena sebelumnya melamar lewat email atau tautan luar, dan hal yang tidak ikut dipindahkan (anggota tim, riwayat chat).
>
> Kami menghubungi Anda lebih dulu agar lowongan Anda siap sebelum pencari kerja kembali. Kami tidak pernah meminta kata sandi atau kode lewat email, WhatsApp, atau telepon. Bantuan langsung: {SUPPORT_EMAIL} atau {SUPPORT_WHATSAPP}.
>
> Tim ONESIS Career

### A9. Reminder (kind `reminder`, once, after 7 days)

Subject: `Akun karir.pkp Anda masih menunggu di ONESIS Career`

> Halo {NAME}, akun Anda di ONESIS Career sudah siap, tetapi Anda belum masuk. Masuk atau aktifkan akun di {ACTIVATION_URL}. Ini pengingat terakhir; kami tidak mengirim email lain sampai Anda masuk. Kami tidak pernah meminta kata sandi atau kode lewat email. Bantuan: {SUPPORT_EMAIL}.

### A10. Landing page A8/B6 text

> **Aktifkan akun Anda.** Akun karir.pkp Anda sudah dipindahkan ke ONESIS Career. Masuk dengan kata sandi lama, atau atur kata sandi baru. [Masuk] [Atur kata sandi baru] Kami tidak pernah meminta kata sandi atau kode lewat email, WhatsApp, atau telepon. Bantuan: {SUPPORT_EMAIL}.

### A11. Summary card (J1 and C1) and page lines

> **Selamat datang kembali.** Ini ringkasan apa yang dipindahkan dari karir.pkp. {N} lamaran, {N} lowongan tersimpan, {N} job alert dipindahkan. {N} CV tidak dipindahkan: {reasons}. Profil Anda belum terlihat oleh perusahaan; ubah di Pengaturan. Riwayat chat dan riwayat dilihat tidak dipindahkan. [Lihat selengkapnya] [Tutup]

### A12. Label explanation on an imported application (J5, C7)

> Lamaran ini dipindahkan dari karir.pkp. Riwayat tahapnya hanya mencatat lamaran dan posisi saat pemindahan, dan data profil di dalamnya disalin saat pemindahan, bukan saat lamaran dikirim. Waktu di tahap dan tanda "menunggu lama" mulai dihitung setelah tahap diubah untuk pertama kali.

## Appendix B: identity check procedure

For the admin correction of an email (section 7) and the two-factor reset (section 8). A request is never done on the first message alone. **Two proofs on two different channels; no photo of an identity card is requested or stored** (user).

1. **The company admin or member of a company:** (a) the request comes from an address on the company's own email domain (or is confirmed by a reply from it), **and** (b) a call back, by the platform team, to the phone number on the company profile or the one known from the sales contact, where the person gives the company name and one recent fact only they know (the last job posted, the number of open jobs).
2. **A job seeker:** two of: the phone number on the profile, the date and the company of the last application, the month of the last job on the profile, the date of the account creation; checked against the account, not read out by the admin. The answers must come from the person, the admin does not hint.
3. **Refuse and escalate** when the two proofs disagree, when the request arrives for an account of a company that is suspended, or when more than one change is asked for the same account within 24 hours.
4. **Record** (the `identity_check` field, 5 to 1000 characters): which two proofs, on which channels, when, and the name of the person who answered. No identity numbers, no documents.
5. **After the change:** the system tells both addresses (section 7) and the user signs in with the new address after setting a password through A8/B6; for a two-factor reset, the user enrols again at the next sign-in.
6. **Control:** the super admin reads the weekly list of corrections and resets (D21 filter) and checks that each has a note.
