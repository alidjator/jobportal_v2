# Job Portal API

A Laravel 13 (PHP 8.3) REST API with DDD, replacing the production web application **karir.pkp** (`/usr/share/nginx/html/karir.pkp`).
`karir.pkp` is a **reference only**: read-only, never ported 1:1, never modified.

**Read `docs/HANDOFF.md` first** (current status and next step; short), then the design document of the phase you work on
(`docs/design/`). The user talks to you in Indonesian; everything in the project is English.

## Working rules (mandatory, set by the user)

1. **Work alone.** No sub-agents, no Workflow, no background tasks. Independent tool calls (reading several files, read-only lookups) may be batched in one step;
   calls that depend on an earlier result, and actions that change something (edits, migrations, commits), run one after another (relaxed 2026-10-08).
2. **Decision gate.** Findings in karir.pkp that violate best practice (security, bugs, architecture, performance) and every schema assumption are
   **reported and confirmed with the user first**. Never silently fix or copy them. Never assume a table or column exists or not: read the schema and confirm.
3. **Rule conflicts.** There is no fixed priority between rules. If two rules conflict, stop and ask. **One fixed exception (decided 2026-10-30):** the hard rule of
   "Testing and checks" always wins over any line that mentions running or passing a check.
4. **Questions.** Do not decide for the user; offer options with a recommendation, record every answer in the design document. Up to 3–4
   **independent** routine decisions may be asked in one turn, numbered (relaxed 2026-10-08). Still one at a time: a decision that depends on an earlier
   answer, a best-practice finding from the decision gate, and an uncertain schema assumption. A decision the user's answer does not mention is not accepted;
   ask again.
5. **Confirm before hard-to-reverse or outward-facing actions.** Commit only when asked. Running seeders against the **real** tables, and any
   write to production or the live site, needs the user's explicit approval each time. `php artisan migrate` on the real database is run by the user (decided 2026-10-11): never ask for approval, only say which migration is new.
6. **Do not re-summarize changes.** Do not recap what was edited or repeat a change list the user can already see in the diff; report only the outcome
   and what needs a decision.
7. **Out of scope for now:** plans, billing, payments, invoices, pay-per-job and featured promotion.
8. **Terms in user stories (decided 2026-10-13):** "Admin" in a user story means the company admin, not the platform admin.
9. **Front-end builds** on the server are run by the user (decided 2026-10-13): give the commands, never run them.
10. **Commands with a full path (decided 2026-10-29):** every command given to the user starts with `cd <project directory> && ` (this repository: `/usr/local/src/jobportal`; the front end: `/usr/local/src/jobportal-frontend/onesis-talent`) and names files and folders by absolute path, never the bare command, because the user's terminal may be in any directory.

## Code standard

- Laravel best practice with DDD is mandatory: layers `Domain` / `Application` / `Infrastructure` / `Interface` under `src/<Context>`, one use case per
  class, thin controllers, FormRequests, API resources, no global helpers, no cross-context foreign keys or table reads (use ports in `src/Shared`).
  **One documented exception (decided 2026-10-07):** the operational tool `src/LegacyImport` (data migration from karir.pkp, no API) may write the tables of other contexts directly, only in its own
  Infrastructure layer, one writer class per entity, because it must set values the application never writes (`verified_at`, historical stages and times, legacy timestamps); its tests check the result through the real API.
  The operational tool `src/DemoData` (`demo:seed`, decided 2026-10-21) writes nothing directly: it calls the use cases of other contexts as a client does (`docs/design/phase-8-demo-data.md`).
- **Layer and context boundaries are tested** (Pest architecture tests, `tests/Arch/LayersTest.php`, added 2026-10-30; Pest runs the PHPUnit classes unchanged, `tests/Pest.php`): the domain knows no framework or outer layer, the application no infrastructure or HTTP class, a context reaches another only through `src/Shared` (an infrastructure listener may use the domain events of another context), `src/Shared` knows no context. A new class that breaks a rule fails the Arch suite of every level-1 check and `composer check` (see "Testing and checks"); fix the code, never add an exception without telling the user. `LegacyImport` and `DemoData` are not checked.
- **English everywhere:** identifiers, comments, documentation, commit messages, API error messages. Map Indonesian terms to common job-portal English
  (lamaran = application, lowongan = job, pelamar = applicant, perusahaan = company). New table, column and route names are designed freely, not bound to legacy names.
- Match the surrounding code (comment density, naming, idiom). Every new behaviour gets tests, written with the code (see "Testing and checks").

## Database

- PostgreSQL 16 `jobportal_v2` (moved from MariaDB on 2026-10-10, see `docs/design/postgres-migration.md`; extensions `pg_trgm` and `unaccent`; searches go through
  `Shared/Infrastructure/Persistence/TextSearch`, NULL ordering through `NullOrder`); credentials only in the git-ignored `.env`. **Never copy secrets** into docs, memory, commits or chat.
  The legacy source (`legacy` connection, used only by `src/LegacyImport`) stays MariaDB, so the server needs both `pdo_pgsql` and `pdo_mysql`.
