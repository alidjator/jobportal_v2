# Handoff

The short entry point for a new session. Working rules, code standard, database rules and commands are in `CLAUDE.md` and are not repeated here.
Keep this file **under about 5 KB**: replace the status below instead of appending to it. Decisions belong in the design documents, not here. The full history up to
2026-10-08 is in `docs/handoff-history.md` (search it for a specific past event; do not read it whole).

## Read order

1. This file.
2. The design document of the step you work on (`docs/design/`, see the index below). Each one records every decision of the user and has "Part N coded" sections.
3. Only when needed: `docs/design/decisions.md` (cross-phase decisions), `architecture.md` (API contract, auth, persistence, queues, files), `phase-0-schema.md`.

## Current state (2026-10-08)

- Branch `phase-1-company`, last commit is the part 5 design handoff (`git log`). `master` is at tag `v0.1.0` (`ee7b463`); local repo, no remote.
- **Phase 0 is complete.** Phase 1 steps 1 to 8 are coded and committed: company (with document verification), jobs, hybrid stages, screening questions,
  job seeker profile and resumes, applying, the recruiter's pipeline.
- **Step 9, the data migration from karir.pkp (`docs/design/phase-1-migration.md`), is fully coded** (parts 1 to 5, context `src/LegacyImport`: runner, report, id map, master matcher, importers for accounts,
  companies, images, job seeker data, resumes, jobs, screening questions and applications). Part 5 (2026-10-08) is committed; its six coding choices were confirmed by the user.
- **The first real run is done (2026-10-08):** `legacy:import --all --write` (no mapping files) against the real `jobportal_v2`, after a full rehearsal in a scratch database
  (dropped since). Result: 887 id map rows, 179 accounts, 173 profiles, 3 companies, 149 jobs (many `draft`, see the design doc), 30 applications (all in `applied`).
  Report: `storage/app/private/legacy-import/20261008-075847-all-write.json`. The importer only adds: a re-run does not fix rows already imported.
- Leftovers (details in the design doc): legacy group "dev" (7 applications) left unmapped on purpose; 10 accounts need a password reset; some files and masters did not match.

- **Phase 2 started (2026-10-08):** order is notes and ratings, interview scheduling, offers, bulk actions, CSV export, chat (`docs/design/phase-2-notes.md`). Step 1 (notes and ratings) is coded and
  committed (`bbb0a18`); its migrations ran on the real database. Step 2 (interview scheduling, `docs/design/phase-2-interviews.md`) is coded, checked (1452 tests) and committed (`f7c4a10`); `000081` ran on both databases.

## Next step

Step 2 is committed (`f7c4a10`) and `000081` ran on the real database. Step 3 (offers, `docs/design/phase-2-offers.md`) is coded, checked (1477 tests) and committed; `000082` ran on both databases. Step 4 (bulk actions, `docs/design/phase-2-bulk-actions.md`) is designed (`618a2dd`), coded, checked (1494 tests) and committed; no migration. Step 5 (CSV export, R13, `docs/design/phase-2-export.md`) is coded, checked (1502 tests) and committed; no migration. Step 6 (chat with attachments and bulk message, `docs/design/phase-2-chat.md`) is designed (`ef76aa6`), coded, checked (1534 tests) and committed; migrations `000083` to `000086` ran on both databases. Next: Phase 2 is complete apart from the deployment tasks of the user (Reverb process, nginx WebSocket proxy, upload limits; see the design doc). Phase 3 step 1 (job alerts, `docs/design/phase-3-job-alerts.md`) is designed (`2a9ab22`), coded, checked (1562 tests) and committed; migrations `000087` and `000088` ran on both databases. Phase 3 step 2 (candidate search, R9, `docs/design/phase-3-candidate-search.md`) is designed (`099d80b`), coded, checked (1583 tests) and committed; migrations `000089` and `000090` ran on the test database, the user runs them on the real one. Legal review of the religion, nationality, age and gender filters is needed before release. Phase 3 step 3 (job invitations, R10, `docs/design/phase-3-invitations.md`) is designed (`c765dd9`), coded, checked (1600 tests) and committed; migration `000091` ran on the test database, the user runs it on the real one (committed `8580c1c`). Phase 3 step 4 (matching engine, J8 and R12, `docs/design/phase-3-matching.md`) is designed (`b5a6e0c`), coded, checked (1635 tests) and committed; migration `000092` ran on the test database, the user runs it on the real one. Phase 3 step 5 (CV generator, J7, `docs/design/phase-3-cv-generator.md`) is designed (`df7d5f7`), coded, checked (1661 tests) and committed; migrations `000093` and `000094` ran on the test database, the user runs them on the real one, then `php artisan identity:sync-permissions` and grants `cv_templates.manage` to the admins who manage the templates. That closes Phase 3. Import follow-ups (2026-10-09): the 10 accounts without a password (ids 16, 17, 18, 20, 24, 25, 26, 27, 35, 36) wait for SMTP, then a command sends them reset emails (dry-run first, ask before running). The 142 `draft` jobs stay as they are by decision (116 are legacy test data with no applications, 26 have real text but no matching skill and hold 25 applications).

