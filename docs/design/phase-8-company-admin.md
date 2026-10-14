# Phase 8: two-factor sign-in for company members, the company activity log, applicant retention and the company settings page (designed 2026-10-14, not coded)

Gaps found from the company admin's point of view and against Mekari Talenta (help center articles 9096990286233: 2-step verification switched on
when a user becomes an admin; 9326458339993: a company-wide user activity log with export; 50946472825113 and 49066156260889: export log),
chosen by the user from the advice of 2026-10-14. Not taken now: job requisitions with approval, a per-member performance report, an embeddable
career page, self-service closing of a company account and single sign-on. Extends `phase-5-security-review.md` (A2 TOTP),
`phase-6-assessments.md` (psychologist TOTP tables), `phase-8-company-permissions.md` (team, member activity), `phase-1-company.md` (setting
overrides) and the account erasure. Coded in a coding session.

## Decisions

- 2026-10-14: **TOTP for company members**: required for every `company_admin`; a company admin may require it for the whole team; any other member
  may switch it on (chosen by the user).
- 2026-10-14: when two-factor **becomes required** for someone, every token of that account is **revoked** (chosen by the user).
- 2026-10-14: a **company activity log** for company admins, with export; audit entries carry a new **`company_id`** column (schema decided by
  the user).
- 2026-10-14: **applicant retention per company**: a final application is **anonymised on the company's side** after the company's period; the job
  seeker keeps the bare history (chosen by the user).
- 2026-10-14: one **company settings page** where the company admin changes the settings that belong to the company (chosen by the user).

## 1. Two-factor sign-in for company members

- **Storage:** the user tables of the psychologists' TOTP (`identity_user_two_factor`, `identity_user_recovery_codes`,
  `identity_user_two_factor_challenges`, migration `000117`) serve company members too; no new table. Same library, encrypted secret, replay guard,
  10 recovery codes, nightly purge.
- **Required** when the member's role is `company_admin` or the company setting `company.require_two_factor` is on (section 4). Otherwise
  optional. Job seekers are not affected.
- **Sign-in:** `POST auth/login` and the Google sign-in, for a company member with a confirmed factor or a required one, answer
  `{status: two_factor_required | two_factor_enrolment_required, challenge_token, enrolment?}` (5-minute challenge, single use; enrolment carries
  `secret`, `otpauth_url`, `qr_code`) instead of the token. New `POST auth/two-factor {challenge_token, code | recovery_code, device_name}` answers
  the usual 30-day token (and the recovery codes once at enrolment). Wrong codes count in the A1 throttle. Accepting a company admin invitation
  ends in the enrolment challenge, not a token.
- **Own account:** `GET me/two-factor` (`enabled`, `required`, `recovery_codes_left`); `POST me/two-factor` (start enrolment: secret and QR);
  `POST me/two-factor/confirm {code}` (recovery codes once); `DELETE me/two-factor {password, code}` (409 `two-factor-required` when required);
  `POST me/two-factor/recovery-codes {code}`. The `me/*` routes are those of C25, shown to company members only.
- **Becoming required** (promotion to `company_admin`, the company setting switched on, and the rollout migration for every existing company admin):
  for each affected account without a confirmed factor, every token is revoked in the same transaction and the email
  `identity.two_factor_required` says that the next sign-in asks for enrolment. An account that already has a factor keeps its tokens.
- **Reset:** `POST company/members/{userId}/reset-two-factor` (company admin; another company admin's factor only by the owner; never one's own,
  409 `cannot-reset-self`): removes the factor and the recovery codes and revokes the tokens; audit `company.member_two_factor_reset`, in-app notice
  to the member. A platform admin resets any user under `users.manage` (`POST admin/users/{id}/reset-two-factor`, `phase-8-user-management.md`).
  A lone company admin who lost the device asks the platform; until that endpoint exists the console `identity:reset-user-two-factor`
  (`phase-8-platform-admin.md` section 4, coded with this section) does it.
- **Team list:** each member row gains `two_factor: on | off | pending` (`pending`: required, not enrolled yet); the member detail shows the same.
- **Audit:** the existing `auth.two_factor_enabled`, `auth.recovery_code_used`, `auth.recovery_codes_regenerated`, `auth.two_factor_reset`, plus
  `auth.two_factor_disabled`.

## 2. Company activity log

- **Schema:** `platform_audit_logs.company_id` (nullable bigint, no foreign key across contexts, index `(company_id, occurred_at)`). The recorder
  fills it when the actor is a company member, through a `Shared` port `ActorCompany` provided by Company (the member's company at the time of the
  action); platform admin actions on a company (suspend, restore, documents, setting overrides, invitations) pass it explicitly. The migration fills
  existing rows from the current memberships (best effort; rows of removed members stay empty).
- **New audit entries for data access** (actor the member, subject and `company_id`): `application.resume_downloaded`,
  `candidate.resume_downloaded` (candidate search), `assessment.report_downloaded`, `interview.recording_downloaded`, `offer.letter_downloaded`.
  Existing: `export.applicants`, `export.hires`, `auth.login`, `auth.login_failed` (now with `company_id`, so failed sign-ins of members show).
