# Project task list: ONESIS ERP / ONESIS Career

The single list of the work left until go-live and after it, grouped by milestone. Written 2026-10-20 from `docs/HANDOFF.md`,
`docs/design/phase-8-index.md`, `docs/design/phase-8-multi-persona-order.md`, `docs/deployment-checklist.md` and `docs/design/frontend.md`.

**This list does not replace those documents.** The design documents hold the decisions; `phase-8-index.md` holds the coding order of the API;
`deployment-checklist.md` holds the exact go-live steps. A task here links to its source and is ticked when the source says it is done. When the two
disagree, the source wins and this list is corrected.

## How to use this list

- **One task, one outcome.** Each task starts with a verb, has one owner and a "Done when" line that can be checked by someone else.
- **IDs are stable.** A task keeps its ID when it moves or is dropped (mark it `Dropped`, do not delete it). New tasks take the next free number
  of their milestone.
- **Update the status the day it changes**, and the "Last updated" line below. Keep the status of an API item in step with `phase-8-index.md`.
- **Blocked tasks name what blocks them** (a task ID, a legal question, a user decision), never just "blocked".
- **Review weekly:** move finished tasks to `Done`, check every `Blocked` task against its blocker, and re-order the `Ready` tasks of the current
  milestone.

Last updated: 2026-10-20.

### Legend

