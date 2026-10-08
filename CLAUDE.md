# Job Portal API

A Laravel 13 (PHP 8.3) REST API with DDD, replacing the production web application **karir.pkp** (`/usr/share/nginx/html/karir.pkp`).
`karir.pkp` is a **reference only**: read-only, never ported 1:1, never modified.

**Read `docs/HANDOFF.md` first** (current status and next step; short), then the design document of the phase you work on
(`docs/design/`). The user talks to you in Indonesian; everything in the project is English.

## Working rules (mandatory, set by the user)

1. **Work alone.** No sub-agents, no Workflow, no background tasks. Independent tool calls (reading several files, read-only checks) may be batched in one step;
   calls that depend on an earlier result, and actions that change something (edits, migrations, commits), run one after another (relaxed 2026-10-08).
2. **Decision gate.** Findings in karir.pkp that violate best practice (security, bugs, architecture, performance) and every schema assumption are
   **reported and confirmed with the user first**. Never silently fix or copy them. Never assume a table or column exists or not: read the schema and confirm.
3. **Rule conflicts.** There is no fixed priority between rules. If two rules conflict, stop and ask.
4. **One question at a time.** Do not decide for the user; offer options, record every answer in the design document.
5. **Confirm before hard-to-reverse or outward-facing actions.** Commit only when asked. Running migrations or seeders against the **real** tables, and any
   write to production or the live site, needs the user's explicit approval each time.
6. **Do not re-summarize changes.** Do not recap what was edited or repeat a change list the user can already see in the diff; report only the outcome
   and what needs a decision.
7. **Out of scope for now:** plans, billing, payments, invoices, pay-per-job and featured promotion.

## Code standard

- Laravel best practice with DDD is mandatory: layers `Domain` / `Application` / `Infrastructure` / `Interface` under `src/<Context>`, one use case per
  class, thin controllers, FormRequests, API resources, no global helpers, no cross-context foreign keys or table reads (use ports in `src/Shared`).
  **One documented exception (decided 2026-10-07):** the operational tool `src/LegacyImport` (data migration from karir.pkp, no API) may write the tables of other contexts directly, only in its own
  Infrastructure layer, one writer class per entity, because it must set values the application never writes (`verified_at`, historical stages and times, legacy timestamps); its tests check the result through the real API.
- **English everywhere:** identifiers, comments, documentation, commit messages, API error messages. Map Indonesian terms to common job-portal English
  (lamaran = application, lowongan = job, pelamar = applicant, perusahaan = company). New table, column and route names are designed freely, not bound to legacy names.
- Match the surrounding code (comment density, naming, idiom). Every new behaviour gets tests; Pint and PHPStan must be clean.

## Database

- MariaDB `jobportal_v2`; credentials only in the git-ignored `.env`. **Never copy secrets** into docs, memory, commits or chat.
- Tables are prefixed per bounded context (`identity_`, `company_`, `reference_`, ...). **Tests run in their own database, `jobportal_v2_test`** (decided 2026-10-06, replacing the
  old `test_` table prefix), with the same table names and no prefix; credentials are only in the git-ignored `.env.testing`. A guard aborts any test run or test migration whose
  database is not `jobportal_v2_test`.
- **Foreign keys** (decided by the user, 2026-10-05): a column that references `reference_*` or `company_companies` has a `RESTRICT` foreign key, also across contexts; columns that reference
  accounts or admins (`user_id`, `created_by_user_id`, `*_admin_id`) have none, and neither do polymorphic columns. Tests that insert such rows create real parent rows (`tests/Concerns/CreatesReferenceRows.php`).
- A new migration runs **twice**: `php artisan test:database:migrate --env=testing` (test database), then `php artisan migrate` (real tables, only with approval).
- The production karir.pkp database is reachable only through the read-only account (`KARIR_PKP_DB_*`): **schema only, never read data** without explicit approval.

## Commands

```bash
composer check 2>&1 | tail -n 40 | cut -c1-4000  # Pint, PHPStan and the whole suite in one step; stops at the first failure
php artisan test                          # whole suite (about 1 minute)
php artisan test tests/Feature/Company    # a folder or file
vendor/bin/pint --test                    # style check (drop --test to fix)
vendor/bin/phpstan analyse --no-progress  # static analysis
php -n -l <file>                          # syntax check; plain `php -l` segfaults on this server
```

## Keeping the context small

Everything read stays in the context for the rest of the session and is sent again on every step, so read only what the step needs.

- **Large files** (design documents, `docs/handoff-history.md`, legacy schema dumps, legacy routes): list the headings or locate the lines first
  (`grep -n '^#' <file>`, `grep -n <term> <file>`), then read only that range. Do not read a file again when it is already in the context and unchanged.
- **Source code:** never `cat` several files at once; locate with `grep -n`, then read the relevant range.
- **Checks:** while coding, run only the tests of the folder or file you touch; run `composer check` once when a part is done, not Pint, PHPStan and
  the suite as three separate steps.
- **Command output:** cut it to what is needed: test runs and `composer check` with `2>&1 | tail -n 40 | cut -c1-4000` (inside Claude Code
  Pint, PHPStan and PHPUnit print one JSON line, so `tail` alone does not cut a long failure report; on a failure, rerun only the failing test with `--filter`), the legacy schema one
  table at a time (`SHOW CREATE TABLE <table>`), never a whole dump into the context.

## Live site

`https://api-karir.satu.solutions` is a **dev** domain served straight from this folder (nginx + system PHP-FPM, user `nginx`). `APP_DEBUG` must stay
`false`. `storage/` and `bootstrap/cache` rely on POSIX ACLs for both `nginx` and `jobportal` (see `docs/handoff-history.md`, section 5). Karir.pkp live-site accounts are
never stored; ask the user when needed.
