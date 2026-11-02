# Self-hosted edition (decisions of 2026-10-16, not coded)

Until now the project was designed as SaaS only (`erp-organization.md` section 1: many customer groups share one codebase and one database). This
document adds a **self-hosted** edition: one installation run on the customer's own infrastructure (also called on-premise when the server is
physical). It changes no code and no schema; it records the decisions, what blocks them today, and the order of work. Plans, billing, payments,
orders and invoices stay out of scope; the module entitlements of section 8 are enforced through an offline licence file, which is a deterrent, not copy
protection (gap A5).

## Decisions

- 2026-11-02: **SaaS runs as one deployment with one database per tenant** (`multi-tenancy.md`, bridge model); a tenant is one customer, so "one installation = one customer" below now reads "one tenant = one customer"; a self-hosted installation is the same code with one tenant, and a tenant moves out by dumping its database and folder (its derived `APP_KEY` goes with it).
- 2026-10-16: the project supports **SaaS and self-hosted** from one codebase; "self-hosted" is the name used in code, documents and `.env` (decided
  by the user; [Meaning](#1-meaning)).
- 2026-10-16: **both packaging forms**: Docker Compose as the main path and a manual runbook for customers who refuse containers (decided by the user;
  [Packaging](#2-packaging)).
- ~~2026-10-16: **all modules** are installed, including the public job portal ONESIS Career and the marketplace features~~ **changed 2026-10-16: the
  modules of an installation follow the customer's purchased package (module entitlements)**; only the entitlement is designed, prices, billing, payments
  and invoices stay out of scope (decided by the user; [Module entitlements](#8-module-entitlements)).
- 2026-10-16: the platform admin role is **split**: the customer runs the daily operation of its installation; ONESIS enters only through audited
  support access (decided by the user; [Platform admin](#3-platform-admin)).
- 2026-10-16: **one group per installation** (decided by the user; [Meaning](#1-meaning), question 1 of section 6 answered).
- 2026-10-16: **releases come as images in a container registry plus a downloadable archive; the source is not shared** (decided by the user; section 6,
  question 2).
- 2026-10-16: **support reaches a customer server only in a shared session during a ticket**, never through a permanent tunnel (decided by the user;
  section 6, question 3).
- 2026-10-16: **upgrades are release by release; a backup is required before migrating and is the rollback** (decided by the user; section 6, question 4).
- 2026-10-16: **telemetry is an opt-in version ping, off by default, version number only** (decided by the user; section 6, question 5). All five open
  questions of section 6 are answered.
- 2026-10-16: **ONESIS Core is in every package** (decided by the user; section 8, question 1).
- 2026-10-16: **module dependencies are fixed and an entitlement that breaks them is refused** (decided by the user; section 8, question 2).
- 2026-10-16: **entitlements come as a signed licence file verified offline** (decided by the user; section 8, question 3).
- 2026-10-16: **entitlements apply to SaaS too, per group**, through the same signed file (decided by the user; section 8, question 4).
- 2026-11-02: **a licence is bound to the group's UUID**, a new unique column `organization_groups.uuid` set once when the group is created (and filled for
  existing groups); the payload names `group_uuid` instead of the group id, and an upload whose UUID is not a group of this installation (or tenant) is
  refused. Closes the reuse of one customer's licence by another, between self-hosted installations and between tenants (decision gate, decided by the
  user; gap A7 of [section 8](#8-module-entitlements)).
- 2026-11-02: **with the bridge model the licence stays in the tenant database** (`platform_licences`), uploaded in the tenant's Console by its platform admin
  or installed by ONESIS with `tenant:licence <slug> <file>`; the same path for SaaS and self-hosted (decided by the user; gap A8 of section 8).
- 2026-11-02: **ONESIS watches the licences of every tenant from the landlord**: `tenant:list` shows the licensed modules and the expiry per tenant, and a
  warning goes to `platform.operations_email`; the warning in the tenant's Console stays for the customer's admin (decided by the user; gap A9, replaces
  the SaaS part of C2).
- 2026-11-02: **`LICENCE_ENFORCED` per tenant under the `.env` switch**: `.env` false turns enforcement off for every tenant (our development and staging
  sites); `.env` true enforces every tenant except one whose landlord record has `licence_enforced = false`, set only by ONESIS with
  `tenant:licence-enforcement <slug> off` (for example `demo`); a self-hosted customer holds the landlord, accepted as in A5 (decided by the user; gap A10).
- 2026-10-31: **Talent and Career are in every package, like Core; Workforce needs Talent**; only Workforce, Employee and the two add-ons are licensed
  (decided by the user; gap A1 of section 8).
- 2026-10-16: **entitlements cover modules and two add-ons: psychological tests and tele-interview** (decided by the user; section 8, questions 5 and 6).
  All questions of sections 6 and 8 are answered.
- 2026-10-16: **the self-hosted edition is released only after the back end is fully Spring Boot** (`spring-boot-migration.md`, question 10); the packaging is
  built for that runtime, and the steps of section 7 that do not depend on the runtime (separating the live site, `deploy/` templates, configuration audit) can
  start earlier (decided by the user).
- 2026-10-16: this session only designs; coding happens in other sessions after the open questions of sections 6 and 8 are answered (decided by the user).

## 1. Meaning

- **One installation = one customer** (SaaS: one tenant = one customer, `multi-tenancy.md` 5.1). The same repository, the same migrations and the same bounded contexts as SaaS; no second code line, no
  separate build per package: the code of every module ships in every release and the entitlements of section 8 decide which modules are active.
- The customer's installation holds its own database, files and secrets. No data goes to ONESIS unless the customer grants support access or switches on the opt-in version ping (version number only).
- When the package includes ONESIS Career, the customer also runs a **public** site: it is the operator of that portal (terms, privacy notice,
  moderation, contact). See section 4.
- **One installation holds one group** (decided 2026-10-16); its legal entities and internal tenants work as in SaaS, and client companies of the portal
  stay external tenants. Creating a second group in a self-hosted installation is refused (how it is enforced is decided when coded).

## 2. Packaging

| Form | Content | For |
|---|---|---|
| Docker Compose (main) | application, queue workers, scheduler, PostgreSQL, Redis, nginx, Reverb; Janus, coturn and Asterisk stay optional files that already exist under `deploy/` | customers with a Docker host |
| Manual runbook | the steps of `docs/deployment-checklist.md` written for a customer server (PHP, PostgreSQL, Redis, Supervisor, nginx, crontab) | customers who refuse containers |

- Both forms install the **same release**; the runbook is generated from the same list of processes as the Compose file so they cannot drift.
- Compose files and runbook live in the repository (`deploy/`), versioned with the code. Today the crontab, the nginx config and the ACLs are
  **outside git** (`HANDOFF.md`, "Operational facts"); they must move into `deploy/` templates first.

## 3. Platform admin

- **Customer (daily):** creates its first admin (`identity:create-admin`), global settings, reference data, email templates, legal documents, portal
  moderation, TOTP resets, creating groups and linking tenants (the permissions of `erp-governance.md` section 1).
- **ONESIS (support only):** no default access. The customer's admin grants temporary support access, every read is audited and shown in the activity
  log (the rule of `erp-governance.md` section 11, extended to the whole installation). How ONESIS reaches the server at all is open (question 3).
- The 16 platform permissions stay as they are; self-hosted adds none.

## 4. What differs from SaaS

| Area | SaaS | Self-hosted |
|---|---|---|
| Operator of the portal | ONESIS | the customer (legal documents, moderation, contact) |
| Updates | ONESIS deploys | the customer applies a release; migrations run by the customer |
| Backups, TLS, domain | ONESIS | the customer |
| SMTP, SIP trunk, Janus, Asterisk | one setup | the customer's own accounts and servers |
| Reference data | global layer maintained by ONESIS | seeded by the release; maintained by the customer's platform admin |
| Legacy import (`src/LegacyImport`) | internal tool for karir.pkp | not shipped |

## 5. What blocks it today (checked 2026-10-16)

- **Names tied to one site:** folder, Linux user, database, Supervisor programs, Asterisk names and the API domain `api-karir.satu.solutions` are fixed
  (`naming.md` section 3 plans the rename with the live-site separation). Self-hosted needs them to come from configuration.
- **Setup outside git:** crontab, nginx, ACLs, root steps of Janus and Asterisk.
- **No first-run and upgrade path:** the order (`migrate`, reference seed, `identity:sync-permissions`, `identity:create-admin`, `queue:restart`, cache
  rebuild) is a checklist for one server, not a command or a script.
- **Dependencies on the operator:** `CORS_ALLOWED_ORIGINS`, `JOB_SEEKER_PROFILE_URL`, `COMPANY_INVITATION_URL` and SMTP are still open for the one live site.
- **Reference data in migrations or seeders:** not checked whether a fresh install gets all reference lists without the legacy import.
- **Settings and legal documents:** the Content context holds the terms and privacy texts; a fresh installation needs the customer's own versions.

## 6. Open questions (to answer before coding)

1. ~~One group or many per installation~~ **answered 2026-10-16: one group** (section 1).
2. ~~Release channel~~ **answered 2026-10-16: a container registry (images for the Compose path) plus a downloadable release archive (for the manual
   runbook); the source repository is not shared.** The licence stays a business matter, not decided here.
3. ~~Support access~~ **answered 2026-10-16: a shared session during a support ticket** (screen sharing, or a temporary time-limited VPN or SSH user the
   customer opens); no permanent tunnel. The application side is the audited support access granted by the customer's admin (section 3).
4. ~~Version policy~~ **answered 2026-10-16: upgrades go release by release (no skipping a major release); the upgrade script requires a database backup
   before it migrates, and rollback means restoring that backup.** Migrations need no tested `down()`.
5. ~~Telemetry~~ **answered 2026-10-16: an opt-in version ping**: off by default; the customer's admin may switch it on; it carries the version number only
   (no customer data), for release and security notices. Its endpoint and payload are designed when coded.

## 7. Order of work (proposal, each step its own coding session)

1. ~~Answer section 6~~ done 2026-10-16.
2. Separate the live site from the working folder and rename the infrastructure (`naming.md` section 3); the identifiers come from configuration.
3. Move crontab, nginx, Supervisor and ACL setup into `deploy/` templates.
4. Audit `.env` and configuration for hard-coded domains and site names.
5. First-run command and upgrade command (idempotent, run by the customer).
6. Docker Compose, then the manual runbook generated from the same process list.
7. Backup and restore guide; support-access procedure.
8. Release pipeline (registry images and archive), the upgrade script with its mandatory backup, the one-group rule, and the opt-in version ping.

## 8. Module entitlements

Changed 2026-10-16 (user): the modules of an installation follow the **purchased package**, instead of "all modules installed". The common terms are
*modular licensing* (or module-based licensing), a customer's right to a module is its **entitlement**, switching a module on or off is **module
gating** (feature gating), and a named set of modules is an **edition**, **tier** or **bundle**. This document says "licensed modules" and "module
entitlements".

- **Scope (decided 2026-10-16):** only the entitlement, meaning which modules a customer has and how they are switched on. Prices, billing, payments,
  orders and invoices stay out of scope (`CLAUDE.md`, rule 7). A package name is only a label for a set of modules.
- **One codebase:** every release carries the code of every module; the entitlements switch routes, menus, permissions and scheduled tasks of a module
  on or off, so the migrations and the schema are the same for every customer.
- Replaces the note of `erp-direction.md` section 8 ("whether modules can be switched on per group is not decided").

Open questions (to answer before coding, one at a time):

1. ~~Is ONESIS Core always included~~ **answered 2026-10-16: yes, Core is in every package** (it holds the IAM, Organization, Approval, Platform and
   Notification that the other modules need; it is not sold separately).
2. ~~Dependencies between modules~~ **answered 2026-10-16: fixed dependencies, and an entitlement that breaks them is refused when it is set.** Workforce,
   Talent and Employee need Core; Career needs Talent (jobs and applications); Employee needs Talent (hires). Talent without Workforce is valid
   (`recruitment.requisition_required` is switched off, `erp-direction.md` section 8). **Changed 2026-10-31 (user, gap A1): Talent and Career are in
   every package like Core, and Workforce needs Talent**; a package without Talent or Career no longer exists, and their contexts are not gated. What
   a licence decides: Workforce, Employee, psychological tests, tele-interview.
3. ~~How an entitlement reaches an installation~~ **answered 2026-10-16: a licence file signed by ONESIS** (the list of modules and a validity period),
   verified offline by the application with a public key. Whether SaaS uses the same file is question 4.
4. ~~Does it apply to SaaS too~~ **answered 2026-10-16: yes, per group.** Each group of the SaaS installation has its own entitlement (a signed licence
   file per group kept by the installation), so one layer of code serves both editions.
5. ~~The granularity~~ **answered 2026-10-16: modules plus specific add-ons.** An add-on is a part of a module licensed separately; the entitlement file
   lists modules and add-ons. Which parts are add-ons is question 6.
6. ~~Which parts are add-ons~~ **answered 2026-10-16: psychological tests and tele-interview** (both of Talent). The marketplace features of Career and
   candidate search with the talent pool stay inside their modules. Psychological tests keep their company setting `assessment.psychological_enabled`
   on top of the add-on. All questions of section 8 are answered.

### Decided 2026-10-31 for coding (user), and the order of work

Four gaps found when the entitlement was prepared for coding (the hand-off to Employee, `erp-employee-handoff.md`, needs the module switch). All answered:

7. **External tenants** (companies without a group in the SaaS installation) get **Core, Talent and Career** with no licence file. Workforce and Employee exist only for **internal tenants** (a group with a licence), as `erp-direction.md` already says. The two add-ons (psychological tests, tele-interview) need a licence that lists them, so an external tenant has neither.
8. **After the licence expires:** a **grace period of 30 days** in which everything works, then **read-only mode**: the module's routes refuse a change with 409 `module-expired` and still answer reads and exports, so no data is lost. Platform admins see a warning before the end and during the grace period.
9. **Without a licence file:** ~~outside production (`APP_ENV` other than `production`)~~ **changed 2026-10-31 (gap A5): while `LICENCE_ENFORCED` is false (default true; false only on our own development and staging sites)** **every module is on**; the automated tests use a fake provider of the entitlements. In production an installation without a file has **Core only** (and the external tenants of item 7 keep Talent and Career). **Changed 2026-10-31 (gap A1): Core, Talent and Career** for every tenant, since they are in every package.
10. **Delivery:** the platform admin **uploads the file per group in the Console**; it is verified when uploaded, and the payload and its signature are kept in the database (`platform_licences`). A self-hosted installation has one group, so one upload.

Technical choices (no question needed): the file is a JSON payload (`schema_version`, `licence_id`, `group_uuid` (changed 2026-11-02, gap A7), `modules`, `add_ons`, `valid_from`, `valid_until`, `issued_at`) with an **Ed25519** signature (PHP `sodium`); the public key is in the repository's configuration, the private key stays with ONESIS outside the repository, and the **tool that issues licences** is a separate script that is not part of a release. The dependencies of question 2 are checked when the file is uploaded (422 `licence-invalid`, `licence-dependency`). The decision point is one port in Shared, `ModuleEntitlements` (`allows(Module)`, `allowsAddOn(AddOn)`, `state()` as `active`, `grace`, `read_only` or `none`), resolved for the company through its legal entity and group.

**Order of coding (each part checked and committed on its own; migrations run by the user):**

| Part | Work |
|---|---|
| E1 | The modules and add-ons as enums, the licence value object and its checks (dependencies, validity), the signature check, the table `platform_licences`, the Console endpoints `GET/POST admin/groups/{id}/licence` with a permission `platform.licences.manage`, audit, and the issuing script outside the release; tests with a test key |
| E2 | The port `ModuleEntitlements` and its two providers (licence-based, and "everything on" outside production), the rules of items 7 to 9, and the middleware `module:<name>` with the read-only mode; applied to the routes, the permissions of the catalogue and the scheduled commands of Workforce, the add-ons (psychological tests, tele-interview) and, when it exists, Employee |
| E3 | The modules of the company in the session data so the front end hides menus and screens (Workspace, Career), and a licence panel in the Console (state, expiry, warnings) |
| E4 | Error catalogue and translations, `scramble:cache`, the demo data (a group with a licence), and the status lines of `HANDOFF.md` and `phase-8-index.md` |

The hand-off to Employee (`erp-employee-handoff.md` section 9) then needs no interim switch: **E1 and E2 come before its part 1**, and its `employee.module_enabled` setting is dropped.

**Names the code does not know, and adding a module (decided 2026-10-31, user).** A licence file issued for a newer release may name a module or add-on this release does not have. The upload **accepts it and ignores the unknown names**, and the Console panel marks them "not in this version", so a self-hosted customer can renew the licence before upgrading the application; the signature covers the whole payload, so the file stays valid. `schema_version` lets a later change of the file format keep reading older files. A file with an unknown `schema_version` higher than the release supports is refused (422 `licence-version-unsupported`).

To add a module: (1) a case in `Module` (or `AddOn` for a part licensed separately); (2) its dependencies in the one table of the code, which the upload checks; (3) the middleware `module:<name>` on its routes, its permissions in the catalogue (hidden while the module is off) and its scheduled commands skipped; (4) say whether external tenants get it by default (they do not unless listed); (5) the menus and screens of the front end read the module list of the session data. A licence file issued before the module existed does not entitle it; a new file does.

### Gaps found 2026-10-31 (before coding E1 to E4)

A check of the design against the code (nothing of E1 to E4 is coded yet). Group A is decided with the user one at a time, before coding (A1 to A6 decided 2026-10-31, A7 to A10 on 2026-11-02); B corrects the design to the code; C is settled while coding and recorded here.

**A. Decisions needed**

| # | Gap | Status |
|---|---|---|
| A1 | E2 gates Workforce, the add-ons and Employee only. Question 2 allows a package without Talent (Workforce needs Core only) and without Career, but no gating of the Talent contexts is planned, and Career (JobSeeker, Training, Newsletter, Campaign, Review) is public and installation-wide, so it has no company to resolve; what "Career off" means for one group of the shared SaaS portal is not decided. | Decided 2026-10-31 (user): **Talent and Career are in every package** (Workforce needs Talent, question 2 and item 9 changed), so neither is gated; the public site keeps following the installation type and the portal settings. |
| A2 | The entitlement is resolved "for the company through its legal entity and group", from the actor. That does not hold for the candidate's routes of an add-on (`me/assessment/...`, joining a video interview: the company comes from the invitation or the application), for the group routes `organization/workforce/*` and `organization/requisitions*` (they start from the group), and for the provider routes (Portal Mitra) of psychological tests. | Decided 2026-10-31 (user): **gated on every side.** The company's routes use the middleware `module:<name>` (company from the actor); the group routes of Workforce resolve the group of the member's group role; the candidate's and the provider's use cases ask the port `ModuleEntitlements` for the company that owns the invitation or the interview, in the application layer. |
| A3 | Read-only mode refuses every change, also the candidate's: a psychological test or video interview already under way cannot be finished after the grace period. | Decided 2026-10-31 (user): **what started may finish.** An invitation or interview created before the add-on turned read-only can still be taken, joined and reported by the candidate and the provider until its own deadline; only new ones are refused (409 `module-expired`). |
| A4 | Only expiry is designed. A new licence that drops a module turns it to `none`, so its data can no longer be read or exported (against item 8, "no data is lost"); removing or revoking a licence is not designed. | Decided 2026-10-31 (user): **a module once licensed and later dropped is read-only**, as after the grace period (reads and exports answer, changes 409 `module-expired`, A3 applies to add-ons); `none` (404) only for a module the group never had. No delete of a licence: a new upload replaces it. So the installation keeps which modules a group ever had (C3). |
| A5 | "Everything on outside production" lets a self-hosted customer, who holds the `.env`, switch every module on with `APP_ENV=staging`; the public key in the configuration and the server clock (grace period) are in the customer's hands too. Either accept and write down that the licence is a deterrent, or use a switch of its own instead of `APP_ENV`. The introduction of this document still says "licence enforcement stay out of scope", which E2 contradicts. | Decided 2026-10-31 (user): **a switch of its own**, `LICENCE_ENFORCED` (default true), replaces `APP_ENV` in item 9; the automated tests keep the fake provider. The offline licence is written down as a **deterrent, not copy protection**: a self-hosted customer can still change the public key or the clock. The introduction is corrected. |
| A6 | An installation of type `multi` / `independent` has no group, so all its companies are external tenants (item 7): a self-hosted marketplace customer can never have Workforce, the add-ons or Employee. Intended or not? | Decided 2026-10-31 (user): **intended.** A marketplace has Core, Talent and Career only; licensing add-ons per company is taken up only when a customer asks. |
| A7 | The payload names the group by its id, and every database starts its groups at `1`: a signed licence of one self-hosted customer (or, with the bridge model of `multi-tenancy.md`, of one tenant) is valid for another. The signature proves who issued the licence, not for whom. | Decided 2026-11-02 (user, option B of four: tenant slug, group UUID, both, none): **bind to the group's UUID** (`organization_groups.uuid`, unique, set at creation and filled for existing groups; new migration in E1); the payload carries `group_uuid`; the upload refuses a UUID that is not a group of this installation or tenant. The UUID moves with the database when a tenant goes self-hosted and does not change with the slug, the name or the domain. |
| A8 | With the bridge model (`multi-tenancy.md`) the platform admin of a tenant is the customer's operator, not ONESIS, so "the platform admin uploads the file" (item 10) no longer means ONESIS. | Decided 2026-11-02 (user): **the licence stays in the tenant database**; the tenant's Console upload stays, and ONESIS also installs it with `tenant:licence <slug> <file>`. The UUID of A7 keeps a licence on its own group. |
| A9 | The expiry warning of C2 reached only the Console of the installation; with many tenants ONESIS has no overview. | Decided 2026-11-02 (user): **`tenant:list` shows the licensed modules and the expiry per tenant, and a warning goes to `platform.operations_email`**; the tenant's Console warning stays for the customer. |
| A10 | `LICENCE_ENFORCED` (A5) is one `.env` value for the whole deployment: with the bridge model the tenant `demo` on a production deployment would be locked too. | Decided 2026-11-02 (user): **`.env` false: no tenant is enforced; `.env` true: every tenant is enforced except one whose landlord record (`tenancy_tenants.licence_enforced`) is false**, set only by ONESIS with `tenant:licence-enforcement <slug> off`. On self-hosted the customer holds the landlord; the licence stays a deterrent (A5). |

**B. Design corrected to the code**

- B1. Workforce has no scheduled commands. What keeps running while it is off is `approvals:send-reminders` (Core) for plan and requisition steps, and the Assessment commands (reminders, expiry of invitations) for psychological tests.
- B2. The approvals inbox (`company/approvals`, Core, not gated) can still approve a plan or a requisition while Workforce is off or read-only, and `Workforce\Infrastructure\Listeners\ApplyApprovalOutcome` then writes Workforce data. Decided with A4: while Workforce is read-only, a waiting step of a plan or a requisition cannot be decided (409 `module-expired`) and the reminders skip it; it continues when the module is active again.
- B3. With Workforce off and `recruitment.requisition_required` on, `RequisitionGate::isRequired` blocks every new job. The gate must read the setting as off while the module is off, instead of the customer switching it off (`erp-direction.md` section 8).
- B4. Hiding permissions "in the catalogue" is not enough: the permissions a member already holds (`GET company/me`, group permissions) and the permission presets holding `workforce.*` must be filtered too (front-end rule 20).
- B5. Like psychological tests, tele-interview needs both the add-on and its settings `interview.video_enabled` / `interview.voice_enabled` (overridable per company). Whether the recorded video answer of an assessment (J7) belongs to the add-on tele-interview is not decided.
- B6. "16 platform permissions" (`erp-governance.md` section 1, section 3 of this document: "self-hosted adds none") changes with `platform.licences.manage`.

**C. Settled while coding**

- C1. The answer for a module that is not licensed (state `none`) has no code; the existing pattern is 404 with a code (`groups-unavailable`), so `module-unavailable`.
- C2. The expiry warning: how many days before, through what (Console panel only, or a notification or email from a scheduled command), and to whom; in SaaS the platform admin is ONESIS, so the customer's group admin sees nothing.
- C3. Licence history (one row per group or a history; it must give, per group and module, the day the module first became active: `erp-employee-handoff.md` section 11, gap A3), replacing a licence with an overlapping validity, rotation of the public key (more than one key).
- C4. A cache of the resolved entitlements per request, cleared on upload.
- C5. The Console licence panel has no page ID in `frontend.md` (D29); E1 to E4 are not in `phase-8-index.md`.
- C6. The dependency check of question 2 does not cover the add-ons (an add-on without Talent).
- C7. `single` keeps a group (`organization_legal_entities.group_id`), so the resolution holds; `admin/groups/{id}/licence` must stay reachable in Console on `single`.
