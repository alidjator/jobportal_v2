# Phase 1: Job seeker profile and resumes (step 6, DONE 2026-10-06)

Status: design confirmed by the user on 2026-10-06 (every answer is recorded below); all four coding parts are done, see the end of the document.
Legacy reference (flow map, web application): the profile has six tabs (profile, CV/resume with several PDF files, experience, education, social media, account with profile and CV visibility); applying
requires a complete profile, experience and education. Decided earlier: contact data (phone, WhatsApp, address) lives in the profile, not in `identity_users`; the public profile URL is a unique, changeable
`slug` on the profile (details open); on anonymisation every file of the applicant (avatar, resumes, generated CVs) is deleted and profile data with personal content is cleared (`phase-0-schema.md`).

## Confirmed decisions

- **Bounded context (2026-10-06):** a new context **`JobSeeker`** (`src/JobSeeker`, table prefix `jobseeker_`) owns the profile, the work and education history, the resumes and the avatar. Identity keeps only the
  account and authentication.
- **Creation (2026-10-06):** the profile row is created **lazily**, when it is first saved; until then `GET` returns an empty profile with the defaults. No hook into the registration or Google sign-in paths.
  Only accounts of the type job seeker have a profile; a company member gets 403 `job-seeker-only`.
- **Profile fields (2026-10-06):** phone, WhatsApp, birth date, gender, marital status, religion, nationality, expected salary (with its period), experience level, availability status with an available-from date,
  location (the chain province to postal code plus a street address), a headline (up to 100 characters) and an "about me" summary (up to 2,000 characters). Gender, marital status, religion and nationality are
  optional. The name stays on the Identity account and the avatar on the account's `avatar_path`.
- **Fixed values (2026-10-06, no master exists, no legacy value assumed):** gender `male` or `female`; marital status `single`, `married`, `divorced` or `widowed`; availability `open_to_work`, `employed_open_to_offers`
  or `not_looking`; all optional (null = not given).
- **Visibility (2026-10-06):** two switches, both **off by default** (opt-in): `is_discoverable` (the profile may appear in candidate search and recommendations, Phase 3) and `resume_discoverable` (companies
  that find the candidate may see the resumes). They are separate from applying: a company that receives an application always sees the applicant's profile and the resume the applicant attached.
- **Slug and public page (2026-10-06):** **deferred to Phase 3** (candidate search). No public profile page and no slug column in this step (the legacy application has no public candidate page either); the slug
  rules (generation, reserved words, changes) are designed with candidate search and added by a migration then.
- **Work experience entry (2026-10-06):** `job_title` (3 to 100 characters), `company_name` (2 to 100, free text), optional `job_type_id` (master), optional `location_text` (up to 100, free text), `start_date`
  (month and year, stored as the first day), `end_date` (null while `is_current`; not before the start and not in the future), optional `description` (up to 2,000 characters, plain text). At most **30
  entries** per user; shown newest first (current ones first, then by end date).
- **Education entry (2026-10-06):** `education_level_id` (master, required), `institution` (2 to 150, free text), optional `field_of_study` (up to 100), `start_year` (four digits), `end_year` (null while
  `is_current`; not before the start year; at most this year plus 10 for someone still studying), optional `gpa` (0.00 to 4.00, two decimals; no other scale) and `description` (up to 1,000). At most **15
  entries**.
- **Skills and languages (2026-10-06):** skills are chosen from the active `reference_skills`, **at most 30**, in the order given, without a level. Languages are chosen from the active
  `reference_languages` with a proficiency (`basic`, `conversational`, `fluent` or `native`), **at most 10**, one entry per language. Both lists are replaced as a whole (`PUT`).
- **Links (2026-10-06):** types `linkedin`, `github`, `portfolio`, `website`, `instagram`, `x` and `other`; one per type except `other` (up to 3); **at most 8** links; each URL is `https`, up to 255
  characters and without credentials; domains are not restricted. The list is replaced as a whole (`PUT`). Links are shown only to companies entitled to see the profile (an application, later candidate search),
  never publicly. **Risk reported:** a recruiter can open a link the applicant supplied (phishing or malicious page); the client should warn before leaving the portal.
