# Handoff

Entry point for a new session; rules, code standard, Git workflow and commands: `CLAUDE.md`. Keep this file **under about 5 KB**: replace the status, do not append; decisions belong in `docs/design/`, per-item coding status in `docs/design/phase-8-index.md`. History: `docs/handoff-history.md` (to 2026-10-08), then `git log -p docs/HANDOFF.md`; search, do not read whole.

## Read order

1. This file.
2. The design document of the step (`docs/design/`: `decisions.md` cross-phase, `architecture.md`, `phase-0-schema.md`, `phase-8-index.md` for the order of phase 8, one `phase-N-*.md` or `erp-*.md` per step whose "coded" sections hold interpretations, migrations and `.env` values). `docs/analysis/`: legacy flow, benchmarks, gaps; `docs/api/`: API-document rules, error catalog; `docs/deployment-checklist.md`: go-live list.

## Current state (2026-10-18)

- Remote `origin`: private GitHub `alidjator/jobportal_v2` (deploy key alias `github-jobportal`); **the user pushes**, a session only gives the commands. `master` is pushed and tagged `v0.6.0` (3145 tests green); earlier tags in `git tag` (`v0.5.0`: team notifications, interviewer workflow, shortlist reviews).
- **Phases 0 to 7 coded and committed.** Phase 8: done and open items in `phase-8-index.md`; coded: #5 to #12, #16, #17 and #13 sections 1 to 3.
- Real PostgreSQL `jobportal_v2` and the test database are at `000169`; reference data only (0 accounts) until the final import.
- **Direction (agreed 2026-10-13): go-live readiness first** (`docs/deployment-checklist.md`; the live dev site is this folder, so the scheduler fails when code needing a new migration is live before `migrate`). Name: ONESIS ERP / ONESIS Career (`docs/design/naming.md`); `onesis` stays the technical identifier.
- **Not coded inside coded items:** #13 Employee hand-off (needs a design session); #12 section 3 (legal question 12) and offer approval of internal tenants (needs a design); #8 one-click opt-out of team emails; #9 assessment summary on the review card and `provider_reviews` (waits for #23); #17 front end and the SMTP provider's webhook parser (`EmailEventParser`, waits for the provider). #14 waits for legal question 13, #15 for #14.
- **Next coding:** pick the next open item of `phase-8-index.md`; #27 `company_locations` (needs a design session) and #28 (job `locations` in the API) come before the front end and the final import run (employee data order: `erp-direction.md` section 9).
- Front end: designed (`frontend.md`), not coded; needs the `phase-8-*` API pieces, #27, #28 and `frontend.md` 11.5, 11.6; open before go-live: client IP behind the Nuxt proxy (5.2).

## Open work (not started)

- Performance audit steps 4 (measure) and 6 (query-count tests); `jobportal_v2_perf` (at `000112`, empty) is dropped when done.
- Architecture review findings deferred: 7, 8, 20 (`decisions.md` section 6), 9, 21 (refactor after the import).
- Benchmark gaps G1-G21 (`docs/analysis/gap-analysis-round-2.md`): G1, G2, G6, G7, G20 designed in `phase-8-platform-admin.md` (G20 before go-live), G14 in `phase-8-job-discovery.md`; the rest await scope or are designed elsewhere. R8 reopened by the tele-interview benchmark.
- Immediate (not nightly) clearing of talent pool notes needs a JobSeeker trigger.

## Items waiting for the user

- Legacy import: rehearsal done 2026-10-13 (`phase-1-migration.md`, checklist section 3); the **freeze date** for the final run is not set.
- SMTP provider (then its webhook parser); then the reset emails to the imported accounts: `identity:send-imported-password-resets` (dry run first, batches of 200; `docs/deployment-checklist.md` section 3).
- Admin setting `platform.operations_email` (scheduler alerts) and an outside uptime monitor on `GET /api/v1/health/scheduler`.
- `CORS_ALLOWED_ORIGINS` (front-end origin); `JOB_SEEKER_PROFILE_URL` and `JOB_SEEKER_APPLICATION_URL` (links in emails); consider nginx `limit_req` for unknown paths.
- `.env` values: `.env.example` and the design "coded" sections; production needs `REDIS_CLIENT=predis` and `CACHE_STORE=redis`.
- Tele-interview: SIP trunk with one DID; root setup of Janus and coturn (`deploy/janus/README.md`) and Asterisk (`deploy/asterisk/README.md`).
- First admin: `identity:create-admin` (enrols TOTP at the first sign-in); other admins through `identity:create-role` and `identity:set-admin-role` (`phase-8-admin-console.md`).
- Legal review brief `docs/legal-review.md`: up to date 2026-10-16, **not sent yet**; section 0 orders the questions (A before go-live: 1 to 7, 10). Sensitive candidate-search filters stay off until answered.
- Deliver `docs/analysis/legacy-security-report.md` (S1-S3, critical) to the karir.pkp maintainers.

## Operational facts not in `CLAUDE.md`

- Commit identity: set in the repository's `.git/config` (since 2026-10-10), so a plain `git commit` works.
- Live dev site: `APP_ENV=staging`, `MAIL_MAILER=log`, `QUEUE_CONNECTION=sync`, `API_DOCS_ENABLED=true`, `COMPANY_INVITATION_URL` empty; nginx directly (no `trustProxies`); scheduler from the `jobportal` crontab. nginx config (root) `/etc/nginx/conf.d/api-karir.satu.solutions.conf`, copy in `/home/jobportal/nginx/`; it, the crontab and ACLs are outside git.
- `tests/Feature/Platform/SettingsTest.php` lists every setting key: update it whenever one is added.
- `KARIR_PKP_FILES_ROOT` is set in `.env`. Import mapping files: `--map=masters=<path>`, `--map=groups=<path>`.
- Rehearsal database: create one (the role has `CREATEDB`), use `DB_DATABASE=<name>` and `LARAVEL_STORAGE_PATH=<scratch dir>`, then `migrate`, `db:seed`, `legacy:import --all --write`. The legacy source (`legacy` connection) stays MariaDB.
- `/docs/api` is served only when `API_DOCS_ENABLED=true`; `php artisan scramble:export` writes `api.json`.
