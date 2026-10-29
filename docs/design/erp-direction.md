# ERP direction: talent acquisition as the first phase of the company ERP (decided 2026-10-15, not coded)

The company plans an ERP; its first phase is talent acquisition (TA). This document records how the job portal relates to that ERP. It changes no
code and no schema before go-live; it adds new designs and sets a new order for `phase-8-index.md` (section 6, confirmed by the user on
2026-10-15). Plans, billing, payments, orders and invoices stay out of scope.

## Decisions

- 2026-10-15: **one codebase**: the public multi-company portal keeps running, and the ERP uses the same recruitment contexts; internal tenants of
  the PKP group get the ERP features on top (decided by the user; section 1).
- ~~2026-10-15: employee sign-in through a central identity provider (IdP), step by step~~ **changed 2026-10-15: the Identity context is the
  IAM of the ERP**, no third-party or separate IdP; all ERP modules share its sign-in (decided by the user; section 2, `erp-sso.md`).
- 2026-10-15: **this ERP replaces the existing HRIS**: a new Employee context is built in this project as ERP phase 2 (decided by the user; section 3).
- 2026-10-15: **one tenant per legal entity**: each company of the PKP group is one internal tenant linked to one legal entity of a new
  Organization context; the holding sees across tenants through a group permission (decided by the user; section 4).