- **Resumes (2026-10-06):** **PDF only**, checked by content (the file must start as a PDF), at most **5 resumes** per user, size limit from the admin setting `job_seeker.resume_max_size_kb` (global, default
  5120, range 512 to 10240). A PDF that is encrypted or carries scripts, launch actions or embedded files (found by a simple scan of the file) is refused; this is a best-effort check, not a malware scan, and
  is reported as such. Files go to the private disk under a random name; each resume has a display name (up to 100 characters, the cleaned original file name at first) that can be changed. Download is an
  authenticated endpoint with `Content-Disposition` and `X-Content-Type-Options: nosniff`.
- **Avatar (2026-10-06):** owned by **Identity**, because `avatar_path` is on the account: `POST|DELETE|GET /api/v1/me/avatar` for every signed-in public account (job seeker or company member). The image is
  checked by its content and re-encoded by GD to a square of at most 512 pixels, stored on the private disk under a random name; the size limit is the admin setting `identity.avatar_max_size_kb` (default
  2048). The old file is deleted when replaced or removed. Other users never see it; a company sees it through an authorised endpoint later (applications).
- **Complete enough to apply (2026-10-06, changed the same day after the user saw the consequence):** the first answer was the strict rule of the legacy application (phone, province and city, at least one
  experience **and** one education entry, a resume); the user then chose the middle option: **phone number, province and city, a resume (or, later, a generated CV chosen at apply time), and at least one
  work experience *or* one education entry**. A fresh graduate fills in education; someone without formal education fills in experience. The profile answers with `completeness {complete, missing[]}` (codes
  `phone`, `location`, `history`, `resume`) so clients can guide the user; the check is enforced by the apply step (step 7) and lives in code, not in the schema.
- **Tables (approved 2026-10-06):** all with a `bigint unsigned` auto-increment `id` and `user_id` as a plain indexed column (no foreign key to accounts); master columns have `RESTRICT` foreign keys to `reference_*`.
  (1) `jobseeker_profiles`: `user_id` (unique), `phone` varchar(20), `whatsapp` varchar(20), `birth_date`, `gender` and `marital_status` (CHECK), `religion_id`, `nationality_id`, `expected_salary` bigint and
  `expected_salary_period_id`, `experience_level_id`, `availability` (CHECK) and `available_from`, `province_id`, `city_id`, `subdistrict_id`, `village_id`, `postal_code_id`, `address_line` (255), `headline`
  (100), `summary` (2,000), `is_discoverable`, `resume_discoverable`, timestamps. (2) `jobseeker_experiences`: `user_id`, `job_title`, `company_name`, `job_type_id`, `location_text`, `start_date`,
  `end_date`, `is_current`, `description`. (3) `jobseeker_educations`: `user_id`, `education_level_id`, `institution`, `field_of_study`, `start_year`, `end_year`, `is_current`, `gpa` decimal(3,2),
  `description`. (4) `jobseeker_skills`: `user_id`, `skill_id`, `position`, unique (`user_id`, `skill_id`). (5) `jobseeker_languages`: `user_id`, `language_id`, `proficiency` (CHECK), unique (`user_id`,
  `language_id`). (6) `jobseeker_links`: `user_id`, `type` (CHECK), `url`. (7) `jobseeker_resumes`: `user_id`, `name` (100), `path` (255, random), `size_bytes`, `created_at`.
- **Endpoints (approved 2026-10-06):** all under `/api/v1/me`, behind `auth:user` (so the terms gate applies), job seekers only except the avatar. `GET /me/profile` returns the profile with the experiences,
  education, skills, languages, links, resumes and `completeness` in one response; `PATCH /me/profile` takes the scalar fields, the location chain and the two visibility switches (send only what changes).
  `GET|POST /me/experiences`, `PATCH|DELETE /me/experiences/{id}` and the same for `/me/educations`. `PUT /me/skills`, `PUT /me/languages`, `PUT /me/links` replace the whole list. `GET|POST /me/resumes`
  (multipart), `PATCH /me/resumes/{id} {name}`, `DELETE /me/resumes/{id}`, `GET /me/resumes/{id}/download`. Avatar (Identity): `POST|GET|DELETE /me/avatar`. Phone and WhatsApp: an optional `+` and 8 to 15
  digits, stored without spaces.
- **Audit and anonymisation (2026-10-06):** changes to the profile, the resumes and the avatar are **not audited**. When an account is anonymised (`UserAnonymized`), JobSeeker deletes every row of that user in
  its seven tables and the resume files (a queued listener on `default`, safe to run again); Identity already deletes the avatar. What a past application keeps about its applicant is decided in step 7.

