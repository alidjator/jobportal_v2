# ERP governance: platform admin, reference data and controls (decided 2026-10-15, not coded)

Recorded from the user's questions of 2026-10-15 ("is the platform admin still relevant?" and "where do moderation, control and reference data come
from for the ERP?"). Every question was decided on 2026-10-15 (see Decisions). Builds on
`erp-direction.md`, `erp-organization.md`, `erp-requisitions.md`, `erp-sso.md` and `phase-8-platform-admin.md`. Plans, billing, payments, orders and
invoices stay out of scope.

## Decisions

- 2026-10-15: **internal tenants count as verified**: the public card shows them verified and their jobs never enter the admin review queue of
  `phase-8-platform-admin.md` section 2 (decided by the user, question 5; [Internal tenants](#12-internal-tenants-and-portal-moderation-decided-2026-10-15)).
- 2026-10-15: **a group activity log**: audit entries gain a nullable `group_id` when #5 is coded (filled on write, backfilled from `company_id`);
  the group permission `group.audit.view` opens the log of the group and all its internal tenants, without IP address or user agent (decided by
  the user, question 3; [Controls](#3-controls-of-the-erp-governance-not-moderation)).
- 2026-10-15: **a `group` scope in the settings**: read order company → group → global → default; each catalog entry names its allowed scopes;
  `group.settings.manage` changes group values (audited); `group.require_two_factor` and the fiscal year start month move there from columns
  (decided by the user, question 2; [Controls](#3-controls-of-the-erp-governance-not-moderation)).
- 2026-10-15: **reference data in three layers; groups add and hide**: global data is maintained by the platform admin and read only for groups; a
  group may add values of its own and hide global values from its members' choices (decided by the user, question 1; [Reference data](#2-reference-data-three-layers-decided-2026-10-15)).
- 2026-10-15: **no default platform admin access to customer ERP data**; a group admin grants temporary support access, every read audited and shown
  in the group activity log (decided by the user, question 4; [Customer ERP data](#11-no-default-access-to-customer-erp-data-decided-2026-10-15)).
- 2026-10-15: **suspending a group suspends all its internal tenants**; restoring the group restores only the tenants it suspended (decided by the
  user, question 6; `erp-organization.md` section 7).

## 1. Platform admin after the ERP

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

### 1.1 No default access to customer ERP data (decided 2026-10-15)

- Manpower plans, salary budgets, requisitions, approvals and (later) employee data belong to the customer. No platform admin permission reads
  them; the ERP data stays out of the admin permission catalog. This holds for the PKP group too: the IT team running ONESIS does not read the
  holding's HR budgets by default.
- When support needs to look, a group admin (`group.members.manage`) grants **temporary support access** to named platform admins: at most 72 hours,
  revocable at any time, read only, every read audited and shown in the group activity log (`group.audit.view`). Details (scope per module,
  storage) are designed with the first ERP coding of #5.

### 1.2 Internal tenants and portal moderation (decided 2026-10-15)

- `phase-8-platform-admin.md` section 2 sends the jobs of an **unverified** company to admin review. An internal tenant is linked to a legal entity
  and its jobs start from approved requisitions, so it counts as **verified**: the public company card reads `verified` = "a verified document
  date or a legal-entity link" (derived, not stored, so removing a link removes it), and none of the three review triggers (`jobs.require_approval`,
  unverified, suspicious phrases) applies to its jobs.
- Public job reports, company reviews and chat reports still apply to every tenant, because the jobs of internal tenants are public on ONESIS Career.

## 2. Reference data: three layers (decided 2026-10-15)

| Layer | Owner | Today | ERP later |
|---|---|---|---|
| Global (platform) | platform admin (`reference_data.manage`) | BPS locations, education levels, `job_types`, industries, skills, job roles, nationalities | national holidays, banks, BPJS and PPh 21 rates (regulation, **effective-dated**), marital status, religion (sensitive under UU PDP) |
| Group (customer) | group admin (`group.structure.manage`) | none | legal entities, units, unit types, positions, grades, cost centres (designed); later leave types, the group's work calendar, employment statuses |
| Tenant | company admin | pipeline stages, screening question bank, interview kits, tags | approval flow overrides (designed) |

Rules (decided 2026-10-15):

- Global data is **read only** for groups; a group never edits a global value.
- A group may **add** values of its own to a list (for example a job role of its own); they are offered only inside the group's tenants, and shown
  wherever a record of the group uses them (a public job of an internal tenant shows its job role name).
- A group may **hide** global values from its members' choices; records that already use a hidden value still read it.
- Each list declares in code whether it allows group additions and hiding; locations (BPS) and regulatory data (rates, national holidays) allow
  neither, since one change applies to every customer and must follow the regulation. The flags of each list are set when the list is coded.
- Group values are managed with `group.structure.manage`.

## 3. Controls of the ERP: governance, not moderation

The ERP has no public content, so it needs no moderation. Its controls:

| Control | Source | State |
|---|---|---|
| Who may do what | company permissions and group roles | designed |
| Business decisions | the Approval context | designed |
| Trail | the audit trail | **decided 2026-10-15**: `group_id` (nullable) added with #5, filled on write and backfilled from `company_id` (#2); `group.audit.view` opens a group activity log covering the group's own actions and its internal tenants, without IP address or user agent, like the company team history |
| Policy | settings | **decided 2026-10-15**: `scope_type` gains `group`; read order company → group → global → default; each catalog entry names its allowed scopes (company, group or both) in place of the single `overridable` flag; group admins change group values with `group.settings.manage`, audited; the group settings of `erp-sso.md` and `erp-requisitions.md` are stored there |
| Operator control | platform admin | creating and suspending groups, linking tenants, temporary support access (section 1.1) |

## 4. Effect on designs already written

- `erp-sso.md` section 5 and `erp-requisitions.md` section 5 (done 2026-10-15): the two group settings use the group scope.
- `erp-organization.md` (decided): the audit entry gains `group_id` next to the `company_id` of `phase-8-company-admin.md` section 2, and the group
  catalog gains `group.audit.view`.
- `phase-8-platform-admin.md` section 2: the review rule reads "verified or internal".
- `erp-organization.md` section 7 (done 2026-10-15): a group status and its effect on the group's tenants.

## 5. Open questions

1. ~~Three layers of reference data~~ decided 2026-10-15 (section 2).
2. ~~A `group` scope in the settings~~ decided 2026-10-15 (section 3).
3. ~~`group_id` on audit entries and a group activity log~~ decided 2026-10-15 (section 3).
4. ~~No default platform admin access to customer ERP data~~ decided 2026-10-15 (section 1.1).
5. ~~Internal tenants counted as verified~~ decided 2026-10-15 (section 1.2).
6. ~~A group status~~ decided 2026-10-15 (`erp-organization.md` section 7).

## Coded: who sees the approval pages (2026-10-29)

An approver holds no permission of their own (`erp-requisitions.md` section 4), so a menu that followed permissions hid the approval pages from a recruiter who was named in a flow. `GET company/me` now carries `takes_part_in_approvals` (port `Shared\Application\Approval\ApprovalParticipation`, provided by Approval): true when the member asked for an approval, holds or held a place in one (as the account, or as the one who decided or stood in for another), or has one waiting (also through a delegation or a group role). Workspace shows the approvals and delegations entries, and the count of what waits, to such a member besides those who hold an approvals or workforce permission or a group role. The pages themselves were always open to any member; the API decides what a member sees in them.

