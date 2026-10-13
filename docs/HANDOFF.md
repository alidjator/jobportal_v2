# Handoff

Entry point for a new session; rules, code standard, Git workflow and commands are in `CLAUDE.md`. Keep this file **under about 5 KB**: replace the status, do not append; decisions and "coded" notes belong in `docs/design/`. History: `docs/handoff-history.md` (to 2026-10-08) and `git log -p docs/HANDOFF.md` (later); search them, do not read them whole.

## Read order

1. This file.
2. The design document of the step you work on (`docs/design/`: `decisions.md` cross-phase, `architecture.md` API/auth/persistence/queues/files, `phase-0-schema.md`, one `phase-N-*.md` per step; each phase's "coded" sections hold its interpretations, migrations and `.env` values). `docs/analysis/` holds the legacy flow map, benchmarks and gap analysis; `docs/api/` the API-document rules and error catalog; `docs/deployment-checklist.md` the go-live list.

## Current state (2026-10-13)

- Local repo, **no remote** (the only copy is this disk). `master` is at the end of phase 7; tag `v0.1.0` is phase 0 + Company.
- **Phases 0 to 7 are coded and committed**: 0-4 product; 5 security review, performance audit steps 1-3 and 5, architecture review; 6 assessments; 7 interview kits and tele-interview forms 1-3 (Janus video, one-way video answers, Asterisk voice). **`composer check` after phase 7 is not yet confirmed green**: the user runs it; when green, tag `v0.2.0`.
- Real PostgreSQL `jobportal_v2`: migrations up to `000133` all ran (checked 2026-10-13); only seeded reference data (0 accounts, 0 companies). The test database needs `php artisan test:database:migrate --env=testing` if it is behind.
- **Direction (evaluation 2026-10-13, agreed by the user): no new features, go-live readiness first**: remote or backup of the repo, `composer check` + tag, legacy import rehearsal on PostgreSQL, `docs/deployment-checklist.md`, and separating the live site from the working folder (the scheduler fails whenever code that needs a new migration is live before `migrate`, as on 2026-10-12).

## Open work (not started)

- Performance audit step 4 (measure) and step 6 (query-count tests). `jobportal_v2_perf` exists, migrated to `000112`, not filled (`db:seed` first); drop it when done.
- Architecture review findings deferred: 7, 8, 20 (`decisions.md` section 6), 9, 21 (refactor after the import).
- Benchmark round 2 gaps G1-G21 (`docs/analysis/gap-analysis-round-2.md`) await the user's scope decision; **G20** (renamed verified company keeps its badge) should be decided before go-live. R8 was reopened by the tele-interview benchmark.
- `phase-7-interview-kits.md` "Part 2 coded": the interpretations other than erasure wait for the user's review.
- Immediate (not nightly) clearing of talent pool notes needs a JobSeeker trigger.

## Items waiting for the user

- Legacy import on PostgreSQL: mapping files and freeze date; dry run `php artisan legacy:import --all` (reads and reports only), ask before `--write`; then rerun `analytics:rollup --backfill`.
- SMTP; then reset emails to the 10 legacy accounts without a password (legacy ids 16, 17, 18, 20, 24, 25, 26, 27, 35, 36): dry run first, ask before sending.
- `CORS_ALLOWED_ORIGINS` (front-end origin); consider nginx `limit_req` for unknown paths.
- `.env` values per phase are in `.env.example` and the design "coded" sections (campaign, Moodle, newsletter, psychologist invitation, `JANUS_*`, `TURN_*`, `ASTERISK_*`, `VOICE_CALLER_ID`); production needs `REDIS_CLIENT=predis` and `CACHE_STORE=redis`.
- Tele-interview: SIP trunk with one DID; root setup of Janus and coturn (`deploy/janus/README.md`) and Asterisk (`deploy/asterisk/README.md`).
- First admin: `identity:create-admin` (enrols TOTP at the first sign-in); grant `cv_templates.manage`.
- Legal review brief `docs/legal-review.md` (sensitive candidate-search filters stay off until answered).
- Deliver `docs/analysis/legacy-security-report.md` (S1-S3, critical) to the karir.pkp maintainers.

## Operational facts not in `CLAUDE.md`

- Commit identity: `git -c user.name=jobportal -c user.email=<the user's email> commit` (no git identity configured).
- Live dev site: `APP_ENV=staging`, `MAIL_MAILER=log`, `QUEUE_CONNECTION=sync`, `API_DOCS_ENABLED=true`, `COMPANY_INVITATION_URL` empty. Served by nginx directly, no proxy or CDN, so no `trustProxies`. Scheduler from the `jobportal` crontab (`schedule:run` every minute). The nginx config is root-owned at `/etc/nginx/conf.d/api-karir.satu.solutions.conf` (copy in `/home/jobportal/nginx/`); neither it, the crontab nor the POSIX ACLs are in git.
- `tests/Feature/Platform/SettingsTest.php` lists every setting key: update it whenever one is added. Parallel tests need `php artisan test:database:parallel --env=testing` after every migration.
- `KARIR_PKP_FILES_ROOT` is set in `.env`. Import mapping files: `--map=masters=<path>`, `--map=groups=<path>`.
- Rehearsal database: create one (the role has `CREATEDB`), use `DB_DATABASE=<name>` and `LARAVEL_STORAGE_PATH=<scratch dir>`, then `migrate`, `db:seed`, `legacy:import --all --write`. The legacy source (`legacy` connection) stays MariaDB.
- `/docs/api` is served only when `API_DOCS_ENABLED=true`; `php artisan scramble:export` writes `api.json`.