- **`GET company/activity`** (role `company_admin`, `company-admin-required`): entries with the company's id, newest first, cursor paginated;
  `filter[user_id]`, `filter[group]` (`sign_in`, `security`, `data_access`, `export`, `team`, `jobs`, `settings`), `filter[from]`, `filter[to]`
  (within the 12-month retention). Each entry: time, actor (name; "Admin platform" without a name), action, subject with a readable title while it
  exists, IP address and user agent (as the member activity). `GET company/members/{userId}/activity` becomes this list with `filter[user_id]`;
  the team history stays as it is.
- **`GET company/activity/export`** (same filters): streamed CSV under `phase-2-export.md` (byte order mark, formula guard, 10 per hour); audit
  `company.activity_exported`.
- `docs/legal-review.md` question 10 gains the company-wide log and its export.
- Front end: "Log aktivitas" page for company admins (new C27), with the group filter, the member filter and the export button.

## 3. Applicant retention

- **Setting:** `recruitment.applicant_retention_months` (company, default **24**, 6 to 60), changed on the settings page (section 4). The period
  waits for `docs/legal-review.md` question 15; the default may change with the answer.
- **Due date** of an application: its final date plus the period. The final date is `stage_entered_at` (`phase-8-recruiter-workday.md` section 2)
  for an application in `rejected` or `withdrawn`, and the job's close date for one still active on a closed job. **Never** for `hired` (it becomes
  employee data) or while a live talent pool entry points at the application (`phase-8-talent-pool.md`; its own 24 months apply).
- **What goes** (nightly `recruitment:apply-retention`, 03:30 Jakarta, batches of 500): on the company's side, the same content as the account
  erasure but per application. A `Shared` registry `ApplicationErasureStep` (like `ErasureStep`, keyed by application id) runs in Recruitment
  (snapshot, cover letter, resume copy, free-text answers, notes, ratings' comments, scorecards' text, interview recordings, rejection and withdrawal
  notes, offer documents except an accepted offer's evidence), Assessment (answers, videos, the stored report PDF) and Messaging (the conversation of
  the company with the person, when no other application of that person to the company is still within its period). Files first, then rows, as
  `EraseApplicantData`. Mark: new column `recruitment_applications.company_erased_at`.
- **What stays:** the row, the job, the stage history (types, dates, reason codes), the source and the match score, so the BI counts do not change.
  The company sees "Data pelamar dihapus (retensi)" in place of the person; search and filters skip the row.
- **Job seeker:** keeps the history (job, company, stage, dates) on their application list; the detail says the company has deleted the
  application data under its retention. No notification.
- **Shown to the company:** the application detail of a final application shows `retention_due_on`. Audit: one `recruitment.retention_applied`
  per company and night (`{count}`), no names.
- The legal review gains question 15: the default period, whether a company may choose it, and what may stay after the anonymisation.

## 4. Company settings page

- `SettingDefinition` gains `companyEditable` (implies `overridable`). Company-editable now: `company.require_two_factor` (default off),
  `recruitment.stale_after_days` (`phase-8-recruiter-workday.md`), `recruitment.applicant_retention_months`. A key joins the page when the
  design that owns it is coded (sections 1, 2 and 4 come before the recruiter workday and section 3).
- **`GET company/settings`** (any member reads): each company-editable key with its value, the platform default and the allowed range.
  **`PATCH company/settings {key: value, ...}`** (role `company_admin`): validated by the definition (422 per key), saved as the company override (the
  storage of D6, so a platform admin still sees and resets it there); unknown or not company-editable keys 422 `setting-not-editable`. Switching
  `company.require_two_factor` on runs section 1 "Becoming required" for the team. Audit `company.settings_updated` (before and after, `company_id`).
- **One page** "Pengaturan perusahaan" (C20 gains a tab, company admin) with three parts that call the existing endpoints too: **Keamanan**
  (require two-factor; allowed email domains, `company/team/email-domains`), **Proses** (stale days; message defaults, `company/message-defaults`),
  **Data** (retention period with the due-date explanation).

## Changes in short

| Piece | Change |
|---|---|
| Migration | `platform_audit_logs.company_id` (filled from memberships); `recruitment_applications.company_erased_at`; the rollout revokes the tokens of company admins without a factor |
| Settings | `company.require_two_factor`, `recruitment.applicant_retention_months`; `companyEditable` on the definition |
| Ports | `ActorCompany` (Company); `ApplicationErasureStep` registry (Shared; Recruitment, Assessment, Messaging steps) |
| API | `POST auth/two-factor`; challenge answers on `auth/login` and Google sign-in; `me/two-factor` (status, enrol, confirm, disable, recovery codes); `POST company/members/{userId}/reset-two-factor`; `two_factor` on members; `GET company/activity` and export; `GET`/`PATCH company/settings`; `retention_due_on` |
| Scheduler | `recruitment:apply-retention` (03:30 Jakarta) |
| Emails and notices | `identity.two_factor_required` (email); member two-factor reset (in-app) |
| Audit | `application.resume_downloaded`, `candidate.resume_downloaded`, `assessment.report_downloaded`, `interview.recording_downloaded`, `offer.letter_downloaded`, `auth.two_factor_disabled`, `company.member_two_factor_reset`, `company.activity_exported`, `company.settings_updated`, `recruitment.retention_applied` |
| Errors | 409 `two-factor-required`, `cannot-reset-self`; 422 `setting-not-editable` |
| Legal | question 10 extended; new question 15 (retention) |
| Front end | two-step sign-in and enrolment for company members; two-factor in the account settings (C25); reset and status on the team page (C22); "Log aktivitas" (C27); "Pengaturan perusahaan" tab (C20); retention notice on the application detail |
