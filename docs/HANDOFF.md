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
| `docs/design/phase-0-schema.md` | Draft column-level design of Phase 0 tables (**not yet reviewed by the user**) |

## 5. Status

Done: step 1 (flow map), step 2 (benchmarks, five platforms), step 3 (gap analysis), scope of every gap, sequencing (five phases, approved),
Phase 0 concept decisions and the **approved list of Phase 0 tables**.

Not done: user review of the Phase 0 column design; the seven open questions at the end of `phase-0-schema.md`; no migrations, no domain code.
New files under `docs/` are **uncommitted** (the user has not asked for a commit).

## 6. Suggested next steps

1. Walk through the seven open questions in `docs/design/phase-0-schema.md`, one at a time (public profile URLs, account deletion model,
   tag popularity flag, company-specific benefits, location name translation, audit-log retention/access, migration mapping).
2. Get the user's approval of the Phase 0 column design, then create the migrations for `jobportal_v2` (confirm every table with the user, since the
   database is shared with the `test_` objects).
3. Rework the test setup (`test_` prefix, guard) before writing tests; update `phpunit.xml` and CI.
4. Re-create an architecture document (the old `docs/ARCHITECTURE.md` was deleted because it described the discarded port).
5. Audit and translate any remaining non-English comments/docs in the repository (the user has asked to be consulted before doing it).
6. Then start Phase 0 implementation by bounded context: Identity, Platform, Notification, ReferenceData, Content.

## 7. Items waiting for the user

- Decisions on the legacy findings (`legacy-findings.md`), especially S1 to S3 which are issues in the **running production application**
  (unauthenticated `migrate`, unauthenticated upload, unauthenticated cache clear). The user decides whether to report them to the maintainers.
- Approval to run the data migration against real production data (not before Phase 1; explicit approval is required at that moment).
- Legal review of the religion/nationality filtering decision.

## 8. Suggested first message for the new session

> Read `/usr/local/src/jobportal/docs/HANDOFF.md` and `docs/design/decisions.md`, check the memory index, then continue with the open questions
> of the Phase 0 schema design, one question at a time.
