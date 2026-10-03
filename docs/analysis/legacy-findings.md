# Legacy findings (karir.pkp)

Findings that violate common best practice, reported under the "decision gate" rule (security, bugs/correctness,
architecture/code quality, performance). **Status of all items: reported to the user; no per-item decision recorded.**
Do not silently copy these behaviors into the new project, and do not silently "fix" them either; confirm with the user.
Line references are to `/usr/share/nginx/html/karir.pkp` as of 2026-10-03. Plan/billing items are excluded by scope.

## Security

| # | Finding | Where |
|---|---|---|
| S1 | **Critical.** `GET /migrate/data` runs `artisan migrate` without authentication | `GlobalController.php:100-103`, `routes/website.php:228` |
| S2 | **Critical.** `POST /ckeditor/upload` is unauthenticated, validates no file type, saves with client extension under `public/uploads/ckeditor` (possible arbitrary file upload; nginx execution rules not checked) | `GlobalController.php:76-92`, `routes/website.php:230` |
| S3 | `GET /optimize-clear` is unauthenticated | `routes/website.php:229` |
| S4 | Plain-text password sent by email to new users | `RegisterController.php:292` |
| S5 | Apply does not check resume ownership; `job_id` not validated; no duplicate/status/deadline check | `WebsiteController.php:749-756` |
| S6 | IDOR in kanban: sync moves any application; group update without owner check; application delete without job-owner check | `HasCompanyApplication.php:28-38, 110, 151-153` |
| S7 | IDOR on campaign edit/update/delete; deletion uses GET (CSRF) | `CompanyController.php:568, 605, 658`; `routes/website.php:164` |
| S8 | IDOR on job expire/activate; `makeJobActive` can activate a `pending` job, bypassing admin approval | `CompanyController.php:989, 1009-1018` |
| S9 | IDOR on screening-question edit/delete | `CompanyController.php:1251, 1295` |
| S10 | Messenger: `sendMessage` trusts `to` and `chat_id`; `candidateMessages` loads all applications of all candidates | `MessengerController.php:84-95, 29` |
| S11 | State-changing actions via GET (campaign delete, bookmark toggle) | `routes/website.php:83, 164` |
| S12 | Moodle token sent in the query string of GET requests | `CandidateTrainingAble.php:25, 59, 93` |
| S13 | Admin: `editedApproved` has no `userCan` permission check; `jobStatusChange` accepts any status value | `Admin/JobController.php:371, 110` |

## Bugs and correctness

| # | Finding | Where |
|---|---|---|
| B1 | Group-delete response is never returned | `HasCompanyApplication.php:140, 143` |
| B2 | Salary min <= max validation is commented out | `CompanyStoreService.php:43-46` |
| B3 | Recruiters can create categories and roles from free text (master data pollution) | `CompanyStoreService.php:63-98` |
| B4 | `campaign_type` not validated (unhandled `match`); slug uniqueness only advisory | `CampaignRequest.php`, `CompanyCampaignService.php:31` |
| B5 | Edit approval copies only some columns (skills, benefits, level, work time, tags, questions are lost), no rejection flow, no transaction | `Admin/JobController.php:371-419` |
| B6 | `Role::...->first()` may be null on document upload | `CompanyVerifyDocuments.php:52-55` |
| B7 | Job-alert preference `received_job_alert` is honored in the admin path but not in the recruiter path | `CompanyStoreService.php:181-190` vs `Admin/JobController.php:122` |
| B8 | Screening-question answers are rendered but never stored (`applied_jobs.answers` exists and is never written) | `job-details.blade.php:731`, `WebsiteController.php:749` |
| B9 | No notification is sent to the job seeker when a recruiter moves an application to another stage | `HasCompanyApplication.php` |
| B10 | Job tags are saved by name but the job forms submit tag ids: choosing an existing tag creates a new junk tag named after its id (e.g. "4") with its translations, and the job is linked to that tag instead of the real one. Each job that reuses a tag adds another numeric tag (the production `tags` table holds 30 tags, 15 of them purely numeric). Found by reading the code; not reproduced by running the application | `JobAble.php:843-872` (insert, name lookup at 851) and `874-` (sync); option values in `postjob.blade.php:47`, `pay-per-job.blade.php:57`, `backend/Job/create.blade.php:435` |

## Architecture and code quality

- Very large controllers with business logic inside (`CompanyController` 1,379 lines, `WebsiteController` 1,285 lines), session-based state, internal error messages shown to users.
- Large amounts of commented-out code (`RegisterController.php:116-173`, `routes/web.php:20-54`), empty stub methods.
- Catch-all routes `/{slug}/register` and `/{slug}` can shadow other routes.
- Campaign type mapping duplicated in two places, with Indonesian-valued codes (`lembaga_pendidikan`, `sosial_media`).

## Performance

- Job-alert and admin notifications sent synchronously in loops (`CompanyStoreService.php:181-190`, `Admin/JobController.php:119-125`, `RegisterController.php:277-280`).
- Writes during reads: the pending-edited-jobs list updates expired jobs in a loop on a GET (`CompanyController.php:247-254`); messenger returns the full history without pagination.

## Schema quality (production database, schema read only)

1. Dates stored as text: `candidate_experiences.start/end`, `candidate_education.year` are `varchar`.
2. Duplicate columns: `candidates.first_name`, `last_name`, `name`; `candidate_experiences.designation` and `job_title`; five social-login columns on `users`.
3. Inconsistent types: `jobs.vacancies` and `show_salary` are `varchar`; `jobs.district` is `int` while `companies.district` is `varchar`; `campaigns.id`/`company_id` are `int` without a foreign key and `slug` is not unique.
4. `applied_jobs` has no unique (candidate, job): duplicate applications possible; `job_id` uses `ON DELETE SET NULL`, leaving orphan applications.
5. Dead tables: `company_applied_job_rejected`, `company_applied_job_shortlist` (never used by code).
6. `jobs.status` only allows `pending`, `active`, `expired`.
7. Salaries are integers without a currency column.

## Production security note

S1 to S3 are issues in the **running production application**. The new project does not touch `karir.pkp` (read-only reference).
Whether and how to report them to the people who maintain it is the user's decision.
