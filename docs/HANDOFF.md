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
| Front end | `/usr/local/src/jobportal-frontend/onesis-talent` (its `CLAUDE.md`: rules 1 to 22), design `docs/design/frontend.md` (`## Decisions` first) |

## Current state (2026-11-02)

**API (this repository)**

- Latest tag `v0.9.1`. Work is on branch `phase-8-undo-invitations-presets-checks` (pushed to `origin` on 2026-11-02, not merged into `master`; the front end repository has **no remote** configured, so nothing of it is pushed). Everything up to migration `000390` (2026-10-31) is committed; the numbering jumped to `000300` on purpose; check `migrate:status` (the user runs `migrate` and `scramble:clear` then `scramble:cache`, then `pnpm api:refresh` in the front end).
- All work of 2026-11-02 is committed, API and front end. Pushed: the API up to its first push of the day; the later API commits are not, and the front end has no remote.
- Phases 0 to 7 coded. Phase 8 per item: `phase-8-index.md`. **Decision 2026-10-30 (`decisions.md` section 5): work blocked by the legal review is coded in full and ships switched off; the platform admin turns it on in Console "Pengaturan".** Switches today: `invitations.email_enabled`, `recruitment.pre_employment_checks_enabled`, `job_seeker.candidate_sensitive_filters_enabled`, `assessment.psychological_enabled`, `interview.video_enabled`, `interview.voice_enabled`. Still to build behind one: #14, #12 section 3, #26, #33 (see Next).
- Also 2026-10-30: company admins save **permission presets** (`phase-8-company-permissions.md`); the catalog has 30 permissions (`checks.manage`; existing recruiters do not get it automatically).
- Tests: Pest 4 runs on top of PHPUnit 12 (2026-10-30); `tests/Arch/LayersTest.php` keeps the layer and context boundaries (117 tests, no database, `php artisan test --testsuite=Arch`). Function-style files cannot run with `vendor/bin/phpunit`; use `vendor/bin/pest`.

**Front end** (Career `apps/public`, Workspace, Console; shared `packages/ui`, `api`, `i18n`; rules in its `CLAUDE.md`)

