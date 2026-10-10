# Handoff

Entry point for a new session; rules, code standard and commands are in `CLAUDE.md`. Keep this file **under about 5 KB**: replace the status, do not append; decisions belong in `docs/design/`. History: `docs/handoff-history.md` (search it, do not read it whole).

## Read order

1. This file.
2. The design document of the step you work on (`docs/design/`: `decisions.md` cross-phase, `architecture.md` API/auth/persistence/queues/files, `phase-0-schema.md`, one `phase-N-*.md` per step). `docs/analysis/` holds the legacy flow map, benchmarks and gap analysis; `docs/api/` the API-document rules and error catalog.

## Current state (2026-10-10)

- Branch `phase-1-company`; `master` is at tag `v0.1.0`; local repo, no remote. The suite takes about 7.5 minutes on PostgreSQL (was about 1 minute), a point for the performance audit.
- **Phases 0 to 4 are coded, checked and committed** (Phase 4: dashboards, business intelligence, campaigns, Moodle training, newsletter, salary insight `GET /salary-insight`, company reviews (`src/Review`), saved jobs (`job_saved_jobs`, Job context) and the talent pool (`src/TalentPool`); designs in `docs/design/phase-4-*.md`). **Phase 5 is scoped** in `docs/design/phase-5-scope.md` (2026-10-10): hardening first (API security review, performance audit, API docs consistency), then deferred decisions, then newsletter and review extensions. The API security review is in `docs/design/phase-5-security-review.md`. **The API security review is fully coded and committed** (slices 1 to 5: P1 to P7, A1 to A3; admin TOTP is commit `0fa7583`; suite 1956 tests green). Migration `000111` (admin second factor) ran on the real database on 2026-10-10, which still has no admin account: create one with `identity:create-admin`; the admin enrols the authenticator at the first sign-in. `composer audit` after google2fa reported no advisories. Next: the performance audit.
- **The real PostgreSQL `jobportal_v2` holds only seeded reference data** (migrations to `000110`, run by the user on 2026-10-10; 0 accounts, 0 companies, empty id map). The legacy import ran once on the former MySQL database, so the whole import must run again there: dry run `php artisan legacy:import --all` (it only reads and reports; the campaign and newsletter importers are included), ask before `--write`. The user is preparing the mapping files and the freeze date.
- **Real database, run on 2026-10-10 at the user's request:** `identity:sync-permissions` (14 permissions), `db:seed`, `analytics:rollup --backfill` (0 days: no data yet; rerun after the import). `.env` needs `CAMPAIGN_REGISTRATION_URL=https://<client site>/{code}/register`, `MOODLE_BASE_URL`, `MOODLE_TOKEN`, `MOODLE_COURSE_URL`, `NEWSLETTER_CONFIRM_URL`, `NEWSLETTER_UNSUBSCRIBE_URL`.
- Production is served straight by nginx, no proxy or CDN (user, 2026-10-10), so no `trustProxies`. Open: an immediate (not nightly) clearing of notes needs a JobSeeker trigger.
- Run artisan as `jobportal`, never as root. The gap analysis is closed (only R8, excluded by the user, remains).

## Items waiting for the user

- Grant `cv_templates.manage` to the admins who manage the CV templates (`identity:sync-permissions` already ran).
- SMTP (credentials entered by the user); then a command sends reset emails to the 10 accounts without a password (ids 16, 17, 18, 20, 24, 25, 26, 27, 35, 36): dry-run first, ask before running.
- Deployment: Reverb process, nginx WebSocket proxy, upload limits, queue worker for `critical,default,bulk` (see `docs/design/phase-2-chat.md`), the invitation page URL.
- Legal review: the brief for the adviser is `docs/legal-review.md`. The candidate-search filters by religion, nationality, age and gender are switched off by default (`job_seeker.candidate_sensitive_filters_enabled`, coded and committed 2026-10-09); an admin switches them on after the answer. The account age limits and the CV personal details are in the brief too.
- Deliver `docs/analysis/legacy-security-report.md` (S1 to S3 of the running karir.pkp, critical) to its maintainers. S4 to S13 are marked as not carried over.

## Operational facts not in `CLAUDE.md`

- Commit identity: `git -c user.name=jobportal -c user.email=<the user's email> commit` (the repo has no git identity configured).
- Live dev site: `MAIL_MAILER=log`, `QUEUE_CONNECTION=sync`, `COMPANY_INVITATION_URL` empty. The scheduler runs from the `jobportal` user's crontab (`schedule:run` every minute);
  the nginx config is root-owned at `/etc/nginx/conf.d/api-karir.satu.solutions.conf` (a copy is in `/home/jobportal/nginx/`). Neither is in git, nor are the POSIX ACLs on `storage/` and `bootstrap/cache`.
- `tests/Feature/Platform/SettingsTest.php` lists every setting key: update it whenever one is added.
- The user runs `composer check`, real-database migrations and the legacy import (explicit approval each time). `KARIR_PKP_FILES_ROOT` is set in `.env`. Mapping files: `--map=masters=<path>`, `--map=groups=<path>`.
- A rehearsal database: create one (the role has `CREATEDB`). Use `DB_DATABASE=<name>` and `LARAVEL_STORAGE_PATH=<scratch dir>`, then `migrate`, `db:seed`, `legacy:import --all --write`. The legacy source (`legacy` connection) stays MariaDB.
- Slice 1 server setup done 2026-10-10: `.env` has `APP_ENV=staging`, `API_RATE_LIMIT_*`, `API_DOCS_ENABLED=true`; the nginx security `add_header` lines are removed (HSTS stays). Still open: `CORS_ALLOWED_ORIGINS` is empty (the user must give the front-end origin); consider nginx `limit_req` for unknown paths.
- `/docs/api` is served only when `API_DOCS_ENABLED=true` (default off, any `APP_ENV`); `php artisan scramble:export` writes `api.json`.
