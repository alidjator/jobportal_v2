# ERP requisitions and a generic approval workflow (designed 2026-10-15; Approval part A coded 2026-10-16)

Item #6 of `phase-8-index.md`. Builds on `erp-organization.md` (groups, legal entities, units, positions, grades, cost centres, internal tenants)
and extends `phase-1-jobs.md` (job lifecycle). Offer approval of internal tenants (#12, `phase-8-offer-letters.md`) uses the approval workflow
designed here. Plans, billing, payments, orders and invoices stay out of scope.

## Decisions

- 2026-10-15: **a setting per internal tenant, required by default**: with `recruitment.requisition_required` on, a new job of an internal tenant
  can only be created from an approved requisition and carries its position, unit and cost centre; external tenants are unchanged (decision gate,
  decided by the user; [Requisition rule](#1-requisition-rule)).
- 2026-10-15: **manpower planning is designed now, in detail**: a new `Workforce` context (prefix `workforce_`) holds plans and requisitions;
  a plan per legal entity per fiscal year with versions and approval; one line per position with opening headcount, year-end target, the additions
  **split per month**, and an optional estimated annual cost; requisitions are `planned` (use the quota), `replacement` (no quota) or `unplanned`
  (decision gate, decided by the user; [Manpower planning](#2-manpower-planning)).
- 2026-10-15: **plans are drafted by tenant members, read by the group**: company permissions `workforce.plans.manage`, `workforce.plans.view`,
  `workforce.costs.view`; group permissions `group.workforce.view` and `group.workforce.manage` (decided by the user; [Manpower planning](#2-manpower-planning)).
- 2026-10-15: the monthly split is **a target, not a limit**; unused quota **lapses** at the end of the fiscal year (decided by the user;
  [Manpower planning](#2-manpower-planning)).
- 2026-10-15: **requisition model**: content, statuses, amendments through approval, **one requisition to many jobs**, permissions
  `workforce.requisitions.create` and `workforce.requisitions.view` (decision gate, decided by the user; [Requisitions](#3-requisitions)).
- 2026-10-15: **generic approval model with delegation**: a new `Approval` context (prefix `approval_`), subject types registered in a catalog,
  flows of ordered steps with approvers, mode and conditions, approve / reject / return for revision, flow snapshot at submission, no
  self-approval, reminders, and delegation for a date range (decision gate, decided by the user; [Approvals](#4-approvals)).
- 2026-10-15: **group default flows, overridable per tenant**: `group.approvals.manage` defines the default flows of the group;
  `approvals.manage` lets a tenant replace them for itself (decided by the user; [Approvals](#4-approvals)).
- 2026-10-15: with **no flow** for a subject type, submission is refused; a step whose approver **leaves** is reassigned by an admin; reminders
  every **2 days** by default (decided by the user; [Approvals](#4-approvals)).
- 2026-10-15: **section 5** (tables, routes, catalog additions) approved as written (decided by the user; [Storage and API](#5-storage-and-api)).

## 1. Requisition rule

- Setting `recruitment.requisition_required` (boolean, default `true`, overridable per company) applies only to internal tenants
  (`company_companies.legal_entity_id` set); for an external tenant it is ignored and not shown.
- When on, `POST company/jobs` without a requisition is refused for an internal tenant; the job is created from an approved requisition and takes its
  position, unit and cost centre. When off, a job may still name a requisition, but need not.

## 2. Manpower planning

- **Context:** `Workforce` (`src/Workforce`, tables `workforce_*`) owns manpower plans and requisitions. Approvals are a separate context
  (section 4) because offers and later ERP modules use them too.
- **Plan:** one per legal entity per fiscal year, in versions. Status `draft`, `pending_approval`, `approved`, `rejected` (back to editable),
  `superseded`. One `approved` version is in force per legal entity and year. The fiscal year starts in a month set per group (default January).
- **Line:** one per position (stable id from `erp-organization.md`): opening headcount (entered by hand until the Employee context exists, then
  computed), year-end target, additions = target − opening, split **per month** (the twelve monthly numbers add up to the additions), and an
  optional estimated annual cost in rupiah.
- **Revision:** a new version copies the lines of the version in force and goes through approval; once approved it supersedes the old one. A line's
  additions can never drop below what requisitions already use.
- **Quota use by requisitions:**
  - `planned`: uses the remaining additions of its position's line; refused when not enough remains.
  - `replacement`: replaces someone who leaves (name and leaving date as free text until Employee exists); uses no quota.
  - `unplanned`: outside the plan; always allowed to submit, and approval rules can add steps for it.
  - Remaining = additions − requisitions submitted or approved; a cancelled or rejected requisition gives its quota back.

- **Permissions:** company permissions (added to the catalog of `phase-8-company-permissions.md`) `workforce.plans.manage` (draft, edit, submit,
  revise), `workforce.plans.view`, `workforce.costs.view` (the estimated costs, also on requisitions); group permissions (added to the catalog of
  `erp-organization.md` section 4) `group.workforce.view` (read the plans of every legal entity) and `group.workforce.manage` (may also draft them).
- **Monthly split:** a target for reports (ahead of or behind plan per month), never a limit: a requisition may use the year's quota at any time.
- **Year end:** unused quota lapses; needs are entered in the next year's plan. Requisitions already approved go on.

## 3. Requisitions

- **Content:** type (`planned`, `replacement`, `unplanned`); position (stable id), with unit and grade range from the position version in force;
  requested grade (inside the range); number of hires (at least 1; for `planned` at most the remaining quota); employment type (the existing
  `job_types` master) and contract length in months for a contract; target join date; cost centre (default from the unit); salary budget range
  (minimum and maximum in rupiah, visible only with `workforce.costs.view`); justification; replacement details (name and leaving date as free text
  until Employee exists); hiring manager (a member of the tenant); requester.
- **Statuses:** `draft`, `pending_approval`, `approved`, `in_recruitment` (from the first job), `filled` (hires reach the number), `closed`
  (closed by hand with a reason from `approved` or `in_recruitment`; the unused quota returns), `rejected` (editable, can be submitted again),
  `cancelled` (only while no job exists).
- **Changes after approval:** raising the number of hires or the budget is an **amendment** that goes through approval again; lowering the number
  needs only a reason and returns the quota.
- **Jobs:** one requisition may have **several jobs**; the `vacancies` of its jobs together never exceed the hires still open; a job takes its title
  (editable) and job role from the position. Hires count across its jobs; when they reach the number the "mark as filled" flow of
  `phase-8-hiring.md` is reused.
- **Permissions:** company permissions `workforce.requisitions.create` (draft, submit, amend, close, cancel) and `workforce.requisitions.view`;
  the group reads them with `group.workforce.view`.

## 4. Approvals

- **Context:** `Approval` (`src/Approval`, tables `approval_*`). It knows nothing of the subjects: each context registers its **subject types** in
  a shared catalog (code, like the `PermissionCatalog`): `workforce.plan`, `workforce.requisition`, `workforce.requisition_amendment`, later
  `recruitment.offer` (#12) and other ERP modules. A subject type declares the facts its conditions may read (for a requisition: type, grade
  rank, number of hires, maximum budget) and receives the outcome through a domain event (`ApprovalGranted`, `ApprovalRejected`,
  `ApprovalReturned`).
- **Flows:** one per subject type, with ordered steps. A step has approvers (named members, holders of a group role, or a relative role such as
  the requisition's hiring manager; "head of unit" waits for Employee), a mode (any one or all) and optional conditions (the step applies only
  when they hold, for example requisition type, minimum grade rank, hires at least N, maximum budget at least an amount).
- **Owners:** the group defines default flows with `group.approvals.manage`; a tenant may replace the default for itself with the company
  permission `approvals.manage`. A tenant flow wins over the group flow for that tenant.
- **Decisions:** approve, reject (reason required; the subject becomes `rejected`), return for revision (reason required; the subject goes back to
  `draft`). Approvers holding a group role decide on the approvals of the group's tenants; this is an approval action, not an action on
  applications, so the rule of `erp-organization.md` section 4 is unchanged.
- **Snapshot:** the flow is copied into the approval instance at submission; editing a flow never changes a running approval.
- **No self-approval:** the requester never decides a step; a step whose only approver is the requester is refused at submission.
- **Delegation:** an approver names a delegate (a member of the same tenant or group) for a date range; a decision by the delegate records both
  names.
- **Reminders and records:** reminders after a number of days (setting), in-app and email notifications to the approvers of the current step and
  to the requester on the outcome; every decision is audited.

- **No flow:** submission is refused with 409 `approval-flow-missing` when neither the tenant nor the group has a flow for the subject type.
- **An approver who leaves** the tenant or the group while their step waits: the step is marked "without approver"; a company admin of the tenant
  (or a holder of `group.approvals.manage` for a group step) names a replacement, audited, and the requester is told.
- **Reminders:** setting `approvals.reminder_days` (integer, default 2, range 1 to 14, overridable per company), repeated every N days until the step
  is decided.

## 5. Storage and API

- **Workforce tables:** `workforce_plans` (legal entity, fiscal year, version, status, approval instance), `workforce_plan_lines` (plan, position,
  opening headcount, target, estimated cost), `workforce_plan_line_months` (line, month 1 to 12, additions), `workforce_requisitions` (the content of
  section 3, status, approval instance), `workforce_requisition_amendments` (requisition, new number or budget, reason, status).
- **Approval tables:** `approval_flows` (owner group or company, subject type, active), `approval_flow_steps` (flow, order, mode, conditions as
  jsonb), `approval_flow_step_approvers` (step, kind `member`, `group_role` or `relative`, value), `approval_instances` (subject type and id,
  requester, snapshot as jsonb, status), `approval_instance_steps` (instance, order, status, decided by, delegate, reason, decided at),
  `approval_delegations` (approver, delegate, from, to).
- **Jobs:** `job_jobs.requisition_id` (nullable, index; a cross-context key to `workforce_requisitions`, added to `cross-context-foreign-keys.md`
  when coded). Creating a job from a requisition: `POST company/requisitions/{id}/jobs`.
- **Group setting:** `workforce.fiscal_year_start_month` (integer 1 to 12, default 1) with the **group scope** of the settings (changed 2026-10-15
  from a column on `organization_groups`, `erp-governance.md` section 3).
- **API** (`architecture.md` 2.5): `company/workforce/plans` (with `submit`, `revise`), `company/requisitions` (with `submit`, `amend`, `close`,
  `cancel`, `jobs`), `company/approvals` (my pending steps; `approve`, `reject`, `return`), `company/approval-flows`, `company/approval-delegations`;
  group side under `organization/workforce/plans`, `organization/requisitions` (read) and `organization/approval-flows`.
- **Catalog additions:** company permissions `workforce.plans.manage`, `workforce.plans.view`, `workforce.costs.view`,
  `workforce.requisitions.create`, `workforce.requisitions.view`, `approvals.manage` (a new group "Workforce" in `phase-8-company-permissions.md`;
  none in the Recruiter preset); group permissions `group.workforce.view`, `group.workforce.manage`, `group.approvals.manage` (added to
  `erp-organization.md` section 4). Every approver may decide the steps assigned to them without a separate permission.

## Still open

- Reports (plan against actual per month, time from requisition to hire) are designed with the dashboards after the TA core.

## Coded: Approval part A, the catalog and the flows (2026-10-16)

#6 is coded in steps, each checked and committed on its own: **Approval A** the subject-type catalog and the flows (this step), **Approval B** instances, decisions, delegation, an approver who leaves and the
reminders, **Workforce plans**, **requisitions and the job rule** (`recruitment.requisition_required`, `job_jobs.requisition_id`). Migration `000148` (`approval_flows`, `approval_flow_steps`,
`approval_flow_step_approvers`; the owner column names either a group or a company, so it has no foreign key). Interpretations, where the design left room:

- **Catalog.** `ApprovalSubjectCatalog` (Shared, a singleton like `SettingsCatalog`) holds `ApprovalSubjectType` rows: code (`area.name`), description, the **facts** a condition may read (`integer`,
  `string` with optional allowed values, `boolean`) and the **relative roles** a step may name (`hiring_manager`). Contexts register theirs from their providers. None is registered yet:
  Workforce registers `workforce.plan`, `workforce.requisition` and `workforce.requisition_amendment` when it is coded, Recruitment `recruitment.offer` (#12); the tests register their own.
- **Flows.** One per owner and subject type, saved whole (`PUT` replaces the steps) and removed with `DELETE`. A step has a mode (`any`, `all`), 0 to 5 conditions (all must hold) and 1 to 10 approvers
  (`member` account id, `group_role` a group permission name, `relative` a role of the subject type); a flow has 1 to 10 steps. Conditions compare a fact with `gte`, `lte`, `eq` (integer), `eq`
  (boolean), `eq` or `in` (string, inside the allowed values); anything else is 422 `invalid-flow-condition`. A member approver must belong to the owner's tenant (any tenant of the group for a group
  flow), no approver twice in a step, a relative role must be one the subject type names, a group role one of the group permissions (422 `invalid-flow-approver`); step count 422 `invalid-flow-steps`.
- **Who.** `organization/approval-flows` (any group member reads, `group.approvals.manage` — new, the sixth group permission — saves and removes) and `company/approval-flows` (any member of an internal
  tenant reads, the new company permission `approvals.manage` in a new group "Workforce" of the catalog saves and removes; not in the Recruiter preset; 403 `not-an-internal-tenant` for any other tenant).
  The company page lists every subject type with the flow that applies and its `source` (`company`, `group` or `none`).
- **Resolution.** `ApprovalFlows::resolve(companyId, groupId, subjectType)`: the tenant's own flow, else its group's, else null (the submission of part B refuses it with 409 `approval-flow-missing`).
  A running approval is not touched by editing a flow (the snapshot of part B).
- **Audit.** `approval.flow_saved` and `approval.flow_removed` (steps count in the metadata); a group flow belongs to the group log, a tenant flow to the tenant's log and so to its group's.
