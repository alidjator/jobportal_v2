# Phase 8: index of the design documents

One line per document of `docs/design/phase-8-*.md`, with its status. **Next coding** is the order agreed with the user; a coding session takes the
first open item, codes it, records a "coded" section in its document and updates this list. `docs/HANDOFF.md` points here instead of listing the
documents.

## Coded

| Document | Status |
|---|---|
| `phase-8-admin-console.md` | coded 2026-10-13 (before go-live) |
| `phase-8-company-permissions.md` | permissions and team coded and tested 2026-10-14 (migrations `000136`, `000137`) |

## Next coding (in this order)

| # | Document | Waits for |
|---|---|---|
| 1 | `phase-8-jobseeker-privacy.md` sections 1 and 2 | **before go-live**; export boundary: question 14 |
| 2 | `phase-8-company-admin.md` sections 1, 2 and 4 | **before go-live**; the login answer changes for company members (front end) |
| 3 | `phase-8-platform-admin.md` sections 1 to 4 | **before go-live**; section 4 (console two-factor reset) is coded with #2 |
| 4 | `phase-8-recruiter-workday.md` | team preferences used by withdrawals and provider tools |
| 5 | `phase-8-hiring-managers.md` | tasks and team preferences of the recruiter workday |
| 6 | `phase-8-interview-calendar.md` | |
| 7 | `phase-8-interviewers.md` | `.ics` of the interview calendar; shares the access rule of the hiring managers |
| 8 | `phase-8-offer-letters.md` | acceptance evidence: `legal-review.md` question 12 |
| 9 | `phase-8-hiring.md` | |
| 10 | `phase-8-rejections.md` | restricted candidates: question 13 |
| 11 | `phase-8-candidate-experience.md` | the pending rejections of the rejections design carry the candidate message |
| 12 | `phase-8-withdrawals.md` | |
| 13 | `phase-8-platform-admin.md` sections 5 to 8 | section 6 (email suppression) **before the reset emails to the imported accounts**, with the SMTP provider |
| 14 | `phase-8-talent-pool.md` | |
| 15 | `phase-8-applicant-search.md` | |
| 16 | `phase-8-job-posting.md` | |
| 17 | `phase-8-interview-results.md` | |
| 18 | `phase-8-assessment-automation.md` | |
| 19 | `phase-8-provider-tools.md` | needed before the first partner institution, not for go-live; question 11 |
| 20 | `phase-8-provider-institutions.md` | after the provider tools; before the first partner institution; question 17 |
| 21 | `phase-8-jobseeker-privacy.md` sections 3 and 4 | |
| 22 | `phase-8-company-admin.md` section 3 | retention period: question 15 |

## After the legacy import

| Document | Note |
|---|---|
| `phase-8-api-translations.md` | coded after the legacy import |
| `phase-8-company-directory.md` | coded after the legacy import |
| `phase-8-company-registrations.md` | coded after the legacy import |
| `phase-8-training-course-detail.md` | coded after the legacy import |
| `phase-8-user-management.md` | coded after the legacy import |

Related, outside phase 8: time-to-fill is in `phase-4-business-intelligence.md` and assessment Part 7 in `phase-6-assessments.md` (both coded
2026-10-14, migration `000138` for Part 7; Part 7 auto reports wait for question 11).
