# Handoff

Entry point for a new session (any account: the working rules are in `CLAUDE.md`, not in anyone's memory). Keep it **short** (about 7 KB at most): replace the status, do not append.

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

## Current state (2026-10-30)

**API (this repository)**

- Latest tag `v0.9.1`; `master` is well past it and not pushed (`git status -sb`). Uncommitted work: installation type, institutions, structure reorder, demo data, and of 2026-10-30 the undo window `000200`, `000201`, email invitations `000300` (#31), company permission presets `000310`, pre-employment checks `000320` (#32 part 1). The numbering jumped to `000300` on purpose; check `migrate:status` (the user runs `migrate` and `scramble:clear` then `scramble:cache`, then `pnpm api:refresh` in the front end).
- Phases 0 to 7 coded. Phase 8 per item: `phase-8-index.md`. **Decision 2026-10-30 (`decisions.md` section 5): work blocked by the legal review is coded in full and ships switched off; the platform admin turns it on in Console "Pengaturan".** Switches today: `invitations.email_enabled`, `recruitment.pre_employment_checks_enabled`, `job_seeker.candidate_sensitive_filters_enabled`, `assessment.psychological_enabled`, `interview.video_enabled`, `interview.voice_enabled`. Still to build behind one: #14, #12 section 3, #26, #33 (see Next).
- Also 2026-10-30: company admins save **permission presets** (`phase-8-company-permissions.md`); the catalog has 30 permissions (`checks.manage`; existing recruiters do not get it automatically).
- Tests: Pest 4 runs on top of PHPUnit 12 (2026-10-30); `tests/Arch/LayersTest.php` keeps the layer and context boundaries (117 tests, no database, `php artisan test --testsuite=Arch`). Function-style files cannot run with `vendor/bin/phpunit`; use `vendor/bin/pest`.

**Front end** (Career `apps/public`, Workspace, Console; shared `packages/ui`, `api`, `i18n`; rules in its `CLAUDE.md`)

- Coded 2026-10-30: R13 undo and reject-remaining, R14 "Menunggu kandidat" (Workspace tab, Career page `/undangan-lamaran/{token}`), team permission presets (tab "Preset izin"), R15 part 1 (page `/perusahaan/cek-pra-kerja`, tab "Cek pra-kerja" on the application, card on the Career application), Console "Pengaturan" with tabs per key prefix, and the **sticky search bar** on every list page (rule 21).
- Open front end: R15 part 2 (offer condition box, bulk request); search bars in the old shape (`OrganizationReadJobs`, Console audit log, `notifikasi`); icon audit D (`AccountSecurityTab`); photos in conversations; approval-flow extras; `LayoutRowLink`; Portal Mitra per `implementation-gap-audit.md` section 7.
- Workspace components are used **with the folder prefix** (`components/company/CheckTypeDialog.vue` is `<CompanyCheckTypeDialog>`, unless the file name already starts with the folder); Career and Console use the file name.

## Next (order agreed 2026-10-30; the user decides when to start each)

0. **Phase 0, the user's check of today's work.** Green so far: all migrations `Ran` (to `000320`), `scramble:cache`, test database, `api:error-catalog --check`, the Arch tests (117), the voice listener refactor. **Not yet reported:** `composer check` (whole suite), the front end (`pnpm api:refresh`, `prettier --write apps packages`, `pnpm ship console|workspace|public`), the manual list in a browser (Console "Pengaturan", Workspace "Jenis cek pra-kerja", "Menunggu kandidat", "Preset izin", the sticky search bars), then commits by group (R13, R14, permission presets, R15 part 1, Console settings and search bars, docs, Pest and Arch tests). Nothing of the 2026-10-26 to 2026-10-30 work was tried in a browser.
1. **Done 2026-10-31, waiting for commits of the other sessions' files only:** #32 part 2 (API and front end; only the candidate's agenda and to-do sources were left, now coded with #15) and #15 sections 3, 4 and 5 (`GET me/tasks`, `GET me/agenda`, the cards `todo` and `agenda`, the Career pages `/me/tasks` and `/me/agenda`; section 4: samples not drawn, `GET me/assessment/invitations/{id}/practice`, `requirements`). The practice page and the device check came with **J7** (Career test pages and runner, all three steps coded and committed 2026-10-31: list, invitation page, runner for questions, speed columns, drawing and video answers, explanation page; `implementation-gap-audit.md` K1).
2. **Legal-blocked items behind switches:** #14 restricted candidates (q13; also the #18 skip code and #15 `candidate_note`), #12 section 3 acceptance evidence (q12), #26 retention (q15, then #39 A14). Each asks the user for its interpretations first.
3. **Ready without legal:** none left (#20, #22, #25, #30 and #38 are coded).
4. **Employee and module entitlements, designed 2026-10-31, not coded:** E1 to E4 (`self-hosted.md`, "Decided 2026-10-31 for coding"), then #13 parts 1 to 8 (`erp-employee-handoff.md` section 9). **Design sessions still open (no code):** offers through approvals (G5), hires per requisition (FG13).
5. **Portal Mitra and institutions:** #23 sections 5 to 8, #24 sections 3 to 8, E16, with their screens.
6. **#39 to #44** when a screen asks. On hold or later: gigs #45 and #46 (user, 2026-10-29), #33 after go-live, the frozen list of `phase-8-index.md`.
- Also open: Performance audit steps 4 and 6; deferred findings 7, 8, 20, 9, 21; benchmark gaps G1-G21; flaky `'42'` assertion of `TestAuthoringTest`; the settings descriptions are English only (translation postponed).

## Items waiting for the user

- Legacy import: rehearsal done 2026-10-13; the **freeze date** is not set. SMTP provider and webhook parser, then `identity:send-activation-emails`; `platform.operations_email` and an uptime monitor on `GET /api/v1/health/scheduler`.
- `CORS_ALLOWED_ORIGINS` and the link settings (values in `frontend.md` 4 and 12.2); production `.env`: `REDIS_CLIENT=predis`, `CACHE_STORE=redis`. Tele-interview: SIP trunk, root setup of Janus, coturn, Asterisk. First admin: `identity:create-admin` (`phase-8-admin-console.md`).
- Legal review `docs/legal-review.md` (29 questions; Indonesian copy `legal-review.id.md`) **not sent**; before go-live: 1 to 7, 10, 23 to 25. Sensitive candidate-search filters stay off until answered.
- Deliver `docs/analysis/legacy-security-report.md` (S1-S3, critical) to the karir.pkp maintainers.

## Operational facts not in `CLAUDE.md`

- Commit identity of this repository is in its `.git/config`.
- Dev site: `APP_ENV=staging`, `MAIL_MAILER=log`, `QUEUE_CONNECTION=sync`; nginx directly (no `trustProxies`), scheduler in the `jobportal` crontab. The nginx config (`/etc/nginx/conf.d/api-karir.satu.solutions.conf`, copy in `/home/jobportal/nginx/`), the crontab and ACLs are outside git.
- Import: `KARIR_PKP_FILES_ROOT`; `--map=masters=<path>`, `--map=groups=<path>`; rehearsal in its own database, then `migrate`, `db:seed`, `legacy:import --all --write`. `/docs/api` needs `API_DOCS_ENABLED=true`; `scramble:export` writes `api.json`.
