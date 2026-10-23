# Phase 8: job preview, summary, hidden vacancies, salary note, job list filters, apply requirements and apply state (designed 2026-10-14; sections 1 to 4 coded 2026-10-23, 5 and 6 not coded)

Gaps found against Mekari Talenta's job listings (help center articles 34576595122073, 34630221946009 and 34573329191321), chosen by the user from
the advice of 2026-10-14. Not taken: splitting the description into three fields (the Markdown description stays one field; the front end offers
headings instead), more screening answer types and per-stage automation (after go-live), roles per job, CSV import, gender and age requirements,
resume parsing, per-job stages and paid posting to other job boards. Extends `phase-1-jobs.md` (Job). Coded in a coding session.

## Decisions

- 2026-10-14: a **preview** of a job as the public will see it, for every status, with what still blocks publishing (chosen by the user).
- 2026-10-14: an optional **short summary** for search results and sharing; the description stays one Markdown field (chosen by the user).
- 2026-10-14: the company may **hide the number of vacancies** and add a **salary note** (chosen by the user).
- 2026-10-14: **more filters** on the company's job list (chosen by the user).
- 2026-10-14: a job may require **extra profile items** chosen from existing profile fields (no form builder), and a signed-in job seeker gets the
  job's **apply state** before applying (chosen by the user). This replaces "no eligibility pre-check endpoint" of `phase-1-applications.md`; the
  public job page stays cached and free of per-user data.
- 2026-10-23: the front end asked for the job editor (C4), so **sections 1 to 4** are coded now (preview, summary, hidden vacancies, salary note, the job list
  filters), together with the waiting period of `phase-8-rejections.md` and the work locations of `company-locations.md`. Sections 5 (requirements) and 6
  (apply state) wait. The coded details are in "Coded (2026-10-23)" below.

## 1. Preview

- `GET company/jobs/{id}/preview` for any member who may see the job, in every status: `data` is the public job detail exactly as
  `GET jobs/{id}` would build it from the current saved fields (company, location names, skills, benefits, salary or `null`, summary, salary note,
  vacancies or `null`), plus `meta.publish_check {ready: bool, missing: [field codes]}` from the same rule set as publishing (`title`,
  `description`, `category_id`, `skill_ids`, `deadline`, …), so the form can mark them.
- Nothing is logged or counted (no view), the job is not cached, and the answer is `Cache-Control: no-store`.
- Front end: "Pratinjau" in the job form (C4) and on the job page (C5); the public detail page component renders it with a "Pratinjau" banner.

## 2. Summary

- New column `job_jobs.summary` (nullable, plain text, trimmed, 20 to 150 characters, no line breaks; 422 `invalid-summary`). Optional for
  publishing. No links: the link rule of `phase-8-interview-calendar.md` section 6 applies (422 `message-link-not-allowed` on `summary`).
- **Shown:** the public search row (`summary`), the public detail, the company list and detail, the job alert email and the recommendation email
  rows; the `og:description` of the job page (front end). Without a summary, clients use nothing (no cut description).
- A **core field** for review: editing it on a live job of a company with `jobs.require_approval` sends the job back to `pending_review`, like the
  description.
- Front end: the job form offers a Markdown outline for the description ("Tanggung jawab", "Persyaratan", "Benefit") and the summary field with a
  counter.

## 3. Hidden vacancies and the salary note

- New column `job_jobs.vacancies_visible` (boolean, default true). `vacancies` stays required for publishing and keeps feeding time-to-fill and
  "filled" (`phase-8-hiring.md`); when hidden, the public detail answers `vacancies: null`. The company side always sees the number.
- New column `job_jobs.salary_note` (nullable, plain text, trimmed, at most 150 characters, no links, 422 `invalid-salary-note`), for example
  "Gaji pokok + insentif bulanan" or "Kompetitif". Shown on the public detail and search row even when the amounts are hidden (`salary: null`,
  `salary_note: "…"`). It is not a filter and never enters the salary statistics. A core field for review, like the salary.
- Clone copies the three new fields.

## 4. Company job list filters

`GET company/jobs` keeps `filter[status]`, `filter[q]` and `filter[assigned_to_me]`, and adds:

| Filter | Meaning |
|---|---|
| `filter[province_id]`, `filter[city_id]` | the job's location |
| `filter[work_mode]` | `onsite`, `hybrid`, `remote` (comma-separated) |
| `filter[job_type_id]`, `filter[category_id]` | masters (comma-separated) |
| `filter[published_from]`, `filter[published_to]` | `published_at` in Jakarta dates |
| `filter[deadline_from]`, `filter[deadline_to]` | the deadline |
| `filter[created_by]` | a member id |
| `filter[assignee]` | a member id (a company admin or `jobs.view_all` only; others already see only their own jobs) |
| `filter[filled]` | true or false (`phase-8-hiring.md`; only once that is coded) |

- **Sort** adds `deadline` and `applications_count` (`-` for descending).
- Rows gain `location {city, province}`, `work_mode`, `created_by {id, name}` and `applications_count` (active applications) so the list can show
  them as columns.
- Malformed values: 422 `invalid-filter`.

## 5. Extra profile requirements per job

- New column `job_jobs.apply_requirements` (JSON, null for none), set in the job form, any status, not a core field for review; it applies to
  applications made after the change. Items, each at most once:

| Item | Met when the profile has |
|---|---|
| `expected_salary` | an expected salary with its period |
| `portfolio` | a link of type `portfolio`, `website` or `github` |
| `linkedin` | a link of type `linkedin` |
| `whatsapp` | a WhatsApp number |
| `language` `{language_id, min_proficiency}` (at most 3) | that language at `min_proficiency` or above (`basic` < `conversational` < `fluent` < `native`) |

