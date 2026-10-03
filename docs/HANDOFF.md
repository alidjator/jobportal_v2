# Handoff (written 2026-10-03)

Read this first when continuing in a new session. It is self-contained; the other documents hold the details.
(The user communicates in Indonesian; project artifacts are English.)

## 1. What this project is

A new **job portal REST API** (Laravel 13, DDD) in `/usr/local/src/jobportal`, replacing the production web application
**karir.pkp** (`/usr/share/nginx/html/karir.pkp`, live at `https://karir.satu.solutions`). The term `karir.pkp` always means that legacy
source. It is a **reference only**, never ported 1:1, and read-only.

History in one paragraph: a first port of the legacy REST API (92 endpoints, 277 tests) was built and then **deliberately discarded**
(commit `ae6736c` still contains it; do not reuse it). The user established that the legacy API is stale and the real flows live in the
web application, so the project restarted from analysis: flow map, benchmark research, gap analysis, then design decisions.
The repository now contains only the Laravel 13 skeleton, a small shared kernel, and design documents.

## 2. Working rules (mandatory, set by the user)

1. **Work alone and strictly sequentially**: no sub-agents, no Workflow, no background tasks, and **one tool call per step** (no parallel tool calls).
2. **Decision gate**: findings in karir.pkp that violate best practice (security, bugs/correctness, architecture/code quality, performance)
   and any schema assumption are **reported/confirmed with the user first**. Never silently fix or copy them. Never assume that a table
   or column does or does not exist; read the current production schema and confirm.
3. **Rule conflicts**: there is no fixed priority between rules. If two rules conflict, stop and ask.
4. **Code standard**: Laravel best practice with DDD is mandatory (layers Domain/Application/Infrastructure/Interface, one use case per class,
   thin controllers, FormRequests, no global helpers, Pint and PHPStan clean).
5. **English everywhere in the project**: identifiers, comments, documentation, commit messages, API error messages. Indonesian terms the user
   gives are mapped to common English job-portal terms (lamaran = application, lowongan = job posting/job, pelamar = applicant/candidate,
   perusahaan = employer/company, etc.). Communication with the user stays in Indonesian. New table/column/field/route names are designed freely
   (not bound to legacy names).
6. **Source of truth**: flows come from the web application (source `routes/web.php`, `website.php`, `admin.php` and the live site). Current schema is read
   from the production database through a **read-only account**.
7. **Benchmarks** for flows (all features): Glints, JobStreet, LinkedIn, karir.com, loker.id. Record only facts verified from public sources; mark the rest as unverified.
8. **Out of scope** this phase: plans, billing, payments, invoices, pay-per-job and featured/highlight promotion.
9. **Database objects**: schema is *hybrid* (only needed tables), table prefix per bounded context. **All test-related objects** (tables, triggers, functions,
   procedures, events) must use the **`test_` prefix** in front of the context prefix; tests run inside `jobportal_v2` and must never touch non-`test_` objects.
10. **Ask one question at a time** and do not decide for the user. The user answers through option prompts; record each answer.

## 3. Environment and access

| Item | Value |
|---|---|
| New project | `/usr/local/src/jobportal` (Laravel 13.34, PHP 8.3, Pint, Larastan, PHPUnit 12). Git repo, last commit `fbb8afa` |
| New database | MariaDB 11.8 at `127.0.0.1:3306`, database `jobportal_v2`, user `jobportal_v2` (full rights on that DB only); **currently empty (0 tables)** |
| Production read-only | user `jobportal_v2_read` (SELECT on `karir_pkp` and `jobportal_v2`); **schema only, never read data** without explicit approval |
| Credentials | only in the git-ignored `/usr/local/src/jobportal/.env` (`DB_*` for the new DB; `KARIR_PKP_DB_*` for the read-only production account). **Never copy secrets into docs, memory or commits** |
| Live site accounts | the user supplied one job seeker, one recruiter and one admin account in chat; **not stored anywhere**. Ask the user again if needed |
| Live site protocol | login and read-only browsing; ask before any action that creates/changes data; end sessions with a **POST logout** and delete cookie files; never open the admin GET routes that change data (`jobs/delete-selected`, `company/change/status`, `company/verify/status`, `candidate/change/status`, `generate/sitemap`, `sync/language/*`, `settings/delete/*`) |
| Memory | `/home/jobportal/.claude/projects/-home-jobportal/memory/` (index `MEMORY.md`) holds the rules and decisions; loaded automatically when the session starts in `/home/jobportal` |

