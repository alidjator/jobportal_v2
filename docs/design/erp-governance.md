# ERP governance: platform admin, reference data and controls (open, recorded 2026-10-15, nothing decided)

Recorded from the user's questions of 2026-10-15 ("is the platform admin still relevant?" and "where do moderation, control and reference data come
from for the ERP?"). Every item below is a **proposal** waiting for the user's decision; nothing is designed for coding yet. Builds on
`erp-direction.md`, `erp-organization.md`, `erp-requisitions.md`, `erp-sso.md` and `phase-8-platform-admin.md`. Plans, billing, payments, orders and
invoices stay out of scope.

## Decisions

None yet. The questions are in [Open questions](#5-open-questions).

## 1. Platform admin after the ERP (proposal)

The platform admin stays and becomes the **ONESIS operator**, not the customer's admin. Three layers of administration:

| Layer | Who | Work |
|---|---|---|
| Platform admin (`identity_admins`, guard `admin`) | the ONESIS operator team | portal moderation (`jobs.manage`, `reviews.moderate`, company verification), reference data, global settings, email templates, legal documents, audit, creating groups and linking tenants (`erp-organization.md` section 7), two-factor resets, customer onboarding |
| Group admin (`group.*` permissions) | the customer's group HR, for example the PKP holding | organisation structure, manpower plans, approval flows, group roles |
| Company admin | one per tenant (legal entity) | team, permissions, jobs, pipeline |

The 16 platform admin permissions of today (`analytics.view`, `assessment_library.manage`, `assessment_providers.manage`, `audit_logs.view`,
`campaigns.manage`, `companies.manage`, `cv_templates.manage`, `dashboard.view`, `email_templates.manage`, `faqs.manage`, `jobs.manage`,
`legal_documents.manage`, `newsletter.manage`, `reference_data.manage`, `reviews.moderate`, `settings.manage`) are all operator work; none overlaps
the group roles.

### 1.1 No default access to customer ERP data (proposal)

- Manpower plans, salary budgets, requisitions, approvals and (later) employee data belong to the customer. No platform admin permission reads
  them; the ERP data stays out of the admin permission catalog. This holds for the PKP group too: the IT team running ONESIS does not read the
  holding's HR budgets by default.
- When support needs to look, a group admin grants **temporary support access** (a time limit, revocable, every read audited and visible to the
  group).

### 1.2 Internal tenants and portal moderation (proposal; decide before coding #3)

- `phase-8-platform-admin.md` section 2 sends the jobs of an **unverified** company to admin review. An internal tenant is linked to a legal entity
  and its jobs start from approved requisitions, so it is proposed to count an internal tenant as **verified**: its jobs skip the admin review queue.
- Public job reports, company reviews and chat reports still apply to every tenant, because the jobs of internal tenants are public on ONESIS Career.

## 2. Reference data: three layers (proposal)

| Layer | Owner | Today | ERP later |
|---|---|---|---|
| Global (platform) | platform admin (`reference_data.manage`) | BPS locations, education levels, `job_types`, industries, skills, job roles, nationalities | national holidays, banks, BPJS and PPh 21 rates (regulation, **effective-dated**), marital status, religion (sensitive under UU PDP) |
| Group (customer) | group admin (`group.structure.manage`) | none | legal entities, units, unit types, positions, grades, cost centres (designed); later leave types, the group's work calendar, employment statuses |
| Tenant | company admin | pipeline stages, screening question bank, interview kits, tags | approval flow overrides (designed) |

Proposed rules:

- Global data is **read only** for groups. A group that needs more values (for example a job role of its own) **adds** them in its own layer; it
  never edits a global value.
- Regulatory data (rates, national holidays) is always maintained by the platform, since one change applies to every customer.

## 3. Controls of the ERP: governance, not moderation (proposal)

The ERP has no public content, so it needs no moderation. Its controls:

| Control | Source | State |
|---|---|---|
| Who may do what | company permissions and group roles | designed |
| Business decisions | the Approval context | designed |
| Trail | the audit trail | **gap**: `company_id` is planned in #2, there is no `group_id`, so a group admin has no group activity log |
| Policy | settings | **gap**: the settings catalog scopes values per company only; `erp-sso.md` had to store a group setting as a column. Proposed: a `group` scope with the order company → group → global → default |
| Operator control | platform admin | creating and suspending groups, linking tenants, temporary support access (section 1.1) |

## 4. Effect on designs already written (if the proposals are accepted)

- `erp-sso.md` section 5: `group.require_two_factor` moves from a column on `organization_groups` to the group scope of the settings.
- `erp-requisitions.md`: the fiscal year start month moves to the group scope of the settings in the same way.
- `phase-8-company-admin.md` section 2 (company activity log): the audit entry gains `group_id` next to `company_id`.
- `phase-8-platform-admin.md` section 2: the review rule reads "verified or internal".
- `erp-organization.md`: a group status (`active`, `suspended`) and its effect on the group's tenants.

## 5. Open questions

1. Three layers of reference data with "global is read only, groups add" (section 2).
2. A `group` scope in the settings, and moving the group settings of `erp-sso.md` and `erp-requisitions.md` there (section 3).
3. `group_id` on audit entries and a group activity log for group admins (section 3).
4. No default platform admin access to customer ERP data, with temporary support access granted by the group (section 1.1).
5. Internal tenants counted as verified, skipping the admin review of jobs (section 1.2); **decide before coding #3**.