## Operational facts not in `CLAUDE.md`

- Commit identity: `git -c user.name=jobportal -c user.email=<the user's email> commit` (the repo has no git identity configured).
- Live dev site: email is `MAIL_MAILER=log`, `QUEUE_CONNECTION=sync` and `COMPANY_INVITATION_URL` is empty. Before real users: SMTP (credentials entered by the user),
  a queue worker for `critical,default,bulk`, and the invitation page URL.
- The scheduler runs from the `jobportal` user's crontab (`schedule:run` every minute); the nginx config is root-owned at `/etc/nginx/conf.d/api-karir.satu.solutions.conf`
  (a copy is in `/home/jobportal/nginx/`). Neither is in git, and neither are the POSIX ACLs on `storage/` and `bootstrap/cache`.
- Production schema dump (read-only account, no data): `mariadb-dump --no-data --skip-comments --no-tablespaces --skip-triggers --skip-lock-tables` with the `KARIR_PKP_DB_*` values.
- After a deployment that adds a permission, `identity:sync-permissions` must be run on the real database (ask first). `tests/Feature/Platform/SettingsTest.php` lists every
  setting key: update it whenever a setting is added.
- Running the legacy import needs the user's explicit approval each time, and the auto-mode classifier may block a `--write` to the real database (the user then runs it or adds a
  Bash permission rule). `KARIR_PKP_FILES_ROOT` is set in `.env` (git-ignored). Optional mapping files: `--map=masters=<path>`, `--map=groups=<path>`.
- A rehearsal database: the app account cannot create databases, but any `test_*` name is open to it. Use `DB_DATABASE=test_<name>` and `LARAVEL_STORAGE_PATH=<scratch dir>`
  (so copied files stay out of the real storage), then `migrate`, `db:seed`, `legacy:import --all --write`.

## Items waiting for the user

- Decisions on the legacy findings (`docs/analysis/legacy-findings.md`), especially S1 to S3 in the running production application.
- Legal review of the religion and nationality filtering decision, and of the age limits (minimum 15, default maximum 60).
- Open product points: whether applicants see rejection reasons (hidden now), the push channel of `application_update` (email only now), whether applying needs a verified email.

## Document index

| File | Content |
|---|---|
| `docs/analysis/` | Legacy flow map, legacy findings, benchmark notes, gap analysis |
| `docs/design/decisions.md` | Cross-phase decisions (stages, screening, gap scope, foundation, delivery and phases) |
| `docs/design/architecture.md` | API contract, authentication, authorization, persistence, queues, files, account states |
| `docs/design/phase-0-schema.md` | Column design of the Phase 0 tables and details still open |
| `docs/design/phase-1-*.md` | One per Phase 1 step: `company`, `jobs`, `stages`, `screening`, `jobseeker`, `applications`, `pipeline`, `migration` |
| `docs/handoff-history.md` | Frozen session log up to 2026-10-08 |

## Suggested first message for the next session

> Read `docs/HANDOFF.md`, then ask me whether to continue Phase 2 (step 3, offers) or the import follow-ups.