Useful details:

- Dump the current production schema (no data): `mariadb-dump --no-data --skip-comments --no-tablespaces --skip-triggers --skip-lock-tables`
  with the `KARIR_PKP_DB_*` values (the read-only account has no LOCK TABLES right, hence `--skip-lock-tables`). 125 tables on 2026-10-03.
- `php -l` segfaults on this server; use `php -n -l <file>`.
- A private MariaDB used for the first port's tests was shut down; no local test database exists now.
- The repository still contains stale test configuration: `phpunit.xml` and `.gitlab-ci.yml` point to a `test_jobportal` database; they must be reworked to use
  `jobportal_v2` with a `test_` table prefix (and a guard that aborts when the prefix is not active).
- `config/auth.php` points to `App\Models\User`, which does not exist yet (the user model arrives with the Identity context).
- The Laravel skeleton ships `AGENTS.md`/`CLAUDE.md` that ask to install Laravel Boost; they were removed because they are not the user's instructions.
- Code in the repo: `src/Shared` (`ApiResponse`, domain exceptions, `RequestContext`, `ContextServiceProvider`, `ApiController`),
  `bootstrap/app.php` (exception rendering), `app/Providers/AppServiceProvider.php`, `tests/TestCase.php`, `tests/FeatureTestCase.php`.
  The exception mapping and `ApiResponse` envelope come from the discarded port and may be revisited.

## 4. Document index

| File | Content |
|---|---|
| `docs/analysis/legacy-flow-map.md` | Web application flows by actor (from source and live site) |
| `docs/analysis/legacy-findings.md` | Best-practice findings in karir.pkp and its schema (reported; no per-item decision yet) |
| `docs/analysis/benchmark-notes.md` | Verified benchmark facts per platform, with verification level |
| `docs/analysis/gap-analysis.md` | Flow gaps vs benchmarks with production-schema evidence |
| `docs/design/decisions.md` | **All confirmed design decisions** (stages, screening, gap scope, foundation, delivery, phases) |
| `docs/design/phase-0-schema.md` | Column-level design of the Phase 0 tables, **reviewed and approved by the user (2026-10-03)**, plus the list of details still open |

## 5. Status

Done: step 1 (flow map), step 2 (benchmarks, five platforms), step 3 (gap analysis), scope of every gap, sequencing (five phases, approved),
Phase 0 concept decisions, the approved list of Phase 0 tables, the seven open schema questions (all answered), and the **column design of all Phase 0
tables, reviewed table by table and approved by the user** (Identity, Platform, Notification, ReferenceData, Content). `decisions.md` and
`phase-0-schema.md` are in sync with every decision taken so far.

Key conventions fixed in that review (details in `phase-0-schema.md`): every project table has `id` `bigint unsigned` auto-increment (exceptions: three
RBAC pivot tables with the Spatie composite key, and framework tables); translatable text is JSON (`id`/`en`) via `spatie/laravel-translatable` in
Infrastructure models only; account deletion is anonymisation with a 30-day grace period; contact data lives in Phase 1 profiles, not in `identity_users`.
The section "Details still open" of `phase-0-schema.md` lists what was deliberately left undecided (do not assume those).

