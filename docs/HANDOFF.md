# Handoff

The short entry point for a new session. Working rules, code standard, database rules and commands are in `CLAUDE.md` and are not repeated here.
Keep this file **under about 5 KB**: replace the status below instead of appending to it. Decisions belong in the design documents, not here. The full history up to
2026-10-08 is in `docs/handoff-history.md` (search it for a specific past event; do not read it whole).

## Read order

1. This file.
2. The design document of the step you work on (`docs/design/`, see the index below). Each one records every decision of the user and has "Part N coded" sections.
3. Only when needed: `docs/design/decisions.md` (cross-phase decisions), `architecture.md` (API contract, auth, persistence, queues, files), `phase-0-schema.md`.

## Current state (2026-10-08)

- Branch `phase-1-company`, last commit `7d6285f` (part 4 of step 9). `master` is at tag `v0.1.0` (`ee7b463`); local repo, no remote.
- **Phase 0 is complete.** Phase 1 steps 1 to 8 are coded and committed: company (with document verification), jobs, hybrid stages, screening questions,
  job seeker profile and resumes, applying, the recruiter's pipeline.
- **Step 9, the data migration from karir.pkp (`docs/design/phase-1-migration.md`), is in progress.** Parts 1 to 4 are coded and committed (context
  `src/LegacyImport`: runner, report, id map, master matcher, importers for accounts, companies, images, job seeker data, jobs and screening questions).
- **Part 5 (applications) is being designed** and is not committed: the "Part 5 design" section of `phase-1-migration.md` (group mapping by name and by mapping file),
  and `CompaniesImporter` now gives every migrated company its seven default stages through `DefaultStageProvisioner`.
- Nothing has been run against production data. The id map migration `000078` exists only in the test database.

## Next step

First steps of the part 5 design session: (1) ask the user to commit the uncommitted part 5 work (`docs/design/phase-1-migration.md`,
`src/LegacyImport/Application/CompaniesImporter.php`, default stages for migrated companies); (2) give `phase-1-migration.md` its `## Decisions`
section (`CLAUDE.md`, "Design documents") and commit it separately.

Continue part 5 of step 9: finish its design (questions per `CLAUDE.md` rule 4), then code it: applications with the group mapping file, the snapshot built from the
migrated profile, the two-entry stage history, the duplicate rule, a full-run test and the documents. Extend `tests/Support/LegacySchema.php` with
`application_groups` and `applied_jobs`. After Phase 1 comes Phase 2 (collaboration, see `decisions.md` section 5).

## Operational facts not in `CLAUDE.md`

- Commit identity: `git -c user.name=jobportal -c user.email=<the user's email> commit` (the repo has no git identity configured).
- Live dev site: email is `MAIL_MAILER=log`, `QUEUE_CONNECTION=sync` and `COMPANY_INVITATION_URL` is empty. Before real users: SMTP (credentials entered by the user),
  a queue worker for `critical,default,bulk`, and the invitation page URL.
- The scheduler runs from the `jobportal` user's crontab (`schedule:run` every minute); the nginx config is root-owned at `/etc/nginx/conf.d/api-karir.satu.solutions.conf`
  (a copy is in `/home/jobportal/nginx/`). Neither is in git, and neither are the POSIX ACLs on `storage/` and `bootstrap/cache`.
- Production schema dump (read-only account, no data): `mariadb-dump --no-data --skip-comments --no-tablespaces --skip-triggers --skip-lock-tables` with the `KARIR_PKP_DB_*` values.
- After a deployment that adds a permission, `identity:sync-permissions` must be run on the real database (ask first). `tests/Feature/Platform/SettingsTest.php` lists every
  setting key: update it whenever a setting is added.
- Running the legacy import needs `KARIR_PKP_FILES_ROOT` (not set yet) and the user's explicit approval each time; the masters mapping and the pipeline-group mapping files
  are filled in by the user at the approved run.

## Items waiting for the user

- Decisions on the legacy findings (`docs/analysis/legacy-findings.md`), especially S1 to S3 in the running production application.
- Approval to run the data migration against real production data.
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

> Read `docs/HANDOFF.md` and `docs/design/phase-1-migration.md`, then continue part 5 of step 9 (applications): design questions one at a time, and I decide.
