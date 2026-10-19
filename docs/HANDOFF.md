# Handoff

Entry point for a new session. Keep it **under about 5 KB**: replace the status, do not append.

| What | Where |
|---|---|
| Rules, code standard, Git workflow, commands | `CLAUDE.md` |
| Decisions | `docs/design/` (`decisions.md` cross-phase, `architecture.md`, `phase-0-schema.md`, one `phase-N-*.md` or `erp-*.md` per step; its "coded" sections hold interpretations, migrations and `.env` values) |
| Phase 8 order and per-item status (coded, open parts, waits) | `docs/design/phase-8-index.md` |
| Go-live list | `docs/deployment-checklist.md` |
| Legacy flow, benchmarks, gaps | `docs/analysis/` |
| API-document rules, error catalog | `docs/api/` |
| History | `docs/handoff-history.md` (to 2026-10-08, frozen), then `git log -p docs/HANDOFF.md`; search, do not read whole |

## Current state (2026-10-19)

- Latest tag `v0.7.0` (#18, = `origin/master`); local `master` holds #19 (`f760853`, 3205 tests green) and is not pushed yet. Earlier tags in `git tag`. Remote `origin`: private GitHub `alidjator/jobportal_v2` (deploy key alias `github-jobportal`).
- **Phases 0 to 7 coded and committed.** Phase 8: #5 to #11 and #16 to #19 coded, checked and committed, #12 and #13 partly; what is still open inside them is in `phase-8-index.md` ("Coded" and the first rows of "Next coding").
- Real PostgreSQL `jobportal_v2` and the test database: **`000172` (#19) is new and to run on both** (`000170` and `000171` of #18 too, if not run yet); reference data only (0 accounts) until the final import.
- **Direction (agreed 2026-10-13): go-live readiness first** (`docs/deployment-checklist.md`; the live dev site is this folder, so the scheduler fails when code needing a new migration is live before `migrate`). Name: ONESIS ERP / ONESIS Career (`docs/design/naming.md`); `onesis` stays the technical identifier.
- **Next coding:** #12 to #15 wait (legal questions 12 and 13, design sessions), so the next item is **#27 `company_locations` (needs a design session)**, then #28 (job `locations` in the API); both come before the front end and the final import run (employee data order: `erp-direction.md` section 9).
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
