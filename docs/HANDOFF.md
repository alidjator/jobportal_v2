# Handoff

The short entry point for a new session. Working rules, code standard, database rules and commands are in `CLAUDE.md` and are not repeated here.
Keep this file **under about 5 KB**: replace the status below instead of appending to it. Decisions belong in the design documents, not here. The full history
(including the per-step status of Phases 2 and 3) is in `docs/handoff-history.md`; search it for a specific past event, do not read it whole.

## Read order

1. This file.
2. The design document of the step you work on (`docs/design/`, see the index below). Each records the user's decisions and what was coded.
3. Only when needed: `docs/design/decisions.md` (cross-phase decisions), `architecture.md` (API contract, auth, persistence, queues, files), `phase-0-schema.md`.

## Current state (2026-10-09)

- Branch `phase-1-company`; `master` is at tag `v0.1.0`; local repo, no remote. Last suite run: 1724 tests, all passing.
- **Phase 0, Phase 1, Phase 2 and Phase 3 are complete**, coded, checked and committed; every migration (to `000094`) ran on the real database.
  Phase 3: job alerts, candidate search, invitations, matching engine, CV generator (`docs/design/phase-3-*.md`).
- **Legacy import (`docs/design/phase-1-migration.md`) ran once on the former MySQL database (2026-10-08); the real PostgreSQL `jobportal_v2` holds none of it (checked 2026-10-10: 0 accounts, 0 companies, empty id map, 38 provinces seeded), so the whole import must run again there (dry run `php artisan legacy:import --all` first; ask before `--write`).** Migration `000103` is applied on the real database; a dry run of `campaigns` read 13 legacy campaigns (2 of company 1, 11 of company 3) and skipped all as `company-not-imported`, as expected. The user is preparing the mapping files and the freeze date. That first run was: 179 accounts, 173 profiles, 3 companies, 149 jobs, 30 applications.
  The importer only adds. The 142 `draft` jobs stay as they are by decision. Legacy group "dev" (7 applications) was left unmapped on purpose.
- Product points settled 2026-10-09: applicants see the rejection reason code (not the note); push for `application_update` waits for a client that can receive it
  (email only now); applying needs a verified email.
- **Phase 4, step 1 dashboards (`docs/design/phase-4-dashboards.md`) is coded, checked and committed;** its real-database migrations (`000095`, `000096`) and `identity:sync-permissions` (`dashboard.view`) wait for the user.
- **Phase 4, step 2 business intelligence (`docs/design/phase-4-business-intelligence.md`) is coded, checked and committed** (migrations `000097` to `000100`; permission `analytics.view`; setting `analytics.min_group_size`). Its real-database steps are not run on MySQL: they run once on the new PostgreSQL database.
- **PostgreSQL 16 port (`docs/design/postgres-migration.md`) is coded, checked and committed (`5ad9e30`).** Cross-context foreign keys (`docs/design/cross-context-foreign-keys.md`, migration `000102`) are coded and checked too (1731 tests). The real and the test database are empty PostgreSQL databases (`jobportal_v2`, `jobportal_v2_test`, role `jobportal_v2`, port 5432; credentials only in `.env` and `.env.testing`).
  Waiting for the user on the real database: `php artisan migrate`, `identity:sync-permissions`, `db:seed`, `analytics:rollup --backfill` (ask before each). The live BI medians now run in SQL (`35427a4`). `GET /company/jobs/{id}/performance` is coded and committed (`b69ef0b`). Campaigns are designed and coded in `docs/design/phase-4-campaigns.md` (context `Campaign`, migration `000103`; full suite green (1746 tests); needs `php artisan migrate` and `identity:sync-permissions` on the real database, and `CAMPAIGN_REGISTRATION_URL=https://<client site>/{code}/register` in `.env`). Run artisan and tests as `jobportal`, never as root (root-owned `storage` breaks the tests).
  Moodle training is designed and coded in `docs/design/phase-4-training.md` (context `Training`, no tables; tests green; needs `MOODLE_BASE_URL`, `MOODLE_TOKEN`, `MOODLE_COURSE_URL` in `.env`). The campaign importer is coded (`CampaignsImporter`, tests green, not run on the real data; run `php artisan legacy:import campaigns` first (it only reads and reports), ask before adding `--write`). Newsletter is designed and coded in `docs/design/phase-4-newsletter.md` (context `Newsletter`, migrations `000104` to `000106` applied to the test database only; tests written, not yet run; needs `php artisan migrate`, `identity:sync-permissions` (`newsletter.manage`) and `NEWSLETTER_CONFIRM_URL` / `NEWSLETTER_UNSUBSCRIBE_URL` on the real server; the importer of the legacy `emails` table is not coded). Do not start new work without asking the user.

