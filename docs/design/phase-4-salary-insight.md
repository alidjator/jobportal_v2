# Salary insight (designed and coded 2026-10-10)

Gap J10a (`decisions.md`): "Salary insight from own jobs only; shown only if at least N = 5 jobs with visible salary exist for the grouping (admin-configurable)". A job seeker, or a visitor, picks a job role (and optionally a place and an experience level) and sees what the jobs posted on this platform pay for it. No outside data, no salary reports from people, no new table.

It is not the BI `market` report: that one is for companies and admins, is grouped by category, province and experience level, and shows an average range per salary period. This one is public, grouped by role, and shows a median with a spread.

## Decided (2026-10-10, user)

1. **Audience:** public, no sign-in. A route limiter per client address and a cache keep it cheap.
2. **Grouping:** by job role (required), with an optional province or city and an optional experience level. A group with too few jobs widens step by step (see Widening) instead of returning nothing.
3. **Figures:** the median and the P25 to P75 range of the **midpoint** of each job's salary range, over the visible-salary jobs published in the **last 12 months** (closed and expired jobs count, they are real offers). Only the **monthly** period is analysed; `Hourly` and `Project Base` are not mixed in.
4. **Source:** computed live from `job_jobs` with one SQL percentile query per candidate group (`percentile_cont`), cached for 1 hour. No summary table, no job.
5. **Companies:** a group needs at least **3 different companies** as well as the minimum number of jobs, so one company posting several jobs never shows its own pay (a fixed rule, not a setting).
6. **Last step of widening (changed the same day, user):** a job role has no category (the two are chosen separately on each job; `reference_job_roles` has no category column), so "the role's category" does not exist. The client may send an optional `category_id`; when the role alone has too few jobs and a category was given, the group widens once more to **all jobs of that category** (any role, anywhere, any level), and the answer says it describes the category. Without `category_id` the answer is `available: false`.

## Rules

- **Which jobs:** `status` in `active`, `closed`, `expired`; `salary_visible = true`; the salary period is the master item with slug `monthly` (looked up by slug, never by id); at least one of `salary_min` and `salary_max` is set; `published_at` within the last 12 months. A hidden-salary job is left out of every public statistic (decision of `phase-1-jobs.md`). Draft, pending, rejected and taken-down jobs never count.
- **Midpoint of a job:** `(salary_min + salary_max) / 2` when both are set, else the one that is set.
- **Small groups:** the minimum is the existing admin setting `analytics.min_group_size` (default 5, 2 to 50), the same one the BI reports use. A group is big enough when it has at least that many jobs **and** at least 3 different companies (decision 5).
- **Widening:** the candidate groups are tried from the most specific to the least, and the first one with enough jobs is answered. With a city, the places are city, province, whole country; with a province only, province, whole country; with none, the whole country. At each place the requested experience level is tried first, then none. So `role + city + level` falls back to `role + city`, then `role + province + level`, `role + province`, `role + level`, then `role` alone, and, only when a `category_id` was sent, as the last step the category (any role, the whole country, any level). The answer says which group it used, so a client can say "in Jawa Barat" instead of "in Bandung".
- **Not enough data:** when even the category has too few jobs, the answer is `200` with `available: false` and no figures and no counts (a count below the minimum is never shown). It is not an error: a role with little data is a normal case.

## API

`GET /salary-insight` (public). Query: `role_id` (required, an active job role), `province_id`, `city_id` (a city implies its province, which is looked up; a city that does not belong to the given province is `422`), `experience_level_id`, `category_id` (only the last widening step uses it). Unknown or inactive ids are `422` with the field, like the job search. Limiter `salary-insight`: 60 per client address per minute. Not an authenticated route: no token is read.

Answer (`data`):

```json
{
  "available": true,
  "currency": "IDR",
  "period": "monthly",
  "window_months": 12,
  "role": {"id": 12, "name": "Backend Developer"},
  "scope": {
    "level": "role",
    "category": null,
    "province": {"id": 32, "name": "Jawa Barat"},
    "city": null,
    "experience_level": {"id": 3, "name": "Mid level"}
  },
  "widened": true,
  "jobs": 18,
  "p25": 9000000,
  "median": 11500000,
  "p75": 15000000,
  "updated_at": "2026-10-10T07:00:00+00:00"
}
```

`scope` is the group that was used (`level` is `role`, or `category` when the last step was needed: then `category` names it and `province`, `city` and `experience_level` are null; `category` is null at level `role`); `widened` is true when it is wider than the request. `jobs` is shown only because it is at least the minimum. Names follow the request language (`Accept-Language`, as in the other public answers). Amounts are whole rupiah, rounded. When `available` is false, only `available`, `currency`, `period`, `window_months`, `role` and `updated_at` are present.

## Where it lives

The Job context (it reads only its own table, `job_jobs`, so no cross-context access): `Application/Insight/GetSalaryInsight` (use case: validate the references through the existing `ReferenceValues` and `LocationReferences` ports, read the minimum through the analytics-settings port the Job context already uses, ask the reader for the candidate groups in order), `Application/Insight/SalaryInsightReader` (port) and `SalaryInsight` (the answer), `Infrastructure/Insight/DatabaseSalaryInsightReader` (SQL, with the table prefix taken from the connection). Two small additions to shared ports: `ReferenceValues::idBySlug()` (the monthly period is found by slug) and `LocationReferences::provinceOfCity()` and `CachedSalaryInsightReader` (1 hour, key from the role, place and level), `Interface/Http/Controllers/SalaryInsightController`, `Requests/SalaryInsightRequest`, `Resources/SalaryInsightResource`, and the route in the Job context's public routes. Limiter `salary-insight` is registered in `JobServiceProvider` next to the view limiter.

No migration. The existing indexes on `role_id`, `province_id` and `city_id` serve the query; a composite index on `(role_id, status, published_at)` is added only if the query proves slow on real data.

## Privacy and honesty

Only job-posting data is read, never a person's data. Companies are not named, and the jobs of companies the public cannot see (suspended or hidden) are left out, as in the job search. A hidden salary stays hidden everywhere. The figures are described as "what jobs on this platform offer", never as what people earn, and the window and the count of jobs are always shown, so a client can show how thin the data is.

## Tests (to write with the code)

A median and spread over known jobs; the midpoint of a one-sided range; a group with enough jobs but fewer than 3 companies being skipped; each exclusion (hidden salary, hourly, project, draft, pending, rejected, taken down, older than 12 months, another role); closed and expired jobs counting; widening through every step including the category, with `widened` and `scope`; `available: false` below the minimum, with no count; the setting changing the minimum; `422` for a missing role, an unknown role, a city outside the province; language of the names; the cache (a second call does not query, a new job shows after the cache is cleared); the limiter (429).
