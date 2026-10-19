# Phase 8: index of the design documents

One line per document of `docs/design/phase-8-*.md`, with its status. **Next coding** is the order agreed with the user; a coding session takes the
first open item, codes it, records a "coded" section in its document and updates this list. `docs/HANDOFF.md` points here instead of listing the
documents. The order was changed for the ERP direction on 2026-10-15 (confirmed by the user; `erp-direction.md` section 6). Items marked **new** have no
design document yet: they need a design session before a coding session can take them.

ERP governance (platform admin, reference data, group scope of settings and audit): `erp-governance.md`, decided 2026-10-15; coded with #5.

## Coded

Everything below passed the full `composer check` (up to #17: tag `v0.6.0`; #18: tag `v0.7.0`; #19: 2026-10-19). Parts still open inside a coded item are listed under "Next coding".

| Document | Status |
|---|---|
| `phase-8-admin-console.md` | coded 2026-10-13 (before go-live) |
| `phase-8-company-permissions.md` | permissions and team coded and tested 2026-10-14 (migrations `000136`, `000137`) |
| `phase-8-jobseeker-privacy.md` sections 1 and 2 | coded and checked 2026-10-15 (migration `000139`), committed; sections 3 and 4 wait (#25) |
| `phase-8-company-admin.md` sections 1, 2 and 4 | coded and checked 2026-10-15 (migration `000140`), committed; `offer.letter_downloaded` coded with #12, section 3 waits for #26 |
| `erp-organization.md` (#5) | parts A and B (`000143`, `000144`), C1 to C3 (group log, group settings, support access; `000145`-`000147`) and D (group read rule) coded 2026-10-16 |
| `naming.md` section 1 | coded 2026-10-15 (no migration; the problem `type` is now `urn:onesis:error:<code>`), checked |
| `phase-8-job-discovery.md` sections 1 to 5 | coded and checked 2026-10-15 (migration `000142`); sections 6 and 7 wait (after go-live) |
| `phase-8-platform-admin.md` sections 1 to 4 | coded and checked 2026-10-15 (migration `000141`), committed; the internal-tenant exemption of section 2 came with #5 (`erp-organization.md`, tenant link); sections 5 to 8 for #17 |
| `erp-sso.md` (#7) | coded 2026-10-16 (no migration) |
| `erp-requisitions.md` (#6) | Approval context A, B1, B2 (`000148`-`000150`), workforce plans (`000151`) and requisitions with the job rule (`000152`) coded 2026-10-16 |
| `phase-8-interview-calendar.md` (#10) | sections 1 to 6 coded 2026-10-17 (`000155`-`000157`; section 5 no migration) |
| `phase-8-recruiter-workday.md` (#8) | sections 1, 2, 4 (`000153`), 5 (with #10 section 6, `000157`) and section 3 parts A (audiences, `team_daily_digest`, the digest) and B (`team_interview_changes`, `team_offer_answers`, `team_new_applications`, `team_scoring`) coded 2026-10-17; open: `provider_reviews` (waits for #23), the one-click opt-out of team emails (deferred) |
| `phase-8-hiring-managers.md` (#9) | sections 1 and 2 (`000154`) and 3 (`000162`; part A: requests, lists, page, answers, lock, close; part B: notifications, `team_reviews`, scheduler, tasks, `reviews[]`, `review_summary`, erasure) coded 2026-10-17; open: the assessment summary on the review card (waits for #23) |
| `phase-8-interviewers.md` (#11) | complete: section 1 (`000158`), sections 3 and 4 (`000160`) and section 2 (`000161`: invitations with `.ics`, the answer, the cancellation; tasks `my_rsvp` and `panel_declined`, `filter[rsvp]`, calendar and pipeline badges) coded 2026-10-17 |
| `phase-8-offer-letters.md` (#12) | sections 1 (terms), 2 (letter PDF, own PDF) and 4 (one email per move) coded 2026-10-18 (`000165`), with the terms columns of the hires export; open: see #12 below |
| `phase-8-hiring.md` (#13) | sections 1 (`000163`, `000164`), 2 (filled jobs) and 3 (hires export, the bridge to the existing HRIS) coded 2026-10-17; open: see #13 below |
| `phase-8-withdrawals.md` (#16) | complete: parts A to C coded 2026-10-17 (`000159`) |
| `phase-8-platform-admin.md` sections 5 to 8 (#17) | coded 2026-10-18 (`000166` to `000169`), with the admin account view (`users.view`) and the mass reset command; section 6 (email suppression) is in before the reset emails to the imported accounts; open: the front end and the SMTP provider's webhook parser `EmailEventParser` (waits for the provider) |
| `phase-8-talent-pool.md` (#18) | coded 2026-10-18 (`000170`, `000171`): consent when applying, saving applicants, invitations from the pool (single and bulk), the richer list and the entry page; checked (`composer check` green, 3182 tests); open: the skip code `candidate-restricted` (waits for #14) |
| `phase-8-applicant-search.md` (#19) | coded 2026-10-19 (`000172`), committed `f760853` on `master`, checked (`composer check` green, 3205 tests): facet table and its command, keyword search, filters and sorts on both lists, `GET company/applications`; open: run `recruitment:build-application-facets` after the final import; interpretations to confirm are in its "Coded" section |

## Next coding (in this order)

| # | Document | Waits for |
|---|---|---|
| 12 | `phase-8-offer-letters.md` section 3 (acceptance evidence) and offer approval of internal tenants through #6 | section 3: `legal-review.md` question 12; the approval needs a design |
| 13 | **new** hand-off to Employee (after `phase-8-hiring.md`) | a design session (`erp-direction.md` section 3) |
| 14 | `phase-8-rejections.md` | restricted candidates: question 13 |
| 15 | `phase-8-candidate-experience.md` | #14: the pending rejections of the rejections design carry the candidate message |
| 27 | **new** `company-locations.md`: several work locations per tenant (`company_locations`: head office, branches, sites), the `locations` API shape of companies, jobs and interview places, the current company address as the main location, one main location per imported company; interview places and requisitions may point to a location | **before the front end and before the final legacy import run** (decided 2026-10-17, `erp-direction.md` section 9) |
| 28 | job `locations` array in the API, filled with one item from today's province and city | after #27, before the front end; jobs with several locations come with #20 |
| 20 | `phase-8-job-posting.md` | also jobs with several locations of `company_locations` (search, matching, alerts, recommendations; from #27, decided 2026-10-17) |
| 21 | `phase-8-interview-results.md` | |
| 22 | `phase-8-assessment-automation.md` | |
| 23 | `phase-8-provider-tools.md` | needed before the first partner institution, not for go-live; question 11 |
| 24 | `phase-8-provider-institutions.md` | after the provider tools; before the first partner institution; question 17; section 8 (in-house psychologists, designed 2026-10-16) also needs `erp-organization.md` (#5) and the entitlements of `self-hosted.md` section 8; question 19 |
| 25 | `phase-8-jobseeker-privacy.md` sections 3 and 4 | |
| 26 | `phase-8-company-admin.md` section 3 | retention period: question 15 |

## Frozen (marketplace features; after the ERP TA core, not dropped)

| Document | Note |
|---|---|
| `phase-8-job-discovery.md` sections 6 and 7 | section 6 uses the review rule of `phase-8-platform-admin.md` section 2 |
| `phase-8-company-directory.md` | also after the legacy import |
| `phase-8-company-registrations.md` | also after the legacy import |
| `phase-8-training-course-detail.md` | also after the legacy import |

## After the legacy import

| Document | Note |
|---|---|
| `phase-8-api-translations.md` | coded after the legacy import |
| `phase-8-user-management.md` | coded after the legacy import |

Related, outside phase 8: time-to-fill is in `phase-4-business-intelligence.md` and assessment Part 7 in `phase-6-assessments.md` (both coded
2026-10-14, migration `000138` for Part 7; Part 7 auto reports wait for question 11).
