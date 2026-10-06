# Phase 1: Data migration from karir.pkp (step 9, design confirmed 2026-10-07, not coded yet)

Status: design confirmed by the user on 2026-10-07 (every answer is recorded below); coding has not started.
Inputs that already exist: `decisions.md` section 5 (production data migration: yes, all core data: accounts, profiles, resumes, jobs, applications, master data; old password hashes kept if compatible; **scripts
and tests are written from the schema and synthetic data; running them against real data, which reads personal data from production, needs the user's explicit approval at that time; read from production only,
write to `jobportal_v2`**), the legacy findings (`docs/analysis/legacy-findings.md`) and the production schema, read through the read-only account (schema only, never data without approval).
Questions deferred by the user to this step: the mapping of `applied_jobs` (jobs with a NULL `job_id`, the legacy pipeline groups, the profile snapshot, duplicates, `answers`, long cover letters, the history)
and legacy jobs that apply by URL or email.

## Confirmed decisions

- **Tooling (2026-10-07):** **artisan commands, one importer per entity**, in a dedicated context `LegacyImport` (no API). Each importer is **idempotent** through an id-map table (legacy id to new id), so it is safe to
  run again; it has a **`--dry-run`** mode that only reports; it works in batches and reports how many rows it read, imported and skipped, with the reason for each skipped one. Tests use synthetic legacy tables
  (schema only) in the test database.
