# Phase 5: performance audit (static audit done and decided 2026-10-10; measurement and coding open)

Scope (`phase-5-scope.md`): query and index performance. Method: the catalog of the real PostgreSQL `jobportal_v2` (schema only, no data was read; it holds only reference data) plus the code of the hot read paths. Everything below marked *static* is an inference from the code and needs a measurement before it is fixed (see [Measurement plan](#measurement-plan)); everything marked *catalog* is a fact read from `pg_catalog`.

## Decisions

- 2026-10-10: I1 to I3: one index migration with an index on **all 39** unindexed foreign key columns, the two redundant indexes dropped, and the partial candidate index ([Findings](#findings)).
- 2026-10-10: R2: Redis through `predis/predis` (composer) for the cache store; the user sets `CACHE_STORE=redis` in `.env` at deployment ([Findings](#findings)).
- 2026-10-10: R1: `last_used_at` of a token is written at most every 5 minutes, through an own token model ([Findings](#findings)).
- 2026-10-10: Measurement volume (assumed by Claude, accepted by the user): 200,000 job seekers, 20,000 jobs, 1,000,000 applications, synthetic data in a rehearsal database ([Measurement plan](#measurement-plan)).
- Not decided yet: Q1 (rating aggregation, no decision needed, a pure rewrite), T1 (after the user's `--profile` run).

## Findings

**Indexes (catalog)**

- **I1** 39 single-column foreign keys have no index of their own (PostgreSQL does not create one). Needed for the join or filter in the hot paths: `reference_cities.province_id`, `reference_subdistricts.city_id`, `reference_villages.subdistrict_id` (the cascading pickers; villages is the largest table, 11 MB), `recruitment_applications.stage_id` and `.company_id` (stage filter, `countByStage`, company-wide lists), `jobseeker_profiles.province_id` and `.city_id` (candidate search filters, talent pool), `jobseeker_skills.skill_id`, `notification_device_tokens.user_id` (push fan-out, erasure), `company_invitations.company_id`. The other 29 serve only the `RESTRICT` check when a parent row is deleted (reference rows, admin audit columns, screening question options) and the remaining profile lookups (`religion_id`, `nationality_id`, `village_id`, ...): no index proposed.
- **I2** Two redundant indexes: `job_jobs_company_id_index` (covered by `job_jobs_company_id_status_index`) and `recruitment_job_questions_job_id_index` (covered by `recruitment_job_questions_job_question_unique`). Each costs a write per row change.
- **I3** `jobseeker_profiles` has no index for the candidate search order (`updated_at desc, id desc`) or for `is_discoverable`; the search sorts every discoverable, complete profile. Proposal: a partial index `(updated_at desc, id desc) where is_discoverable and slug is not null`. The `identity_users` join and the four `EXISTS` checks of `CompleteProfiles` are indexed (`user_id` on resumes, experiences, educations).
- **I4** `notifications` has `(notifiable_type, notifiable_id)` and a plain `created_at` index; the list and the unread count read per user and sort by `created_at`. Low priority: a per-user composite `(notifiable_type, notifiable_id, created_at desc)` only if the measurement shows a sort.

**Queries (static)**

- **Q1** `DatabaseRecruiterApplicationReader::withRatings` joins a subquery that groups the whole `recruitment_application_ratings` table; PostgreSQL cannot push the join condition into a grouped subquery, so every applicant list aggregates all ratings of the platform. Fix: aggregate only the ratings of the listed applications (a lateral join, or a second query for the page ids).
- **Q2** Job search (`DatabaseJobSearchReader`): `count(*)` plus offset pagination, and `NullOrder::sql(...)` orders by a raw expression, which no index on `published_at` or `deadline` can serve. Fine for thousands of jobs; measure at the target volume before adding a partial index `(published_at desc) where status = 'active'`.
- **Q3** The applicant name search reads the JSON snapshot with `LIKE`; it is bounded by one job's applicants, so it is accepted.

**Runtime (static and configuration)**

- **R1** Sanctum updates `personal_access_tokens.last_used_at` on **every** authenticated request (`Guard::updateLastUsedAt`): one `UPDATE` per API call. Option: a custom token model that writes it at most every few minutes.
- **R2** `CACHE_STORE=file` on the live site. The cache serves the rate limiter (every request, `ApiRateLimit` and the sign-in throttles), the dashboards, the settings and the scheduler locks (`onOneServer`, `withoutOverlapping`). A file store has no real atomic increment and does file I/O on each hit. There is no `cache` table, `redis-server` is installed on the server, but neither `phpredis` nor `predis` is. Options: Redis through `predis/predis` (composer) or `phpredis` (server), or the `database` store (a migration for `cache` and `cache_locks`).
- **R3** Fixed cost per request, to be measured and then guarded by a test: token lookup and its tokenable (2 queries), the token `UPDATE` (R1), the terms gate (a query per user request), the membership resolver (company members), the language resolver.

**Test suite**

- **T1** Measured 2026-10-10 (`--profile`, 457 s, 1956 tests): there is no slow test. The ten slowest sum to 34 s (7%); the rest averages about 0.2 s per test, so the cost is a fixed price per test, not a bug. Warm numbers: boot plus transaction 45 ms, shared fixtures 120 ms (82 queries, about 80 ms SQL at roughly 1 ms per statement, the rest PHP in the HTTP kernel), first request per app about 22 ms. Ruled out: connection time (19 ms), JIT (no effect), opcache and xdebug (opcache on, no xdebug), test config (array cache, bcrypt 4). The earlier 'about 1 minute' came from a much smaller suite, so the PostgreSQL slowdown is probably overstated. Decision 2026-10-10: run the suite in parallel with `brianium/paratest` (8 processes). Each process works in its own copy `jobportal_v2_test_<token>`, made from the migrated test database by `php artisan test:database:parallel --env=testing` (rerun after every `test:database:migrate`); `TestCase` maps `TEST_TOKEN` to the copy and `TestDatabaseGuard` accepts only `jobportal_v2_test` and `jobportal_v2_test_<n>`. Run with `composer test:parallel`; `composer check` stays serial.

## Measurement plan

No row exists yet to measure on. Proposal: a rehearsal database (the role has `CREATEDB`, see `HANDOFF.md`) filled with synthetic data at the target volume, then `EXPLAIN (ANALYZE, BUFFERS)` of the hot queries: job search (keyword, filters, each sort), candidate search, applicant list with and without ratings, notification list and unread count, conversations, the dashboards and analytics reads, and the reference pickers. The result decides I3, I4, Q2 and the partial indexes; I1, I2, Q1, R1, R2 do not depend on it.

## Coding plan

1. Migration `000112`: the 39 foreign key indexes, drop the two redundant ones, the partial candidate index (I1 to I3); run by the user on the test database, then the real one with approval.
2. Q1 rewrite of `withRatings`; R1 token model that throttles `last_used_at` (`Sanctum::usePersonalAccessTokenModel`), each with tests.
3. R2: `composer require predis/predis` and the `.env.example` keys (`CACHE_STORE`, `REDIS_*`); the user switches the live `.env`.
4. Measurement on the rehearsal database (synthetic seeder as a console tool, EXPLAIN of the hot queries); its result decides I4, Q2 and any further index.
5. T1: decided 2026-10-10: parallel runs with `paratest`, coded (see T1 note below the findings list).
6. R3: query-count tests for the fixed cost of a request.
