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
  removable when everything is done. **Report:** one JSON file per run under `storage/app/private/legacy-import/` on the private local disk (not publicly reachable) with the counts read, imported and skipped per entity and the list of skipped rows by
  **legacy id and reason code only**; no name, email or personal content (except the names of groups, languages and masters that did not match, which are not personal data). The same summary is printed in the terminal.
- **Cut-over (2026-10-07):** the importers only **add** rows that are not in the id map and never overwrite a row already imported, so they can be run again (a rehearsal, then the final run) without duplicates. For the
  final run the user freezes karir.pkp (maintenance or read-only) so no data changes in the middle; a change to a row already imported is not carried. There is no `--update` mode, so nothing a user did in the new
  system can be overwritten.
- **Account mapping (approved 2026-10-07):** `role = candidate` becomes a job seeker account; `role = company` becomes a company member **only when a `companies` row belongs to it** (an owner without a company is
  skipped and reported). Name from `name`, email from `email` (lower case), `status = 0` to `deactivated` and otherwise `active`, `email_verified_at` carried as it is, account language `id`, the legacy `created_at`
  and `updated_at` kept. The registration campaign code comes from `candidates.campaign_channel` except the default value `nosession`. The legacy alert flags (`*_alert`) are not carried (the notification
  preferences start at their defaults). Terms acceptances are not carried, so every migrated user is asked to accept the terms at the first sign-in (existing gate). The migration sends no email and no notification.
- **Writing other contexts' tables (2026-10-07, a conflict with the code standard that the user settled):** `LegacyImport` is a **documented exception**: an operational tool that may write the tables of other
  contexts directly, only in its own Infrastructure layer, one writer class per entity; recorded in `CLAUDE.md`; its tests check the result through the real API endpoints.

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

## Part 1 coded (2026-10-07, not committed)