- **Scope (2026-10-07):** what has tables in the new schema today: accounts (job seekers and company members), companies and their members, job seeker profiles (experience,
  education, skills, languages, links) and resume files, jobs (skills, tags, benefits, assignees), screening questions and their attachment to jobs, and applications. Master data and locations were already seeded from
  legacy (with the user's approval). Features of Phases 2 to 4 (bookmarks, job alerts, chat, newsletter, campaigns) bring their own importers when they are built. Plans, orders and payments are not migrated (out of scope).
- **Which accounts (2026-10-07):** every legacy `users` row **except demo accounts** (`is_demo_field = 1`). Inactive accounts (`status = 0`) become `deactivated`; accounts whose email was never verified stay unverified
  (they verify before applying, as in the new rules). A legacy row count is not needed to write the scripts; it is read only in the approved run.
- **Passwords and social logins (2026-10-07):** a legacy password that is a valid **bcrypt hash** is copied as it is; any other value (empty, another format) becomes no password, and the owner uses the password
  reset or Google (which links by an email Google vouches for, an existing rule). `google_id`, `facebook_id`, `firebase_uid`, `provider` and `provider_id` are **not copied**: no legacy identity is trusted.
- **Legacy jobs that apply by email or URL (2026-10-07):** a job with `apply_on` = `email` or `custom_url` is imported with the status **`closed`** (history for the company; not public, takes no applications; the company may
  reopen it to take applications through the portal). The apply email and URL are not carried (applications go only through the portal).
- **Company status and badge (2026-10-07):** a legacy company becomes `active`; one with `document_verified_at` set or `is_profile_verified = 1` keeps the verified badge (`verified_at` = the legacy time, or the import
  time when only the flag is set), written directly by the importer (the application code never writes `verified_at` outside the document flow). Legacy has **no verification documents** (no table for them), so none
  are migrated; an admin can ask a company to upload documents later.
- **Job status and pending edits (2026-10-07):** `pending` becomes `pending_review`, `active` stays `active`, `expired` stays `expired` (a job that applies by email or URL is `closed`, decided above). A row with a
  `parent_job_id` (a copy waiting for edit approval) is **not imported as a job of its own**; its parent is imported with its own content, and the skipped copy is reported as "pending edit skipped" so the company can
  edit again. Featured and highlight promotion fields are not carried (out of scope).
- **Legacy admins (2026-10-07):** **not migrated.** Admins are created again with `identity:create-admin` (password from a hidden prompt) and the new roles; no account with full rights is inherited silently, and the
  legacy roles have no direct counterpart in the new permission catalog.
- **Resume files (2026-10-07):** a legacy resume is copied to the private disk under a random name **only when it passes the new rules**: a valid PDF (checked by content), the simple scan for encryption, scripts,
  launch actions and embedded files, the admin size limit (`job_seeker.resume_max_size_kb`), and at most **five** per user (the newest). Every other one is skipped and reported with its reason (not a PDF, failed
  the scan, too large, file missing, over the limit of five) so the owner can upload it again.
- **Images (2026-10-07):** profile photos (`users.image`, `candidates.photo`), company logos and banners are carried **through the new image processor** (content check, re-encoding, the same size rules as a new
  upload, private disk, random name); the default image (`default.png`) means no image; a broken or oversized image is skipped and reported. No raw legacy image enters the new storage.
- **Profile visibility (2026-10-07):** `is_discoverable` and `resume_discoverable` start **off** for every migrated profile (opt-in); the legacy `visibility` and `cv_visibility` are not carried (their default was
  on, so they say nothing about a choice). An application already sent stays visible to the company it went to.
- **Languages (2026-10-07):** a legacy language name is matched (any letter case, English or Indonesian name) to the `reference_languages` master; a match is carried with the proficiency **`conversational`** (a
  neutral middle value, because legacy has no level; the owner can change it); a name with no match is skipped and reported by name, so an admin can add the master item if it is worth having. No master item is
  created by the migration.
- **Experience and education rows (2026-10-07):** the new rules apply to migrated rows too. Tidying that cannot change the meaning is done automatically (trimming, cutting a long text to its limit, reading a date in
  a common format). A row that is still invalid (an unreadable date, an end before the start, a required field empty) is skipped and reported with its reason; an optional field that is invalid (for example a GPA outside
  0 to 4) is emptied instead of dropping the whole row.
- **Screening questions (2026-10-07):** every legacy `company_questions` row becomes a **text** question in its company's library (the text 5 to 300 characters; others are skipped and reported). `reuse = 0`
  (made for one job) is **archived at once**, so it does not fill the library but stays attached to its job. `required` becomes the per-job required flag on the attachment (`company_question_job`). A job with
  more than five questions is imported whole (the limit applies only when editing) and reported. No ideal answer and no must-have. Legacy answers are not migrated (finding B8 says they were never stored; this is
  checked in the approved run).
- **Legacy pipeline groups (2026-10-07):** the importer reads a **mapping file** (legacy group id to a stage type, optionally creating an extra stage of the same name for the types `screening`, `interview` and
  `offer`). In the approved run the group names (company configuration, not personal data) are shown, the user fills in the mapping, then the import runs. An application in a group with no mapping goes to the company's
  default `applied` stage and is reported. The legacy `order` (position inside a column) has no counterpart and is not carried.
- **Snapshot of a migrated application (2026-10-07):** legacy has none, so it is **built from the migrated profile at import time** (same fields and exclusions as a normal snapshot: no gender, religion, marital
  status, nationality, birth date or avatar; the age as of the import) and marked `migrated: true` with the import time, so it is honest that it is not the state at the time of applying. An applicant without a
  migrated profile gets a snapshot with the name and email only.
- **Duplicate applications (2026-10-07):** for one applicant and job, the **newest** application keeps its mapped stage and the unique key; older ones are imported as **`withdrawn`** (history, no note, nobody is
  notified). An application with no job (`job_id` NULL: the company cannot be derived) or from an account that is not migrated cannot be imported and is reported.
- **Cover letter and history of a migrated application (2026-10-07):** the cover letter is imported **whole** (the 2,000 character limit is an input rule; old applications are only read). The history gets one
  first entry `applied` by the applicant at the time of applying and, when the mapped stage is not `applied`, one entry by the system to that stage at the import time (it does not claim when it was really moved).
  The resume copy is made from the migrated resume file when it exists and passed the file rules; otherwise the application has no resume.
- **Expected salary (2026-10-07):** the legacy number is carried with the period **`monthly`** (the common case; it cannot be verified from the schema). A number that is clearly not a monthly salary (below
  100,000 or above 1,000,000,000) is emptied and reported.
- **Education year (2026-10-07):** the single legacy `year` is read as the graduation year; the entry is imported with `start_year` = `end_year` = `year` (no invented range) and marked in the report so the owner can
  correct it. A `year` that cannot be read as a four-digit year skips the entry (a required field).
- **Rich text (2026-10-07):** a legacy job description (HTML from a rich-text editor) is converted to **Markdown** (paragraphs, lists, bold and italic, headings and safe links are kept) with a small conversion
  library (a new dependency, to be added with the coding and checked first); scripts, styles, attributes and unknown tags are dropped. Profile text (`bio`, responsibilities) becomes **plain text** with line breaks.
  Nothing raw is stored. The length limits of the new rules apply (a longer text is cut and reported).
- **Job skills, benefits and tags (2026-10-07):** skills and benefits are mapped to the new masters through the legacy id (the seeders created the masters from legacy names, by slug); one with no counterpart is
  skipped and reported. **Legacy tags are not carried** (mostly test data, numeric names); companies add tags through the new tag rules.
- **Master data mapping (2026-10-07):** the seeders kept the legacy values and order but **not the legacy ids** (the names were tidied), so each legacy master id is mapped by **normalised name** (letter case,
  spaces, punctuation; English or Indonesian name) to the new master, and the unmatched ones are listed. An optional **mapping file** (legacy id to slug) overrides or completes the result; the user reviews the list
  before the real import. A value with no counterpart is emptied when optional; when a job needs it to be published (category, role, job type, job level, work time) the job is imported as **`draft`** and reported.
  This applies to the earlier skill and benefit decision too (skills and benefits that stay unmatched are skipped and reported).
- **Job seeker profile mapping (approved 2026-10-07):** the name comes from the account (`users.name`; the candidate's own name columns are not used). Phone and WhatsApp are tidied to the rule (optional `+`, 8 to 15
  digits) or emptied. A birth date outside the age range of the settings (15 to 60) is emptied. Gender from `gender`; marital status from the known texts (single, married, divorced, widowed and their Indonesian
  equivalents) or empty; religion through the master map. Availability: `available` to `open_to_work`, `not_available` to `not_looking`, `available_in` to `open_to_work` with the date when it is within the allowed
  window, else empty. The location (BPS codes of province, city, subdistrict, village and postal code) is kept as far as the chain is valid (cut at the deepest valid level). `title` becomes the headline (100),
  `bio` the summary (2,000, plain text). Links: `social_links` and `website` are mapped to the new types (twitter to `x`; facebook, youtube, pinterest and reddit to `other`, at most three `other`) under the link rules
  (https, own domain for the named types); an invalid one is skipped. Skills through the master map. Experience: `designation` or `job_title` is the job title, `company` the company, `employment_type` is matched to the
  job type or left empty, `responsibilities` the description. Education: as decided above.
- **Company and job mapping (approved 2026-10-07):** **Company:** the name comes from the owner's account (`users.name`), the slug is generated from it under the new rules (unique); the owner's account becomes the
  company's one `company_admin` member (and the creator); bio and vision as plain text; website and establishment date when valid; industry, organization type and team size through the master map; the location from the
  BPS codes as far as the chain is valid; `visibility` to `is_visible`; the contact fields stay empty. A company whose industry or organization type has no counterpart (required columns) is skipped and reported.
  **Job:** title (150), the slug generated again, the description converted to Markdown, masters through the map, `vacancies` (text) to a number when readable, the deadline, the salary minimum and maximum with the
  period from `salary_type`, `show_salary` to `salary_visible`; a `custom` salary (text) is not carried; `is_remote` to `remote`, otherwise `onsite`; the location from the BPS codes (cut at the deepest valid level);
  `published_at` = the creation time for `active` and `expired` jobs; `created_by_user_id` and the only assignee = the company owner's account. `company_name`, `job_order_id`, views, featured and highlight are not carried.
- **Id map and report (approved 2026-10-07):** one table `legacyimport_id_map`: `id`, `entity` varchar(40) (`user`, `company`, `job`, `application`, `resume`, ...), `legacy_id` bigint unsigned, `new_id` bigint
  unsigned, `imported_at`; unique (`entity`, `legacy_id`) and an index (`entity`, `new_id`); no foreign keys; created by a migration (on the test database first, the real tables only with the user's approval) and
  removable when everything is done. **Report:** one JSON file per run under `storage/app/legacy-import/` (not publicly reachable) with the counts read, imported and skipped per entity and the list of skipped rows by
  **legacy id and reason code only**; no name, email or personal content (except the names of groups, languages and masters that did not match, which are not personal data). The same summary is printed in the terminal.
- **Cut-over (2026-10-07):** the importers only **add** rows that are not in the id map and never overwrite a row already imported, so they can be run again (a rehearsal, then the final run) without duplicates. For the
  final run the user freezes karir.pkp (maintenance or read-only) so no data changes in the middle; a change to a row already imported is not carried. There is no `--update` mode, so nothing a user did in the new
  system can be overwritten.
- **Account mapping (approved 2026-10-07):** `role = candidate` becomes a job seeker account; `role = company` becomes a company member **only when a `companies` row belongs to it** (an owner without a company is
  skipped and reported). Name from `name`, email from `email` (lower case), `status = 0` to `deactivated` and otherwise `active`, `email_verified_at` carried as it is, account language `id`, the legacy `created_at`
  and `updated_at` kept. The registration campaign code comes from `candidates.campaign_channel` except the default value `nosession`. The legacy alert flags (`*_alert`) are not carried (the notification
  preferences start at their defaults). Terms acceptances are not carried, so every migrated user is asked to accept the terms at the first sign-in (existing gate). The migration sends no email and no notification.

## Order and coding plan (confirmed 2026-10-07, five parts)

**Import order** (by dependency): master map, accounts, companies and their members, images, job seeker profiles and their lists, resumes, jobs (skills, benefits, assignee), screening questions, applications.
**Commands:** `php artisan legacy:masters:check` (lists the masters that did not match), `legacy:import {entity} [--dry-run]` and `legacy:import --all`.

1. Foundation: the context `LegacyImport`, the read connection `legacy` (the read-only account from `.env`), the migration of the id map table, the runner (batches, dry run, report), the master matcher and the
   synthetic legacy schema for the tests.
2. Accounts, companies, members and images.
3. Job seeker profiles, their lists and the resume files.
4. Jobs and screening questions.
5. Applications (the group mapping file, the snapshot, the history, duplicates), the full-run test, the documents and the handoff.

Each part is tested with synthetic data in the test database; **nothing is run against production data without the user's explicit approval**, and nothing is written to the real tables except the id map migration,
which needs the user's approval too.
