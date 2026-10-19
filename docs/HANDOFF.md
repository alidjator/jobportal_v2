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

- Latest tag `v0.9.0` (#29, `composer check` green; 3294 tests); push state: `git status -sb`. Earlier tags in `git tag`. Remote `origin`: private GitHub `alidjator/jobportal_v2` (deploy key alias `github-jobportal`).
- **Phases 0 to 7 coded and committed.** Phase 8 status per item: `phase-8-index.md`. `master` is at #29 (2026-10-19), with the designs #29 to #33 and the gap analysis FG1 to FG18 (`docs/analysis/actor-gap-analysis.md`).
- Test database at `000176`; real PostgreSQL `jobportal_v2` at `000175`, `000176` (#29) run by the user (check `php artisan migrate:status`); reference data only (0 accounts) until the final import.
- **Go-live readiness first** (2026-10-13, `docs/deployment-checklist.md`; the live dev site is this folder, so code needing a new migration fails until `migrate`). Name: ONESIS ERP / ONESIS Career (`naming.md`); `onesis` is the technical identifier.
- **API coding limited (2026-10-19):** only #30, #15, #20 (#29 done) and endpoints the front end asks for until the front end catches up (`decisions.md` section 5).
- Front end: designed (`frontend.md`), not coded; working folder `/usr/local/src/jobportal-frontend/onesis-talent` (empty); needs the `phase-8-*` API pieces and `frontend.md` 11.5, 11.6; open before go-live: client IP behind the Nuxt proxy (5.2).

## Open work (not started)

- Performance audit steps 4 (measure) and 6 (query-count tests); `jobportal_v2_perf` (at `000112`, empty) is dropped when done.
- Architecture review findings deferred: 7, 8, 20 (`decisions.md` section 6), 9, 21 (refactor after the import).
- Benchmark gaps G1-G21 (`docs/analysis/gap-analysis-round-2.md`): G1, G2, G6, G7, G20 in `phase-8-platform-admin.md` (G20 before go-live), G14 in `phase-8-job-discovery.md`; the rest await scope. R8 reopened (tele-interview benchmark).
- Immediate (not nightly) clearing of talent pool notes needs a JobSeeker trigger.
- Flaky: the `'42'` assertion of `TestAuthoringTest` (passes on rerun).

## Items waiting for the user

- Legacy import: rehearsal done 2026-10-13 (`phase-1-migration.md`, checklist section 3); the **freeze date** for the final run is not set.
- SMTP provider (then its webhook parser); then the reset emails to the imported accounts: `identity:send-imported-password-resets` (dry run first, batches of 200; `docs/deployment-checklist.md` section 3).
- Admin setting `platform.operations_email` (scheduler alerts) and an outside uptime monitor on `GET /api/v1/health/scheduler`.
- `CORS_ALLOWED_ORIGINS` (front-end origin); `JOB_SEEKER_PROFILE_URL` and `JOB_SEEKER_APPLICATION_URL` (links in emails); consider nginx `limit_req` for unknown paths.
- `.env` values: `.env.example` and the design "coded" sections; production needs `REDIS_CLIENT=predis` and `CACHE_STORE=redis`.
- Tele-interview: SIP trunk with one DID; root setup of Janus and coturn (`deploy/janus/README.md`) and Asterisk (`deploy/asterisk/README.md`).
- First admin: `identity:create-admin` (enrols TOTP at the first sign-in); other admins through `identity:create-role` and `identity:set-admin-role` (`phase-8-admin-console.md`).
- Legal review `docs/legal-review.md` (questions 1 to 22, 2026-10-19; Indonesian copy `legal-review.id.md`), **not sent yet**; section 0 orders them (before go-live: 1 to 7, 10). Sensitive candidate-search filters stay off until answered.
- Deliver `docs/analysis/legacy-security-report.md` (S1-S3, critical) to the karir.pkp maintainers.

## Operational facts not in `CLAUDE.md`

- Commit identity is in the repository's `.git/config`: a plain `git commit` works.
- Live dev site: `APP_ENV=staging`, `MAIL_MAILER=log`, `QUEUE_CONNECTION=sync`, `API_DOCS_ENABLED=true`, `COMPANY_INVITATION_URL` empty; nginx directly (no `trustProxies`); scheduler from the `jobportal` crontab. nginx config (root) `/etc/nginx/conf.d/api-karir.satu.solutions.conf`, copy in `/home/jobportal/nginx/`; it, the crontab and ACLs are outside git.
- `tests/Feature/Platform/SettingsTest.php` lists every setting key: update it whenever one is added.
- `KARIR_PKP_FILES_ROOT` is set in `.env`. Import mapping files: `--map=masters=<path>`, `--map=groups=<path>`.
- Rehearsal database: create one (the role has `CREATEDB`), use `DB_DATABASE=<name>` and `LARAVEL_STORAGE_PATH=<scratch dir>`, then `migrate`, `db:seed`, `legacy:import --all --write`. The legacy source (`legacy` connection) stays MariaDB.
- `/docs/api` is served only when `API_DOCS_ENABLED=true`; `php artisan scramble:export` writes `api.json`.
