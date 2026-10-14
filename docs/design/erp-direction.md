# ERP direction: talent acquisition as the first phase of the company ERP (decided 2026-10-15, not coded)

The company plans an ERP; its first phase is talent acquisition (TA). This document records how the job portal relates to that ERP. It changes no
code and no schema before go-live; it adds new designs and sets a new order for `phase-8-index.md` (section 6, confirmed by the user on
2026-10-15). Plans, billing, payments, orders and invoices stay out of scope.

## Decisions

- 2026-10-15: **one codebase**: the public multi-company portal keeps running, and the ERP uses the same recruitment contexts; internal tenants of
  the PKP group get the ERP features on top (decided by the user; section 1).
- 2026-10-15: **employee sign-in through a central identity provider (IdP), step by step**: job seekers keep today's sign-in, employees sign in
  through OIDC single sign-on; the direction is fixed before go-live, the SSO itself is coded after it (decided by the user; section 2).
- 2026-10-15: **this ERP replaces the existing HRIS**: a new Employee context is built in this project as ERP phase 2 (decided by the user; section 3).
- 2026-10-15: **one tenant per legal entity**: each company of the PKP group is one internal tenant linked to one legal entity of a new
  Organization context; the holding sees across tenants through a group permission (decided by the user; section 4).
- 2026-10-15: go-live readiness stays first (agreement of 2026-10-13); none of the decisions above blocks it (section 5).
- 2026-10-15: the new phase 8 order of section 6 is applied to `phase-8-index.md` (confirmed by the user).

## 1. One codebase

- The bounded contexts that exist today stay. Marketplace features (Review, Newsletter, Campaign, Training, the public company directory,
  company registrations, following companies) are **frozen, not removed**: no new features until the ERP TA core is coded (section 6).
- A tenant (`company_companies`) is either **external** (a client company of the portal, as today) or **internal** (a legal entity of the PKP
  group). Only internal tenants get the ERP features: requisitions, approvals, the link to the organisation structure and the hand-off to Employee.
- The recruitment contexts (Job, Recruitment, interviews, offers, assessments) are shared by both kinds of tenant; ERP rules are added through
  ports and policies that check the tenant kind, never through a second copy of the recruiting logic.

## 2. Identity: central IdP, step by step

- Job seekers keep the current sign-in (email and password, Google) in the Identity context.
- Employees of internal tenants sign in through a central IdP with OIDC; every ERP module trusts the same IdP, so no module depends on the
  recruitment application for sign-in.
- Before go-live only the direction matters: the company member sign-in of `phase-8-company-admin.md` (item #2 of the phase 8 order) is coded as
  designed, and a later, additive table links a member account to its IdP subject. No column is added now.
- The two-guard model (`identity_users`, `identity_admins`) stays; how platform admins relate to the IdP is decided with the SSO design.

## 3. Employee replaces the HRIS

- A new **Employee** context (employee record, employment data, onboarding tasks) is ERP phase 2. Identity numbers (NIK), bank accounts and
  similar data live only there, under stricter access, never in Recruitment; the "out of scope" note of `phase-8-hiring.md` stays true for Recruitment.
- The hires export of `phase-8-hiring.md` is the **bridge** until Employee exists: it feeds the existing HRIS.
- A hire of an internal tenant later creates a pending employee record through a domain event (hand-off design, section 6, item 13).

## 4. Tenant per legal entity

- A new **Organization** context holds the legal entities, organisation units, positions, grades and cost centres of the PKP group.
- One internal tenant is linked to exactly one legal entity. Teams, stages and jobs stay per tenant, as today.
- The holding sees across the group's tenants through a group-level permission (designed with Organization); external tenants never see each
  other.

## 5. Effect on go-live

- No schema change is needed before go-live: the internal tenant kind, the legal-entity link and the IdP subject link are additive migrations.
- The final legacy import is not affected: legacy companies become external tenants; the PKP group's own tenants are marked internal afterwards.

## 6. New order of `phase-8-index.md` (confirmed by the user, 2026-10-15)

New designs are marked **new**; they need a design session before they can be coded. Items keep their sections and waits; `phase-8-index.md` is the
working list from now on and wins if the two differ.

| # | Item | Note |
|---|---|---|
| 1 | `phase-8-jobseeker-privacy.md` sections 1 and 2 | before go-live (unchanged) |
| 2 | `phase-8-company-admin.md` sections 1, 2 and 4 | before go-live (unchanged); IdP link stays additive (section 2) |
| 3 | `phase-8-platform-admin.md` sections 1 to 4 | before go-live (unchanged) |
| 4 | `phase-8-job-discovery.md` sections 1 to 5 | before go-live (unchanged) |
| 5 | **new** Organization context and internal tenants | legal entities, units, positions, grades, cost centres, group permission |
| 6 | **new** requisitions and a generic approval workflow | a job of an internal tenant starts from an approved requisition |
| 7 | **new** employee sign-in through the central IdP | needs the choice of IdP product |
| 8 | `phase-8-recruiter-workday.md` | |
| 9 | `phase-8-hiring-managers.md` | requisitions are now in scope through item 6 |
| 10 | `phase-8-interview-calendar.md` | |
| 11 | `phase-8-interviewers.md` | |
| 12 | `phase-8-offer-letters.md` | offer approval of internal tenants through item 6; question 12 |
| 13 | `phase-8-hiring.md`, then **new** hand-off to Employee | hires export is the bridge to the HRIS |
| 14 | `phase-8-rejections.md` | question 13 |
| 15 | `phase-8-candidate-experience.md` | |
| 16 | `phase-8-withdrawals.md` | |
| 17 | `phase-8-platform-admin.md` sections 5 to 8 | section 6 still before the reset emails, whatever its place |
| 18 | `phase-8-talent-pool.md` | |
| 19 | `phase-8-applicant-search.md` | |
| 20 | `phase-8-job-posting.md` | |
| 21 | `phase-8-interview-results.md` | |
| 22 | `phase-8-assessment-automation.md` | |
| 23 | `phase-8-provider-tools.md` | before the first partner institution; question 11 |
| 24 | `phase-8-provider-institutions.md` | question 17 |
| 25 | `phase-8-jobseeker-privacy.md` sections 3 and 4 | |
| 26 | `phase-8-company-admin.md` section 3 | question 15 |

**Frozen (after the ERP TA core, not dropped):** `phase-8-job-discovery.md` sections 6 and 7, `phase-8-company-directory.md`,
`phase-8-company-registrations.md`, `phase-8-training-course-detail.md`. **After the legacy import (unchanged):** `phase-8-api-translations.md`,
`phase-8-user-management.md`.

## 7. Still open

- The IdP product (for example Keycloak) and who runs it; how platform admins relate to it.
- Which existing HRIS application runs in production, which of its features Employee replaces (payroll with BPJS and PPh 21 is a large scope of
  its own) and whether its data is migrated.
- Requisition approval levels and who approves (by organisation unit, grade or cost centre).
- Whether hiring managers and interviewers of internal tenants are company members, employees, or both.
- Scope of the holding's group permission (read only, or acting on the tenants' applications).
