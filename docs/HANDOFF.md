# Handoff

Entry point for a new session; rules, code standard, Git workflow and commands: `CLAUDE.md`. Keep this file **under about 5 KB**: replace the status, do not append; decisions belong in `docs/design/`. History: `docs/handoff-history.md` (to 2026-10-08), then `git log -p docs/HANDOFF.md`; search, do not read whole.

## Read order

1. This file.
2. The design document of the step (`docs/design/`: `decisions.md` cross-phase, `architecture.md`, `phase-0-schema.md`, one `phase-N-*.md` per step whose "coded" sections hold interpretations, migrations and `.env` values). `docs/analysis/`: legacy flow, benchmarks, gaps; `docs/api/`: API-document rules, error catalog; `docs/deployment-checklist.md`: go-live list.

## Current state (2026-10-15)

- Remote `origin`: private GitHub `alidjator/jobportal_v2` (deploy key alias `github-jobportal`); **the user pushes**, a session only gives the commands. Tags `v0.1.0` (phase 0 + Company), `v0.2.0` (phases 1-7, 2430 tests green).
- **Phases 0 to 7 coded and committed.**
- Real PostgreSQL `jobportal_v2`: migrated to `000142` (the user migrates the newer ones), reference data only (0 accounts). Test database behind: `php artisan test:database:migrate --env=testing`.
- **Direction (agreed 2026-10-13): no new features, go-live readiness first**: `docs/deployment-checklist.md`, and separating the live site from the working folder (the scheduler fails when code needing a new migration is live before `migrate`).
- **Name (changed 2026-10-16):** ONESIS ERP (One Solutions Integrated System) / ONESIS Career, `docs/design/naming.md`; `onesis` stays the technical identifier (URN done).
- **ERP (decided 2026-10-15):** TA is ERP phase 1 in this codebase; `docs/design/erp-direction.md`; the phase 8 order was changed for it (three new designs before the hiring work).
- **Branch `phase-8-company-permissions`:** permissions, team, time-to-fill, assessment Part 7 and `phase-8-jobseeker-privacy.md` sections 1 and 2 coded, tested and committed (migrations `000136`-`000139`); `phase-8-company-admin.md` sections 1, 2 and 4 coded, checked and committed (`000140`; company admins enrol a second factor at sign-in, so the sign-in answer changes for the front end); `phase-8-platform-admin.md` sections 1 to 4 coded 2026-10-15 (`000141`, checked and committed: job reports and holds, review of unverified companies and suspicious text, name lock and name change requests; until a required document type exists every job is reviewed). `phase-8-job-discovery.md` sections 1 to 5 coded 2026-10-15 (`000142`, checked). `erp-organization.md` (#5), `erp-sso.md` (#7) and the Approval context of `erp-requisitions.md` coded 2026-10-16 (`000143`-`000150`); Workforce plans (`000151`, committed) and requisitions with the job rule (`000152`, coded 2026-10-16, **not checked yet**: the user runs migrate and the targeted tests) complete #6. **Next coding:** the first open item of `docs/design/phase-8-index.md` (order, status and legal waits of every phase 8 design; #4 is before go-live).
- Front end: designed (`frontend.md`), not coded; needs the `phase-8-*` API pieces and `frontend.md` 11.5, 11.6; open before go-live: client IP behind the Nuxt proxy (5.2).

## Open work (not started)

- Performance audit steps 4 (measure) and 6 (query-count tests); `jobportal_v2_perf` (at `000112`, empty) is dropped when done.
- Architecture review findings deferred: 7, 8, 20 (`decisions.md` section 6), 9, 21 (refactor after the import).
- Benchmark gaps G1-G21 (`docs/analysis/gap-analysis-round-2.md`): G1, G2, G6, G7, G20 designed in `phase-8-platform-admin.md` (G20 before go-live), G14 in `phase-8-job-discovery.md`; the rest await scope or are designed elsewhere. R8 reopened by the tele-interview benchmark.
- Immediate (not nightly) clearing of talent pool notes needs a JobSeeker trigger.

## Items waiting for the user

- Legacy import: rehearsal done 2026-10-13 (`phase-1-migration.md`, checklist section 3); the **freeze date** for the final run is not set.
- SMTP; then reset emails to the imported accounts without a usable password (761 in the 2026-10-13 copy): dry run first, ask before sending.
- `CORS_ALLOWED_ORIGINS` (front-end origin); `JOB_SEEKER_PROFILE_URL` (the profile reminder email's link); consider nginx `limit_req` for unknown paths.
- `.env` values: `.env.example` and the design "coded" sections; production needs `REDIS_CLIENT=predis` and `CACHE_STORE=redis`.
- Tele-interview: SIP trunk with one DID; root setup of Janus and coturn (`deploy/janus/README.md`) and Asterisk (`deploy/asterisk/README.md`).
- First admin: `identity:create-admin` (enrols TOTP at the first sign-in); other admins with limited roles through `identity:create-role` and `identity:set-admin-role` (`phase-8-admin-console.md`).
- Legal review brief `docs/legal-review.md`: brought up to date 2026-10-16, **not sent yet**; section 0 orders the questions (A before go-live: 1 to 7, 10). Sensitive candidate-search filters stay off until answered.
- Deliver `docs/analysis/legacy-security-report.md` (S1-S3, critical) to the karir.pkp maintainers.

## Operational facts not in `CLAUDE.md`

- Commit identity: `git -c user.name=jobportal -c user.email=<the user's email> commit` (no git identity configured).
- Live dev site: `APP_ENV=staging`, `MAIL_MAILER=log`, `QUEUE_CONNECTION=sync`, `API_DOCS_ENABLED=true`, `COMPANY_INVITATION_URL` empty; nginx directly (no `trustProxies`); scheduler from the `jobportal` crontab. nginx config (root) `/etc/nginx/conf.d/api-karir.satu.solutions.conf`, copy in `/home/jobportal/nginx/`; it, the crontab and ACLs are outside git.
- `tests/Feature/Platform/SettingsTest.php` lists every setting key: update it whenever one is added. Parallel tests need `php artisan test:database:parallel --env=testing` after every migration.
- `KARIR_PKP_FILES_ROOT` is set in `.env`. Import mapping files: `--map=masters=<path>`, `--map=groups=<path>`.
- Rehearsal database: create one (the role has `CREATEDB`), use `DB_DATABASE=<name>` and `LARAVEL_STORAGE_PATH=<scratch dir>`, then `migrate`, `db:seed`, `legacy:import --all --write`. The legacy source (`legacy` connection) stays MariaDB.
- `/docs/api` is served only when `API_DOCS_ENABLED=true`; `php artisan scramble:export` writes `api.json`.
