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
  checked (1414 tests) but **not committed**; migrations `000079` and `000080` ran on the test database only, the real tables need approval.

## Next step

Commit step 1 and run its migrations on the real tables (with approval), then design step 2 (interview scheduling, R7b); or follow-ups of the run: reset the 10 non-bcrypt accounts, review the `draft` jobs with the user.

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

> Read `docs/HANDOFF.md`, then ask me whether to start the approved migration run or Phase 2.
