# Self-hosted edition (decisions of 2026-10-16, not coded)

Until now the project was designed as SaaS only (`erp-organization.md` section 1: many customer groups share one codebase and one database). This
document adds a **self-hosted** edition: one installation run on the customer's own infrastructure (also called on-premise when the server is
physical). It changes no code and no schema; it records the decisions, what blocks them today, and the order of work. Plans, billing, payments,
orders, invoices and licence enforcement stay out of scope.

## Decisions

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
- 2026-10-16: **entitlements cover modules and two add-ons: psychological tests and tele-interview** (decided by the user; section 8, questions 5 and 6).
  All questions of sections 6 and 8 are answered.
- 2026-10-16: this session only designs; coding happens in other sessions after the open questions of sections 6 and 8 are answered (decided by the user).

## 1. Meaning

- **One installation = one customer.** The same repository, the same migrations and the same bounded contexts as SaaS; no second code line, no
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
   (`recruitment.requisition_required` is switched off, `erp-direction.md` section 8).
3. ~~How an entitlement reaches an installation~~ **answered 2026-10-16: a licence file signed by ONESIS** (the list of modules and a validity period),
   verified offline by the application with a public key. Whether SaaS uses the same file is question 4.
4. ~~Does it apply to SaaS too~~ **answered 2026-10-16: yes, per group.** Each group of the SaaS installation has its own entitlement (a signed licence
   file per group kept by the installation), so one layer of code serves both editions.
5. ~~The granularity~~ **answered 2026-10-16: modules plus specific add-ons.** An add-on is a part of a module licensed separately; the entitlement file
   lists modules and add-ons. Which parts are add-ons is question 6.
6. ~~Which parts are add-ons~~ **answered 2026-10-16: psychological tests and tele-interview** (both of Talent). The marketplace features of Career and
   candidate search with the talent pool stay inside their modules. Psychological tests keep their company setting `assessment.psychological_enabled`
   on top of the add-on. All questions of section 8 are answered.
