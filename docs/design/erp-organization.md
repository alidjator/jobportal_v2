# ERP Organization context and internal tenants (designed 2026-10-15; parts A to D coded 2026-10-16)

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
values of the settings), and by `phase-8-provider-institutions.md` section 8 with `group.assessment.manage` (private assessment providers, their
psychologists and their battery grants).

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

## Coded: part A (2026-10-16)

#5 is coded in parts, each checked and committed on its own: **A** groups, legal entities, the tenant link, group roles, the group status and "internal tenants count
as verified" (this part); **B** grades, unit types, cost centres, units and positions with versions and `as_of` (sections 5 and 6); **C** governance (`group_id` on
audit entries, the group activity log `group.audit.view`, the `group` scope of the settings with `group.settings.manage`, temporary support access); **D** the read rule
`group.recruitment.view` in the recruitment contexts. Migration `000143` (`organization_groups`, `organization_legal_entities`, `organization_group_members`,
`organization_group_member_permissions`, `company_companies.legal_entity_id` and `suspended_by_group_at`; the two new cross-context keys are in `CrossContextForeignKeysTest`).
Interpretations, where the design left room:

- **Who does what.** Platform admin (`companies.manage`, no new admin permission): `admin/groups` (list, create with the first legal entity, show, suspend with a reason of 5 to
  500 characters, restore), `PUT`/`DELETE admin/groups/{id}/legal-entities/{entityId}/tenant` (link and unlink an existing company; creating the company stays
  `POST admin/companies`) and `admin/groups/{id}/members` (the first group role, and support). Group member (`auth:user`): `organization/group`, the legal entities
  (`POST`, `PATCH`, `.../deactivate`, `.../reactivate`, `group.structure.manage`) and `organization/members` (`group.members.manage`, only the own group, never the own role:
  422 `cannot-change-own-group-role`). The group role is one `PUT members/{userId}` with the permission list (it grants or replaces; audited as `granted` or `changed`).
- **Group role rules.** The account must be a member of a tenant of the group (422 `user-not-in-group`), holds one role in one group (409 `user-in-another-group`), the
  role needs at least one permission, and it ends with the company membership (`CompanyMemberRemoved`). A role is refused with 403 `not-a-group-member`,
  `group-suspended` or `group-permission-required`. The invitation of the first company admin does not grant the group role yet (the platform admin does it with `PUT`).
- **Tenant link.** One entity has one tenant (409 `legal-entity-has-tenant`) and one company one entity (409 `company-already-linked`); linking needs an active group and
  an active entity (409 `group-suspended`, `legal-entity-inactive`). Unlinking is refused while a group member belongs to that tenant (409 `tenant-link-in-use`; requisitions
  and employees will count when they exist). The link is the only storage of "internal": `CompanyDirectory::isVerified` and the public card read `verified_at` **or** the
  link, so the review rule of `phase-8-platform-admin.md` section 2 and the contact rule of `phase-8-job-discovery.md` section 4 treat an internal tenant as verified, and
  removing the link removes it. `verified_at` itself stays null. The name lock is not extended to internal tenants.
- **Group status.** Suspending a group suspends its active tenants in one statement (`TenantRegistry::suspendForGroup`: status, reason, `suspended_by_group_at`); restoring
  restores only the tenants carrying that mark, so a tenant suspended on its own (or by an admin before) stays suspended. 409 `group-already-suspended` and
  `group-not-suspended`. A deactivation of a legal entity whose tenant is active is 409 `legal-entity-has-active-tenant`.
- **Audit.** `group.created`, `group.suspended`, `group.restored`, `group.legal_entity_created`, `.updated`, `.deactivated`, `.reactivated`, `group.tenant_linked`,
  `.unlinked`, `group.role_granted`, `.changed`, `.removed` (subject `organization_group`, `group_id` in the metadata). They go in the platform audit log now; the group
  activity log and the `group_id` column come with part C.

## Coded: part B (2026-10-16)

Migration `000144` (`organization_grades`, `organization_unit_types`, `organization_cost_centres`, `organization_units`, `organization_unit_versions`, `organization_positions`,
`organization_position_versions`; all keys stay inside the context except `reference_job_roles`). Interpretations, where the design left room:

- **Who reads and who changes.** Any group member (any role, group not suspended) reads grades, unit types, cost centres, units and positions; `group.structure.manage` changes
  them. A legal entity, unit, position or cost centre of another group is 404. Routes under `organization/`: `grades`, `unit-types`, `cost-centres` (`GET`, `POST`,
  `PATCH {id}`, `POST {id}/deactivate`, `POST {id}/reactivate`; cost centres take `legal_entity_id` on create and list) and `units`, `positions` (the same plus
  `GET {id}` and `GET {id}/versions`). The code never changes after creation (`code` on a `PATCH` is 422).
- **Codes** are unique per owner whatever the letter case (409 `grade-code-taken`, `unit-type-code-taken`, `cost-centre-code-taken`, `unit-code-taken`, `position-code-taken`),
  letters, digits, dot, dash and underscore only. Nothing is added to an inactive legal entity (409 `legal-entity-inactive`).
- **Dating.** `valid_from` (a day, today by default) and `reason` on every create and change of a unit or position. A day before today needs a reason of 5 to 500 characters
  (422 `reason-required`). A change copies the version that was valid on its day, applies the fields sent (null clears the parent, cost centre, grades, boss or job role) and
  becomes a version from that day: the version it cuts into ends the day before, and it ends the day before the next version that already starts later, or stays open. A
  change on the first day of an existing version replaces it. A day before the first version is 422 `before-first-version`; a `PATCH` with no field is 422 `no-changes`.
  The timeline therefore has no gap and its last version stays open, so "valid over the whole period" of a reference means the referenced unit or position **exists from the
  day the version starts** (422 `invalid-parent`, `invalid-unit`, `invalid-reports-to` otherwise).
- **Rules per version.** Unit: unit type active in the group (`invalid-unit-type`), cost centre active in the entity (`invalid-cost-centre`), parent active in the same entity and
  not the unit itself (`invalid-parent`), no loop on any day of the version's period, checked on its first day and on every day another version starts inside it
  (`parent-cycle`). Position: unit active in the same entity (`invalid-unit`), grades active in the group (`invalid-grade`) with the lowest not above the highest by rank
  (`invalid-grade-range`), job role active in the reference data (`invalid-job-role`), "reports to" as the parent rule (`invalid-reports-to`, `reports-to-cycle`).
- **Reads.** `?as_of=<day>` (today by default) on `GET units`, `GET units/{id}` and the same for positions; a list leaves out what does not exist yet on that day, a single read is
  404. `valid_from` and `valid_to` of the returned version say how long it lasts. `GET {id}/versions` is the whole history.
- **Deactivating** never deletes: a grade used in the range of an active position, a unit type or cost centre used by an active unit, a unit that an active unit has as parent
  or an active position as its unit, and a position that an active position reports to, each now or later, are refused (409 `grade-in-use`, `unit-type-in-use`,
  `cost-centre-in-use`, `unit-in-use`, `position-in-use`); 409 `already-inactive` and `already-active`. A change to an inactive unit or position is 409 `unit-inactive`,
  `position-inactive`.
- **Order of the units (2026-10-28, decided by the user: dragging in the Workspace table).** `organization_units.sort_order` is the place of a unit among the units of its entity (a list of the entity is by `sort_order`, then code; a new unit goes last); it belongs to the unit and is not dated. `PUT organization/units/order` (`group.structure.manage`, active entity) takes `legal_entity_id` and `unit_ids`, the units in the wanted order, and the named units take each other's places while the others keep theirs: 204, 422 `invalid-unit-order` when an id is not a unit of the entity or repeats.
- **Audit and events.** `group.grade_*`, `group.unit_type_*`, `group.cost_centre_*` (created, updated, deactivated, reactivated), `group.unit_created`, `.unit_restructured`,
  `.unit_deactivated`, `.unit_reactivated`, `.units_reordered`, `group.position_created`, `.position_changed`, `.position_deactivated`, `.position_reactivated`. A dated change keeps `valid_from`,
  `reason` and the version `before` and `after`. Events `StructureItemChanged`, `UnitRestructured`, `PositionChanged` (`LegalEntityCreated` and
  `TenantLinkedToLegalEntity` exist from part A).