Migrations: the **ten Identity migrations are written** (`database/migrations/2026_10_03_000001` to `000010`, one per table, each confirmed with the user) and
have been run **only against the `test_` tables** (`php artisan test:database:migrate`). The real (non-prefixed) tables have **not** been created
(`php artisan migrate` was not run). Lessons: MariaDB limits identifiers to 64 characters, so long index names need an explicit name (done for
`identity_verification_codes` and `identity_terms_acceptances`); DDL is not transactional, so a failed migration leaves a partial table that must be
dropped by hand before retrying. The two RBAC pivots `admin_roles` and `admin_permissions` also have an extra `(model_id, model_type)` index copied from the
Spatie structure (not in the approved design; the user was told). The four Platform migrations (`000011` to `000014`) are also written and run against the `test_` tables only; the `updated_by_admin_id` columns have a plain
index (cross-context convention) and the long index names in `platform_email_template_overrides` are explicit. The Notification (`000015` to `000017`, with the
`created_at` index inside the `notifications` migration) and ReferenceData (`000018` to `000039`: five location tables with `RESTRICT` foreign keys, 17
masters) migrations are also written and run against the `test_` tables only, and so are the three Content migrations (`000040` to `000042`). **All 42 Phase 0
migrations exist and the **real (non-prefixed) tables were created on 2026-10-03 with `php artisan migrate`, on the user's explicit approval** (`jobportal_v2`
now holds 43 real tables including `migrations`, and 43 `test_` tables; compared column by column, FK count 13 and CHECK count 42 are identical). Tables are empty.
**Master seeders (2026-10-03):** 16 seeders under `database/seeders/Reference/` (one per master, on a shared `MasterSeeder` that inserts by slug and never
overwrites existing rows) plus `ReferenceDataSeeder`, called by `DatabaseSeeder` (192 rows in total). Their values come from the legacy master tables read
with the user's explicit approval (SELECT only), tidied and approved per master; English/Indonesian names, ranges, kinds and codes not present in the legacy
data were written for this project and approved by the user. `reference_tags` is deliberately not seeded (the legacy tags are mostly test data; numeric
names suggest ids stored as names, unverified in the legacy code). Benefits (12), nationalities (25) and part of the languages (25) are our own lists because
the legacy tables are empty or hold other data. **Run against the real tables on 2026-10-03 with `php artisan db:seed`, on the user's explicit approval** (the 16 target
tables were empty beforehand; afterwards they hold the 192 rows, the `test_` tables stay empty, `reference_tags` and the location tables remain empty). The
seeders are safe to run again (they only add missing slugs). Not done: location data (BPS source undecided), legal documents and FAQ content, domain code. From now on every new migration must be run twice: `php artisan test:database:migrate`
(test tables) and `php artisan migrate` (real tables, only with the user's approval). Open points noticed while writing the migrations: `reference_languages.code` is not unique
(the design did not say so); `min_years`/`max_years`/`rank` are signed smallint as written in the design.
New files under `docs/` are **uncommitted** (the user has not asked for a commit).

## 6. Suggested next steps

1. **Test setup rework: DONE (2026-10-03), code not committed.** `phpunit.xml` and `.gitlab-ci.yml` use `jobportal_v2` with `DB_PREFIX=test_`
   (forced); `config/database.php` reads `DB_PREFIX`; `Src\Shared\Infrastructure\Testing\TestDatabaseGuard` fails closed (prefix must be exactly `test_`,
   environment `testing`, no `RefreshDatabase`/`DatabaseMigrations`) and is called from `tests/TestCase.php`; `php artisan test:database:migrate`
   (`MigrateTestDatabaseCommand`) forces the prefix, runs the guard and a plain `migrate` (never `migrate:fresh`); feature tests use
   `DatabaseTransactions`. Unit tests for the guard and a smoke feature test pass; Pint and PHPStan are clean. **Not yet run:**
   `php artisan test:database:migrate` itself (it would create `test_migrations` in `jobportal_v2`; run it once the first migration exists).
2. Write the Phase 0 migrations for `jobportal_v2` from `phase-0-schema.md`, **confirming every table with the user** (the database is shared with the
   `test_` objects). Write foreign key columns explicitly as `bigint unsigned` (the design document does not spell out their type). Add the
   `created_at` index on the framework `notifications` table through a separate migration (confirm when writing it).
3. Verify package compatibility with Laravel 13 when adding `spatie/laravel-translatable` and the Spatie permission package (custom table names, composite
   pivot keys).
4. Re-create an architecture document (the old `docs/ARCHITECTURE.md` was deleted because it described the discarded port).
5. Audit and translate any remaining non-English comments/docs in the repository (the user has asked to be consulted before doing it).
6. Then start Phase 0 implementation by bounded context: Identity, Platform, Notification, ReferenceData, Content. Open details (seed data and the BPS
   source, verification-code lifetime, purge jobs) are decided when reached, one question at a time.
7. Commit the `docs/` files only when the user asks.

## 7. Items waiting for the user

- Decisions on the legacy findings (`legacy-findings.md`), especially S1 to S3 which are issues in the **running production application**
  (unauthenticated `migrate`, unauthenticated upload, unauthenticated cache clear). The user decides whether to report them to the maintainers.
- Approval to run the data migration against real production data (not before Phase 1; explicit approval is required at that moment).
- Legal review of the religion/nationality filtering decision.

## 8. Suggested first message for the new session

> Read `/usr/local/src/jobportal/docs/HANDOFF.md` and `docs/design/decisions.md`, check the memory index, then continue with the Phase 0
> migrations (confirm every table with me, one question at a time).
