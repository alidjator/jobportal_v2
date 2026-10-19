# Phase 4, step 2: business intelligence (designed 2026-10-09, coded 2026-10-09, checked)

Analytics for the platform admin and for each company, next to the dashboards of `phase-4-dashboards.md` (the dashboards show the present; this answers analytical questions over a date range).
Lives in the `Dashboard` context (read side). Reads other contexts through their `Shared` read interfaces.

## Confirmed by the user (2026-10-09)

- **Users:** the platform admin (whole platform) and each company (own data only).
- **Analyses:** funnel and speed, job market, rejection reasons and application source, matching quality.
- **Presentation:** analytic endpoints with a date-range filter and CSV export. The client draws the charts; the server returns aggregates.
- **New data on applications (recorded from now on):** `source` (search, invitation, recommendation, direct link, job alert) and `match_score` at apply time, both set by the server, never by the client. Existing applications keep them empty, so the source and matching analyses only become meaningful once data has gathered.
- **Computation:** daily summary tables from the start, filled by a scheduled job (not computed live).
- **Privacy:** the company BI shows aggregates only. A group of fewer than **5** people is shown as "fewer than 5" (the threshold is the same as the salary insight one, an admin setting). No religion, gender, age or nationality in any analysis, for the company or the admin.

- **Source of an application (decided 2026-10-09, user):** the apply request may carry an optional `source` hint from a fixed list (`search`, `recommendation`, `alert`, `direct`). The server validates it, overrides it with `invitation` when the applicant has an invitation for that job, and uses `direct` when the hint is missing or invalid. The hint can be forged by the applicant; it only affects statistics.
- **Date range (decided 2026-10-09, user):** at most 366 days per request, for every analysis.
- **Freshness (decided 2026-10-09, user):** data runs up to yesterday. The summary job rebuilds the previous Jakarta day shortly after midnight; today is not shown. The live medians use the same cut-off so the figures agree.
- **Endpoints (decided 2026-10-09, user):** one endpoint per analysis (funnel and speed, job market, rejection and source, matching quality) under `/admin/analytics/...` and `/company/analytics/...`, with `from`, `to` and `format=csv`. All are GET and only read.
- **CSV (decided 2026-10-09, user):** UTF-8 with a byte order mark, comma separator, dot as decimal mark. The columns are the fields of the matching JSON response, flattened, with small groups written as `<5`.
- **Access (follows `phase-4-dashboards.md`):** the admin analytics need the new permission `analytics.view` (needs `identity:sync-permissions`); the company analytics are for the company admin role only (`CompanyRole::CompanyAdmin`), not for recruiters.

## Analyses

- **Funnel and speed:** conversion between pipeline stages, average time in each stage, average time from application to offer and to hired, offer acceptance rate. Company: per job and per recruiter. Admin: platform-wide.
- **Job market:** jobs and applicants by category, region and experience level, applicants per job, salary spread from our own jobs (only when at least 5 jobs have a visible salary, as in the salary insight).
- **Rejection and source:** the spread of rejection reason codes, applications by source, and the jobs with the lowest conversion.
- **Matching quality:** match score bands against the application outcome, and the success of invitations that came from candidate recommendations.

## Consequences of the summary tables

- A daily table can only be added up, so every measure in it is stored as a **count and a sum** (for example number of transitions and total seconds). Averages are computed from the sums.
- **Median (decided 2026-10-09, user):** a median cannot be added up from daily rows, so it is **not** taken from the summary tables. The median of a duration (time in a stage, time from application to offer and to hired) is computed **live** from `recruitment_application_stage_history` for the requested range: the median is taken by the database (`percentile_cont`, coded 2026-10-10 in `LiveMedians`; first computed in the application when the database was MySQL). Only duration measures get a median.
  The date range of a request is capped, the result is cached, and the small-group rule applies ("fewer than 5" instead of a median over fewer than 5 applications).
  If this proves slow, the fallback is a duration histogram in the daily table (buckets can be added up, so a percentile can be estimated for any range); the endpoint shape does not change.
- The job rebuilds a day from the source tables, so it can be run again for any past day (idempotent). Applications and stage history that already exist are **backfilled** by running the job over the past days; only `source` and `match_score` cannot be backfilled.
- The small-group rule is applied to the total of the requested range, not to each day.
- Tables and exact columns are decided when coding.

## Open points to settle while coding

- The CSV columns of each analysis.
- Add the BI rows to `docs/legal-review.md` if the summaries keep anything per person (they should not).

## What was coded (2026-10-09)