The context `src/LegacyImport` (an operational tool, no API) with: the read connection `legacy` (`config/database.php`, from `KARIR_PKP_DB_*`, optional `KARIR_PKP_DB_PREFIX`); the migration `000078` of the id map
(run on the test database only, **the real table is not created**); the id map (`IdMap`: `find`, `findMany`, `remember` that keeps the first answer, `replace` for the masters); the runner (`ImportRunner`: one
entity or all in dependency order, circles and missing dependencies refused), the report (`ImportReport`, JSON on the private local disk under `legacy-import/`, rows named by legacy id and reason code only) and
`LegacySource` (the only way to read legacy rows); the master matcher (`MasterMatcher`: normalised name in any language, a mapping file overrides, ambiguous matches are not guessed; `MasterCatalog` lists the 15
masters including the plain-name languages) and `MastersImporter`; the guard `LegacyConnectionGuard` (refuses an unconfigured legacy connection and one that points at the application's own database and prefix).
Commands: `php artisan legacy:import {entity|--all} [--write] [--map=name=path]` and `legacy:masters:check [--map=path]`. **Confirmed by the user (2026-10-07):** (1) **a run is a dry run unless `--write` is given**, so nothing can
be written by accident; (2) the report is written on the private local disk (`storage/app/private/legacy-import/`, not `storage/app/legacy-import/`); (3) the masters are worked out again at every run and stored in
the id map as `master.<key>` (a corrected mapping file takes effect; rows already imported with the old map are not changed). The tests use synthetic legacy tables (schema only) that the test helper
`Tests\Support\LegacySchema` creates once in the test database under the prefix `legacy_`, through the `legacy` connection (a second connection, so the test transaction is never touched). 51 new tests; suite 1228
passed, Pint and PHPStan clean. Nothing was run against production data.

## Part 2 coded (2026-10-07, not committed)

Three importers (each with a writer class in Infrastructure, the documented exception): **accounts** (`AccountsImporter`, entity `accounts`, id map `user`), **companies** (`CompaniesImporter`, entity `companies`,
id map `company`, after the masters and the accounts) and **images** (`ImagesImporter`, entity `images`, after the accounts and the companies; id maps `images.avatar`, `images.logo`, `images.banner`).
**Accounts:** every legacy user except demo accounts; a company owner needs a `companies` row; skip reasons `demo-account`, `unknown-role`, `company-owner-without-company`, `name-missing`, `email-invalid`,
`email-taken` (a duplicate inside the legacy data, compared case-insensitively, or an account of the new schema that already uses it); a bcrypt hash is copied (note `password-not-bcrypt` counts the others, which
become no password); `status = 0` to `deactivated`; the language is `id`; the legacy `created_at` and `updated_at` are kept; the campaign code comes from `candidates.campaign_channel` except `nosession`, at most 64
characters. **Companies:** name from the owner's account, the owner becomes the one `company_admin` member, slug from the name (unique, valid, never reserved, `company-<legacy id>` when the name gives nothing
usable), website (http or https, at most 255), establishment date, bio and vision as plain text cut to 5,000, address cut to 255, the team size, the location through BPS codes (`LocationLookup`: each level must
belong to the one above, cut at the first that does not), `visibility` to `is_visible`, the badge from `document_verified_at` (that time) or `is_profile_verified` (the run's time); skip reasons
`owner-not-imported`, `name-missing`, `industry-unmatched`, `organization-type-unmatched`; notes `website-invalid`, `establishment-date-invalid`, `team-size-unmatched`. **Images:** read from the legacy project folder
(`KARIR_PKP_FILES_ROOT`, looked up under `public`, `storage/app` and `storage/app/public`; a path with `..`, a null byte or a leading slash, or a link that leaves the folder, is refused: `LocalLegacyFiles`),
checked against the admin size limit of the new upload, then through the image processor (avatar 512 by 512, logo 512 by 512, banner 1600 by 600) and stored under a random name; `candidates.photo` wins over
`users.image`, `default.png` and empty mean no image; skip reasons `files-root-not-configured`, `file-missing`, `too-large`, `invalid-image`; an account or company that already has an image keeps it (note
`already-has-image`). All three only add rows that are not in the id map and never overwrite; a dry run reads and reports but writes nothing (the images are still read and processed, to find the broken ones).
New: config `config/legacy_import.php` (`KARIR_PKP_FILES_ROOT`), `LegacySource::whereIn`, `LegacyText`, `SlugMaker`. **Confirmed by the user (2026-10-07):** a dry run of a dependent entity before its parents were written reports those rows as skipped
(the command says so); a full rehearsal means `--write` against a scratch database. **Finding (the user chose to leave them empty, 2026-10-07; to be checked in the approved run):** the legacy `companies.kelurahan` column is an `int(11)`, which cannot hold a 10-digit
BPS village code (the largest is 2,147,483,647), so company villages and postal codes probably cannot be matched; they are then simply left empty. 56 new tests; suite 1273 passed, Pint and PHPStan clean. Nothing was
run against production data and the real id map table does not exist yet.

## Part 3 coded (2026-10-07, not committed)

Seven importers for the job seeker side, writing through `JobSeekerWriter` (Infrastructure writes `jobseeker_*` directly, the documented exception): **profiles** (`ProfilesImporter`, entity `profiles`, id map `profile`
by legacy candidate id), **experiences**, **educations**, **skills**, **languages** (the four share `CandidateListImporter`; each depends on the profiles; id maps `experiences`, `educations`, `skills`, `languages`),
**links** (`LinksImporter`, id maps `link` and `link.website`) and **resumes** (`ResumesImporter`, id map `resume`). **Profiles:** for the job seeker accounts that were imported (a candidate of a skipped
account is `user-not-imported`, a second row of one account `duplicate-profile`); the visibility switches start off; phone and WhatsApp as an optional `+` and 8 to 15 digits (spaces, dashes, dots and brackets
removed) else emptied; the birth date must give an age inside the admin settings (15 to 60) else emptied; gender `male` or `female`; marital status from the known texts (English and Indonesian) else emptied; religion
and experience level through the master map; availability `available` and `available_in` to `open_to_work`, `not_available` to `not_looking`, the `available_in` date kept only from today up to the admin window
(30 days); the location through the BPS codes (the chain is cut where it stops being valid); headline (100) and summary (2,000, plain text); the expected salary as `monthly` when between 100,000 and 1,000,000,000,
else dropped; the legacy timestamps are kept; notes count each emptied value. **Experiences:** the title is `job_title` else `designation`; dates are read from free text (`LegacyDates`: `2020-03`, `03/2020`,
`15/03/2020`, `March 2020`, `Maret 2020`); the new `HistoryRules` decide, and the first failing field is the skip reason (`invalid-start_date`, ...); the employment type is matched to a job type by name; at most
30. **Educations:** the level through the master map (`education-level-unmatched` otherwise), the institution from `name`, the year as both start and end, the GPA kept only from 0 to 4 with two decimals (note
`gpa-invalid`), `HistoryRules` for the rest; at most 15. **Skills:** through the master map, one per skill, in order, at most 30. **Languages:** through the master map, `conversational`, at most 10. **Links:** the
website and the social links under the new `LinkRules` (https, domain, no shortener, own domain for the named kinds), one per kind except `other` (three), eight in all; links of a company owner are
`not-a-job-seeker`. **Resumes:** read from the legacy project folder, a PDF by content, the scan of `ResumePdf`, the admin size limit, the **newest five that pass** per user, a random name under
`jobseeker/resumes/`; reasons `files-root-not-configured`, `file-missing`, `too-large`, `not-a-pdf`, `unsafe-pdf`, `over-limit`, `user-not-imported`. All only add rows that are not in the id map and never
overwrite; a dry run reads and checks (the resume files too) but stores nothing. New: `LegacyDates`, `NewMasterReader::idBySlug`. 47 new tests; suite 1320 passed, Pint and PHPStan clean. Nothing was run against
production data and the real id map table does not exist yet. **Confirmed by the user (2026-10-07):** (1) for links, the per-kind limit is counted inside one run (a re-run only adds the missing ones and does not recount the kinds
that earlier runs wrote; the total of eight is checked against the table); (2) the legacy `candidates.cv` (a single CV uploaded through the profile settings, per the legacy source) is not migrated: the
resumes come from `candidate_resumes` only, so a candidate who only has a `cv` uploads it again.

## Part 4 design (jobs and screening questions, in progress, 2026-10-07)

Decisions taken one question at a time, on top of the confirmed decisions above:

- **Job text columns (2026-10-07):** the legacy `jobs.hard_skill`, `soft_skill` and `notes` (required in the legacy posting form and shown publicly as separate sections; the new schema has no column for them) are
  converted to Markdown like the description and **appended to it** as sections titled "Hard skills", "Soft skills" and "Notes" (only the filled ones). The description length limit of the new rules applies (a longer text
  is cut and reported).
  **Extended the same day (the master skill replaces the legacy text fields in `decisions.md`; the legacy `job_skills` pivot is probably sparse: its auto-increment is 27 for about 155 jobs, schema metadata only, to be
  checked in the approved run):** the legacy `hard_skill` and `soft_skill` text is also **split into items** (lines, list items, commas, semicolons) and each item is matched to the skill master by normalised name
  (`hard_skill` to kind `hard`, `soft_skill` to kind `soft`; an ambiguous match is not guessed). Matched items become master skills of the job together with the ones from the legacy `job_skills` pivot (no
  duplicates, at most 15 in all, the rest reported as a count). The whole text is **still appended to the description**, so nothing is lost. The report counts items matched and unmatched per run, without the text.
- **Job vacancies (2026-10-07):** the legacy `vacancies` is free text (the legacy form only requires a string). After trimming, a bare number (`5`) or a number followed by words (`5 orang`, `10 posisi`) is read as
  that number; a range (`2-3`, `2 sampai 3`) uses the **larger** number; text with no number, with several unrelated numbers (not a range), a value of 0 or one outside 1 to 65,535 is **emptied and reported** by
  reason code (such a job becomes a `draft` under the completeness rule).
- **Admin-created jobs (2026-10-07):** the legacy `jobs` table records no creator. A job an admin created for an existing company (`company_id` set) is imported as a job of that company, with that company's owner as
  creator and only assignee (the admin origin is not recoverable, and legacy admins are not migrated); the legacy admin form leaves job level, work time and the BPS location empty, so such jobs mostly become
  `draft` under the completeness rule. A job with `company_id` NULL and only a free-text `company_name` is **skipped and reported** (`company-missing`), as is every application to it.
- **Job benefits text (2026-10-07):** the legacy `jobs.benefits` text column is **not carried** (the web form stores it next to the master relation `job_benefit`, but the public job page shows only the master
  relation); only the benefits mapped to the new masters are imported.
- **Active job with a passed deadline (2026-10-07):** a legacy `active` job whose deadline is before today in Jakarta time is imported as **`expired`** (deadline and `published_at` kept; the company can reopen it with a
  new deadline) and noted in the report by legacy id and reason code.
- **Completeness (2026-10-07, extends the five-master rule of "Master data mapping"):** a job that would be `active`, `pending_review` or `expired` but fails the full publish-required list of `phase-1-jobs.md`
  (description 50 to 20,000 characters, category, role, job type, job level, work time, experience level, education level, vacancies of at least 1, a deadline, province and city unless remote, at least one skill)
  is imported as **`draft`**; its filled columns are still carried, and the report names the first failing rule by legacy id and reason code. For the deadline of an `expired` job the rule is that a deadline
  exists (the "future date" test is not applied to a job that is expired by its own deadline; the user did not object when this was shown, 2026-10-07).
- **Closed jobs and completeness (2026-10-07):** a job imported as `closed` (legacy `apply_on` = email or URL) stays `closed` even when it fails the completeness list (it is history and not public); the gaps are
  noted in the report, and the full rules apply when the company reopens it.
- **Job location columns (2026-10-07):** legacy `jobs` holds two sets of BPS codes: the web form writes `region` (province), `district` (city or regency), `subdistrict` and `postcode`, the old API writes
  `province_id` and `city_id` (no foreign keys). The **web set is used first** (through `LocationLookup`, cut at the deepest valid level); only when it is empty or invalid from the province down, the province and city
  come from `province_id` and `city_id` (no subdistrict); the use of the fallback is noted in the report.
- **Job address (2026-10-07):** `address_line` comes from the legacy `address` (cut to 255) when it is filled; `exact_location`, `neighborhood`, `locality`, `place`, `long` and `lat` are not carried (the
  legacy create form does not fill them; they come from an older location picker). `country` is not carried either: the legacy form fixes it to Indonesia (stated by the user, 2026-10-07), and the new schema has no country column.
- **Job salary rules (2026-10-07):** only `salary_mode = range` is read. A value of 0 means empty. When both numbers are filled and the minimum is above the maximum, **the two are swapped** and the job is noted in
  the report; when only one is filled, the other stays empty. The period comes from `salary_type_id` through the master map; a salary without a matching period is **emptied entirely** and noted. `salary_visible` is
  true only when `show_salary` is `'1'` (anything else, including NULL, hides the salary).

## Part 4 coded (2026-10-07, not committed)

Three importers and their writers (Infrastructure writes `job_*` and `recruitment_*` directly, the documented exception): **jobs** (`JobsImporter`, entity `jobs`, id map `job`; `JobWriter` writes the job, its skills, its
benefits and its one assignee), **questions** (`QuestionsImporter`, entity `questions`, id map `question`) and **job-questions** (`JobQuestionsImporter`, entity `job-questions`, id map `job-question` by legacy link id;
`ScreeningWriter`). New: `LegacyMarkdown` (HTML to Markdown; new dependency `league/html-to-markdown` 5.1.3, MIT, needs `ext-dom` and `ext-xml`, checked on Packagist and in `php -m`; unsafe links, images, scripts and
attributes are dropped and a decoded `<` is escaped), `LegacyVacancies`, `SkillTextMatcher` (`NewMasterReader::skills()` gained the active skills with their kind), `ImportReport::flagged` (an imported row that changed on the
way, named by legacy id and reason code under `flagged_rows`, also counted in the notes) and the synthetic legacy tables `jobs`, `job_skills`, `job_benefit`, `company_questions` and `company_question_job`.
**Jobs:** skip reasons `pending-edit-skipped`, `company-missing`, `company-not-imported`, `owner-not-imported`, `title-missing`, `title-too-short` (under 3 characters, the new minimum); flags `deadline-passed-expired`,
`draft-incomplete-<rule>` and `closed-incomplete-<rule>` with the rules in this order: `description`, `category`, `role`, `job-type`, `job-level`, `work-time`, `experience-level`, `education-level`, `vacancies`, `deadline`,
`deadline-passed`, `skills`, `location`, and `salary-swapped`; notes `<master>-unmatched`, `vacancies-*`, `skill-unmatched`, `skill-link-incomplete`, `skill-text-matched|unmatched|ambiguous`, `skills-over-limit`,
`benefit-unmatched`, `benefit-link-incomplete`, `benefits-over-limit`, `salary-period-unmatched`, `location-fallback`, `description-cut`, `title-cut`. `published_at` is the creation time for a job that was `active` or
`expired` in legacy and is `active`, `expired` or `closed` now, otherwise empty; `reviewed_at` stays empty. **Questions:** skip reasons `company-not-imported`, `owner-not-imported`, `text-too-short`; note
`archived-single-use`. **Job-questions:** skip reasons `link-incomplete`, `job-not-imported`, `question-not-imported`, `company-mismatch`, `duplicate-link`; note `job-over-question-limit` (limit from the setting
`recruitment.max_screening_questions`); positions follow the legacy link id and continue after the questions a job already has. 53 new tests (the jobs and screening ones also read the result through the public job
API and the company screening-questions API); suite 1373 passed, Pint and PHPStan clean. Nothing was run against production data and the real id map table does not exist yet.
**Six choices made while coding, all confirmed by the user one at a time (2026-10-07):** (1) a legacy `pending` job whose deadline has passed fails the "future date" rule and becomes a `draft` (flag
`draft-incomplete-deadline-passed`); (2) a legacy `postcode` is dropped (the new chain needs a village, which legacy jobs do not have) and is not counted in the report; (3) a legacy question whose company differs from
the job's company is not attached (`company-mismatch`), not copied; (4) a legacy job with no status is read as `pending`; (5) the limit of 15 skills and 15 benefits cuts the rest and counts it (no per-job row);
(6) a title shorter than 3 characters is skipped (`title-too-short`).

## Part 5 design (applications, in progress, 2026-10-07)

Findings from the legacy schema and source (schema only, no data read): the only status of a legacy application is its pipeline group (`applied_jobs.application_group_id`); `applied_jobs` has no status, rejection
reason or expected salary. Every legacy company gets five default groups by name when it is created (`Company::booted`): `No Group` (not deletable), `All Applications`, `Shortlisted`, `Interview` and `Rejected`;
companies can add their own. The tables `company_applied_job_rejected` and `company_applied_job_shortlist` look unused (their dumps carry no `AUTO_INCREMENT`, so they never held a row, and no source refers to the
tables; the words "Shortlisted" and "Rejected" are the default group names); to be checked in the approved run.

- **Group mapping (2026-10-07):** the five default names (letter case ignored) are mapped **by name**: `No Group` and `All Applications` to `applied`, `Shortlisted` to `screening`, `Interview` to `interview`,
  `Rejected` to `rejected`. The mapping file (legacy group id to a stage type, optionally creating an extra stage of the same name for the types `screening`, `interview` and `offer`) overrides these and covers the groups
  the companies made themselves. A group that is neither a default name nor in the file sends its applications to `applied` and is reported with its group name (company configuration, not personal data).
- **Default stages of migrated companies (2026-10-07):** the importer of companies (part 2) did not give a company its seven default stages (the application code does it through the port `DefaultStageProvisioner`
  inside `CreateCompany`). It is **changed to call the port** for every company it writes, inside the same transaction, so a migrated company equals one made through the application and the importer of applications
  can rely on the stages. A dry run writes nothing.
