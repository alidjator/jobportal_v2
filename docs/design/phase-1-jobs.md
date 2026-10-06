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

**Table names (confirmed with the user, 2026-10-06):** the context prefix is followed by the entity name, so an entity named like its context repeats the word (`job_jobs`, `job_job_skills`,
`job_job_tags`, `job_job_benefits`, as `company_companies` already did). The main table cannot be called `jobs` because Laravel's queue table already has that name. The user considered renaming the
three pivots (to `job_skills`, `job_tags`, `job_benefits`) or the entity (to `job_postings`) and chose to **keep the names as they are**.

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

## Public job search (step 3 of Phase 1; designed and coded 2026-10-06, not committed)

**Done:** `GET /api/v1/jobs` as decided below. 31 new tests in `JobSearchTest` (whole suite 888 passed, Pint and PHPStan clean); it was also run once, read-only, against the real tables (200, empty). No migration,
permission or real-database change. Implementation notes: the validation lives in `SearchJobsRequest` (a keyword of at most 100 characters and 5 words, each id filter one to 20 positive whole numbers separated
by commas, `work_mode` the same with its three values; a bad value or an unknown filter is 422); `salary-period-required` and `page-out-of-range` are use-case rules (422); the keyword's words are resolved through
ports (`TagRegistry::idsMatching`, `CompanyDirectory::idsByName`, at most 200 matches per word) so Job never reads other contexts' tables; the page is built with batch ports (`PublicCompanies::cards` and
`hiddenIds`, `ReferenceValues::names`, `LocationReferences::provinceNames` and `cityNames`, `MasterRepository::findMany`), so a page of 20 rows takes about ten queries, not one per row (a test caps it at 15); the
limit is 60 searches per minute per IP through `RateLimiter` (the detail page is not limited); a company that became hidden between the two reads is left out of the page. **A bug the tests found and that is
fixed:** a raw `COALESCE(job_jobs.salary_max, ...)` ignored the table prefix (it would have worked on the real tables, not on the `test_` ones); the column names are now wrapped by the query grammar. **Performance
note:** the search relies on the existing indexes `(status, deadline)`, `(company_id, status)` and the single-column ones; no index covers `published_at`. That is fine for the production scale seen so far (149 jobs in
total), but the plan must be checked with `EXPLAIN` when real data grows (a composite index on `status, published_at` is the likely addition); this is not done now because no data exists to measure.

The criteria of the job alerts (J5, decided earlier: role, category, title keyword, province and city, level, experience, education, job type, minimum salary) are the same as a saved search, so the public search
uses at least that set and an alert will later store the same filters.

Decisions so far:
- **Filters:** `filter[q]` (keyword), `filter[category_id]`, `filter[role_id]`, `filter[job_type_id]`, `filter[job_level_id]`, `filter[experience_level_id]`, `filter[education_level_id]`, `filter[work_time_id]`,
  `filter[work_mode]`, `filter[province_id]`, `filter[city_id]`, `filter[skill_id]` (jobs that need any of them), `filter[company_id]` and a minimum salary (decided separately). Every master id filter accepts several
  values separated by commas and matches any of them.
