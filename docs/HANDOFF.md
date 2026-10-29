# Handoff

Entry point for a new session. Keep it **under about 5 KB**: replace the status, do not append.

| What | Where |
|---|---|
| Rules, code standard, Git workflow, commands | `CLAUDE.md` |
| Decisions | `docs/design/` (`decisions.md` cross-phase, `architecture.md`, `phase-0-schema.md`, one `phase-N-*.md` or `erp-*.md` per step; its "coded" sections hold interpretations, migrations and `.env` values) |
| Phase 8 order and per-item status | `docs/design/phase-8-index.md` |
| Go-live list | `docs/deployment-checklist.md` |
| Legacy flow, benchmarks, gaps | `docs/analysis/`; **`implementation-gap-audit.md`** lists what the API does that no screen lets an actor do, per actor, with the order of work (its section 9) |
| API-document rules, error catalog | `docs/api/` |
| History | `docs/archive/handoff-history.md` (to 2026-10-08, frozen), then `git log -p docs/HANDOFF.md`; search, do not read whole |
| Front end | `/usr/local/src/jobportal-frontend/onesis-talent` (its `CLAUDE.md`: template rules 1 to 16), design `docs/design/frontend.md` (`## Decisions` first) |

## Current state (2026-10-29)

**API (this repository)**

- Latest tag `v0.9.1`; `master` is 106 commits past it, 52 not pushed (`git status -sb`). Remote `origin`: private GitHub `alidjator/jobportal_v2` (alias `github-jobportal`). Uncommitted work in the tree: installation type, institutions, structure reorder, demo data (`git status`).
- Phases 0 to 7 coded and committed. Phase 8 per item: `phase-8-index.md`. Coded 2026-10-26/28: #23 provider tools sections 1 to 4, #24 sections 1 and 2, screening presets (`phase-1-screening-presets.md`), installation type (`erp-installation-type.md`). Multi-persona items #34 to #37 coded; #38 maybe coded, not started; #39 to #44 designed, wait for the front end. API coding is limited to #38 and what the front end asks for (`decisions.md` section 5).
- Branches: `job-editor-api` is merged into `master`; `frontend-step-0-api` holds commit `1b5b359` (status-count endpoints for the Console tabs) not on `master`; `phase-8-activation` and `workspace-api-gaps` also exist: check before deleting.
- PostgreSQL `jobportal_v2`: migrations go to `000199` (`000195` and `000199` not yet committed): check `migrate:status` (the user runs `migrate`, and `php artisan test:database:migrate --env=testing` before the tests). Reference data only until the final import (`demo:seed` for development).
- Go-live readiness first (`docs/deployment-checklist.md`); the dev site is this folder, so code with a new migration fails until `migrate`. Name: ONESIS (`naming.md`).

**Front end** (Career `apps/public`, Workspace, Console; shared `packages/ui`, `api`, `i18n`)

- Able Pro Tailwind 1.2.0, compact density, Font Awesome Pro 7.1 light, 4 px spacing scale (`CLAUDE.md` there); the job description is Markdown (CKEditor Visual or EasyMDE tab); Workspace opens pages as tabs (`frontend.md` 11.1).
- Coding order `frontend.md` section 13 (its status paragraph is the list of what is coded); Console against the Workspace: `docs/analysis/console-workspace-audit.md`. Last commit `c539eed` (screening presets screen). **Not tried in a browser**: the ERP pages, the kanban across jobs, the MPP grid, the approval flow editor, the Workspace tabs.
- Open front end questions: icon audit finding D (`AccountSecurityTab`), photos in conversations and in the group's applicant page, more approval flow features (templates, change history), `LayoutRowLink` in older lists.
- Git: local commits on `main`, no remote, no identity configured; `backup/before-reauthor` may be deleted.
- Deploy: `pnpm release:<app>` builds and switches `releases/<app>/current`; run it as `jobportal`, never root; Workspace then needs `systemctl restart onesis-workspace` as root. The user runs `pnpm install`, `icons:build`, builds, tests and pushes.

## Open work (not started)

- Next in the audit order: the job editor steps (screening questions, default test and kit per stage, hiring manager boundary, delete a draft, review requests), then the Career chains (messages, invitations, tests, notifications, settings), Console step 5, Portal Mitra with #23 and #24. Needs `pnpm api:refresh` after API endpoints change.
- Performance audit steps 4 (measure) and 6 (query-count tests); then drop `jobportal_v2_perf`.
- Deferred architecture findings 7, 8, 20 (`decisions.md` section 6), 9, 21 (after the import).
- Benchmark gaps G1-G21 (`docs/analysis/gap-analysis-round-2.md`): G1, G2, G6, G7, G20 in `phase-8-platform-admin.md` (G20 before go-live), G14 in `phase-8-job-discovery.md`; R8 reopened.
- Talent pool notes need a JobSeeker trigger for immediate clearing. Flaky: the `'42'` assertion of `TestAuthoringTest`.

## Items waiting for the user

- Legacy import: rehearsal done 2026-10-13 (`phase-1-migration.md`); the **freeze date** is not set.
- SMTP provider and its webhook parser; then `identity:send-activation-emails` (checklist section 3).
- `platform.operations_email` and an outside uptime monitor on `GET /api/v1/health/scheduler`.
- `CORS_ALLOWED_ORIGINS` and the link settings (`COMPANY_INVITATION_URL`, `JOB_SEEKER_*_URL`, `COMPANY_APP*_URL`, ...) with the values of `frontend.md` 4 and 12.2; production `.env`: `REDIS_CLIENT=predis`, `CACHE_STORE=redis`.
- Tele-interview: SIP trunk with one DID; root setup of Janus, coturn and Asterisk (`deploy/*/README.md`).
- First admin: `identity:create-admin`; others by `identity:create-role` and `identity:set-admin-role` (`phase-8-admin-console.md`).
- Legal review `docs/legal-review.md` (25 questions; Indonesian copy `legal-review.id.md`) **not sent**; before go-live: 1 to 7, 10, 23 to 25. Sensitive candidate-search filters stay off until answered.
- Deliver `docs/analysis/legacy-security-report.md` (S1-S3, critical) to the karir.pkp maintainers.

## Operational facts not in `CLAUDE.md`

- Commit identity of this repository is in its `.git/config`.
- Dev site: `APP_ENV=staging`, `MAIL_MAILER=log`, `QUEUE_CONNECTION=sync`, `API_DOCS_ENABLED=true`; nginx directly (no `trustProxies`), scheduler in the `jobportal` crontab. The nginx config (`/etc/nginx/conf.d/api-karir.satu.solutions.conf`, copy in `/home/jobportal/nginx/`), the crontab and ACLs are outside git.
- `tests/Feature/Platform/SettingsTest.php` lists every setting key: update it with each new one.
- Import: `KARIR_PKP_FILES_ROOT` in `.env`; `--map=masters=<path>`, `--map=groups=<path>`. Rehearsal: own database (`DB_DATABASE`, `LARAVEL_STORAGE_PATH`), then `migrate`, `db:seed`, `legacy:import --all --write`; `legacy` stays MariaDB.
- `/docs/api` needs `API_DOCS_ENABLED=true`; `php artisan scramble:export` writes `api.json`.
