# Phase 8: job preview, summary, hidden vacancies, salary note and job list filters (designed 2026-10-14, not coded)

Gaps found against Mekari Talenta's job listings (help center articles 34576595122073, 34630221946009 and 34573329191321), chosen by the user from
the advice of 2026-10-14. Not taken: splitting the description into three fields (the Markdown description stays one field; the front end offers
headings instead), more screening answer types and per-stage automation (after go-live), roles per job, CSV import, gender and age requirements,
resume parsing, per-job stages and paid posting to other job boards. Extends `phase-1-jobs.md` (Job). Coded in a coding session.

## Decisions

- 2026-10-14: a **preview** of a job as the public will see it, for every status, with what still blocks publishing (chosen by the user).
- 2026-10-14: an optional **short summary** for search results and sharing; the description stays one Markdown field (chosen by the user).
- 2026-10-14: the company may **hide the number of vacancies** and add a **salary note** (chosen by the user).
- 2026-10-14: **more filters** on the company's job list (chosen by the user).

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

## Changes in short

| Piece | Change |
|---|---|
| Migration | `job_jobs.summary`, `vacancies_visible`, `salary_note` |
| API | `GET company/jobs/{id}/preview`; the three fields on the job form, the company detail, the public detail and search row, alerts and recommendation emails; new filters, sorts and row fields on `GET company/jobs` |
| Review | `summary` and `salary_note` are core fields |
| Errors | 422 `invalid-summary`, `invalid-salary-note`, `message-link-not-allowed`, `invalid-filter` |
| Front end | preview button and banner (C4, C5); summary with counter, Markdown outline, vacancies switch and salary note in the job form (C4); list filters and columns (C3) |