- Tables are prefixed per bounded context (`identity_`, `company_`, `reference_`, ...). **Tests run in their own database, `jobportal_v2_test`** (decided 2026-10-06, replacing the
  old `test_` table prefix), with the same table names and no prefix; credentials are only in the git-ignored `.env.testing`. A guard aborts any test run or test migration whose
  database is not `jobportal_v2_test`.
- **Foreign keys** (decided by the user, 2026-10-05, widened 2026-10-10 in `docs/design/cross-context-foreign-keys.md`): a column that references `reference_*`, `company_companies`,
  `company_locations`, `identity_users`, `identity_admins` or `job_jobs` has a `RESTRICT` foreign key, also across contexts (only `recruitment_job_questions.job_id` is `CASCADE`). Never on logs, audit,
  notifications, `*_daily` tables, the legacy id map, match deliveries or polymorphic columns. Tests that insert such rows create real parent rows (`tests/Concerns/CreatesReferenceRows.php`).
- A new migration runs three times, all by the user (see "Testing and checks").
- The production karir.pkp database is reachable only through the read-only account (`KARIR_PKP_DB_*`): **schema only, never read data** without explicit approval.

## Git (decided 2026-10-13)

- One branch per piece of work, named after it (`go-live-readiness`, `phase-8-...`), created from the tip of `master`. When the user's check is green and they ask,
  move `master` forward without a merge commit and delete the branch; tag each release after a green `composer check`.
- The live dev site is this working folder, so a checkout changes the served code at once: never check out an older commit or a `master` that is behind.
  Move `master` from the work branch with `git push . HEAD:master` (fast-forward only), then `git checkout master` once both point at the same commit.
- `origin` is the private GitHub repo `alidjator/jobportal_v2`.
- **Git commands are run by you, after confirmation (decided 2026-10-30, replacing "only the user pushes"):** show the command and ask first, every time (branch, `master`,
  checkout, tags, `git push origin`); run it only after the user agrees. When to commit stays the user's decision: commit only when asked.

## Commands

Every line below is a check run by the user (see "Testing and checks"); give them, never run them on your own.

```bash
composer check 2>&1 | tail -n 40 | cut -c1-4000  # Pint, PHPStan (--memory-limit=1G), config:clear and the whole suite (serial); stops at the first failure
php artisan test                          # whole suite (about 13 minutes, serial); run `php artisan config:clear` first, as `composer check` does
composer test:parallel                    # whole suite in 8 processes; first `php artisan test:database:parallel --env=testing` (again after each new migration)
php artisan test tests/Feature/Company    # a folder or file
php artisan test --testsuite=Arch         # the architecture tests (Pest `arch()`, `tests/Arch`): layers and context boundaries, no database
php artisan test --filter=SomeTest         # one test class or method (Pest runs under `artisan test`; the existing tests stay PHPUnit classes)
vendor/bin/pint --test                    # style check (drop --test to fix); also `composer lint`
vendor/bin/phpstan analyse --no-progress --memory-limit=1G  # static analysis, with the memory limit of `composer check`
php -n -l <file>                          # syntax check; plain `php -l` segfaults on this server
```

## Testing and checks (merged into one section 2026-10-30)

- **Only the user runs checks, a HARD RULE (decided 2026-10-08, made strict 2026-10-10, widened 2026-10-30).** Testing, debugging and linting are a separate step from coding.
  Never run on your own: `php artisan test` (not even with `--filter`), `composer check`, Pint, PHPStan, `php -n -l`, `php artisan api:error-catalog --check`,
  `test:database:migrate`, `tinker`, a request to the API, or anything else that executes code to verify a result. Allowed: reading code, logs (`storage/logs`) and the schema.
  Never report a check as passed unless the user's own run showed it. Run one only when the user asks, or pastes failures to fix; then run `composer check` once (not Pint,
  PHPStan and the suite as three separate steps), or only the tests of the folder or file the user names. If a task seems to need a check, stop and tell the user.
  This rule is the fixed exception of working rule 3.
- **Two levels (decided 2026-10-11, aligned with `composer check` 2026-10-30):**
  1. After each coding step or part, the user checks only what the change touches, with the settings of `composer check`:
     `php artisan config:clear && php artisan test <related tests> 2>&1 | tail -n 40 | cut -c1-4000`, `php artisan test --testsuite=Arch` (fast, no database),
     `vendor/bin/pint --test <changed files>`, `vendor/bin/phpstan analyse --no-progress --memory-limit=1G <changed files>`, and `php artisan api:error-catalog --check --untranslated`
     when `lang/` or an exception message changed.
  2. `composer check` runs once, at the end of the whole coding stage (the last part of a design document, or an item of `docs/design/phase-8-index.md`); do not propose it before.

  After coding, give the exact commands in the **last message of the turn**, one `bash` block per command (decided 2026-10-17), with the full path of working rule 10.
