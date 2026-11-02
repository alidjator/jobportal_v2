# Multi-tenancy: many customers in one deployment (proposal of 2026-11-02, all questions answered the same day, not coded)

Research of the current project and a proposed order of work. Nothing here is decided yet: section 7 holds the questions, section 6 the proposed steps.
This is a design document only; coding happens in other sessions once the questions are answered.

## Decisions

- 2026-11-02: **the bridge model**: one deployment (one API process, one set of Nuxt processes) serves every customer; each tenant keeps its own
  database and file folder; the host picks the tenant. Changes decision 1 of section 2 for the process, keeps it for the data (decision gate, decided
  by the user; [Models](#4-models), question 1 of [section 7](#7-questions-all-answered-2026-11-02)).
- 2026-11-02: **one tenant = one customer**: a group with all its legal entities, or one single company; the marketplace is a tenant of its own (decided
  by the user; [section 5.1](#51-the-tenant), question 2).
- 2026-11-02: **one PostgreSQL database per tenant**, not one schema per tenant (decided by the user; [Models](#4-models), question 6).
- 2026-11-02: **started now**, not after the PKP go-live (decided by the user against the recommendation; [section 6](#6-order-of-work-proposed-each-step-its-own-coding-session-after-the-questions-are-answered), question 7).
- 2026-11-02: **built in Laravel now**, rebuilt during the backend migration to Spring Boot (decided by the user; question 8).
- 2026-11-02: **a subdomain per tenant for every application**: Career, Workspace and Console on the Career pattern, the API on
  `<slug>.api-karir.satu.solutions`; the host picks the tenant (decided by the user; [section 5.5](#55-hosts-and-the-front-end), question 3).
- 2026-11-02: **one PostgreSQL role per tenant database**, its password derived from a secret in `.env`, never stored in the landlord (decided by the
  user; [section 5.2](#52-the-landlord), question 4).
- 2026-11-02: **three more test databases** for the tenancy suite, `jobportal_v2_test_landlord`, `jobportal_v2_test_t1`, `jobportal_v2_test_t2`; the test
  guard allows them next to `jobportal_v2_test` (changes the rule of 2026-10-06; decided by the user; [section 5.6](#56-tests), question 5).
  All questions of section 7 are answered; next is step T1 of [section 6](#6-order-of-work-proposed-each-step-its-own-coding-session-after-the-questions-are-answered).

## 1. What "tenant" already means in this project

The word is in use, at a level **below** the installation:

| Level | Today | Where |
|---|---|---|
| Installation | one API process, one database `jobportal_v2`, one storage folder, one `.env`, one set of Nuxt processes | `career-modes.md` 6, `self-hosted.md` 1 |
| Installation type | `single`, `multi`/`group`, `multi`/`independent` (marketplace), read from `platform_portal_identity` | `erp-installation-type.md` |
| Group | `organization_groups`; many groups are allowed by the Organization schema (every Organization table is keyed by group) | `erp-organization.md` 1 |
| Tenant | a `company_companies` row; **internal** when linked 1:1 to a legal entity, **external** otherwise | `erp-organization.md` 2, `Shared\Application\Company\TenantRegistry` |

So "multi-tenant" inside one installation exists already: several companies (and several legal entities of one group) live in one database,
separated by `company_id` / `group_id`. What does **not** exist is several **customers** (each with its own operator, brand, accounts and
settings) in one deployment.

## 2. Decisions that bind this work

1. **2026-10-25, one installation per company** (`career-modes.md` 6): a portal has its own API process, database, files and accounts; "the
   shared-database variant with a lock across companies is **not built**". PKP's portal and a client's portal share nothing.
2. **2026-10-16, one installation = one customer, one group per installation** (`self-hosted.md` 1); the customer runs the daily operation, ONESIS
   enters only through audited support access (`self-hosted.md` 3, `organization_support_grants`, `Actor::forSupport`).
3. **2026-10-28, one account per installation** for a job seeker (`erp-installation-type.md` 4.3).
4. **2026-10-16, entitlements per group** through a signed licence file, for SaaS too (`self-hosted.md` 8).
5. **2026-10-16, backend migration to Spring Boot** after the ERP talent acquisition go-live, incremental, nginx routes by path, one shared schema
   (`spring-boot-migration.md`). Whatever tenancy layer is built in Laravel must be rebuilt there.
6. **Subdomains first**: `<company>.karir.satu.solutions` with one wildcard certificate, the customer's own domain later (`career-modes.md` 2).

## 3. How isolation works today (findings)

- **Between companies, in application code only.** The `Actor` carries the membership (company and role); repositories filter by `company_id`
  explicitly (about 156 `where('company_id' ...)` in `src`); there is no global scope and no database-level guard (no row-level security).
  Group reads go through `group.recruitment.view` (`Actor::readingForGroup`).
- **Installation-wide state with no customer key** (one copy per installation; a shared deployment must separate each of these):

| Kind | Tables or values |
|---|---|
| Accounts | `identity_users` (job seekers and company members), `identity_admins`, roles and permissions, Sanctum `personal_access_tokens` |
| Portal and settings | `platform_portal_identity`, `platform_portal_brands`, `platform_portal_drafts`, `platform_settings`, `platform_setting_overrides`, `platform_email_template_overrides`, `platform_email_suppressions` |
| Operator content | `content_*` (FAQ, announcements, legal documents), `newsletter_*`, `campaign_campaigns` |
| Job seeker data | `jobseeker_*`, `job_saved_jobs`, `jobalert_*`, `notification_*`, `matching_deliveries` (keyed by user) |
| Masters | `reference_*` (only `reference_benefits` has a company), assessment providers and psychologists |
| Operations | `platform_audit_logs`, `platform_scheduled_task_runs`, `platform_queue_alerts`, `jobs`, `failed_jobs`, cache (one prefix), storage (one disk root) |
| `.env` values that belong to a customer | the link targets (`COMPANY_*_URL`, `JOB_SEEKER_*_URL`, `NEWSLETTER_*_URL`, `CAMPAIGN_REGISTRATION_URL`, `PSYCHOLOGIST_INVITATION_URL`, `REPORT_VERIFICATION_URL`), `APP_URL`, `CORS_ALLOWED_ORIGINS`, `SANCTUM_STATEFUL_DOMAINS`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, `MOODLE_*`, `VOICE_CALLER_ID`, `ASTERISK_TRUNK_ENDPOINT`, `KARIR_PKP_*` (PKP only) |

- **Front end:** each Nuxt application (Career, Workspace, Console) is one process with one `NUXT_API_BASE_URL` (`packages/server`); the session
  cookie is per host; `NUXT_SITE_ORIGIN` may stay empty so the host decides. The server has about 1 GB of free memory (`frontend.md` 10), each Nuxt
  service is capped at 384 MB.

Consequence: under decision 1, a second customer today means a **second full deployment** (database, storage, `.env`, PHP-FPM pool, queue worker,
cron, three Nuxt services, nginx hosts). On the current server that does not fit more than one more customer.

## 4. Models

The names follow the common SaaS terms (AWS SaaS Lens): **silo** (everything per tenant), **bridge** (shared process, data per tenant), **pool** (everything shared).

| | **Silo** (status quo, automated) | **Bridge**: database per tenant, one deployment (**recommended**) | **Pool**: shared database, `tenant_id` on every row |
|---|---|---|---|
| What it is | one full deployment per customer; "multi-tenant" = a script that provisions and upgrades them | one code, one API process, one set of Nuxt processes for all customers; the host picks the customer, the customer has its own database and storage folder | one database for all customers; every table and every query carries the customer |
| Decision 1 (2026-10-25) | kept | kept for the data (own database, files, accounts); changed for the process | **reversed** |
| Code change | none in `src` | a thin tenancy layer in `Shared/Infrastructure` and a new landlord context; the domain and the 156 company filters stay as they are | every installation-wide table of section 3 gets a key, global scopes, row-level security as a safety net; every singleton becomes per customer |
| Isolation | strongest (process and data) | strong (data); a bug in the switch is the one risk, tested centrally | weakest: one missed filter leaks data across customers |
| Resources | N × (FPM pool + worker + cron + 3 × Nuxt) | one set for all; a database per customer is cheap in PostgreSQL | lowest |
| Upgrades | N deployments, N migrations | one deployment, migrations looped over the tenant databases | one migration |
| Self-hosted edition | same as SaaS | same code with one tenant; a customer moves out by dumping its database and folder | needs a separate export per customer |
| Spring Boot | nothing to rebuild | Hibernate supports database-per-tenant natively (`MultiTenantConnectionProvider`); both back ends resolve the tenant from the host the same way | the largest rebuild |
| Erasure at contract end (UU PDP) | delete the deployment | drop the database and the folder | delete rows in every table |

Variant of the bridge model: **one schema per tenant** in one database (`search_path`) instead of one database per tenant. Cheaper on connections, but backup,
restore and drop are less clean and every extension (`pg_trgm`, `unaccent`) must be reachable from every schema. Recommendation: database per tenant.

## 5. Shape of the bridge model

### 5.1 The tenant

- **One tenant = one customer**: a group (`multi`/`group`, for example PKPGROUP) or one company (`single`). The marketplace (`multi`/`independent`) is a
  tenant of its own. Inside a tenant nothing changes: companies, legal entities and the installation type work as today.
- Everything in section 3 stays in the tenant database: accounts (one account per tenant, decision 3), settings, portal identity, content, masters
  (copied by the seeder at creation), audit, scheduled task runs.

### 5.2 The landlord

- A new bounded context `Tenancy` with its own database (`jobportal_landlord`, prefix `tenancy_`): `tenancy_tenants` (slug, name, status, database name,
  created and suspended times), `tenancy_domains` (host, application: `career`, `workspace`, `console`, `api`), later the licence of
  `self-hosted.md` 8 per tenant, and the provisioning log.
- Database credentials are never stored in the landlord: one role per tenant with a password derived from a secret in `.env`, or one shared role with
  `CONNECT` granted per database (question 7.4).
- First tool: artisan commands (`tenant:create`, `tenant:list`, `tenant:suspend`, `tenant:restore`, `tenants:migrate`, `tenants:run`), run by the user like
  `migrate`. A ONESIS operator screen comes later.

### 5.3 Resolving the tenant

- **API:** a middleware reads the host, finds the tenant in `tenancy_domains`, switches the default connection, the cache prefix and the storage root,
  and puts the tenant slug in the log context. Unknown host: 404; suspended tenant: 503 with code `tenant-suspended`.
- The Application layer stays unaware of the tenant (the same rule as "no ambient current user"); an architecture test keeps `Tenancy` out of
  Domain and Application of other contexts.
- **Queue:** the job payload carries the tenant; the worker switches before handling and resets after (one worker for every tenant; the `jobs` table
  moves to the landlord). Long-lived services that memoize settings must be reset between jobs.
- **Scheduler:** each scheduled task runs once per active tenant (`tenants:run`); `GET health/scheduler` answers per tenant.
- **Mail:** the sender name and address become tenant settings; the SMTP account stays shared.

### 5.4 Configuration that moves out of `.env`

- Link targets: built from the tenant's own hosts (`tenancy_domains`) and a path template, so the 13 `*_URL` values become templates.
- `CORS_ALLOWED_ORIGINS` and `SANCTUM_STATEFUL_DOMAINS`: computed from the tenant's hosts.
- `MAIL_FROM_*`, `MOODLE_*`, `VOICE_CALLER_ID`, `ASTERISK_TRUNK_ENDPOINT`: tenant settings (Console "Pengaturan").
- `KARIR_PKP_*`: stays in `.env`, used only when importing into the PKP tenant (`legacy:import --tenant=pkp`).

### 5.5 Hosts and the front end

- Career `<slug>.karir.satu.solutions` (decided), Workspace and Console on the same pattern (question 7.3), API `<slug>.api-karir.satu.solutions`;
  wildcard certificates; a customer's own domain later through `tenancy_domains`.
- `packages/server`: `NUXT_API_BASE_URL` becomes a template (`https://{tenant}.api-karir.satu.solutions/api/v1`); the Nuxt server fills it from the host it
  serves. The browser never chooses the tenant; the Nuxt server never forwards a tenant header from the client.
- nginx: `server_name` with wildcards per application; one systemd service per application, as today.

### 5.6 Tests

- The suite keeps running on `jobportal_v2_test` as one tenant. A new tenancy suite needs a landlord test database and two tenant test databases
  (`jobportal_v2_test_landlord`, `jobportal_v2_test_t1`, `jobportal_v2_test_t2`): the guard that refuses any database but `jobportal_v2_test` has to
  allow these three (question 7.5). Main cases: host to tenant, no cross-tenant read through tokens, cache keys, files and queue jobs.

## 6. Order of work (proposed; each step its own coding session after the questions are answered)

| Step | Content | Depends on |
|---|---|---|
| T0 | Answer section 7; record the decisions here and change `career-modes.md` 6 and `self-hosted.md` 1 accordingly | – |
| T1 | Inventory: every `config()`/`env()` read and every memoized service, classified as landlord, tenant setting or shared; the list goes into this document | T0 |
| T2 | Landlord: `Tenancy` context, landlord connection and migrations, `tenant:create` (database, extensions, migrate, seed masters, first platform admin), `tenant:list`, `tenant:suspend`, `tenant:restore` | T1 |
| T3 | Resolution: API middleware, connection, cache prefix, storage root, log context, 404 and 503 answers; tenancy tests | T2 |
| T4 | Background work: tenant-aware queue, `tenants:run` for the scheduler, `tenants:migrate`, health per tenant | T3 |
| T5 | Configuration of 5.4 out of `.env`; link templates; mail sender per tenant | T3 |
| T6 | Front end: API base from the host, nginx wildcards, certificates; tried with two tenants on the dev site | T3 |
| T7 | Rollout: the current `jobportal_v2` becomes the tenant `demo` (no data migration: the landlord points at it); PKP becomes its own tenant on the legacy import; backup per tenant; `deployment-checklist.md` runbook | T4 to T6 |
| Later | ONESIS operator screens for tenants, the customer's own domain, moving a tenant to self-hosted, multiposting from a company tenant to the marketplace tenant (`career-modes.md` 10) | T7 |

Timing: **started now** (decided 2026-11-02; the proposal was after the PKP go-live and before the second customer). The bridge model needs no data migration of the existing database, so nothing is lost
by waiting; starting earlier only competes with the go-live list.

## 7. Questions (all answered 2026-11-02)

1. ~~Model~~ **answered 2026-11-02: bridge** (database per tenant, one deployment).
2. ~~Tenant unit~~ **answered 2026-11-02: one customer.** Was: one customer (a group or a single company) = one tenant (**recommended**), or one company = one tenant even inside a group.
3. ~~Hosts~~ **answered 2026-11-02: a subdomain per tenant.** Was: Workspace and Console per tenant on the Career pattern (`<slug>.workspace...`, `<slug>.console...`) (**recommended**), or one Workspace and one
   Console host with a tenant chosen at sign-in.
4. ~~Database roles~~ **answered 2026-11-02: one role per tenant.** Was: one role per tenant (**recommended**, a leak in one connection reaches one database only) or one shared role.
5. ~~Test databases~~ **answered 2026-11-02: allowed.** Was: allow the three tenancy test databases of 5.6 next to `jobportal_v2_test` (changes the rule of 2026-10-06).
6. ~~Database or schema~~ **answered 2026-11-02: database per tenant.** Was: database per tenant or schema per tenant (section 4, variant): **database (recommended)**.
7. ~~Timing~~ **answered 2026-11-02: now.** Was: after the PKP go-live and before the second customer (**recommended**), or now.
8. ~~Spring Boot~~ **answered 2026-11-02: Laravel now.** Was: build the tenancy layer in Laravel now (rebuilt later) (**recommended**, the second customer may come before the backend migration
   ends), or wait for Spring Boot.