- 2026-10-16: the ERP also ships as a **self-hosted edition** next to SaaS, from the same codebase (decided by the user; `self-hosted.md`).
- 2026-10-15: go-live readiness stays first (agreement of 2026-10-13); none of the decisions above blocks it (section 5).
- 2026-10-15: the new phase 8 order of section 6 is applied to `phase-8-index.md` (confirmed by the user).
- 2026-10-17: **several work locations per tenant** (`company_locations`) before the front end; contacts and addresses of users, job seekers,
  companies and legal entities stay single; employee data (several addresses, contacts, family, bank accounts, with history) follows the
  principles and the order of [section 9](#9-locations-and-employee-data-decided-2026-10-17) (decided by the user).
- 2026-10-29: **the gig service catalogue is an exception to the marketplace freeze** of section 1 (decided by the user; `gigs.md` section 11.2).

## 1. One codebase

- The bounded contexts that exist today stay. Marketplace features (Review, Newsletter, Campaign, Training, the public company directory,
  company registrations, following companies) are **frozen, not removed**: no new features until the ERP TA core is coded (section 6).
- A tenant (`company_companies`) is either **external** (a client company of the portal, as today) or **internal** (a legal entity of the PKP
  group). Only internal tenants get the ERP features: requisitions, approvals, the link to the organisation structure and the hand-off to Employee.
- The recruitment contexts (Job, Recruitment, interviews, offers, assessments) are shared by both kinds of tenant; ERP rules are added through
  ports and policies that check the tenant kind, never through a second copy of the recruiting logic.

## 2. Identity: the IAM of the ERP (changed 2026-10-15)

- **Changed 2026-10-15 (user):** the Identity context of this codebase is the IAM of the ERP; no third-party or separate IdP. The modules share
  one codebase, so one Sanctum token serves all of them. Detail in `erp-sso.md`; the bullets below are the earlier direction, kept as history.


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
- **Pre-employment documents** (KTP, NPWP, diplomas, SKCK, bank account, BPJS cards; FG5 of `docs/analysis/actor-gap-analysis.md`) are collected
  by Employee through a pre-boarding page that the new hire opens with the same account, designed with the hand-off (item 13); external tenants keep
  collecting them outside the system (decided by the user, 2026-10-19).
- The hand-off does **not** carry psychological reports or test results by default (`phase-6-assessments.md`, "ERP later"; `legal-review.md`
  question 18).

## 4. Tenant per legal entity

- A new **Organization** context holds the legal entities, organisation units, positions, grades and cost centres of the PKP group.
- One internal tenant is linked to exactly one legal entity. Teams, stages and jobs stay per tenant, as today.
- The holding sees across the group's tenants through a group-level permission (designed with Organization); external tenants never see each
  other.
- Widened on 2026-10-15 (`erp-organization.md`): the ERP serves **many customer groups** (SaaS), not only the PKP group; every Organization
  table is scoped by group.

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
| 7 | **new** employee sign-in (changed: Identity is the IAM, `erp-sso.md`) | |
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

## 8. ONESIS modules (decided 2026-10-15)

A map of the bounded contexts to the modules of ONESIS (`naming.md`), used for the front-end menus, the permission catalogs and the customer
documentation. The code stays in `src/<Context>`; nothing moves.

| Module | Contexts |
|---|---|
| **ONESIS Core** | Identity (the IAM), Platform, Notification, ReferenceData, Content, Organization, Approval, Shared |
| **ONESIS Workforce** | Workforce (manpower plans, requisitions) |
| **ONESIS Talent** | Company (team, permissions), Job, Recruitment (pipeline, interviews, offers, hires), Assessment, TalentPool, Messaging, JobInvitation, Matching, Dashboard |
| **ONESIS Career** (job seekers, public) | JobSeeker, JobAlert, Review, Newsletter, Campaign, Training |
| (internal tool) | LegacyImport |

- Workforce is a module of its own (decided by the user); Talent uses it through the requisition rule of `erp-requisitions.md` section 1. A
  customer without Workforce turns `recruitment.requisition_required` off.
- The ONESIS Career side of a Talent feature (the candidate taking a test, answering an offer, seeing their status) is a screen of Career over a
  Talent context, not a context of Career.
- ~~Whether modules can be switched on per group is not decided~~ **changed 2026-10-16:** the modules of an installation follow the purchased package
  (module entitlements, `self-hosted.md` section 8); prices and billing stay out of scope.

## 7. Still open

- ~~The IdP product and who runs it~~ settled 2026-10-15: no separate IdP (`erp-sso.md`).
- Which existing HRIS application runs in production, which of its features Employee replaces (payroll with BPJS and PPh 21 is a large scope of
  its own) and whether its data is migrated.
- ~~Requisition approval levels and who approves~~ settled 2026-10-15: flows with conditions (`erp-requisitions.md` section 4).
- ~~Whether hiring managers and interviewers of internal tenants are company members, employees, or both~~ decided 2026-10-15 (user): they stay
  **company members**; when Employee exists, a member account of an internal tenant may be linked 1:1 to an employee record (used later for
  "head of unit" approvers and inviting from the employee directory); one account, one sign-in.
- ~~Scope of the holding's group permission~~ settled 2026-10-15: manage the structure, read across tenants (`erp-organization.md` section 4).

## 9. Locations and employee data (decided 2026-10-17)

Today every contact and address is a column of its parent: one email of a user, one phone and WhatsApp and one domicile of a job seeker, one
contact email, phone and address of a company, one address of a legal entity, one province and city of a job. Only job seeker links are several.

**What changes and what stays (user, 2026-10-17):**

| Data | Decision |
|---|---|
| Work locations of a tenant (head office, branches, sites) | **several**, a new `company_locations` in Company for every tenant; the current company address becomes the main location (#27) |
| Locations of a job | **several**, pointing to `company_locations` (no copied province and city); the API returns `locations` at once (#28), the full feature comes with #20 |
| Address of a legal entity | one (its registered address) |
| Contact email and phone of a company | one public contact; a phone per location may live on `company_locations` |
| Email of a user | one (the sign-in identity) |
| Domicile, phone and WhatsApp of a job seeker | one each |
| ID card address and other HR data | never on the job seeker profile; they belong to Employee |

**Employee data principles (for the Employee design):**

- Several, by kind: addresses (`ktp`, `domicile`, `mailing`), phones, a personal email (the work email is the sign-in email of the linked
  Identity account, not copied), emergency contacts in order, family and dependants (PTKP status, BPJS Kesehatan), payroll bank accounts (one
  active), identity documents (NIK, KK, passport, driving licence, KITAS, with expiry), BPJS numbers, and placement (location, unit, position,
  manager) as history.
- One pattern for every list: `type`, `is_primary` and `valid_from` / `valid_to`; "one primary per kind" is a PostgreSQL partial unique index.
- Effective dating for addresses, bank accounts, placement and family status: HR asks what was valid on a date (tax slips, audit, BPJS).
- Tables owned by Employee, no shared polymorphic `addresses` table; only an `Address` value object in `src/Shared` and the reference regions are shared.
- Single source of truth: a hire copies name, phone and domicile from the job seeker profile once; Employee owns them afterwards, no two-way sync;
  sign-in stays with Identity, structure with Organization, work locations with Company.
- Sensitive data (personal data protection law): NIK, KK, bank accounts, family and religion (needed for the THR date) encrypted at rest, a blind
  index for NIK lookups, field-level permissions, masked in the API, every read logged; retention after leaving is a legal question.
- Self-service changes (address, bank account, family) are requests approved by HR through the Approval context, effective from a date.
- Indonesian validation: NIK of 16 digits, domestic addresses through the reference regions, foreign addresses as free text with a country.

**Order:**

| Step | Work | Depends on |
|---|---|---|
| A | #27 company locations (`company-locations.md`), #28 job `locations` in the API (`phase-8-index.md`) | before the front end and the final legacy import run |
| B | jobs with several locations inside #20 `phase-8-job-posting.md` | A; after go-live |
| C1 | answer the open HRIS questions of section 7 (current HRIS, payroll scope, data migration) | the user |
| C2 | benchmark the ERP actors (approver, workforce planner, group admin) and employee data: Mekari Talenta, SAP SuccessFactors, Workday | |
| C3 | new legal questions in `legal-review.md`: retention of employee data, family data, religion | |
| D1 | Employee core and the hand-off of #13: employee record, 1:1 link to the member account, one-time copy at hire, pending record by domain event | C1, A |
| D2 | placement with history: location, unit, position, manager | D1 |
| D3 | several addresses, phones, personal email, emergency contacts | D1 |
| D4 | sensitive data: identity documents, family and dependants, payroll bank accounts, BPJS numbers, with the protections above | D1, C3 |
| D5 | self-service changes through Approval | D3, D4 |
| D6 | migration of the old HRIS data, if C1 says so (like `LegacyImport`) | D1 to D4 |
| D7 | payroll as its own context, if C1 puts it in scope | D4, C1 |

Each step is designed in one session and coded in another.