- **Keyword:** `filter[q]` is split into words and **every word must match** as a piece of text, whatever the letter case, in the title, in one of the tags, or in the company name (a LIKE search, so `dev` finds
  `Developer` and two-letter words such as `QA` or `IT` work). The description is not searched (long and noisy), there is no relevance ranking (the result follows the chosen `sort`), no FULLTEXT index and no
  migration. `%`, `_` and `\` typed by the caller are searched for literally. A word may match any of the three places; all words must match.
- **Order and pages:** offset pagination with a total (`page` from 1, `per_page` 1 to 50, default 20, `meta.total`); `sort` of `-published_at` (default, newest first), `published_at`, `deadline` (closing soonest
  first) or `-deadline`, stable through the id. A position beyond 10,000 (`page` times `per_page`) is 422 `page-out-of-range`, so a very deep page cannot make the database scan hundreds of thousands of rows.
- **Company visibility:** the search asks Company for the ids of the companies that are suspended or hidden (a new shared port method `PublicCompanies::hiddenIds`) and excludes them (`NOT IN`), so Job never reads
  Company's tables and nothing is stored twice. This is fine while those companies are few; if they ever pass about 5,000 it is replaced by a derived column kept by events.
- **Minimum salary:** `filter[salary_min]` (whole rupiah) must come with `filter[salary_period_id]` (422 `salary-period-required` otherwise). A job matches when it has the same period, its salary is visible
  and its upper bound (`salary_max`, or `salary_min` when that is all it has) is at least the amount asked. Jobs without a salary or with a hidden one never match. No conversion between periods.
- **Result row (approved), in the request language:** `id`, `slug`, `title`, `company {id, name, slug, logo_url, verified}`, `location {city, province}`, `job_type {id, name}`, `job_level {id, name}`, `work_mode`, `salary`
  (as on the detail page: `null` when hidden or empty), `deadline` and `published_at`; no description, skills, tags or benefits. `Cache-Control: public, max-age=60` and an `ETag`. The search is limited to **60
  requests per minute per IP** (429 `too-many-requests` with `Retry-After`); the detail page is not limited.
- **A job is searchable** when it is `active`, its deadline is not before today in Jakarta time (as on the detail page) and its company is not in that hidden list.

## Part 5 of the coding: the public job detail (designed and coded 2026-10-06, not committed)

**Done:** `GET /api/v1/jobs/{job}` with the shape below, 17 new tests in `PublicJobTest` (whole suite 857 passed, Pint and PHPStan clean). Implementation notes: the identifier is the route pattern
`[0-9]+(?:-[a-z0-9-]*)?` (anything else, such as `12abc`, an upper-case slug or `0`, is the framework's 404); a job is shown only when it is `active`, **its deadline day is not over in Jakarta time (checked on every
request, so a job whose deadline passed is hidden at once instead of waiting for the hourly expiry)** and the company card exists; the new shared port `PublicCompanies` returns the card or null for a company that is
missing, suspended or hidden; the logo URL is built by Company, so Job never knows Company's routes; a benefit that is not approved is left out of the list. No migration, permission or real-database change was needed.

`GET /api/v1/jobs/{id}` (no sign-in; `{id}` and `{id}-{slug}` are both accepted, the slug part is ignored and nothing redirects; anything else is 404). It shows a job only when the job is `active` and its company is
active and not hidden by its admin; otherwise the answer is the same 404 as for a job that does not exist. Answer, in the request language: `id`, `slug` (`title-123`), `title`, `description` (Markdown),
`company {id, name, slug, logo_url, verified}`, `category`, `role`, `job_type`, `job_level`, `work_time`, `experience_level` and `education_level` (each `{id, name}`), `vacancies`, `deadline` (a date, open until
23:59:59 WIB), `work_mode`, `location {province, city, subdistrict, village, postal_code, address_line}` (names), `skills` and `benefits` (`[{id, name}]`), `tags` (text), `published_at` and `salary {min, max, period
{id, name}}`, which is `null` when the salary is hidden or empty. It never shows the status, reasons, assignees, creator, review details or the company's email and phone. `Cache-Control: public, max-age=60` and an
`ETag`, as for the public company page, so a closed or taken-down job disappears within a minute. The company card comes through the new shared port `PublicCompanies` (provided by Company).

## Part 4 of the coding: the admin side (designed and coded 2026-10-06, not committed)

**Done:** `GET /api/v1/admin/jobs`, `GET /admin/jobs/{id}`, `POST /admin/jobs/{id}/approve`, `/reject`, `/take-down` and `/restore` (guard `admin`, permission `jobs.manage`), with the audit (`job.approved`,
`job.rejected`, `job.taken_down`, `job.restored`; actor the admin; metadata `from`, `to` and, for a rejection and a take-down, `reason`) and the in-app notifications to the assigned members (the data holds the job id
and the reason, never the admin's name). 25 new tests in `AdminJobTest` (whole suite 838 passed, Pint and PHPStan clean). Implementation notes: a decision answers 200 with the whole job as an admin sees it; the four
decisions share one base class (`AdminJobDecision`: admin check, row lock, the decision applied with the status checked first, event after commit; `approve` refuses a passed deadline and `restore` turns such a job `expired`); the shared port
`CompanyDirectory` gained `summaries` (name, slug and status of companies, used instead of the planned `names`) and `idsByName` (so the search text also matches company names; at most 200 companies); the company
side needed no change: a rejected job is editable and can be published again, and a job taken down is locked (409 on edit, close, publish and reopen). **Real database (2026-10-06, on the user's explicit
approval):** `php artisan identity:sync-permissions` was run; the permission `jobs.manage` (group `job`, guard `admin`) now exists (8 permissions, 8 role links) and the user's admin account holds it through `super_admin`;
the `test_` tables were not touched. No migration was needed.

Decisions (on top of the earlier ones: permission `jobs.manage`, `approve`, `reject {reason}`, `take-down {reason}`, `restore`, reason of 5 to 1,000 characters stored on the job and audited, in-app notifications
`job.approved`, `job.rejected`, `job.taken_down`, `job.restored` to the assigned members):
- **A deadline that has passed (changed the same day, after the user saw the consequence):** `approve` of a job whose deadline is before today in Jakarta fails with 409 `job-deadline-passed` and changes nothing; the
  admin rejects the pending job with a reason and the company edits it and publishes again with a new deadline. `restore` of such a job **succeeds and makes it `expired`** (audit `job.restored` with `to` `expired`,
  notification `job.restored`), because the company cannot edit or reopen a job that is taken down and a refusal would leave it stuck for good; the company then reopens it with a new deadline. The first rule chosen
  (409 for both) was replaced for `restore`.
- **Transitions (strict per status):** `approve` and `reject` only from `pending_review` (409 `job-not-pending-review`); `take-down` only from `active` (409 `job-not-active`); `restore` only from `taken_down`
  (409 `job-not-taken-down`) and it returns the job to `active`. Every decision stores `reviewed_at` and `reviewed_by_admin_id`; the reason is stored for `reject` and `take-down` only, and cleared by
  `approve`, `restore` and a new publication. `published_at` is set by the first approval, as the job first goes live.
- **Admin list and detail (approved):** `GET /api/v1/admin/jobs` (offset-paginated): row `id`, `title`, `status`, `company {id, name, slug}`, `deadline`, `vacancies`, `assignees_count`, `published_at`,
  `created_at`, `updated_at`; `filter[status]`, `filter[company_id]`, `filter[q]` (title or company name, case-insensitive); `sort` of `updated_at`, `created_at`, `published_at`, `title` or `deadline` with an
  optional `-`, default `-updated_at` (the review queue is `filter[status]=pending_review&sort=updated_at`, oldest first). `GET /admin/jobs/{id}`: every field as the company sees it plus `company {id, name, slug,
  status}`, and `status_reason`, `reviewed_at` and `reviewed_by_admin_id` whenever they exist. Company names come through the new shared port method `CompanyDirectory::names`.

## Part 3 of the coding: publishing and the lifecycle (designed and coded 2026-10-06, not committed)

**Done:** `POST /company/jobs/{id}/publish`, `/close`, `/reopen` and `/clone`, the editing of live, closed and expired jobs with the review rule, and the command `job:expire-due` (hourly, `withoutOverlapping`,
`onOneServer`; batches of 200). 39 new tests in `JobLifecycleTest` (whole suite 813 passed, Pint and PHPStan clean). Implementation notes: `JobStatus::isEditable` is now true for every status except
`pending_review` and `taken_down`; the transitions live on the `Job` entity (`publish`, `close`, `reopen`, `expire`, `sendForReview`); `JobCompleteness` holds the required-field and deadline rules, and
`JakartaDate` the "today in WIB" rule (a deadline is a date kept as midnight UTC; a job expires when its deadline is before today in Jakarta, so 17:00 UTC is the turn of the day); every status change is one
event `JobStatusChanged` (`published`, `closed`, `reopened`, `sent_for_review`, `expired`) that is audited (`from` and `to`) and, for `expired`, notified (`job.expired`); `published_at` is set once. A brand new
`reference_*`, migration or permission was not needed: nothing has to run on the real database except the scheduler picking up the new command. Not done in this part: the admin side of the review (approve, reject, take
down, restore: part 4) and the public job detail (part 5). **Production note:** the hourly expiry only runs when the Laravel scheduler runs (`php artisan schedule:run` every minute, for example from cron or a
systemd timer); no cron entry or timer for it was found on this server on 2026-10-06 (checked as the `jobportal` user, without root). **Decided with the user the same day and installed on their approval:** the crontab of the
`jobportal` user runs `* * * * * cd /usr/local/src/jobportal && /usr/bin/php artisan schedule:run >> /dev/null 2>&1` (every minute: the schedule stays in code and in UTC, so 02:00 is 09:00 WIB; one boot takes about
0.6 s and 0.45 s of CPU, about 0.75% of one core of eight; the user asked whether it was too frequent, and a sparser pattern such as `0,30 * * * *` would silently skip any task scheduled at another minute). The scheduled
commands are `job:expire-due` (hourly), `identity:anonymize-due-accounts` (02:00 UTC), `platform:purge-audit-logs` (03:00) and `notification:purge-old` (03:30). Remove it with `crontab -r`. If the project is restored on
another server, this crontab line must be added again (it is not part of git).

Decisions:

Decisions so far:
- **Review rule (when `jobs.require_approval` is on for the company):** every time a job goes live again it goes through review. Publishing from `draft` or `rejected`, reopening from `closed` or `expired`,
  and editing a **core field** of an `active` job (title, description, salary amounts, period and visibility, work mode, location and street address) all put the job in `pending_review`. Editing a
  `closed` or `expired` job never triggers a review (it is not live); the review happens when it is reopened. When approval is not required, all of these go straight to `active`. No extra status or column.
- **Audit of status changes (approved):** `job.published` (draft or rejected to `active` or `pending_review`), `job.closed`, `job.reopened` and `job.sent_for_review` (a core edit of a live job returned it to
  review); metadata `from` and `to` (statuses), actor the user. The automatic expiry is audited as `job.expired` with the `system` actor. Cloning and ordinary edits are not audited.
- **Endpoints and rules (approved):** `POST /company/jobs/{id}/publish` (from `draft` or `rejected`; to `active` or `pending_review`), `/close` (from `active` only, to `closed`), `/reopen {deadline}` (from
  `closed` or `expired`; to `active` or `pending_review`) and `/clone` (201 with the new draft); the first three answer 200 with the whole job. `publish` and `reopen` check **completeness** (the stricter required
  list: 422 `job-incomplete` with `errors` per field) and the **deadline** (not before today in WIB, not after today plus `jobs.max_deadline_days` of the company). A live (`active`) job can be edited
  but must stay complete (422 `job-incomplete` when a required field would be emptied); its deadline range is checked only when the deadline itself changes, so a job whose deadline passed minutes ago is not locked
  before the expiry job runs. `closed` and `expired` jobs can be edited freely. Wrong status: 409 `job-not-publishable`, `job-not-active` or `job-not-reopenable`. Expiry: the command `job:expire-due` runs hourly and
  sets `active` jobs whose deadline is before today (WIB) to `expired`, with the audit `job.expired` and the in-app notification `job.expired` to the assigned members.
- **`published_at`:** set **once**, when the job first becomes `active` (directly or through the admin's approval), and never changed afterwards, so closing and reopening cannot be used to push a job up a list.

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