- Later (user, 2026-10-10): no menu table (clients render menus; access stays in permissions and roles). Check whether `GET admin/me` returns the admin's permissions so clients can build menus; add a `permissions` field there if not.

## Items waiting for the user

- Grant `cv_templates.manage` to the admins who manage the CV templates (`identity:sync-permissions` already ran).
- SMTP (credentials entered by the user); then a command sends reset emails to the 10 accounts without a password (ids 16, 17, 18, 20, 24, 25, 26, 27, 35, 36): dry-run first, ask before running.
- Deployment: Reverb process, nginx WebSocket proxy, upload limits, queue worker for `critical,default,bulk` (see `docs/design/phase-2-chat.md`), the invitation page URL.
- Legal review: the brief for the adviser is `docs/legal-review.md`. The candidate-search filters by religion, nationality, age and gender are switched off by default (`job_seeker.candidate_sensitive_filters_enabled`, coded and committed 2026-10-09); an admin switches them on after the answer. The account age limits and the CV personal details are in the brief too.
- Deliver `docs/analysis/legacy-security-report.md` (S1 to S3 of the running karir.pkp, critical) to its maintainers. S4 to S13 are marked as not carried over.
- If the weekly recommendation email grows past a few thousand job seekers, rework it into one queue job per batch (`docs/design/phase-3-matching.md`).

## Operational facts not in `CLAUDE.md`

- Commit identity: `git -c user.name=jobportal -c user.email=<the user's email> commit` (the repo has no git identity configured).
- Live dev site: `MAIL_MAILER=log`, `QUEUE_CONNECTION=sync`, `COMPANY_INVITATION_URL` empty. The scheduler runs from the `jobportal` user's crontab (`schedule:run` every minute);
  the nginx config is root-owned at `/etc/nginx/conf.d/api-karir.satu.solutions.conf` (a copy is in `/home/jobportal/nginx/`). Neither is in git, nor are the POSIX ACLs on `storage/` and `bootstrap/cache`.
- Production schema dump (read-only account, no data): `mariadb-dump --no-data --skip-comments --no-tablespaces --skip-triggers --skip-lock-tables` with the `KARIR_PKP_DB_*` values.
- After a deployment that adds a permission, `identity:sync-permissions` must be run on the real database (ask first). `tests/Feature/Platform/SettingsTest.php` lists every setting key: update it whenever one is added.
- The user runs `composer check`, real-database migrations and the legacy import (explicit approval each time). `KARIR_PKP_FILES_ROOT` is set in `.env`. Mapping files: `--map=masters=<path>`, `--map=groups=<path>`.
- A rehearsal database: create one (the role has `CREATEDB`). Use `DB_DATABASE=<name>` and `LARAVEL_STORAGE_PATH=<scratch dir>`, then `migrate`, `db:seed`, `legacy:import --all --write`. The legacy source (`legacy` connection) stays MariaDB.

## Document index

| File | Content |
|---|---|
| `docs/analysis/` | Legacy flow map, legacy findings, benchmark notes, gap analysis |
| `docs/design/decisions.md` | Cross-phase decisions |
| `docs/design/architecture.md` | API contract, authentication, authorization, persistence, queues, files, account states |
| `docs/design/phase-0-schema.md` | Column design of the Phase 0 tables |
| `docs/design/phase-1-*.md`, `phase-2-*.md`, `phase-3-*.md`, `phase-4-*.md` | One per step, with the decisions and what was coded |
| `docs/handoff-history.md` | Session log, including the status of every step of Phases 2 and 3 |

## Suggested first message for the next session

> Read `docs/HANDOFF.md`, then ask me what to work on next.
