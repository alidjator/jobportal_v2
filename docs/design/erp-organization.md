# ERP Organization context and internal tenants (designed 2026-10-15, not coded)

Item #5 of `phase-8-index.md`. Extends `erp-direction.md` (sections 1, 4 and 7) and `phase-1-company.md` (tenant = `company_companies`, one company
per member account). Requisitions and approvals (#6) build on this design. Plans, billing, payments, orders and invoices stay out of scope, also
for ERP customers.

## Decisions

- 2026-10-15: the Organization context serves **many groups (ERP as SaaS)**: each customer group has its own legal entities and structure, and
  every Organization table is scoped by group (decided by the user; [Scope](#1-scope)).
- 2026-10-15: **group → legal entity → tenant**: new tables `organization_groups` and `organization_legal_entities` (one group each); an internal
  tenant is a `company_companies` row with a unique `legal_entity_id` (1:1), null for an external tenant; Company stays the portal context
  (decision gate, decided by the user; [Relations](#2-relations)).
- 2026-10-15: **a group role on top of the company membership**: the account stays a `company_member` of one tenant of its group (usually the
  holding's tenant); `organization_group_members` (unique `user_id`) adds the group role; "one company per account" and sign-in are unchanged
  (decision gate, decided by the user; [Group accounts](#3-group-accounts)).
- 2026-10-15: **group permissions manage the structure and read across tenants**: a fixed catalog `group.structure.manage`,
  `group.members.manage`, `group.recruitment.view` (read only, audited); acting on applications still needs a membership of that tenant (decision
  gate, decided by the user; [Group permissions](#4-group-permissions)).
- 2026-10-15: **structure model with effective dating**: unit tree per legal entity, grades per group without salary ranges, positions (job
  titles, not seats) per legal entity without headcount, cost centres per legal entity; **units and positions are effective-dated** so the
  structure on any date can be read and restructurings can be scheduled (decision gate, decided by the user; [Structure](#5-structure)).
- 2026-10-15: **identity plus versions**: `organization_units` and `organization_positions` keep the stable id and code; `*_versions` tables keep
  the dated data with `valid_from` and `valid_to` without overlaps; every reference points at the stable id (decision gate, decided by the user;
  [Effective dating](#6-effective-dating)).
- 2026-10-15: changes to units and positions **may be dated in the past** with a required reason, audited before and after (decided by the user;
  [Lifecycle](#7-lifecycle)).
- 2026-10-15: **unit types are a list per group** with a level order (decided by the user; [Lifecycle](#7-lifecycle)).
- 2026-10-15: **the platform admin creates groups and links tenants**; the group admin manages the other legal entities and the structure (decided by
  the user; [Lifecycle](#7-lifecycle)).
- 2026-10-15: **legal entity core data**: legal name, legal form, NPWP, NIB, registered address from the location masters, status; no legal
  documents yet (decided by the user; [Lifecycle](#7-lifecycle)).
- 2026-10-15: deactivating a legal entity whose tenant is active is **refused**; the tenant is suspended first (decided by the user;
  [Lifecycle](#7-lifecycle)).
- 2026-10-15: a tenant link may be **removed or moved only before ERP data depends on it** (decided by the user; [Lifecycle](#7-lifecycle)).
- 2026-10-15: a **group status** (`active`, `suspended`); suspending a group suspends its internal tenants, stored as `organization_groups.status`
  and `company_companies.suspended_by_group_at` (decided by the user; [Lifecycle](#7-lifecycle)).
- 2026-10-15: the details of section 7 (legal forms, reason length, error codes) and **section 8** (tables, routes, `as_of`, domain events)
  approved as written (decided by the user; [Lifecycle](#7-lifecycle), [Storage and API](#8-storage-and-api)).

## 1. Scope

- A **group** is one ERP customer (for example the PKP group with its holding and subsidiaries). Each group owns its legal entities, units,
  positions, grades and cost centres; no data is shared between groups.
- External tenants of the portal (client companies that only post jobs) have no group and no structure, as today.
- ~~Consequence for `erp-direction.md` section 2: the central IdP must separate groups~~ no longer applies (2026-10-15): the Identity context
  is the IAM of the ERP (`erp-sso.md`).

## 2. Relations

- `organization_groups`: one row per ERP customer group.
- `organization_legal_entities`: belongs to exactly one group; holds the legal data of the entity (kept apart from the portal profile).
- `company_companies.legal_entity_id`: nullable, unique, foreign key to `organization_legal_entities` (an allowed cross-context key, to be added
  to `cross-context-foreign-keys.md` when coded). Set = internal tenant; null = external tenant. A legal entity without recruitment simply has no
  tenant.
- The tenant kind is derived from the column, not stored twice.

## 3. Group accounts

- `organization_group_members`: `group_id`, `user_id` (unique: one group per account), the group role and permissions. A row is allowed only
  when the account's company membership is in a tenant whose legal entity belongs to that group; removing the company membership removes the
  group role too.
- The holding needs a tenant of its own for its staff to hold a group role.
- Nothing changes in sign-in, tokens or `company_members`; the group role is read on every request, like company permissions.

## 4. Group permissions

The catalog is code (like the company catalog of `phase-8-company-permissions.md`), not a table a group edits.

| Permission | Covers |
|---|---|
| `group.structure.manage` | legal entities, organisation units, positions, grades, cost centres of the group |
| `group.members.manage` | granting, changing and removing group roles (never one's own) |
| `group.recruitment.view` | reading jobs, applicants and reports of every tenant of the group; read only; every read of an applicant is audited |

Extended by `erp-requisitions.md` with `group.workforce.view`, `group.workforce.manage` and `group.approvals.manage`, and by `erp-governance.md`
with `group.audit.view` (a group activity log; audit entries gain `group_id`, backfilled from `company_id`) and `group.settings.manage` (group
values of the settings).

- Moving, rejecting, offers, notes and every other action on an application keep the company rules: the actor needs a membership of that tenant
  with the permission, and the job assignment stays the data boundary.
- The policies of the recruitment contexts gain one read rule ("a group member with `group.recruitment.view` of the tenant's group may read"); no
  write rule changes.

## 5. Structure

| Entity | Owner | Main data | Rules |
|---|---|---|---|
| Organisation unit | legal entity | code, name, parent (tree: directorate, division, department, section), unit type from a list per group, default cost centre | the parent is in the same legal entity; no cycles; **effective-dated** |
| Grade | group | code, name, rank | shared by every legal entity of the group; no salary range (payroll, later) |
| Position (job title) | legal entity | code, title, unit, grade range (minimum and maximum), reports-to position, optional link to `reference_job_roles` | a title, not a seat; headcount lives in manpower planning (#6); **effective-dated** |
| Cost centre | legal entity | code, name | default of a unit; later used by requisitions and Finance |

- Codes are unique per owner. Status `active` or `inactive`; a referenced row is never deleted, only deactivated. Every change is audited.
- Effective dating applies to units and positions: the structure can be read as it is on any date, and a restructuring can be entered ahead of its
  date. Grades and cost centres are not effective-dated. Employee placement history (later) is kept by the Employee context.

## 6. Effective dating

- `organization_units` (id, legal entity, code, status) and `organization_unit_versions` (unit, `valid_from`, `valid_to` null = open, name, parent
  unit, unit type, default cost centre); `organization_positions` and `organization_position_versions` (title, unit, grade range, reports-to
  position, job role) in the same way.
- Versions of one unit or position never overlap; a new version closes the previous one the day before its `valid_from`.
- Jobs, requisitions and (later) employees reference the stable id; reading "as of" a date picks the version valid on that date. A parent or
  reports-to reference must be valid over the whole period of the version that points at it.

## 7. Lifecycle

- **Creating a group (platform admin):** the admin creates the group, its first legal entity (usually the holding) and the holding's tenant, or
  links an existing tenant; the invitation of the tenant's first company admin also grants the group role with every group permission. Creating and
  linking tenants stays with the platform admin, as creating companies does today (R1).
- **Group admin (`group.structure.manage`):** creates and edits the other legal entities, unit types, units, positions, grades and cost centres of
  the group; grants group roles with `group.members.manage`.
- **Legal entity data:** legal name, legal form (`pt`, `pt_tbk`, `cv`, `firma`, `koperasi`, `yayasan`, `perum`, `persero`, `other`), NPWP, NIB,
  registered address (street plus the location masters), status `active` or `inactive`. No legal documents in this step.
- **Unit types:** a list per group (code, name, level order); a unit's type is free within the tree, the level order only sorts and labels.
- **Changes dated in the past:** allowed for units and positions with a required reason (5 to 500 characters); the audit entry holds the versions
  before and after.
- **Deactivating a legal entity** whose tenant is `active` is refused with 409 `legal-entity-has-active-tenant`; the platform admin suspends the
  tenant first (the suspension rules of `phase-1-company.md`).
- **Group status (decided 2026-10-15, `erp-governance.md` question 6):** `active` or `suspended`, set by the platform admin with a reason
  (audited). Suspending a group suspends every internal tenant of the group with the company suspension rules of `phase-1-company.md` (public
  profile and jobs hidden, members refused with 403 `company-suspended`, applications unchanged) and refuses every group role with 403
  `group-suspended`. Restoring the group restores only the tenants that the group suspension suspended; a tenant suspended on its own stays
  suspended. Nothing is deleted.
- **Storage (approved by the user 2026-10-15):** `organization_groups.status` and `status_reason`; `company_companies` gains
  `suspended_by_group_at` (nullable) so a restore knows which tenants to bring back.
- **Removing or moving a tenant link** (platform admin) is allowed only while nothing of the ERP depends on it (group roles of its members,
  requisitions, later employees); otherwise 409 `tenant-link-in-use`.

## 8. Storage and API

- Tables with the prefix `organization_`: `groups`, `group_members`, `legal_entities`, `unit_types`, `units`, `unit_versions`, `positions`,
  `position_versions`, `grades`, `cost_centres`; plus `company_companies.legal_entity_id`. Conventions of `phase-0-schema.md`; the new
  cross-context key is added to `cross-context-foreign-keys.md` when coded.
- API following `architecture.md` 2.5: platform admin under `/api/v1/admin/groups/...` (groups, first legal entity, tenant links); group members
  under `/api/v1/organization/...` (legal entities, unit types, units, positions, grades, cost centres, group members); `?as_of=<date>` on unit and
  position reads, today by default.
- Domain events for the later contexts: `LegalEntityCreated`, `UnitRestructured`, `PositionChanged`, `TenantLinkedToLegalEntity`.

## Still open

- Whether a job of an internal tenant must name a position and unit: decided in `erp-requisitions.md` (a setting per tenant, required by default).
- ~~How groups map to the central IdP~~ no longer applies: the Identity context is the IAM of the ERP (`erp-sso.md`).