- Errors on save: 422 `invalid-apply-requirements` (unknown item, inactive language, more than 3 languages, a repeated item).
- **Applying:** Recruitment asks Job (`JobDirectory`) for the requirements and passes them to the JobSeeker port that builds the submission; a
  missing item answers the existing 422 `profile-incomplete` with its code in `missing` next to the basic ones (`expected_salary`, `portfolio`,
  `linkedin`, `whatsapp`, `language:{id}`). No new error.
- **Shown:** the public detail lists them (`apply_requirements: [{item, language?, min_proficiency?}]`, names in the request language), so the job
  page reads "Wajib: portofolio, Bahasa Inggris (lancar)"; the company detail and clone carry them.
- Nothing about protected attributes can be required (the list is closed).

## 6. Apply state

- `GET me/jobs/{id}/apply-state` (job seekers, `auth:user`, `Cache-Control: no-store`; 404 when the job is not live, as applying):
  `{can_apply: bool, reasons: [{code, missing?, reapply_on?}], application: {id, status, applied_at}|null, applications_left_today: int}`.
- **Reasons**, the same codes the apply request would answer, checked in its order: `email-not-verified`, `profile-incomplete` (with `missing`,
  basic and per-job items together), `already-applied` (with `reapply_on` when the job's waiting period of `phase-8-rejections.md` gives one),
  `application-limit-reached`, `applications-unavailable`. Screening answers are not judged here; they belong to the apply form.
- Read only, no locks; the apply request still decides. Limited by the general API limiter.
- Front end: the job page calls it after sign-in; the "Lamar" button shows the first reason with its fix ("Verifikasi email", "Lengkapi
  profil: portofolio", "Anda sudah melamar", "Batas lamaran hari ini tercapai").

## Coded (2026-10-23)

Migration `2026_10_23_000186` (new, run on the test and the real database): `job_jobs.summary` (150), `vacancies_visible` (default true), `salary_note` (150),
`reapply_after_days`, and `job_job_locations` (`company-locations.md` "Coded: #20").

- **Preview (section 1):** `GET company/jobs/{id}/preview` answers `{data: <the public job detail>, meta: {publish_check: {ready, missing}}}` with `Cache-Control: no-store, private`. `missing` holds the
  API field names `PublishJob` would refuse (the keys of `job-incomplete`, the deadline range included). Any member who may see the job gets it in every status. A company the public cannot see (hidden by
  its own admin) still previews its jobs: the company shows under its name, `verified` from the company and without a logo. Nothing is counted or logged.
- **Summary (section 2):** `invalid-summary` (422, `errors.summary`) for a text outside 20 to 150 characters or with a line break; the link rule answers `message-link-not-allowed` on `summary`. A blank text is stored
  as null. Only a changed value is checked, so an old value never blocks an unrelated edit. **Shown (coded):** the public search row and detail, the company list and detail, the preview. **Not yet:** the job alert email,
  the recommendation email (the rows of those emails are built outside Job). Core field: a change sends a live job back to review when the review policy asks for it.
- **Hidden vacancies and salary note (section 3):** `vacancies_visible` (public `vacancies: null` when false; the company detail and list always show the number); `salary_note` (150, one line, link rule, `invalid-salary-note`),
  a core field like the salary, shown on the public detail and search row even when `salary` is null. `vacancies_visible` is not a core field. Clone copies all of them.
- **Company list filters (section 4):** every filter of the table is coded, comma-separated for the id lists (`province_id`, `city_id`, `work_mode`, `job_type_id`, `category_id`). A malformed value is refused by the
  validation of the request (422 `validation-failed` with `errors`, like the applicant filters), **not** by an `invalid-filter` code: the design's code is dropped. The place filters match through any of the job's company
  locations; `published_from` and `published_to` are Jakarta days (the last day counts); `filter[assignee]` is an extra condition, so a recruiter (who sees only their jobs) can only narrow their list with it;
  `filter[filled]` compares the hired applications with the vacancies in SQL (a job without vacancies is never filled). The counts by status (`GET company/jobs/status-counts`) take the same filters. Sorts `deadline`
  (already there) and `applications_count`. Rows gain `summary`, `applications_count` (active applications), `work_mode`, `location {city, province}` (the job's own address, the main location when it has
  locations) and `created_by {id, name}`.
- **Company detail:** `summary`, `vacancies_visible`, `salary_note`, `reapply_after_days`, `location_ids` and `locations`.

| Piece | Change |
|---|---|
| Migration | `job_jobs.summary`, `vacancies_visible`, `salary_note` (coded `000186`), `apply_requirements` (not coded) |
| API | `GET company/jobs/{id}/preview`; the three fields on the job form, the company detail, the public detail and search row, alerts and recommendation emails; new filters, sorts and row fields on `GET company/jobs`; `apply_requirements` on the job form and the public detail; `GET me/jobs/{id}/apply-state` |
| Review | `summary` and `salary_note` are core fields |
| Errors | 422 `invalid-summary`, `invalid-salary-note`, `message-link-not-allowed`, (`invalid-filter` dropped, see above), `invalid-apply-requirements`; new `missing` codes in `profile-incomplete` |
| Front end | preview button and banner (C4, C5); summary with counter, Markdown outline, vacancies switch and salary note in the job form (C4); list filters and columns (C3); requirements picker in the job form; apply state on the job seeker's job page |
