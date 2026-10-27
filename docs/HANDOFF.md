# Handoff

Entry point for a new session. Keep it **under about 5 KB**: replace the status, do not append.

| What | Where |
|---|---|
| Rules, code standard, Git workflow, commands | `CLAUDE.md` |
| Decisions | `docs/design/` (`decisions.md` cross-phase, `architecture.md`, `phase-0-schema.md`, one `phase-N-*.md` or `erp-*.md` per step; its "coded" sections hold interpretations, migrations and `.env` values) |
| Phase 8 order and per-item status (coded, open parts, waits) | `docs/design/phase-8-index.md` |
| Go-live list | `docs/deployment-checklist.md` |
| Legacy flow, benchmarks, gaps | `docs/analysis/`; **`implementation-gap-audit.md`** (2026-10-27) lists what the API does that no screen lets an actor do, per actor, with the order of work (its section 9) |
| API-document rules, error catalog | `docs/api/` |
| History | `docs/handoff-history.md` (to 2026-10-08, frozen), then `git log -p docs/HANDOFF.md`; search, do not read whole |
| Front end | `/usr/local/src/jobportal-frontend/onesis-talent` (its `CLAUDE.md`: template rules 1 to 15), design `docs/design/frontend.md` (`## Decisions` first) |

## Current state (2026-10-27)

**API (this repository)**

- Latest tag `v0.9.1` (3296 tests); `master` is 73 commits past it (19 not pushed on 2026-10-27); push state: `git status -sb`. Remote `origin`: private GitHub `alidjator/jobportal_v2` (alias `github-jobportal`).
- Phases 0 to 7 coded and committed. Added 2026-10-26/27: for the front end `GET company/workforce/options`, `candidate_slug`, `candidate_has_avatar`, talent pool `candidate.has_avatar`, the calendar of a panel member; **#23 provider tools sections 1 to 4 coded** (norm groups, candidate context, claiming a review, recommendation with its standard-score rule; `phase-8-provider-tools.md` "Coded"), sections 5 to 8 and #24 not. Phase 8 per item: `phase-8-index.md`. Multi-persona items: #34 to #37 coded; #38 maybe coded, not started; #39 to #44 designed, wait for the front end. API coding is limited to #38 and what the front end asks for (`decisions.md` section 5).
- Branches: `job-editor-api` (C4: migrations `000186`, `000187`) is merged into `master`; `frontend-step-0-api` holds one commit not on `master` (status-count endpoints for the Console tabs): check before deleting. Branches `phase-8-activation` and `workspace-api-gaps` also exist: check before deleting.
- PostgreSQL `jobportal_v2` was at `000176` on 2026-10-19; migrations go to `000192`: check `migrate:status` (the user runs `migrate`, and `php artisan test:database:migrate --env=testing` for the test database before the tests). Reference data only until the final import (`demo:seed` for development).
- Go-live readiness first (`docs/deployment-checklist.md`); the dev site is this folder, so code with a new migration fails until `migrate`. Name: ONESIS (`naming.md`).

**Front end** (Career `apps/public`, Workspace, Console; shared `packages/ui`, `api`, `i18n`)

- Able Pro Tailwind 1.2.0, compact density (rules 11 to 14), Font Awesome Pro regular; the job description is Markdown (CKEditor Visual or EasyMDE tab); Workspace opens pages as tabs (`frontend.md` 11.1, rule 15; coded 2026-10-25, not yet tried in a browser).
- Coding order `frontend.md` section 13 (its status paragraph of 2026-10-27 is the list of what is coded; Console step 5 and Portal Mitra are not started: audit sections 7 and 8). Workspace steps 5 and 7 are coded except C9, C10 (step 6) and E16 (waits for #24 section 8). Career step 4 has the public pages, apply and `/me` (P5 to P7, P9, P10, J8 to J17 left). Console and Portal Mitra: see the section 13 status. Decisions of 2026-10-26 and 2026-10-27 (tabs switch, sidebar user card, avatars, row links, applicant kanban on all jobs) are in `frontend.md` Decisions and 11.1. **Not tried in a browser**: the ERP pages, the kanban across jobs, the MPP grid, the approval flow editor.
- Open front end questions: card header icons (icons for form-section cards only, or none; then a rule in `CLAUDE.md`), icon audit finding D (`AccountSecurityTab`), photos in conversations and in the group's applicant page, more approval flow features (templates, change history), `LayoutRowLink` in older lists.
- Git: local commits on `main` (last `1502ac2`, 2026-10-27) (author `alidjator@gmail.com`); no remote and no identity configured there; `backup/before-reauthor` may be deleted.
- Deploy: `pnpm release:<app>` builds and switches `releases/<app>/current`; run it as `jobportal`, never root (root-owned files break the next build); Workspace then needs `systemctl restart onesis-workspace` as root. The user runs `pnpm install`, `icons:build`, builds, tests and pushes.

## Open work (not started)

- Next in the audit order: the job editor steps (screening questions, default test and kit per stage, hiring manager boundary, delete a draft, review requests), then the Career chains (messages, invitations, tests, notifications, settings), Console step 5, Portal Mitra with #23 and #24. Needs `pnpm api:refresh` for the endpoints added on 2026-10-27.
- Performance audit steps 4 (measure) and 6 (query-count tests); then drop `jobportal_v2_perf`.
- Deferred architecture findings 7, 8, 20 (`decisions.md` section 6), 9, 21 (after the import).
- Benchmark gaps G1-G21 (`docs/analysis/gap-analysis-round-2.md`): G1, G2, G6, G7, G20 in `phase-8-platform-admin.md` (G20 before go-live), G14 in `phase-8-job-discovery.md`; R8 reopened.
- Talent pool notes need a JobSeeker trigger for immediate clearing. Flaky: the `'42'` assertion of `TestAuthoringTest`.

## Items waiting for the user

- Legacy import: rehearsal done 2026-10-13 (`phase-1-migration.md`); the **freeze date** is not set.
- SMTP provider and its webhook parser; then `identity:send-activation-emails` (staff, company owners, pilot of 200, 500 a day; checklist section 3).
- Setting `platform.operations_email` and an outside uptime monitor on `GET /api/v1/health/scheduler`.
- `CORS_ALLOWED_ORIGINS` and the link settings (`COMPANY_INVITATION_URL`, `JOB_SEEKER_*_URL`, `COMPANY_APP*_URL`, ...) with the values of `frontend.md` 4 and 12.2; production `.env`: `REDIS_CLIENT=predis`, `CACHE_STORE=redis` (`.env.example`).
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
