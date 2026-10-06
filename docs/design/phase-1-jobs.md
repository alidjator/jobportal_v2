# Phase 1: Jobs (design complete, not coded)

Status: design confirmed by the user on 2026-10-05 (every decision below, endpoints and table columns); **no migrations, endpoints or code yet**. Gap code R2 (post a job) refers to `docs/analysis/gap-analysis.md`; the order inside Phase 1 is in
`decisions.md` section 5 (jobs are step 2). The legacy `jobs` table was read from the production schema on 2026-10-05 (schema only, no data); it is a reference, not a model:
it stores `vacancies` as text and `show_salary` as `varchar(1)`, keeps location twice (text address plus ids and coordinates), keeps skills and benefits both as text and in
pivot tables, and creates a "pending" copy of a job when an active one is edited. None of that is copied.

## Confirmed decisions

- **Moderation:** an admin setting `jobs.require_approval` (default **off**; global value plus a per-company override). When on, publishing puts the job in `pending_review`; an admin approves
  it (it goes live) or rejects it with a reason. When off, publishing goes live at once. In both modes an admin can **take a job down** (`taken_down`, with a reason). Editing a live job
  never makes a copy: when approval is required, the job returns to `pending_review` only if a **core field** changes (title, description, salary, location).
- **Statuses (7):** `draft` (not published, freely editable), `pending_review`, `active` (live), `closed` (closed by the company before the deadline; can be reopened), `expired` (past the
  deadline, set by a scheduled job; can be activated again with a new deadline), `rejected` (rejected by an admin; can be edited and submitted again) and `taken_down` (taken down by an admin;
  only an admin can restore it). Only `active` jobs are public and accept applications.
- **Required to publish (the stricter list, chosen by the user):** title (3 to 150 characters), description (Markdown, 50 to 20,000 characters), category, role, job type, job level, work time,
  **experience level, education level**, number of vacancies (at least 1), deadline (a future date), province and city (not needed for a remote job) and **at least one skill**. Optional: salary, tags,
  benefits, detailed address. A draft needs only a title. Missing fields are a 422 listing each field.
- **Salary (optional):** `salary_min` and `salary_max` (whole rupiah, min not above max; either may be given alone), `salary_period_id` (the salary period master; required when an amount is given) and
  `salary_visible` (default true). The currency is always IDR and is not stored. No free-text salary.
- **Hidden salary:** a job with `salary_visible = false` shows no amount in the public API, is **left out of the public salary filter** and of every public statistic (a finding of this design, settled with
  the user: counting it would let anyone find the amount by filtering repeatedly). Only the company and admins see the amount.
- **Location and work mode:** `work_mode` is `onsite`, `hybrid` or `remote` (it replaces the legacy `is_remote`). One location per job, one valid chain of the location masters as for companies
  (province, city, subdistrict, village, postal code, optional street address; 422 `invalid-location` otherwise). Province and city are required except for `remote`, where the location may be empty. No
  coordinates. Several cities mean several jobs, or `remote`.
- **Apply destination:** applications go **only through the portal**. No external URL and no application email (the legacy `apply_on`, `apply_url` and `apply_email` are not carried over), so every job
  has tracking, screening and statistics, and no outgoing link can be abused for phishing. (The data migration must decide what to do with legacy jobs that apply by URL or email.)
- **Skills, tags, benefits:** skills are chosen from the active `reference_skills`, 1 to 15 per job; tags are free keywords, at most 10 per job (2 to 30 characters, lower case, normalised), and a new
  tag is created in `reference_tags` while a job is saved (an existing one is reused); benefits are chosen from the active `reference_benefits`, at most 15 (proposals for new benefits, with admin
  approval, are discussed separately later). Pivot tables with a unique key (job, item); no duplicate text columns.
- **Deadline and expiry:** the deadline is a date; the job accepts applications until 23:59:59 **WIB** (Asia/Jakarta) of that day, then a scheduled job (hourly) sets it to `expired`. The farthest allowed
  deadline is the admin setting `jobs.max_deadline_days` (default 90, range 7 to 365, overridable per company). Reopening a `closed` job or reactivating an `expired` one needs a new valid deadline.
  The API documentation states the end time in WIB.