- Coded 2026-10-30: R13 undo and reject-remaining, R14 "Menunggu kandidat" (Workspace tab, Career page `/undangan-lamaran/{token}`), team permission presets (tab "Preset izin"), R15 part 1 (page `/perusahaan/cek-pra-kerja`, tab "Cek pra-kerja" on the application, card on the Career application), R15 part 2 (offer condition box, bulk "Minta cek", Career offer summary; only the candidate to-do and agenda wait for #25), Console "Pengaturan" with tabs per key prefix, and the **sticky search bar** on every list page (rule 21).
- **Career: all of `implementation-gap-audit.md` section 5 is coded (K1 to K12; K10 to K12 on 2026-11-02, with `MyReviewResource.company` in the API); the Moodle training pages are not built: the training menu goes to the LMS (`learning.md` section 5, user 2026-11-02)**: J7 tests, newsletter links (K6), account settings (K7, with notifications as a tab), notifications (K4), messages (K2), invitations (K3), saved jobs and job alerts (K8, K9; "Buat peringatan" on the search), campaign landing (K5). Each was checked by the user, not all tried against real data. Not built: J19 `/me/pemindahan`, a "Kirim pesan" button on company pages. Nuxt auto-imports every export of `utils/`: **never reuse an export name** (the typecheck warns "Duplicated imports").
- **Console step 5 is coded (the user's ship passed 2026-11-02, not tried in a browser):** newsletter, FAQ, email and CV templates, undelivered email, the activation report and support reads D28 (`/dukungan`, `views/support/`); the API now documents `group_id` on `admin/support/*` and types `admin/support/groups`.
- Workspace polish of 2026-11-02: the note-only interview update has its own dialog with the email preview; search bars of the group jobs, the group applicants and the Console audit log are in the rule 21 shape; Workspace conversations show the candidate photo (API `ConversationResource` gives the slug, needs `candidates.search`); `LayoutRowLink` is on the review list, my scoring and the member's jobs (rows that hold their own menu or buttons stay as they are); icon audit D is closed (the account page is not a work page).
- Open front end: "approval-flow extras" (an old note nobody defined: ask the user or drop it); Portal Mitra.
- Workspace components are used **with the folder prefix** (`components/company/CheckTypeDialog.vue` is `<CompanyCheckTypeDialog>`, unless the file name already starts with the folder); Career and Console use the file name.

## Next (order agreed 2026-10-30; the user decides when to start each)

0. **Phase 0 passed 2026-10-31.** Left over: the J7 video recording was only unit-tested, never tried with a camera; the Console pages of step 5 and the Career pages were never tried in a browser.
1. **Order proposed 2026-10-31, updated 2026-11-02:** Career K5 to K9 are done; Console pages done 2026-11-02; next E1 to E4 and #13 (item 4), K10 to K12 done 2026-11-02.
2. **Legal-blocked items behind switches:** #14 restricted candidates (q13; also the #18 skip code and #15 `candidate_note`), #12 section 3 acceptance evidence (q12), #26 retention (q15, then #39 A14). Each asks the user for its interpretations first.
3. **Ready without legal:** none left (#20, #22, #25, #30 and #38 are coded).
4. **Employee and module entitlements, designed 2026-10-31, not coded:** E1 to E4 (`self-hosted.md`, "Decided 2026-10-31 for coding"), then #13 parts 1 to 8 (`erp-employee-handoff.md` section 9). **Design sessions still open (no code):** offers through approvals (G5), hires per requisition (FG13).
4b. **ONESIS Learning (LMS), design started 2026-10-31, not coded:** `learning.md` (D1 to D8, domain model K1 to K4, permissions and endpoints P1 to P5 decided); paid courses D10 designed only (section 4, new `Commerce` context, legal question 31); storage S1 to S4, enrolment E1 to E7, grading G1 to G8, learning methods L1 to L9, course management C1 to C9, scheduling J1 to J10, audit trail A1 to A7, reports R1 to R8 and user management U1 to U7 decided; sales V1 to V10 designed only; design complete, coding waits as below. Public side coded after E1 to E4, internal side after #13.
5. **Portal Mitra and institutions:** #23 sections 5 to 8, #24 sections 3 to 8, E16, with their screens.
6. **#39 to #44** when a screen asks. On hold or later: gigs #45 and #46 (user, 2026-10-29), #33 after go-live, the frozen list of `phase-8-index.md`.
- Also open: Performance audit steps 4 and 6; deferred findings 7, 8, 20, 9, 21; benchmark gaps G1-G21; flaky `'42'` assertion of `TestAuthoringTest`; the settings descriptions are English only (translation postponed).

## Items waiting for the user

- Legacy import: rehearsal done 2026-10-13; the **freeze date** is not set. SMTP provider and webhook parser, then `identity:send-activation-emails`; `platform.operations_email` and an uptime monitor on `GET /api/v1/health/scheduler`.
- `CORS_ALLOWED_ORIGINS` and the link settings (values in `frontend.md` 4 and 12.2); production `.env`: `REDIS_CLIENT=predis`, `CACHE_STORE=redis`. Tele-interview: SIP trunk, root setup of Janus, coturn, Asterisk. First admin: `identity:create-admin` (`phase-8-admin-console.md`).
- Legal review `docs/legal-review.md` (31 questions; Indonesian copy `legal-review.id.md`) **not sent**; before go-live: 1 to 7, 10, 23 to 25. Sensitive candidate-search filters stay off until answered.
- Deliver `docs/analysis/legacy-security-report.md` (S1-S3, critical) to the karir.pkp maintainers.

## Operational facts not in `CLAUDE.md`

- Commit identity of this repository is in its `.git/config`.
- Dev site: `APP_ENV=staging`, `MAIL_MAILER=log`, `QUEUE_CONNECTION=sync`; nginx directly (no `trustProxies`), scheduler in the `jobportal` crontab. The nginx config (`/etc/nginx/conf.d/api-karir.satu.solutions.conf`, copy in `/home/jobportal/nginx/`), the crontab and ACLs are outside git.
- Import: `KARIR_PKP_FILES_ROOT`; `--map=masters=<path>`, `--map=groups=<path>`; rehearsal in its own database, then `migrate`, `db:seed`, `legacy:import --all --write`. `/docs/api` needs `API_DOCS_ENABLED=true`; `scramble:export` writes `api.json`.