- **Contract** in `Shared/Application/Analytics`: `AnalyticsReportProvider` (one analysis, built by the context that owns the data), `AnalyticsReports` and `AnalyticsRollups` (registries every context fills from its service provider, like the dashboard cards), `AnalyticsRollup` (a daily summary that can rebuild any day), `AnalyticsScope`, `AnalyticsTable`, `AnalyticsDays`. The `Dashboard` context serves them: range check, small-group rule, 10-minute cache, JSON and CSV, the command and its schedule.
- **Application data:** `recruitment_applications.source` and `match_score` (migration `000097`). `ApplyToJob` sets them: `invitation` when the applicant has an invitation for the job, else the validated hint (`search`, `recommendation`, `alert`, `direct`), else `direct`; the score comes from the new port `ApplicantMatchScores` (Matching, same scorer as the recommendations). Neither field is returned by any endpoint.
- **Summaries:** `recruitment_applications_daily` (per day, job, source, match band) and `recruitment_transitions_daily` (per day, job, recruiter, stage pair, rejection reason, source, band, with the sums of seconds in the stage and since the application) in Recruitment; `job_market_daily` (per day, company, category, province, experience level, salary period) in Job. Match bands: unknown, low (under 40), fair (40 to 59), good (60 to 79), strong (80 and up). `php artisan analytics:rollup` rebuilds yesterday at 00:30 Jakarta every night; `--from`, `--to` and `--backfill` rebuild any past days. The rates are rates of moves in the range (not cohorts), capped at 1.
- **Analyses** (`GET /company/analytics/{report}` for a company admin, `GET /admin/analytics/{report}` with `analytics.view`; `from`, `to`, `format=csv`, `table`): `funnel` (stages, speed, offers, by_reason, interviews by status with the no-show rate (added by #29), and for a company by_job and by_recruiter), `rejections` (reasons, sources, and for a company worst_jobs), `matching` (bands, invitations), `market` (categories, provinces, experience levels, salary per period). Medians (time in a stage, time to an offer and to a hire) are read live from the stage history, over the same range.
- **Small groups:** setting `analytics.min_group_size` (default 5, 2 to 50). A row whose count is below it has its figures set to `null` and `suppressed: true` (the market tables hide only the applicant figures, the salary table the whole row); a CSV writes the count as `<5`. A median over fewer values than that is `null`.
- **CSV:** one table per file (`table` picks it, the first by default); the file name carries the report, the table and the range.
- **Not done:** the legal-review row only mentions the new per-application fields (the summaries hold no person). A recruiter filter on `by_job` and a per-job time series are not built.

## Time to fill (designed and coded 2026-10-14)

How long a job takes from going live until its positions are filled. Time to hire (application to hired) already exists in `funnel` (`speed`).

**Decided by the user (2026-10-14):**

- **Start:** `job_jobs.published_at`, set once when the job first goes live (directly or by the first approval); draft and review time are left out. A
  job never published is ignored.
- **Filled:** the moment of the **N-th hire**, N = `vacancies` (the first hire when `vacancies` is empty). The hire time is the move to a `hired` stage
  in `recruitment_application_stage_history`; only applications **still hired now** count, in the order of their hire time. A job with fewer current
  hires than N is not filled, whatever its status; a job filled after it was closed counts.
- **Where:** a new report **`fill`**: `GET company/analytics/fill` and `GET admin/analytics/fill`, same rules as the other analyses (`from`, `to` at
  most 366 days, up to yesterday, `format=csv`, `table`, access).
- **Computed live** from jobs and stage history (no summary table), cached 10 minutes like the live medians.

**Report `fill`:** a job belongs to the range when the Jakarta day it was filled lies in it. Days are the whole time from `published_at` to the fill,
in days with one decimal.

| Table | Company | Admin | Columns |
|---|---|---|---|
| `summary` | yes | yes | filled jobs, average days, median days |
| `by_job` | yes | no | job id, title, published, filled, days, vacancies, hires |
| `by_category` | yes | yes | category, filled jobs, average days, median days |
| `by_recruiter` | yes | no | recruiter (the job's assignees now; a job counts for each), filled jobs, average days, median days |
| `by_company` | no | yes | company, filled jobs, average days, median days (top 100 by filled jobs) |
| `open` | yes | no | active jobs not filled yet: job id, title, published, days open so far, vacancies, hires so far; longest open first |

- Small groups: as today, a median over fewer values than `analytics.min_group_size` is `null`; the figures describe jobs, not people, so rows are
  not suppressed otherwise.
- Owned by **Recruitment** (it holds the stage history); job data (`published_at`, `vacancies`, title, category, assignees) is read through a
  `Shared` job port. Registered in `AnalyticsReports` like the other reports.
- **C1 dashboard card** (company): median time to fill of the last 90 days and the number of active jobs not filled yet, linking to C2.
- Access: company admin today; `analytics.view` once the company permissions are coded (`phase-8-company-permissions.md`).

**Coded (2026-10-14):** no migration. `FillReport` (key `fill`, Recruitment) is built on `TimeToFill`, which combines `HireTimes` (the latest move into a hired
stage of each application that is hired now) with the new `Shared` port `PublishedJobs` (`among`, `liveOfCompany`; Job's `DatabasePublishedJobs`:
`published_at`, `vacancies`, title, category, current assignees). The card is `CompanyTimeToFillCard` (`time_to_fill`: `period_days` 90, `filled_jobs`,
`median_days`, `open_jobs`; needs `analytics.view`, so a recruiter has no such card). Interpretations:

1. A hire moment is the **latest** move into a hired stage, so an application hired twice counts once, at its last hire; hire times are not read before
   `published_at` (a hire earlier than publication, as imported data may have, counts as 0 days).
2. `by_job` (latest fill first) and `open` (longest open first) list every job, so a CSV export is complete (decided by the user); `by_company` is the top 100 by filled jobs; `by_category` has a row with empty
   `category_id` for jobs without a category. `days_open` runs to the moment of the request (the table is the state now, not tied to the range).
3. The `by_recruiter` table counts a job for each account assigned to it now, including a company admin who sits on it.
4. A median needs `analytics.min_group_size` filled jobs, the card too; the average is shown whatever the count.
