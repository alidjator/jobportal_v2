# Phase 8: index of the design documents

One line per document of `docs/design/phase-8-*.md`, with its status. **Next coding** is the order agreed with the user; a coding session takes the
first open item, codes it, records a "coded" section in its document and updates this list. `docs/HANDOFF.md` points here instead of listing the
documents. The order was changed for the ERP direction on 2026-10-15 (confirmed by the user; `erp-direction.md` section 6). Items marked **new** have no
design document yet: they need a design session before a coding session can take them.

ERP governance (platform admin, reference data, group scope of settings and audit): `erp-governance.md`, decided 2026-10-15; coded with #5.

## Coded

| Document | Status |
|---|---|
| `phase-8-admin-console.md` | coded 2026-10-13 (before go-live) |
| `phase-8-company-permissions.md` | permissions and team coded and tested 2026-10-14 (migrations `000136`, `000137`) |
| `phase-8-jobseeker-privacy.md` sections 1 and 2 | coded and checked 2026-10-15 (migration `000139`), committed; sections 3 and 4 wait (#25) |
| `phase-8-company-admin.md` sections 1, 2 and 4 | coded and checked 2026-10-15 (migration `000140`), committed; `offer.letter_downloaded` waits for #12, section 3 for #26 |
| `phase-8-job-discovery.md` sections 1 to 5 | coded and checked 2026-10-15 (migration `000142`); sections 6 and 7 wait (after go-live) |
| `phase-8-platform-admin.md` sections 1 to 4 | coded and checked 2026-10-15 (migration `000141`), committed; the internal-tenant exemption of section 2 waits for #5, sections 5 to 8 for #17 |

## Next coding (in this order)

| # | Document | Waits for |
|---|---|---|
| 4a | `naming.md` section 1 (problem `type` to `urn:onesis:error:<code>`) | **before go-live**; small, mechanical; may be done in any coding session before go-live |
| 5 | `erp-organization.md` (Organization context and internal tenants) | designed 2026-10-15 |
| 6 | `erp-requisitions.md` (manpower planning, requisitions, generic approvals) | designed 2026-10-15; after #5 |
| 7 | `erp-sso.md` (Identity as the IAM of the ERP: two-factor and token lifetime of internal tenants) | designed 2026-10-15; after #2 and #5 |
| 8 | `phase-8-recruiter-workday.md` | team preferences used by withdrawals and provider tools |
| 9 | `phase-8-hiring-managers.md` | tasks and team preferences of the recruiter workday; requisitions are now in scope through #6 |
| 10 | `phase-8-interview-calendar.md` | |
| 11 | `phase-8-interviewers.md` | `.ics` of the interview calendar; shares the access rule of the hiring managers |
| 12 | `phase-8-offer-letters.md` | acceptance evidence: `legal-review.md` question 12; offer approval of internal tenants through #6 |
| 13 | `phase-8-hiring.md`, then **new** hand-off to Employee | the hires export is the bridge to the existing HRIS; the hand-off needs a design session (`erp-direction.md` section 3) |
| 14 | `phase-8-rejections.md` | restricted candidates: question 13 |
| 15 | `phase-8-candidate-experience.md` | the pending rejections of the rejections design carry the candidate message |
| 16 | `phase-8-withdrawals.md` | |
| 17 | `phase-8-platform-admin.md` sections 5 to 8 | section 6 (email suppression) **before the reset emails to the imported accounts**, with the SMTP provider, whatever its place here |
| 18 | `phase-8-talent-pool.md` | |
| 19 | `phase-8-applicant-search.md` | |
| 20 | `phase-8-job-posting.md` | |
| 21 | `phase-8-interview-results.md` | |
| 22 | `phase-8-assessment-automation.md` | |
| 23 | `phase-8-provider-tools.md` | needed before the first partner institution, not for go-live; question 11 |
| 24 | `phase-8-provider-institutions.md` | after the provider tools; before the first partner institution; question 17 |
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
