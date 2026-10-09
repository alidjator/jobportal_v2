# Phase 4, step 2: business intelligence (designed 2026-10-09)

Analytics for the platform admin and for each company, next to the dashboards of `phase-4-dashboards.md` (the dashboards show the present; this answers analytical questions over a date range).
Lives in the `Dashboard` context (read side). Reads other contexts through their `Shared` read interfaces.

## Confirmed by the user (2026-10-09)

- **Users:** the platform admin (whole platform) and each company (own data only).
- **Analyses:** funnel and speed, job market, rejection reasons and application source, matching quality.
- **Presentation:** analytic endpoints with a date-range filter and CSV export. The client draws the charts; the server returns aggregates.
- **New data on applications (recorded from now on):** `source` (search, invitation, recommendation, direct link, job alert) and `match_score` at apply time, both set by the server, never by the client. Existing applications keep them empty, so the source and matching analyses only become meaningful once data has gathered.
- **Computation:** daily summary tables from the start, filled by a scheduled job (not computed live).
- **Privacy:** the company BI shows aggregates only. A group of fewer than **5** people is shown as "fewer than 5" (the threshold is the same as the salary insight one, an admin setting). No religion, gender, age or nationality in any analysis, for the company or the admin.

## Analyses

- **Funnel and speed:** conversion between pipeline stages, average time in each stage, average time from application to offer and to hired, offer acceptance rate. Company: per job and per recruiter. Admin: platform-wide.
- **Job market:** jobs and applicants by category, region and experience level, applicants per job, salary spread from our own jobs (only when at least 5 jobs have a visible salary, as in the salary insight).
- **Rejection and source:** the spread of rejection reason codes, applications by source, and the jobs with the lowest conversion.
- **Matching quality:** match score bands against the application outcome, and the success of invitations that came from candidate recommendations.

## Consequences of the summary tables

- A daily table can only be added up, so every measure in it is stored as a **count and a sum** (for example number of transitions and total seconds). Averages are computed from the sums.
- **Median (decided 2026-10-09, user):** a median cannot be added up from daily rows, so it is **not** taken from the summary tables. The median of a duration (time in a stage, time from application to offer and to hired) is computed **live** from `recruitment_application_stage_history` for the requested range: the durations are read in order and the median is taken in the application (MySQL has no median function). Only duration measures get a median.
  The date range of a request is capped, the result is cached, and the small-group rule applies ("fewer than 5" instead of a median over fewer than 5 applications).
  If this proves slow, the fallback is a duration histogram in the daily table (buckets can be added up, so a percentile can be estimated for any range); the endpoint shape does not change.
- The job rebuilds a day from the source tables, so it can be run again for any past day (idempotent). Applications and stage history that already exist are **backfilled** by running the job over the past days; only `source` and `match_score` cannot be backfilled.
- The small-group rule is applied to the total of the requested range, not to each day.
- Tables and exact columns are decided when coding.

## Open points to settle while coding

- The date-range limit (a maximum range per request) and the CSV columns of each analysis.
- Permissions: the admin analytics use a new permission (`analytics.view`, separate from `dashboard.view`); company members: which roles may see the company BI (owner and admin of the company, not every recruiter).
- Add the BI rows to `docs/legal-review.md` if the summaries keep anything per person (they should not).