## Coded: part C1, the group activity log (2026-10-16)

Part C is coded in three steps: **C1** `group_id` on audit entries and the group activity log (this step), **C2** the `group` scope of the settings with
`group.settings.manage`, **C3** temporary support access (its details are still to be decided with the user). Migration `000145` adds `platform_audit_logs.group_id`
(nullable, no foreign key, index `group_id, occurred_at`). Interpretations:

- **Filling `group_id`.** `AuditEntry` gains `groupId`; `RecordAuditEntry` fills it when empty: the subject when it is an `organization_group`, otherwise the group of the entry's
  company (the one the entry already got: the subject company or the acting member's company) through the new Shared port `GroupDirectory::groupOfCompany`, provided by
  Organization. An external tenant has no group, so its entries stay without one. The migration backfills from the subject (`organization_group`) and from `company_id`
  through the tenant link. An entry keeps the group it had when it was written: a company that joins a group later does not pull its older entries in, except through the
  backfill of that day.
- **The log.** `GET organization/activity` (`group.audit.view`, any other member 403 `group-permission-required`): newest first, cursor paginated like the company log, filters
  `filter[user_id]`, `filter[company_id]`, `filter[action]`, `filter[from]`, `filter[to]`. It covers the group's own changes, what members of its internal tenants did
  (every action the company log would show) and what the platform did to the group. No IP address or user agent in any row; each row carries `company_id` and
  `company_name` (the tenant it belongs to), the actor name for members, and the audit `metadata` (the versions before and after for dated structure changes). `MemberActivity`
  gains `ofGroupLog`. Reading the log is not itself audited, as for the company log. No export yet.
- `GroupPermission` gains `group.audit.view` (the catalog is now `group.structure.manage`, `group.members.manage`, `group.recruitment.view`, `group.audit.view`).

## Coded: part C2, the group scope of the settings (2026-10-16)

Migration `000146` lets `platform_setting_overrides.scope_type` hold `group` next to `company` (the group id is the `scope_id`). Interpretations:

- **Allowed scopes.** `SettingDefinition` gains `groupEditable` next to the existing `overridable` (company scope) and `companyEditable`; a setting may allow either or both. The
  design said "in place of the single `overridable` flag"; the flag stays for the company scope, so none of the 53 existing definitions changes. No production setting is
  group-editable yet: `group.require_two_factor` (`erp-sso.md`) and `workforce.fiscal_year_start_month` (`erp-requisitions.md`) register with `groupEditable: true` when
  their modules are coded (#7, #6). The tests register their own.
- **Reading.** `StoredSettings` resolves company value (when `overridable`), then the group value (when `groupEditable` and the company is an internal tenant: the group comes from
  `GroupDirectory::groupOfCompany`), then the global value, then the default. A group value the definition no longer accepts is ignored, like the others. Reading with no
  company id never sees a group value.
- **Group settings page.** `GET organization/settings` (any group member) lists the group-editable settings with `value` (what applies to the group's tenants without a value
  of their own) and `default` (what applies without a group value: the global value, else the default). `PATCH organization/settings` (`group.settings.manage`, the new
  fifth group permission) takes `{key: value}`, all or none, 422 `setting-not-editable` for a key the group may not set and `invalid-setting-value` with `errors`. An unchanged
  value saves and audits nothing. There is no "remove my value" call yet.
- **Audit and events.** One `group.settings_updated` entry (subject `organization_group`) with the values before and after; a `GroupSettingChanged` event per changed key for the
  context that owns the setting. The platform admin's setting list shows `editable_per_group`.

## Coded: part C3, temporary support access (2026-10-16)

Decided with the user on 2026-10-16: support access covers **the structure and the group activity log** for now (each later ERP module adds its own reads when it is coded), and a
platform admin must hold a new permission, **`groups.support`**, to receive it. Migration `000147` (`organization_support_grants`; the three keys to `identity_admins` and
`identity_users` are in `CrossContextForeignKeysTest` and `cross-context-foreign-keys.md`). The rest follows `erp-governance.md` section 1.1:

- **Grant.** A group member with `group.members.manage` gives one named platform admin access for 1 to 72 hours: `GET organization/support-admins` (active admins that hold
  `groups.support`), `GET organization/support-grants` (the last 30 days, with `status` `active`, `revoked` or `expired`), `POST organization/support-grants`
  (`admin_id`, `hours`; 422 `invalid-support-admin`, `invalid-support-duration`; 409 `support-grant-exists` while that admin's grant is running, so a duration is changed by
  revoking first) and `DELETE organization/support-grants/{id}` (ends it at once; 409 `support-grant-not-active`). Audited as `group.support_granted` and
  `group.support_revoked`.
- **Read.** The platform admin (`auth:admin`, `groups.support`) lists the groups that granted access with `GET admin/support/groups` and reads with `GET admin/support/...?group_id=<group>`:
  `group`, `activity`, `grades`, `unit-types`, `cost-centres`, `units`, `units/{id}`, `units/{id}/versions`, `positions`, `positions/{id}`, `positions/{id}/versions`. These are the same controllers
  and use cases as the member's reads: `Actor::supportGroupId` (set by the `ActAsSupport` middleware) makes `GroupAccess` check the grant, and it carries no group permission except
  `group.audit.view`, so every change is refused (`group-permission-required`) even if a write route were ever added; no write route exists under `admin/support`. Without an active grant: 403
  `support-access-required`. A missing `group_id` is 422. A suspended group can still be read under a grant (nothing is changed and support may be needed most then).
- **Audit.** Every successful read is `group.support_read` (actor type `admin`, metadata `admin_name` and `request`, the IP address stays with the platform) with the group's `group_id`, so it shows in
  the group activity log (`group.audit.view`) and, for the group, names which platform admin read what. A refused read is not audited as a read.

## Coded: part D, the group read rule in recruitment (2026-10-16)

No migration. The rule is one new read path, not a change inside each recruitment policy: a group member with `group.recruitment.view`, in a group that is not suspended, reads a tenant of
their group **as a read-only member of that tenant**, so the recruiter use cases answer unchanged and every action that changes something is refused by their own permission checks.

- **The read-only membership.** `RecruitmentReadAccess` (Organization) checks the group role, the group status and that the tenant's legal entity belongs to the member's group (otherwise the
  tenant does not exist: 404; an external tenant, or one of another group, is the same), then builds a `CompanyMembership` of that tenant with exactly `applications.view`, `jobs.view_all`
  and `analytics.view`. Plumbing: the `ReadAsGroup` middleware sets it on the request, `ActorResolver` returns the actor with `Actor::readingForGroup()`. A suspended tenant
  stays unreadable (`company-suspended`), as for its own members.
- **Routes** (`auth:user`, GET only, `?tenant_id=<company>` required, 422 without it): `organization/tenants` (the internal tenants of the group, `group.recruitment.view`) and under
  `organization/read/`: `jobs`, `jobs/{id}`, `jobs/{id}/performance`, `analytics/{report}`, and, audited, `jobs/{id}/applications`, `applications/{id}`, `applications/{id}/resume`. They are the
  recruiter's own controllers, so filters, pagination and resources are the recruiter's. No write route exists under `organization/read`; notes, ratings, interviews, offers and the CSV export are not
  exposed (not needed to read jobs, applicants and reports, and the export has its own permission).
- **No trace on the applicant's side.** Reading an application through the group leaves `viewed_at` alone (`Actor::groupRead`), so the applicant is not told a group member looked.
- **Audit.** Every successful applicant read (list, detail, resume) is `group.recruitment_read`: actor the member, subject the group, `company_id` the tenant read, metadata `request`. It shows in
  the group activity log and in the tenant's own company activity log (its admins see which group member read what). The resume download keeps its own `application.resume_downloaded` entry as well.
- **Contact data.** The unverified-company rule of `phase-8-job-discovery.md` section 4 applies as for any member; internal tenants count as verified, so contact data is open in every stage.
