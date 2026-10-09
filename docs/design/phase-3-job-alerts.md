# Phase 3, step 1: job alerts (J5 and J6, designed 2026-10-09)

New bounded context `JobAlert` (`src/JobAlert`, tables `jobalert_*`). An alert is a **saved search** (J6 is merged into J5): the criteria are the ones of the public job search, so the same results can be opened from the alert.
Spec in `decisions.md`: criteria = role, category, title keyword, location (province, city), level, experience, education, job type and a minimum salary; several alerts per job seeker (**default 5**, admin setting);
frequency immediate, daily or weekly; channels in-app and email chosen per alert.

## Confirmed by the user (2026-10-09)

- **Minimum salary:** a job matches when its maximum salary is at least the alert's minimum, in the same period. A job whose salary is hidden or empty does **not** match an alert that has a minimum salary (as in the public search).
- **Schedule (Jakarta time):** daily alerts at **08:00**, weekly alerts on **Monday 08:00**. One summary per alert with the jobs published since the last one; nothing is sent when there are none.
- **Immediate alerts:** the in-app notification is always created; the email is limited to **5 per alert per Jakarta day**. A job beyond that gets the in-app notification only.

## Details (approved by the user, 2026-10-09)

- **What matches:** only jobs that go live **for the first time** after the alert was created (or resumed): no backlog is sent. A job is checked again when the summary is built, so a job that was closed or expired in between is left out.
- **One matching rule:** matching reuses the public search reader (`JobSearchReader`) with the alert criteria plus a published-after bound (and, for an immediate alert, the id of the job), so alerts and search never disagree.
- **Criteria:** the filter names of the search (`q`, `category_id`, `role_id`, `job_type_id`, `job_level_id`, `experience_level_id`, `education_level_id`, `work_time_id`, `work_mode`, `province_id`, `city_id`, `skill_id`, `company_id`,
  `salary_min` with `salary_period_id`), validated by the same rules as the search, kept as one JSON column. At least one criterion is required (an empty alert would match everything).
- **Alert fields:** `name` (up to 100 characters), `frequency` (`immediate`, `daily`, `weekly`), `email` and `in_app` (at least one is on), `paused`, `last_run_at` (the cursor of the summaries), `emails_day` and `emails_sent` (the immediate email cap).
  Pausing stops matching; resuming moves the cursor to now (no backlog).
- **Notifications:** type `job_alert.matches`; in-app one notification per immediate job, one per summary; the email follows the new preference `job_alert` (email, default on) and a template `jobalert.digest` listing up to 10 jobs with a link to the
  alert results. The email goes on the `bulk` queue.
- **Endpoints (job seeker, `me/job-alerts`):** `GET` list, `POST` create (422 `alert-limit` over the limit), `GET {id}`, `PUT {id}`, `DELETE {id}`, `POST {id}/pause`, `POST {id}/resume`, `GET {id}/jobs` (the search results of the alert, same shape and paging
  as `GET /jobs`). Other people's alerts are 404.
- **Setting:** `jobalert.max_alerts_per_user` (5), admin editable; `SettingsTest` is updated.
- **Triggers:** a listener on the job going live (queue `bulk`) for immediate alerts; the command `jobalert:send-digests` (scheduled at 08:00 Jakarta every day and on Monday) for daily and weekly alerts; idempotent through `last_run_at`.
- **Accounts:** alerts of a deactivated or anonymised account are skipped; anonymising the account deletes its alerts. An email is sent only to a verified address.
- **Legacy data:** the legacy `candidate_job_alerts` (one criterion: the role) are **not imported** in this step; an importer can be added later if the user wants it.
- **No new permission.** Tests: criteria validation, limit, ownership, immediate and digest delivery, the email cap, paused and resumed alerts, salary rule, erasure.

## Coded (2026-10-09, checked with 1562 tests, committed)

Context `src/JobAlert` (Domain, Application, Infrastructure, Interface), provider `JobAlertServiceProvider` (registered in `bootstrap/providers.php`), migrations `000087` (`jobalert_alerts`) and `000088` (`jobalert_deliveries`).
Tests: `tests/Feature/JobAlert/JobAlertTest.php` (API, rules, limit, ownership, `{id}/jobs`) and `JobAlertDeliveryTest.php` (immediate, summaries, cap, paused and resumed, accounts, schedule, erasure), fixtures in `tests/Concerns/BuildsJobAlertFixtures.php`;
`SettingsTest`, `NotificationPreferencesTest` and `EmailTemplatesTest` list the new setting, preference and template.

Choices made while coding (all inside the approved design; the last two are the ones to look at):

- **One matching rule:** `JobSearchQueryBuilder` (new, Job) builds the reader query for both the public search and the alerts. `JobSearchQuery` got an optional window on `published_at` (after, up to and including) and one job id. Job answers
  alerts through the Shared contract `JobMatcher` (`AlertJobMatcher`); JobAlert hands the filters to Job as an array (`JobSearchCriteria::fromFilters`), never Job's classes.
- **`GET me/job-alerts/{id}/jobs` is served by Job** (`AlertJobController`, `SearchJobsOfAlert`), which reads the alert's filters through the Shared contract `SavedSearches` (provided by JobAlert). Same rows, paging and IP throttle as `GET /jobs`; `sort` is not offered (newest first).
- **Cursor:** `last_run_at` is the lower bound of the window (strictly after it). A summary moves it to its own slot (the 08:00 it covers, not "now"), so a job is never sent twice or skipped; changing the frequency also moves it to now.
- **A table `jobalert_deliveries`** (alert, job; unique) makes an immediate alert tell about a job once, so a queue retry, a second approval after a review or any repeated event never sends twice. It goes with the alert (cascade).
- **The email cap is one atomic UPDATE** (`reserveEmail`: `emails_day`/`emails_sent`), taken only when the email would really go out (alert has it on, verified address, preference on), so a skipped email does not use a slot.
- **Which events count as "went live":** `JobStatusChanged` with change `published` that ended `active`, and `JobDecided` `approved`. Reopen, restore and expiry never alert.
- **`ApplicantContact` now says whether the account is active and its email verified** (Identity fills it), used to skip suspended or deactivated accounts and unverified addresses.
- **No link in the email.** The design said the email links to the alert results, but no client page exists yet (every other email says "open your account"). The template lists up to 10 titles and the total; a link can be added once the client URL is known.
- **Alert criteria are validated in two places on purpose:** the request checks the shape (types, lists of up to 20 ids), the domain (`AlertCriteria`) enforces the rules (`criteria-required`, `salary-period-required`, `invalid-criteria`).
- **Cost of an immediate alert:** one search query per immediate alert per job going live (walked in batches of 200 on the `bulk` queue). Fine for a few thousand alerts; a reverse index would be the next step if it grows.