- **Related tests (defined 2026-10-30):** the tests that use a changed class or endpoint (find them with `grep -rl '<Class or route>' tests`), the unit tests of that class, and the
  catalog tests below when their catalog changed. Name each file in the command.
- **Tests that change with the code:** every new behaviour gets tests. A new setting key goes into `tests/Feature/Platform/SettingsTest.php`, a new email template into
  `EmailTemplatesTest`, a new permission into the catalog counts of `CompanyTeamTest` and `MemberPermissionRulesTest`.
- **Before a commit (decided 2026-10-30):** a commit of a part needs the user's level-1 run green (Pint and PHPStan on the changed files, the related tests); moving `master`
  and tagging a release need a green `composer check` (see "Git").
- **A new migration** runs three times, all by the user, no approval is asked (decided 2026-10-11): `php artisan test:database:migrate --env=testing` (test database),
  `php artisan test:database:parallel --env=testing` (the databases of `composer test:parallel`), then `php artisan migrate` (real tables). Tell the user which migration is new.
- **Command output:** test runs and `composer check` are cut with `2>&1 | tail -n 40 | cut -c1-4000` (inside Claude Code Pint, PHPStan and PHPUnit print one JSON line, so
  `tail` alone does not cut a long failure report). On a failure, give the user the command that reruns only the failing test with `--filter`.
- **Exit code:** trust the exit code, not the JSON summary: it can say `"passed"` while a PHPUnit warning makes the run exit with 1. To see the warning, give the user
  `env -u AI_AGENT -u CLAUDECODE -u CLAUDE_CODE vendor/bin/phpunit --no-progress 2>&1 | tail -n 40 | cut -c1-4000`. It only shows the warning and never replaces
  `php artisan test` (plain PHPUnit skips the Pest `arch()` tests).
- **Generators are not checks:** `php artisan api:error-catalog` (without `--check`, rewrites `docs/api/error-catalog.md`) and the Scramble cache commands are run by you.

## Keeping the context small

Everything read stays in the context for the rest of the session and is sent again on every step, so read only what the step needs.

- **Sessions:** design a part (questions and answers) in one session and code it in a fresh one; the design document carries the decisions across.
- **Design documents:** each starts with a short `## Decisions` section (one line per decision: date, decision, link to its detail section; aim for
  2–4 KB). Read that section first, then the detail sections you need via `grep -n`. Record every new answer in both places in the same edit; if they
  disagree, the detail section wins. A document without the section gets one the next time it is edited (added 2026-10-08).
- **Large files** (design documents, `docs/archive/handoff-history.md`, legacy schema dumps, legacy routes): list the headings or locate the lines first
  (`grep -n '^#' <file>`, `grep -n <term> <file>`), then read only that range. Do not read a file again when it is already in the context and unchanged.
- **Source code:** never `cat` several files at once; locate with `grep -n`, then read the relevant range.
- **Checks and their output:** see "Testing and checks".
- **Legacy schema:** one table at a time (`SHOW CREATE TABLE <table>`), never a whole dump into the context.

## Live site

`https://api-karir.satu.solutions` is a **dev** domain served straight from this folder (nginx + system PHP-FPM, user `nginx`). `APP_DEBUG` must stay
`false`. `storage/` and `bootstrap/cache` rely on POSIX ACLs for both `nginx` and `jobportal` (see `docs/archive/handoff-history.md`, `grep -n 'POSIX ACLs'`). Karir.pkp live-site accounts are
never stored; ask the user when needed.

- **API docs cache, MANDATORY (decided 2026-10-16):** `/docs/api` serves the cached OpenAPI document (Scramble). After every change to a route or to an API response (controller, FormRequest, API resource, response shape), run `php artisan scramble:clear` and then `php artisan scramble:cache`, in that order; otherwise the docs show the old version, and with an empty cache the page takes about 20 seconds.
- **API messages are translated (decided 2026-10-16, `docs/design/phase-8-api-translations.md`):** every new message of an exception, a validation attribute or an email goes into `lang/id.json` (4-space indent, a message with and without an `:id` is two entries) or `lang/id/validation.php`; `php artisan api:error-catalog` rewrites `docs/api/error-catalog.md` (run by you); `--check --untranslated` must be clean, a check run by the user (see "Testing and checks", where the catalog tests are listed too).
  The cache is only read back because `serializable_classes` in `config/cache.php` lists the Scramble classes; review that list after upgrading `dedoc/scramble` (a missing class makes the page slow again, without an error).