| Field | Values |
|---|---|
| Status | `Done`, `In progress`, `In review` (coded, waiting for the user's check or commit), `Ready` (nothing blocks it), `Blocked`, `Not started` (needs a design first), `Dropped` |
| Priority | `P0` blocks go-live; `P1` needed for go-live but does not block other work; `P2` after go-live; `P3` backlog, no date |
| Owner | `User` (decisions, root, secrets, production, push, builds, checks), `Design` (a design session, no code), `Code` (a coding session), `Legal` (outside counsel), `Ext` (a third party) |
| Size | `S` up to one session, `M` two to three sessions, `L` more than three sessions |

## Milestones

| ID | Milestone | Target | Exit criteria |
|---|---|---|---|
| M1 | Close the current API batch (#34 to #38) | TBD | All five items committed on `master`, `composer check` green, release tagged |
| M2 | Second import rehearsal and freeze date | TBD | Rehearsal report accepted by the user; freeze date set |
| M3 | Remaining go-live API items | TBD | #30 and #20 coded and checked; #15 coded or moved out of go-live |
| M4 | Front end, go-live pages | TBD | The three applications serve the go-live pages of `frontend.md` against the dev API; Playwright main flows green |
| M5 | External decisions and inputs | TBD | Every `P0` task of M5 done (legal answers, SMTP, domains, contacts) |
| M6 | Production environment and go-live | TBD | `deployment-checklist.md` sections 1 to 6 ticked; smoke test passed |
| M7 | Final import and account activation | TBD | Final import written; activation emails sent to all audiences; activation report reviewed |
| M8 | After go-live backlog | — | Ordered by the user when M6 is done |
| TD | Technical debt and audits | — | Each task closed or moved into a milestone |

Dependency path to go-live: **M1 → M2 → (freeze) → M7**, in parallel **M3 → M4**, and **M5 → M6**. Go-live needs M3, M4, M5 and M6; M7 runs on the
production database after M6.

## M1: Close the current API batch

| ID | Task | Owner | Priority | Size | Depends on | Status | Done when | Source |
|---|---|---|---|---|---|---|---|---|
| M1-01 | Check #37 (company team fixes MG-P03, MG-B03, MG-B09) with the targeted tests, Pint and PHPStan | User | P1 | S | — | In review | Targeted tests, Pint and PHPStan green on the changed files | `phase-8-company-team-fixes.md` "Coded" |
| M1-02 | Commit #37 on `phase-8-activation` | Code | P1 | S | M1-01 | Ready | Commit on the branch; `phase-8-index.md` and `HANDOFF.md` updated | same |
| M1-03 | Code #38 (admin queue cards, MG-C06) | Code | P1 | S | — | Ready | Cards and tests of its section 2 written; "Coded" section recorded | `phase-8-admin-queue-cards.md` |
| M1-04 | Run the full `composer check` for #34 to #38 | User | P0 | S | M1-02, M1-03 | Ready | `composer check` green (apart from the known flaky test TD-05) | `CLAUDE.md` "Checks" |
| M1-05 | Move `master` to the branch tip (fast-forward), tag the release, delete the branch | Code | P0 | S | M1-04 | Ready | `master` and the new tag point at the same commit | `CLAUDE.md` "Git" |
| M1-06 | Push the branch, `master` and the tag to `origin` | User | P1 | S | M1-05 | Ready | `git status -sb` shows nothing ahead of `origin/master` | same |
| M1-07 | Run the new migrations on the real database and the test database | User | P0 | S | M1-05 | Ready | `migrate:status` shows no pending migration on both | `CLAUDE.md` "Database" |
| M1-08 | Run `recruitment:backfill-job-snapshots` once on the dev database | User | P1 | S | M1-07 | Ready | Command finished without errors | `deployment-checklist.md` 3 |
| M1-09 | Refresh the API docs cache (`scramble:clear`, `scramble:cache`) | Code | P1 | S | M1-05 | Ready | `/docs/api` shows the new routes | `CLAUDE.md` "Live site" |

## M2: Second import rehearsal and freeze date

| ID | Task | Owner | Priority | Size | Depends on | Status | Done when | Source |
|---|---|---|---|---|---|---|---|---|
| M2-01 | Take a fresh copy of the karir.pkp database and files folder for the rehearsal | User | P0 | S | M1-07 | Ready | Copy available; `KARIR_PKP_FILES_ROOT` points at it | `phase-1-migration.md` |
| M2-02 | Run the second rehearsal on a new rehearsal database (covers #34, #35, #36) | User | P0 | M | M2-01 | Blocked (M2-01) | Dry run and `--write` both finish; report saved | `deployment-checklist.md` 3, HANDOFF "Operational facts" |
| M2-03 | Compare the report with the 2026-10-13 rehearsal and list the differences | Code | P0 | S | M2-02 | Blocked (M2-02) | Differences explained or turned into tasks | `phase-1-migration.md` |
| M2-04 | Collect the missing legacy avatar and resume files (964 avatars and 3 resumes were missing) | User | P1 | S | — | Ready | Files present under the import root, or accepted as lost | `deployment-checklist.md` 3 |
| M2-05 | Approve the unmatched masters review and the groups map | User | P0 | S | — | Ready | `masters-unmatched-review.csv` reviewed; `--map=groups` file approved | `/home/jobportal/import/` |
| M2-06 | Set the freeze date of the final import | User | P0 | S | M2-03, M2-05 | Blocked (M2-03) | Date recorded in `HANDOFF.md` and `phase-1-migration.md` | `phase-8-multi-persona-order.md` |
| M2-07 | Drop the rehearsal databases no longer needed | User | P2 | S | M2-06 | Not started | Only the kept rehearsal database remains | — |

## M3: Remaining go-live API items

Only the items allowed by `decisions.md` section 5 are coded until the front end catches up.

| ID | Task | Owner | Priority | Size | Depends on | Status | Done when | Source |
|---|---|---|---|---|---|---|---|---|
| M3-01 | Code #30, queue watch (FG16) | Code | P0 | M | M1-05 | Ready | Section 9 coded and checked; "Coded" section recorded | `phase-8-platform-admin.md` 9 |
| M3-02 | Code #20, job posting, including jobs with several locations in search, matching, alerts and recommendations | Code | P0 | L | M1-05 | Ready | All sections coded and checked | `phase-8-job-posting.md` |
| M3-03 | Get the answer to legal question 13 (restricted candidates) | Legal | P0 | — | M5-01 | Blocked (M5-01) | Answer recorded in `legal-review.md` | `legal-review.md` |
| M3-04 | Code #14, rejections | Code | P1 | M | M3-03 | Blocked (M3-03) | Coded and checked | `phase-8-rejections.md` |
| M3-05 | Code #15, candidate experience with the agenda (section 5) | Code | P1 | M | M3-04 | Blocked (M3-04) | Coded and checked | `phase-8-candidate-experience.md` |
| M3-06 | Decide whether #15 stays a go-live item if M3-03 is late | User | P1 | S | — | Ready | Decision recorded in `decisions.md` section 5 | `decisions.md` 5 |
| M3-07 | Code the endpoints the front end asks for: the team `.ics` link (12.5) and the `my_scoring` list (8.5) | Code | P0 | S | M1-05 | Ready | Endpoints coded, checked and in the API docs | `frontend.md` 8.5, 12.5 |
| M3-08 | Code the Indonesian API translations (validation messages, attribute names, problem `detail`) | Code | P1 | M | M7-03 | Blocked (after the import) | `lang/id` complete; front end shows Indonesian errors | `phase-8-api-translations.md`, `frontend.md` 10 |
| M3-09 | Tag the go-live API release | Code | P0 | S | M3-01, M3-02, M3-07 | Blocked | `composer check` green; tag pushed by the user | `CLAUDE.md` "Git" |

## M4: Front end, go-live pages

Working folder `/usr/local/src/jobportal-frontend/onesis-talent` (empty). Builds are run by the user. Coding waits for the import rehearsal
(`frontend.md` section 1), so M4 starts after M2-02.

| ID | Task | Owner | Priority | Size | Depends on | Status | Done when | Source |
|---|---|---|---|---|---|---|---|---|
| M4-01 | Decide the remaining open front-end points: client IP behind the Nuxt proxy (5.2), complex screens (11.5), texts (11.6) | User | P0 | S | — | Ready | Answers recorded in `frontend.md` | `frontend.md` 5.2, 11.5, 11.6 |
| M4-02 | Set up the pnpm workspace: `apps/public`, `apps/backoffice`, `apps/admin`, `packages/ui`, `packages/api`, `packages/i18n` | Code | P0 | M | M2-02 | Blocked (M2-02) | Workspace installs; lint, `vue-tsc` and Vitest run empty | `frontend.md` 10 |
| M4-03 | Build `packages/api`: generated types from `api.json`, typed client, problem parser, stale-types check | Code | P0 | M | M4-02 | Blocked | Client calls the dev API; parser maps every problem code | `frontend.md` 10 |
| M4-04 | Build `packages/ui`: Able Pro theme, layouts, shared components | Code | P0 | L | M4-02 | Blocked | Layouts of 11.1 and components of 11.7 available | `frontend.md` 6, 11 |
| M4-05 | Build `packages/i18n`: `id` and `en` messages, notification wording and targets | Code | P0 | M | M4-02 | Blocked | Coverage tests of 11.6 and 12.4 pass | `frontend.md` 10, 12.4 |
| M4-06 | Build the public application: public pages (7.1), account pages (7.2), job seeker area (7.3), SEO (11.4) | Code | P0 | L | M4-03, M4-04, M4-05 | Blocked | Pages of 7.1 to 7.3 served; apply flow passes in Playwright | `frontend.md` 7, 11.4 |
| M4-07 | Build the back office: account pages (8.1), recruitment (8.2), ERP pages (8.3), link entry (12.3) | Code | P0 | L | M4-03, M4-04, M4-05, M3-07 | Blocked | Pages of 8.1 to 8.3 served; pipeline move passes in Playwright | `frontend.md` 8, 12.3 |
| M4-08 | Build the admin application: admin panel (9.1) and psychologist area (9.2), including the activation report page D29 | Code | P0 | L | M4-03, M4-04, M4-05 | Blocked | Pages of 9.1 and 9.2 served; admin two-factor passes in Playwright | `frontend.md` 9, `phase-8-activation.md` 13 |
| M4-09 | Prepare the build and release commands (heap limit, one application at a time, release folder and `current` symlink) | Code | P0 | S | M4-06 | Blocked | Commands written in the front-end README | `frontend.md` 10 |
| M4-10 | Build and deploy the three applications on the dev domains | User | P0 | M | M4-09 | Blocked | `karir`, `preview` and `admin-karir` dev domains serve the builds | `frontend.md` 4, 10 |
| M4-11 | Set the systemd units and nginx server blocks for the two Nuxt services and the static admin site | User | P0 | S | M4-09 | Blocked | Both services start on boot; admin CSP as designed | `frontend.md` 10 |
| M4-12 | Point the API link settings at the front-end paths | User | P0 | S | M4-10 | Blocked | Every link of the link contract opens the right page | `frontend.md` 12.2 |
| M4-13 | Lift the API coding limit | User | P1 | S | M4-10 | Blocked | Recorded in `decisions.md` section 5 | `decisions.md` 5 |

## M5: External decisions and inputs

| ID | Task | Owner | Priority | Size | Depends on | Status | Done when | Source |
|---|---|---|---|---|---|---|---|---|
| M5-01 | Send the legal review (questions 1 to 22) to counsel | User | P0 | S | — | Ready | Sent; date recorded in `legal-review.md` | `legal-review.md` |
| M5-02 | Get the answers to the go-live questions (1 to 7, 10, and question j of the activation design) | Legal | P0 | — | M5-01 | Blocked (M5-01) | Answers recorded; affected designs updated | `legal-review.md` 0 |
| M5-03 | Decide `job_seeker.candidate_sensitive_filters_enabled` after the answer | User | P1 | S | M5-02 | Blocked | Admin setting set | `deployment-checklist.md` 7 |
| M5-04 | Deliver the legacy security report (S1 to S3, critical) to the karir.pkp maintainers | User | P0 | S | — | Ready | Delivery confirmed | `docs/analysis/legacy-security-report.md` |
| M5-05 | Choose the SMTP provider | User | P0 | S | — | Ready | Provider and sending domain chosen | HANDOFF "Items waiting for the user" |
| M5-06 | Code the provider's webhook parser `EmailEventParser` (bounces and complaints) | Code | P0 | S | M5-05 | Blocked (M5-05) | Coded and checked | `phase-8-platform-admin.md` 6 |
| M5-07 | Give the support contact (`platform.support_email`, optional WhatsApp) and the public contact details | User | P0 | S | — | Ready | Values recorded; settings set | `phase-8-activation.md`, `frontend.md` 11.6 |
| M5-08 | Decide the production domains and the front-end origins (`CORS_ALLOWED_ORIGINS`) | User | P0 | S | — | Ready | Domains recorded in `frontend.md` 4 | `frontend.md` 4 |
| M5-09 | Arrange the SIP trunk with one DID for phone interviews | User | P1 | S | — | Ready | Trunk credentials available | `phase-7-tele-interview.md` |
| M5-10 | Start Meta Business verification and the WhatsApp template approvals (for #33 after go-live) | User | P2 | S | — | In progress | Verification and templates approved | `phase-8-whatsapp.md` 3 |

## M6: Production environment and go-live

Each task is a section of `deployment-checklist.md`; tick the section there, then here.

| ID | Task | Owner | Priority | Size | Depends on | Status | Done when | Source |
|---|---|---|---|---|---|---|---|---|
| M6-01 | Prepare the server and PHP (section 1) | User | P0 | S | — | Not started | Section 1 ticked | `deployment-checklist.md` 1 |
| M6-02 | Fill the production `.env` (section 2), including `QUEUE_CONNECTION=database`, `CACHE_STORE=redis`, `REDIS_CLIENT=predis` | User | P0 | S | M5-05, M5-08 | Blocked | Section 2 ticked | `deployment-checklist.md` 2 |
| M6-03 | Rename infrastructure from `jobportal` to `onesis` and separate the live site from the working folder | User | P0 | M | — | Not started | Live site no longer served from the working folder | `naming.md` 3 |
| M6-04 | Install Janus and coturn (video interviews) | User | P1 | M | M6-01 | Not started | Video room opens between two browsers | `deploy/janus/README.md` |
| M6-05 | Install Asterisk and the voice listener (phone interviews) | User | P1 | M | M5-09, M6-01 | Blocked | Test call recorded | `deploy/asterisk/README.md` |
| M6-06 | Prepare the database (section 3, before the import): back up, migrate, seed, sync permissions | User | P0 | S | M3-09 | Blocked | Steps ticked | `deployment-checklist.md` 3 |
| M6-07 | Create the first admin and at least a second `super_admin`; create the other admin roles | User | P0 | S | M6-06 | Blocked | Two active `super_admin` accounts with TOTP | `phase-8-admin-console.md` |
| M6-08 | Create at least one required verification document type and review the job moderation settings | User | P0 | S | M6-07 | Blocked | Document type exists; settings reviewed | `deployment-checklist.md` 3 |
| M6-09 | Start the processes (section 4): scheduler, queue workers, Reverb if used, caches | User | P0 | S | M6-06 | Blocked | `supervisorctl status` shows all workers running | `deployment-checklist.md` 4 |
| M6-10 | Set `platform.operations_email` and an outside uptime monitor on `GET /api/v1/health/scheduler` | User | P0 | S | M6-09 | Blocked | Monitor alerts on a stopped scheduler | `deployment-checklist.md` 4 |
| M6-11 | Configure nginx and the edge (section 5), consider `limit_req` | User | P1 | S | M6-01 | Not started | Section 5 ticked | `deployment-checklist.md` 5 |
| M6-12 | Run the smoke test (section 6) | User | P0 | S | M6-02 to M6-11, M4-10 | Blocked | Section 6 ticked | `deployment-checklist.md` 6 |
| M6-13 | Go/no-go meeting and switch | User | P0 | S | M6-12, M5-02 | Blocked | Decision recorded; DNS points at production | — |

## M7: Final import and account activation

| ID | Task | Owner | Priority | Size | Depends on | Status | Done when | Source |
|---|---|---|---|---|---|---|---|---|
| M7-01 | Take the copy of the karir.pkp database and files at the freeze | User | P0 | S | M2-06, M6-06 | Blocked | Copy taken on the freeze date | `deployment-checklist.md` 3 |
| M7-02 | Dry run of the final import and comparison with the rehearsal report | User | P0 | S | M7-01 | Blocked | Report matches the rehearsal or differences are explained | same |
| M7-03 | Write the final import (`legacy:import --all --write` with the approved maps) | User | P0 | S | M7-02 | Blocked | Import finished; report saved | same |
| M7-04 | Run the post-import commands: `analytics:rollup --backfill`, `recruitment:build-application-facets` | User | P0 | S | M7-03 | Blocked | Both finished; report read | same |
| M7-05 | Set ISO codes for the languages created by the import | User | P2 | S | M7-03 | Blocked | Needed languages have real codes | same |
| M7-06 | Set the activation settings and `.env` links (`JOB_SEEKER_ACTIVATION_URL`, `COMPANY_ACTIVATION_URL`, `JOB_SEEKER_SIGN_IN_URL`, `identity.activation_job_seekers_not_before`) | User | P0 | S | M4-10, M5-07 | Blocked | Values set; links open the right pages | `phase-8-activation.md` 3, 14 |
| M7-07 | Send the staff test of the activation email | User | P0 | S | M5-06, M7-06 | Blocked | Staff receive the email and activate | `deployment-checklist.md` 3 |
| M7-08 | Send the activation emails to company owners | User | P0 | S | M7-07 | Blocked | Batch sent under the bounce and complaint limits | same |
| M7-09 | Send the job seeker pilot (200) | User | P0 | S | M7-08 | Blocked | Pilot sent; bounce under 2%, complaints under 0.1% | same |
| M7-10 | Send the job seeker batches (500 a day) and the 7-day reminders | User | P1 | M | M7-09 | Blocked | All audiences sent; reminders sent | same |
| M7-11 | Review the activation report weekly and fix the support cases | User | P1 | S | M7-08 | Blocked | Report reviewed; open cases handled | `phase-8-activation.md` 10 |

## M8: After go-live backlog

Order to be set by the user when M6 is done. Items marked "Design" need a design session before coding.

| ID | Task | Owner | Priority | Depends on | Status | Source |
|---|---|---|---|---|---|---|
| M8-01 | Code #39, account and public items (A14 after #26) | Code | P2 | front end asks for it | Ready | `phase-8-account-and-public-items.md` |
| M8-02 | Code #40, recruitment closure | Code | P2 | front end asks for it | Ready | `phase-8-recruitment-closure.md` |
| M8-03 | Code #41, approval escalation | Code | P2 | front end asks for it | Ready | `phase-8-approval-escalation.md` |
| M8-04 | Code #42, concurrent edits (front end sends `If-Match` first) | Code | P2 | M4 | Ready | `phase-8-concurrent-edits.md` |
| M8-05 | Code #43, reference merge | Code | P2 | — | Ready | `phase-8-reference-merge.md` |
| M8-06 | Code #44, company support access | Code | P2 | — | Ready | `phase-8-company-support-access.md` |
| M8-07 | Code #12 section 3 (acceptance evidence) and design the offer approval of internal tenants | Design, Code | P2 | legal question 12 | Blocked | `phase-8-offer-letters.md` |
| M8-08 | Design #13, the hand-off to Employee with pre-boarding documents (FG5) | Design | P2 | — | Not started | `erp-direction.md` 3 |
| M8-09 | Code #22, assessment automation | Code | P2 | — | Ready | `phase-8-assessment-automation.md` |
| M8-10 | Code #23, provider tools (before the first partner institution) | Code | P2 | legal question 11 | Blocked | `phase-8-provider-tools.md` |
| M8-11 | Code #24, provider institutions | Code | P2 | M8-10, legal questions 17 and 19 | Blocked | `phase-8-provider-institutions.md` |
| M8-12 | Code #25, job seeker privacy sections 3 and 4 | Code | P2 | — | Ready | `phase-8-jobseeker-privacy.md` |
| M8-13 | Code #26, company admin section 3 | Code | P2 | legal question 15 | Blocked | `phase-8-company-admin.md` |
| M8-14 | Code #31, manual candidates (FG9) | Code | P2 | legal question 20 | Blocked | `phase-8-manual-candidates.md` |
| M8-15 | Code #32, pre-employment checks (FG12) | Code | P2 | legal question 21, M3-05 | Blocked | `phase-8-pre-employment-checks.md` |
| M8-16 | Code #33, WhatsApp notices (FG6) | Code | P2 | M5-10, legal question 22, M8-15 | Blocked | `phase-8-whatsapp.md` |
| M8-17 | Code the user management design | Code | P2 | M7-03 | Blocked | `phase-8-user-management.md` |
| M8-18 | Code the open parts left inside coded items (`provider_reviews` of #8, assessment summary of #9 and #21, skip code of #18, group read rule of #27, one-click opt-out of team emails) | Code | P2 | M8-10, M3-04 | Blocked | `phase-8-index.md` "Coded" |
| M8-19 | Decide the scope of the remaining benchmark gaps G1 to G21 and the reopened R8 (tele-interview benchmark); G20 is before go-live | User | P2 (G20: P1) | — | Ready | `gap-analysis-round-2.md` |
| M8-20 | Frozen marketplace features: job discovery sections 6 and 7, company directory, company registrations, training course detail | Code | P3 | ERP TA core, M7-03 | Blocked | `phase-8-index.md` "Frozen" |

## TD: Technical debt and audits

| ID | Task | Owner | Priority | Depends on | Status | Source |
|---|---|---|---|---|---|---|
| TD-01 | Performance audit step 4 (measure) | Code | P1 | M7-03 (real data) | Blocked | `phase-5-performance-audit.md` |
| TD-02 | Performance audit step 6 (query-count tests); drop `jobportal_v2_perf` when done | Code | P2 | TD-01 | Blocked | same |
| TD-03 | Architecture review findings 7, 8 and 20 | Code | P2 | — | Ready | `decisions.md` 6 |
| TD-04 | Architecture review findings 9 and 21 (refactor) | Code | P2 | M7-03 | Blocked | `phase-5-architecture-review.md` |
| TD-05 | Fix the flaky `'42'` assertion of `TestAuthoringTest` | Code | P1 | — | Ready | HANDOFF "Open work" |
| TD-06 | Clear talent pool notes immediately (not nightly): needs a JobSeeker trigger | Design, Code | P2 | — | Not started | HANDOFF "Open work" |

## Risks

| Risk | Impact | Mitigation | Owner |
|---|---|---|---|
| Legal answers arrive late (M5-02) | Go-live items #14, #15 and sensitive filters stay blocked | Send the review now (M5-01); decide M3-06 so go-live does not wait for #15 | User |
| The freeze date slips | Front end and activation are built against old data | Run M2 right after M1; keep the import add-only so a later run is safe | User |
| Server memory (about 1 GB free) during Nuxt builds | Build fails or slows the live API | Heap limit and swap, one application at a time, build at low traffic (M4-09) | User |
| No SMTP provider | No activation emails, no bounce suppression | Choose it early (M5-05); the parser is small (M5-06) | User |
| The live dev site is the working folder | A new migration breaks the scheduler until `migrate` | Migrate right after each merge (M1-07); separate the live site (M6-03) | User |