## Coding plan (confirmed 2026-10-06, four parts)

1. The context `JobSeeker`, the seven migrations (run on the `test_` tables first; the real tables only with the user's approval) and the profile (`GET` and `PATCH /me/profile`, `completeness`), with tests.
2. Experiences, education, skills, languages and links, with tests.
3. Resumes (upload, rename, delete, download, the size setting), with tests.
4. The avatar in Identity, the anonymisation listener of JobSeeker, and tests.
- **Link safety (2026-10-06, refines "Links" above after the risk was discussed):** the types `linkedin`, `github`, `instagram` and `x` accept only their own domain (the usual `www` and other subdomains
  included). For every type, a URL with a raw IP address, a non-standard port or a known URL shortener is refused. `portfolio`, `website` and `other` stay free, so the warning before leaving the portal remains a
  task of the client. The list of shorteners and the domains per type live in code.

## Part 1 coded (2026-10-06, not committed)

The context `src/JobSeeker`, the seven migrations `000067` to `000073` (run on the `test_` tables only; the real tables are **not** created yet), and `GET|PATCH /api/v1/me/profile`. 19 new tests (suite 995 passed,
Pint and PHPStan clean). Implementation notes: the profile answers with ids for the masters and the location levels (as the company profile does) and `completeness {complete, missing[]}`; `PATCH` checks a changed
master value for being active, the location as one chain (`invalid-location`), and that an expected salary has its period (`invalid-profile`, `errors` per field); phone numbers are cleaned of spaces, dashes,
dots and brackets and must be an optional `+` and 8 to 15 digits; the first version of the date rules was my own choice (birth date from 1900 to today, `available_from` free); after the user saw the consequences on 2026-10-06 they became: the birth date must make the job seeker **at least 15 years old** today in Jakarta (the legal basis of the age limit was not verified and should be confirmed) and not be before 1900, and `available_from` must be **from today (Jakarta) up to 365 days ahead**; empty means "available now" and a saved date that has passed is read as available now. Only a date that is being changed is checked, so an old value never blocks other edits.
The lists (experiences, education, skills, languages, links, resumes) are added to `GET /me/profile` as their parts are coded. Not yet done: the anonymisation listener (part 4).

**Date rules made configurable (2026-10-06, user's decision, replaces the fixed rules above):** three global admin settings (no per-company override; `settings.manage`): `job_seeker.min_age` (default **15**, range
13 to 30), `job_seeker.max_age` (default **60**, range 40 to 100; 100 effectively switches the upper limit off) and `job_seeker.available_from_max_days` (default **30**, range 1 to 40). The ranges keep the minimum
below the maximum. A birth date must give an age from the minimum to the maximum today in Jakarta (a person is still `max_age` until the day before their next birthday); `available_from` must be from today
up to that many days ahead. Only a date that is being changed is checked. **Risk reported:** with the default maximum of 60, a job seeker older than 60 cannot save a birth date at all (and so, since the birth
date is optional, can only use the portal by leaving it empty); an upper age limit also excludes people from being found. The legal basis of the age limits was not verified.

## Part 2 coded (2026-10-06, not committed)

`GET|POST /me/experiences`, `PATCH|DELETE /me/experiences/{id}`, the same for `/me/educations`, and `PUT /me/skills`, `/me/languages` and `/me/links`; `GET /me/profile` now also carries the six lists (skills as
`{id, name}` and languages as `{id, name, proficiency}`, the names in the request language). No migration. 43 tests in `tests/Feature/JobSeeker` and 13 unit tests of the link rules. Implementation notes: months are
sent and returned as `YYYY-MM` (stored as the first day); an entry is checked **as a whole** on every change (`invalid-experience`, `invalid-education`, `errors` per field), so a partial change cannot leave it
inconsistent; **decided with the user the same day:** for someone still studying `end_year` may be empty or an expected year (from the start year up to this year plus 10), for a finished education it is required
and not in the future; the limits are 30 experiences and 15 education entries (409 `experience-limit-reached`, `education-limit-reached`); the skill, language and link lists answer with the whole profile
(`invalid-skills`, `invalid-languages`, `invalid-links`, `errors` per `<list>.<n>.<field>`); the link rules (`LinkRules`) and the shortener list are in the domain; an experience or education entry of another
account is 404. The limit of 30 or 15 was first read and written without a lock (two simultaneous requests could pass it by one entry); **fixed the same day on the user's decision**: adding an entry first locks the user's profile row (`ProfileRepository::lock`, which creates an empty row when there is none, and leaves an existing profile alone), so simultaneous additions run one after the other. The resume limit of part 3 will use the same lock. A side effect: an empty profile row appears when the first experience or education entry is added; clients see no difference, because `GET /me/profile` already returned an empty profile.

## Part 3 coded (2026-10-06, not committed)

`GET|POST /me/resumes` (multipart `file`), `PATCH /me/resumes/{id} {name}`, `DELETE /me/resumes/{id}` and `GET /me/resumes/{id}/download`; `GET /me/profile` also lists the resumes (`id`, `name`,
`size_bytes`, `created_at`, newest first; the storage path is never shown). New admin setting `job_seeker.resume_max_size_kb` (global, default 5120, range 512 to 10240). No migration. 18 tests in `ResumeTest`.
Implementation notes: the file is checked by content only (`ResumePdf`: it must start with `%PDF-`; a scan refuses `/Encrypt`, `/JavaScript`, `/JS`, `/Launch` and `/EmbeddedFile`, matching whole names so
`/JSON` or `/Launcher` pass), stored first under `jobseeker/resumes/<random>.pdf` on the private disk, then the row is written under the profile-row lock (the 5-resume limit, 409 `resume-limit-reached`), and the
new file is deleted again if the database step fails; the display name is the client's file name without a path or control characters, at most 100 characters, and a rename must already be clean
(422 `invalid-resume` with `errors.name`, nothing is silently changed); the download answers `application/pdf` with `attachment`, `no-store`, `nosniff` and `Content-Security-Policy: default-src 'none'`, named after the
display name with `.pdf` added when missing (an ASCII fallback plus the encoded real name for other letters); a missing file answers 404 and is logged; another account's resume is 404. Deleting a resume that an
application used is decided with applications (step 7).

## Part 4 coded (2026-10-06, not committed), which completes step 6

**Avatar (Identity):** `POST|GET|DELETE /api/v1/me/avatar` for a job seeker or a company member (403 `public-account-required` for anyone else; behind the terms gate like the other `/me` routes). New global setting
`identity.avatar_max_size_kb` (default 2048, range 100 to 10240). **Decided with the user the same day, differing from the design above:** the picture is shrunk to **fit 512 by 512, not cropped to a square** (the shared
image processor never crops; clients crop or round the picture themselves). An opaque picture is stored as a JPEG and one with transparency as a PNG; the file goes under `identity/avatars/<random>` on the private disk,
the old file is deleted last, `GET` answers `inline` with `no-store`, `nosniff` and `Content-Security-Policy: default-src 'none'`, and an account without a picture, or whose file is missing, gets 404 `avatar-not-found`.
**Decided with the user later the same day:** `GET /me` (and the sign-in answers that carry the same account) now has `has_avatar`, a boolean, so clients need not ask `GET /me/avatar` and read a 404. The public never sees avatars.
**Anonymisation (JobSeeker):** the queued listener `EraseJobSeekerDataWhenUserAnonymized` (queue `default`, 3 tries) runs `EraseJobSeekerData`: it deletes the resume files first and every row of the seven
`jobseeker_` tables for that user in one transaction, so a failing file deletion stops before any row is removed and a retry still knows the files; running it again does nothing. 15 new tests (suite 1067). Nothing
changed in the database schema: the `jobseeker_` tables on the real database are in place since 2026-10-06.

## Decision for step 7 (2026-10-06): resumes and applications

**Each application keeps its own copy of the resume file.** When a job seeker applies, the chosen PDF (or the CV generated at that moment, J7) is copied and becomes the application's own file, so the job seeker can delete or rename
resumes at any time without touching applications, and a recruiter always sees exactly what was submitted. The copies count as "files owned by the applicant" and are deleted when the account is anonymised (the principle
of `phase-0-schema.md`); the five-resume limit counts only the resumes of the profile. The cost is storage (up to the resume size limit per application). Chosen over: a reference that blocks deletion (the job seeker gets stuck
with five old resumes), a reference that lets the file disappear (the recruiter loses it mid-process) and a soft delete (the user believes the file is gone while it is kept).