- **Roles and assignment (R1):** a **company admin** sees and manages every job of the company and assigns or unassigns members (company admins or recruiters) per job. A **recruiter** creates jobs (the
  creator is assigned automatically), edits, publishes and closes only the jobs **assigned** to them, and sees only those (plus the public results). Every job keeps at least one assignment: when a member is
  removed their assignments end, and a job left without any is assigned to the remaining company admins (the rule that Company's member removal left open for "the company reassigns them").
- **Public identifier:** `GET /api/v1/jobs/{id}` with a cosmetic `slug` in the answer (title plus id, for example `backend-developer-123`); both `{id}` and `{id}-{slug}` are accepted and nothing
  redirects. The slug follows the title. A job that is not `active` (or whose company is suspended or hidden) is 404 to the public.
- **Editing and deleting (the user chose the stricter option):** a job can be edited in `draft`, `rejected`, `active`, `closed` and `expired`, not in `pending_review` (wait for the decision) and not in
  `taken_down`. **Once a job has applications, its title, category, role, job type and job level are locked**; description, salary, deadline, vacancies, location and the other fields can still change
  (a 409 `job-locked-after-applications` names the locked field). Only a `draft` can be deleted (permanently); any other job is only closed, so application history stays whole. The lock needs
  applications, which come in a later step: until then the Jobs context asks a port that reports no applications.
- **Admin side and notifications:** new permission `jobs.manage`. Endpoints: list of the jobs of every company (filters: status, company, keyword; paginated), detail, `approve`, `reject {reason}`,
  `take-down {reason}` and `restore`. A reason is required (5 to 1,000 characters), is stored on the job and shown only to admins and the job's team, and goes to the audit log. Every action is audited.
  In-app notifications to the assigned members: `job.approved`, `job.rejected` (with the reason), `job.taken_down` (with the reason), `job.restored` and `job.expired` (the automatic expiry), none with
  an admin's name.
- **Clone:** `POST /api/v1/company/jobs/{id}/clone` copies every field, skill, tag and benefit into a new `draft` (same title, no deadline, assigned only to the person cloning, no applications). A recruiter
  can clone only jobs assigned to them; a company admin any job of the company.
- **No view counter** (the legacy `total_views` is not carried over), as for the company profile; useful statistics come from application data later.
- **Endpoints (approved):** company side, for members of an active company (a company admin sees every job, a recruiter only the assigned ones): `GET /api/v1/company/jobs` (paginated; `filter[status]`,
  `filter[q]`, `filter[assigned_to_me]`, `sort`), `POST /company/jobs` (creates a draft, only `title` required), `GET /company/jobs/{id}` (the whole job with assignees and any rejection or take-down reason),
  `PATCH /company/jobs/{id}` (send only what changes), `DELETE /company/jobs/{id}` (draft only), `POST /company/jobs/{id}/publish`, `/close`, `/reopen {deadline}` and `/clone`, and
  `GET|PUT /company/jobs/{id}/assignees` (PUT for company admins: replaces the whole set, at least one). Public: `GET /api/v1/jobs/{id}` (the list and search come with step 3). Admin (`jobs.manage`):
  `GET /api/v1/admin/jobs`, `GET /admin/jobs/{id}`, and `POST /admin/jobs/{id}/approve`, `/reject`, `/take-down`, `/restore`.

## Tables (`job_`) — column design **APPROVED by the user (2026-10-05)**

Conventions as in `phase-0-schema.md`: `bigint unsigned` auto-increment `id`, enumerations as `varchar` with a check constraint. Foreign keys: **changed 2026-10-05** (see `phase-0-schema.md`):
`company_id`, every classification column, the salary period, the five location columns and the skill, tag and benefit columns of the pivots have `RESTRICT` foreign keys (migration `000060`); the columns
that point to accounts and admins (`created_by_user_id`, `reviewed_by_admin_id`, `job_assignments.user_id` and `assigned_by_user_id`) have none. The columns below were first designed without them.

### `job_jobs`
id; company_id (index); title varchar(150); slug varchar(160) (lower case from the title; the API builds `{slug}-{id}`); status varchar(20) (check: `draft`, `pending_review`, `active`, `closed`,
`expired`, `rejected`, `taken_down`); status_reason varchar(1000) null; description mediumtext null; category_id, role_id, job_type_id, job_level_id, work_time_id, experience_level_id,
education_level_id bigint unsigned null (index each); vacancies smallint unsigned null; deadline date null; salary_min, salary_max bigint unsigned null; salary_period_id null; salary_visible tinyint(1)
default 1; work_mode varchar(10) null (check: `onsite`, `hybrid`, `remote`); province_id, city_id, subdistrict_id, village_id, postal_code_id null (index each); address_line varchar(255) null;
published_at, reviewed_at timestamp null; reviewed_by_admin_id null; created_by_user_id; created_at, updated_at. Indexes (status, deadline) and (company_id, status).

### `job_job_skills`, `job_job_tags`, `job_job_benefits`
id; job_id (FK `job_jobs`, cascade); skill_id / tag_id / benefit_id (plain index); created_at. Unique (job_id, item).

### `job_assignments`
id; job_id (FK `job_jobs`, cascade); user_id (index); assigned_by_user_id null; created_at. Unique (job_id, user_id). There is no status history table: the audit log records every action.

### Settings (registered by the Jobs context)
`jobs.require_approval` (boolean, default false, overridable per company) and `jobs.max_deadline_days` (integer, default 90, range 7 to 365, overridable per company).

## Part 2 of the coding: drafts and assignment (shapes approved 2026-10-06)

- **List** `GET /api/v1/company/jobs` (offset-paginated): row `id`, `title`, `status`, `deadline`, `vacancies`, `assignees_count`, `published_at`, `created_at`, `updated_at`; `filter[status]`, `filter[q]` (title,
  case-insensitive), `filter[assigned_to_me]`; `sort` of `updated_at`, `created_at`, `title` or `deadline`, each with an optional `-`, default `-updated_at`.
- **Detail** `GET /api/v1/company/jobs/{id}`: every column, with ids for the classification and the location (as the company profile does); `skills` and `benefits` as `[{id, name}]` (name in the request
  language), `tags` as a list of text, `assignees` as `[{user_id, name, role}]`, and `status_reason` only while `rejected` or `taken_down`.
- **Saving:** `POST` and `PATCH` take `skill_ids`, `tags` (text) and `benefit_ids`; when sent, the list is replaced as a whole. A draft is checked only for the shape and validity of what it holds;
  completeness, the deadline and the day limit are checked at publish (part 3). In part 2 `PATCH` applies to `draft` and `rejected` jobs; editing `active`, `closed` and `expired` jobs (including the return to
  `pending_review`) comes in part 3 with publishing.
- **Assignment audit and notifications (approved):** a change of assignees is audited as `job.assignees_changed` (actor the company admin, or `system` for the automatic reassignment after a member was
  removed; metadata `job_id`, `added` and `removed` as account ids, no names or emails), because an assignment gives access to applications. Members newly assigned get the in-app notification
  `job.assigned` (data `job_id`) and removed ones `job.unassigned`; the creator who is assigned automatically is not notified. Creating, editing and deleting a draft is not audited (as for the company profile).
- **Tags:** the text is trimmed, lower-cased and its spaces collapsed; 2 to 30 characters from letters, digits, spaces and `+ # . -`, with at least one letter or digit. A tag is identified by its slug (`+`
  becomes `plus` and `#` becomes `sharp` before slugging) and the name is kept as first written.

**Done (2026-10-06, branch `phase-1-company`, not committed):** the seven company-side endpoints (`GET|POST /company/jobs`, `GET|PATCH|DELETE /company/jobs/{id}`, `GET|PUT /company/jobs/{id}/assignees`),
the audit and the notifications of assignment, and the automatic hand-over when a member is removed. 56 tests in `tests/Feature/Job` (whole suite 774 passed, Pint and PHPStan clean). Implementation notes:
`POST` answers 201 and `PATCH` 200 with the whole job (not 204), `PUT .../assignees` 200 with the new list; a job that a recruiter may not see, or that belongs to another company, is 404, and the status
is checked before the content (409 `job-not-editable` before 422); an invalid draft is 422 `invalid-job` with `errors` per field (`category_id`, `skill_ids`, `tags`, `location`, ...); new shared ports `CompanyTeam`
(roles of a company's members, provided by Company), `TagRegistry` (tags created while a job is saved, provided by ReferenceData) and `CompanyDirectory::isActive`; the lock "title, category, role, type and level
cannot change once a job has applications" is **not coded yet** (it needs applications, so it arrives with them). **Finding fixed with the user's approval:** `ReferenceValues::isActive` and `name` ignored the
fixed columns of a master (benefits are global only while `status` is `active`), so a company's pending or rejected benefit proposal would have counted as a valid benefit; `find` now respects them (3 regression tests).
A tag's slug turns `+` into `plus` and `#` into `sharp`; two tags with the same slug are one tag.

## Part 1 of the coding: tables and settings (2026-10-05, branch `phase-1-company`, not committed)

Migrations `2026_10_05_000054` to `000058` (one per table: `job_jobs`, `job_job_skills`, `job_job_tags`, `job_job_benefits`, `job_assignments`) are written and were run **only against the `test_` tables**
(`php artisan test:database:migrate`) and then, **on the user's explicit approval, against the real tables** (58 migrations recorded; columns and indexes identical in both sets, the two CHECK constraints
exist, all five real tables hold 0 rows). The new context `Src\Job` has `JobServiceProvider` (registered in `bootstrap/providers.php`)
and `JobSettings`, which registers the two settings in the shared catalog. 12 new tests (schema checks, settings defaults and per-company overrides); the whole suite has 708 tests, Pint and PHPStan clean.
