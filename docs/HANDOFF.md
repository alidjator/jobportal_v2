# Handoff

The short entry point for a new session. Working rules, code standard, database rules and commands are in `CLAUDE.md` and are not repeated here.
Keep this file **under about 5 KB**: replace the status below instead of appending to it. Decisions belong in the design documents, not here. The full history
(including the per-step status of Phases 2 and 3) is in `docs/handoff-history.md`; search it for a specific past event, do not read it whole.

## Read order

1. This file.
2. The design document of the step you work on (`docs/design/`, see the index below). Each records the user's decisions and what was coded.
3. Only when needed: `docs/design/decisions.md` (cross-phase decisions), `architecture.md` (API contract, auth, persistence, queues, files), `phase-0-schema.md`.

## Current state (2026-10-09)

- Branch `phase-1-company`; `master` is at tag `v0.1.0`; local repo, no remote. Last suite run: 1662 tests, all passing.
- **Phase 0, Phase 1, Phase 2 and Phase 3 are complete**, coded, checked and committed; every migration (to `000094`) ran on the real database.
  Phase 3: job alerts, candidate search, invitations, matching engine, CV generator (`docs/design/phase-3-*.md`).
- **Legacy import (`docs/design/phase-1-migration.md`) ran once on the real database (2026-10-08):** 179 accounts, 173 profiles, 3 companies, 149 jobs, 30 applications.
  The importer only adds. The 142 `draft` jobs stay as they are by decision. Legacy group "dev" (7 applications) was left unmapped on purpose.
- Product points settled 2026-10-09: applicants see the rejection reason code (not the note); push for `application_update` waits for a client that can receive it
  (email only now); applying needs a verified email.
- Nothing is in progress. Do not start new work without asking the user.

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
- A rehearsal database: any `test_*` name is open to the app account. Use `DB_DATABASE=test_<name>` and `LARAVEL_STORAGE_PATH=<scratch dir>`, then `migrate`, `db:seed`, `legacy:import --all --write`.

## Document index

| File | Content |
|---|---|
| `docs/analysis/` | Legacy flow map, legacy findings, benchmark notes, gap analysis |
| `docs/design/decisions.md` | Cross-phase decisions |
| `docs/design/architecture.md` | API contract, authentication, authorization, persistence, queues, files, account states |
| `docs/design/phase-0-schema.md` | Column design of the Phase 0 tables |
| `docs/design/phase-1-*.md`, `phase-2-*.md`, `phase-3-*.md` | One per step, with the decisions and what was coded |
| `docs/handoff-history.md` | Session log, including the status of every step of Phases 2 and 3 |

## Suggested first message for the next session

> Read `docs/HANDOFF.md`, then ask me what to work on next.
