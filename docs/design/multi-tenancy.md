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
- 2026-11-02: **the installation type belongs to the tenant**: each tenant has its own type (`single`, `multi`/`group`, `multi`/`independent`) in its own
  `platform_portal_identity`; one deployment may serve tenants of every type (decided by the user; [section 5.1](#51-the-tenant), question 9).
- 2026-11-02: **ONESIS sets the type at `tenant:create`**; the Console screen "Installation type" of a tenant shows it read only (decided by the user;
  question 9).
- 2026-11-02: **the name `installation_type` stays** in the API and the code; one tenant counts as one installation in the documents (decided by the user;
  question 10).
- 2026-11-02: **the current `jobportal_v2` becomes the tenant `demo`** and `karir.satu.solutions` stays mapped to it in `tenancy_domains`, next to the
  `<slug>` pattern (decided by the user; step T7, question 11).
- 2026-11-02: **"tenant" means the customer only; the old meaning (a company of an installation) is renamed** (decided by the user against the
  recommendation to call the customer "installation"; [section 5.7](#57-naming), question 12): tenant → company, internal tenant → group company,
  external tenant → independent company, in code, API (routes, JSON keys, error codes), front end and documents.
- 2026-11-02: **the rename is its own step T1b**, after T1 and before T2, so "tenant" never has two meanings in the code (decided by the user; question 12).
  All questions of section 7 are answered (questions 9 to 12 added and answered the same day); next is step T1 of [section 6](#6-order-of-work-proposed-each-step-its-own-coding-session-after-the-questions-are-answered).
- 2026-11-02, T1 ([section 8](#8-inventory-of-config-env-and-memoized-state-t1-2026-11-02)): **`APP_KEY` is per tenant**, derived from a master secret in `.env` plus the slug
  (the pattern of the database role password); the `Encrypter` is rebuilt on every tenant switch (decided by the user; 8.2, 8.5).
- 2026-11-02, T1: **real-time channel names carry the tenant slug** (`t.{slug}.conversations.{id}`), one Reverb server and one Reverb app for all tenants,
  names built on the API side (decided by the user; 8.5).
- 2026-11-02, T1: **Janus, Asterisk ARI and coturn are shared (landlord)**; the tenant travels in the webhook path, the SIP username and the ARI event mapping;
  `MOODLE_*`, `VOICE_CALLER_ID` and `ASTERISK_TRUNK_ENDPOINT` are tenant settings (decided by the user; 8.2, 8.5).
- 2026-11-02, T1: **`app.name` comes from the tenant's portal brand** (`platform_portal_brands`), not from `APP_NAME`; **rate limits stay landlord defaults**;
  the `Asia/Jakarta` zone of the scheduled tasks stays a shared constant (decided by the user; 8.2, 10 tasks).
- 2026-11-02, T1: **singletons that hold a database connection become `scoped`** (the settings store and the registries of Dashboard, Shared and Platform); the
  `TenantSwitcher` flushes scoped instances on a switch (decided by the user, finding F1; 8.4, 8.6).
- 2026-11-02, T1: **`GOOGLE_CLIENT_IDS` is a tenant setting** (Google OAuth lists exact origins, no wildcard, so each tenant registers its own client id; decided by the user; 8.6).

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
- **The installation type is per tenant** (decided 2026-11-02): ONESIS chooses it at `tenant:create`, the tenant's Console shows it read only; the
  name `installation_type` stays, a tenant counts as one installation.
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

### 5.7 Naming

Decided 2026-11-02 (question 12): **"tenant" is the customer** of the bridge model only. Before this decision the code and 26 documents used
"tenant" for a company of an installation (140 PHP files, `TenantRegistry`, routes, JSON keys and error codes); that meaning is renamed in step T1b:

| Old | New |
|---|---|
| tenant (a company row) | company |
| internal tenant (linked 1:1 to a legal entity) | group company |
| external tenant (no legal entity) | independent company (as `multi_kind` `independent`) |
| `GET organization/tenants` | `GET organization/companies` |
| `PUT`/`DELETE admin/groups/{id}/legal-entities/{entityId}/tenant` | `.../{entityId}/company` |
| error codes `not-an-internal-tenant`, `approval-not-internal-tenant`, `legal-entity-has-tenant`, `legal-entity-has-active-tenant`, `tenant-link-in-use`, `plan-entity-without-tenant` | `not-a-group-company`, `approval-not-group-company`, `legal-entity-has-company`, `legal-entity-has-active-company`, `company-link-in-use`, `plan-entity-without-company` |
| JSON keys `tenant`, `tenant_id` | `company`, `company_id` (each checked for a clash with an existing `company` key of the same resource) |
| `TenantRegistry`, `LinkTenant`, `UnlinkTenant`, `TenantLinkedToLegalEntity`, `TenantUnlinkedFromLegalEntity`, `GroupTenantController`, `ListGroupTenants`, `TenantRead`, `LinkTenantRequest` | `LegalEntityLinks`, `LinkCompany`, `UnlinkCompany`, `CompanyLinkedToLegalEntity`, `CompanyUnlinkedFromLegalEntity`, `GroupCompanyController`, `ListGroupCompanies`, `GroupCompanyRead`, `LinkCompanyRequest` |

The API has no client but our own front end (not live yet), so the breaking change ships with the front end in one step; the error catalogue,
`lang/id.json`, the Scramble cache and `pnpm api:refresh` follow. Database columns keep their names (`legal_entity_id`, `suspended_by_group_at`);
any column or table name with "tenant" found in T1b is listed for the user before it is renamed.

## 6. Order of work (proposed; each step its own coding session after the questions are answered)

| Step | Content | Depends on |
|---|---|---|
| T0 | Answer section 7; record the decisions here and change `career-modes.md` 6 and `self-hosted.md` 1 accordingly | – |
| T1 | Inventory: every `config()`/`env()` read and every memoized service, classified as landlord, tenant setting or shared; the list goes into this document (**done 2026-11-02, [section 8](#8-inventory-of-config-env-and-memoized-state-t1-2026-11-02); one item left for T5**) | T0 |
| T1b | Rename the old meaning of "tenant" (section 5.7): API, front end, documents, error catalogue and translations | T1 |
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
9. ~~Who sets the installation type~~ **answered 2026-11-02: ONESIS at `tenant:create`**; the tenant's Console screen shows it read only. Was: the
   tenant's platform admin in Console, as today, or ONESIS at creation (**recommended**).
10. ~~Name~~ **answered 2026-11-02: `installation_type` stays.** Was: rename it now that it is per tenant, or keep it (**recommended**).
11. ~~The current site~~ **answered 2026-11-02: `jobportal_v2` becomes the tenant `demo`, `karir.satu.solutions` stays mapped to it.**
12. ~~Name of the customer level~~ **answered 2026-11-02: "tenant", and the old meaning is renamed** (section 5.7). Was: call the customer "installation" and keep the old "tenant" (**recommended**), rename the old meaning, or qualify it as "company tenant".

## 8. Inventory of `config()`, `env()` and memoized state (T1, 2026-11-02)

Read-only survey of `src`, `app`, `config`, `routes` and `bootstrap`; no behaviour changed. Classes: **L** = landlord (one value for the installation, same for
every tenant), **T** = tenant (differs per tenant: a tenant setting, a value derived from the host or the slug, or a per-tenant secret), **S** = shared code
constant (identical for everyone, no switch needed).

### 8.1 What the survey found

- **`env()` is read only inside `config/`** (16 files); `src`, `app`, `routes` and `bootstrap` never call it. So `.env` can only reach the code through
  `config`, and one switch point (the config repository) covers every value.
- **`config()` / `Repository::get()` in `src` and `app`: 8 direct `config()` calls and about 30 repository reads** (several through the `service()` and `setting()` helpers of two providers), listed in 8.3. Most are inside `bind()` closures, which run again on
  every resolve, so a value set at tenant switch is picked up. Three are read when a process boots and stay (8.4, F2).
- **Memoized state:** 1 singleton store with an in-memory copy (`SettingsStore`), 9 singleton registries that hold objects with an injected database connection
  (8.4), 6 cache users keyed without a tenant (8.4), and framework managers that remember their first resolve (8.5).
- **Code constants (S):** `LocationCatalog::$levels`, `MasterCatalog::$definitions`, `PermissionCatalog`, `SettingsCatalog`, `ApprovalSubjectCatalog`,
  `EmailTemplateCatalog`, `NotificationPreferenceCatalog` are built from code only (no database, no config); they do not leak. The overrides live in tables.

### 8.2 Every `env()` value, by config file

| Config file | Keys | Class | Note |
|---|---|---|---|
| `app.php` | `APP_ENV`, `APP_DEBUG`, `APP_LOCALE`, `APP_FALLBACK_LOCALE`, `APP_FAKER_LOCALE`, `APP_MAINTENANCE_*`, cipher, `timezone` (UTC, literal) | L | maintenance is installation-wide |
| `app.php` | `APP_URL` | T | built from the tenant's API host |
| `app.php` | `APP_NAME` | T | replaced by the portal brand name of the tenant (decided, section "Decisions"); `config('app.name')` is read in 3 places (8.3) |
| `app.php` | `APP_KEY`, `APP_PREVIOUS_KEYS` | T | per tenant, derived from a master secret and the slug (decided); the master secret is L |
| `app.php` | 13 link templates: `COMPANY_INVITATION_URL`, `PSYCHOLOGIST_INVITATION_URL`, `REPORT_VERIFICATION_URL`, `CAMPAIGN_REGISTRATION_URL`, `JOB_SEEKER_ACTIVATION_URL`, `COMPANY_ACTIVATION_URL`, `JOB_SEEKER_SIGN_IN_URL`, `JOB_SEEKER_PROFILE_URL`, `COMPANY_APPLICATION_URL`, `COMPANY_APP_URL`, `JOB_SEEKER_APPLICATION_URL`, `NEWSLETTER_CONFIRM_URL`, `NEWSLETTER_UNSUBSCRIBE_URL` | T | templates over the tenant's own hosts (5.4) |
| `database.php` | `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_CHARSET`, `DB_COLLATION`, `DB_SSLMODE`, `DB_URL`, `DB_PREFIX`, `DB_FOREIGN_KEYS` | L | the landlord connection and the template of every tenant connection |
| `database.php` | `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | T | database name and role from `tenancy_tenants`; password derived from the master secret (decided) |
| `database.php` | `KARIR_PKP_DB_*`, `legacy_import.php` `KARIR_PKP_FILES_ROOT` | L | only for `legacy:import --tenant=pkp` (5.4) |
| `database.php` | `REDIS_*` (host, port, password, client, prefix, retries) | L | one Redis; separation is by key prefix (below) |
| `cache.php` | `CACHE_STORE`, `DB_CACHE_*`, `REDIS_CACHE_*`, `MEMCACHED_*`, `DYNAMODB_*`, `CACHE_STORAGE_*` | L | the store |
| `cache.php` | `CACHE_PREFIX` (default from `APP_NAME`) | T | prefix = tenant slug, set at switch |
| `session.php` | `SESSION_DRIVER`, `SESSION_LIFETIME`, `SESSION_SECURE_COOKIE`, `SESSION_SAME_SITE`, `SESSION_*` flags, `SESSION_CONNECTION`, `SESSION_TABLE`, `SESSION_STORE` | L | the API is token-based; the Nuxt cookie is per host already |
| `session.php` | `SESSION_DOMAIN` | T | the tenant host (empty = current host is enough) |
| `sanctum.php` | `SANCTUM_STATEFUL_DOMAINS` | T | the tenant's hosts (5.4) |
| `sanctum.php` | `SANCTUM_TOKEN_PREFIX` | L | |
| `cors.php` | `CORS_ALLOWED_ORIGINS` | T | the tenant's hosts (5.4) |
| `queue.php` | every key (`QUEUE_CONNECTION`, `DB_QUEUE_*`, `QUEUE_BULK_RETRY_AFTER`, `REDIS_QUEUE*`, `SQS_*`, `BEANSTALKD_*`, `QUEUE_FAILED_DRIVER`) | L | `jobs` and `failed_jobs` move to the landlord (5.3) |
| `mail.php` | `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_SCHEME`, `MAIL_URL`, `MAIL_EHLO_DOMAIN`, `MAIL_SENDMAIL_PATH`, `MAIL_LOG_CHANNEL`, `POSTMARK_MESSAGE_STREAM_ID` | L | the SMTP account is shared (5.3) |
| `mail.php` | `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | T | tenant settings; read by `InterviewCalendar` and by every mailable through Laravel (8.5, mail manager) |
| `filesystems.php` | `FILESYSTEM_DISK`, `AWS_*`, `AWS_BUCKET`, `AWS_ENDPOINT`, `AWS_URL`, `AWS_USE_PATH_STYLE_ENDPOINT` | L | the disk |
| `filesystems.php` | disk `root` (storage path, `APP_URL` for the public URL) | T | root = tenant folder; public URL from the tenant host |
| `logging.php` | all (`LOG_CHANNEL`, `LOG_LEVEL`, `LOG_STACK`, `LOG_SLACK_*`, `PAPERTRAIL_*`, `LOG_SYSLOG_FACILITY`, `LOG_DAILY_DAYS`, ...) | L | the tenant slug goes into the log context, not into the config |
| `broadcasting.php` | `BROADCAST_CONNECTION`, `REVERB_APP_*`, `REVERB_HOST/PORT/SCHEME`, `PUSHER_*`, `ABLY_KEY`, `MERCURE_*` | L | one Reverb app (decided); tenant separation is in the channel names (8.5) |
| `reverb.php` | all (`REVERB_SERVER_*`, `REVERB_APP_*` limits, `REVERB_ALLOWED_ORIGINS`, `REVERB_SCALING_*`, `REDIS_*`) | L | `REVERB_ALLOWED_ORIGINS` must list the wildcard of every tenant host |
| `services.php` | `POSTMARK_API_KEY`, `RESEND_API_KEY`, `AWS_*`, `SLACK_BOT_*` | L | |
| `services.php` | `JANUS_*` (http, admin, admin secret, api token, ws, webhook secret, converter, recordings dir and path), `ASTERISK_ARI_URL/USER/PASSWORD`, `ASTERISK_WSS_URL`, `ASTERISK_SIP_DOMAIN`, `TURN_URLS`, `TURN_SECRET` | L | shared infrastructure (decided); the tenant travels in the webhook path and the SIP username (8.5) |
| `services.php` | `MOODLE_BASE_URL`, `MOODLE_TOKEN`, `MOODLE_COURSE_URL`, `VOICE_CALLER_ID`, `ASTERISK_TRUNK_ENDPOINT` | T | tenant settings (5.4, decided) |
| `services.php` | `GOOGLE_CLIENT_IDS` | T | tenant setting (decided; 8.6) |
| `security.php` | `API_RATE_LIMIT_USER`, `API_RATE_LIMIT_GUEST` | L | defaults for every tenant (decided) |
| `scramble.php` | `API_DOCS_ENABLED`, `API_VERSION`, `API_CONTACT_EMAIL`, `APP_DEBUG`, `SCRAMBLE_DEV_TOOLS` | L | `API_CONTACT_EMAIL` may become a tenant value later; not now |
| installation type (`platform_portal_identity`, read by the four `Portal*Feature` classes) | per tenant row | T | decided 2026-11-02; lives in the tenant database, no config involved |
| `auth.php`, `permission.php` | guards, providers, models (literals, no `env`) | S | |

### 8.3 Every `config()` or repository read in `src` and `app`

| Where | Key | Class | Resolved when |
|---|---|---|---|
| `Shared/Interface/Http/RateLimit/ApiRateLimit` (2) | `security.rate_limit.user`, `.guest` | L | per request |
| `Shared/Infrastructure/Queue/QueueName` | `queue.connections.bulk.driver` | L | per call |
| `Shared/Interface/Http/ArchitectureOverviewController`, `Shared/Infrastructure/Documentation/ApiDocumentation`, `AppServiceProvider::boot` | `scramble.*` | L | per request; `ApiDocumentation::configure` is a boot-time static (L, no leak) |
| `Recruitment/Interface/Http/Resources/InterviewResource` | `services.asterisk.caller_id` | T | per request |
| `Recruitment/Interface/Http/Middleware/VerifyJanusWebhook` | `services.janus.webhook_secret` | L | per request; one secret for every tenant (8.5) |
| `RecruitmentServiceProvider` (`bind`) | `services.janus.*` (6), `services.asterisk.*` (8), `services.turn.*` | L | per resolve |
| `RecruitmentServiceProvider` (`bind`) | `app.name`, `app.key`, `app.company_app_url`, `app.company_application_url`, `app.application_url`, `mail.from.address` | T | per resolve |
| `Recruitment/.../AriEventListener`, `VoiceEventStream` (`bind`) | `services.asterisk.ari_*` | L | a daemon (`php artisan` listener) holds it for its whole life (8.5) |
| `TrainingServiceProvider` (`bind`) | `services.moodle.url`, `.token`, `.course_url` | T | per resolve |
| `IdentityServiceProvider` (`bind`) | `app.name` (Google 2FA issuer), `services.google.client_ids` | T | per resolve |
| `CompanyServiceProvider` (`bind`) | `app.name` (`CompanyEmailFrame`) | T | per resolve |
| `Newsletter/Infrastructure/HmacUnsubscribeTokens`, `Job/Infrastructure/HashedVisitorKeys`, `Recruitment` `HmacJoinTokenFactory` | `app.key` | T | per resolve |
| `Newsletter/Infrastructure/ConfiguredNewsletterLinks` | `app.newsletter_*_url` | T | per resolve |
| `Company/.../ConfiguredInvitationLinkBuilder`, `Assessment/.../ConfiguredInvitationLinkBuilder`, `Campaign/.../ConfiguredCampaignLinks`, `Assessment/.../ConfiguredReportVerificationLinks`, `JobSeeker/.../MailProfileReminderSender` | `app.invitation_url`, `app.psychologist_invitation_url`, `app.campaign_registration_url`, `app.report_verification_url`, `app.profile_url` | T | per resolve |
| `Messaging/Infrastructure/Presence/ReverbCompanyPresence` | `broadcasting.default` | L | per resolve; answer memoized per instance, per request |
| `LegacyImport` (`DatabaseLegacySource`, provider) | `database.connections.legacy`, `database.default`, `legacy_import.*` | L | tool, `--tenant=pkp` |
| `Shared/Infrastructure/Testing/*Command` (2) | `database.default` | L | test tooling; adapts to the tenancy test databases (5.6) |
| `Identity/Infrastructure/Mail/ConfigActivationLinks` | `app.company_activation_url`, `app.job_seeker_activation_url`, `app.job_seeker_sign_in_url` | T | per resolve; same rule as the other links (5.4) |

### 8.4 State kept in memory, and the keys that carry no tenant

| What | Where | Problem on a worker or a second tenant | Class and action |
|---|---|---|---|
| `SettingsStore` singleton (`DatabaseSettingsStore`) | `PlatformServiceProvider::$singletons` | holds `$memo` **and** a `Connection` captured in its constructor; a queue worker would keep tenant A's settings and connection for tenant B's job. The cache key `platform:settings` has no tenant. | T. F1; make it `scoped`; cache prefix per tenant |
| Registries (singletons) | `DashboardCards`, `TaskProviders`, `ApplicantTaskSources`, `ApplicantAgendaSources`, `CalendarSources`, `AnalyticsReports`, `AnalyticsRollups` (Dashboard), `CompanyLocationUsages` (Shared), `EmailEventParsers` (Platform) | each context adds objects (cards, sources, readers) that take a `ConnectionInterface` in their constructor; the registry is built once, so those objects keep the connection of the first tenant (checked on `OperationsCard` and `CompanyVerificationQueueCard`; the others follow the same pattern and are audited in T3) | T. F1 |
| `DatabasePortals` | bound, constructor calls `$database->connection()` | safe while it is resolved per use; unsafe if a singleton keeps it | T. F1 |
| `PortalGroupFeature`, `PortalSingleCompany`, `PortalCompanyBlockingFeature`, `PortalVerificationFeature` | bound; each memoizes one bool per instance | safe per resolve; stale only if a long-lived holder keeps the instance | T. check holders in T3 |
| `ReverbCompanyPresence::$known`, the `$rendered` of the 20 mailable and notice classes | per instance | safe (instance lifetime = one job or request) | S |
| `AttemptSessionToken`, `RequestContext` | `scoped` | flushed per request and per queued job | S |
| `RateLimiter::for(...)` closures (22) | provider `boot` | keys are `ip`, user id or company id inside the cache; with a prefix per tenant they separate; without it, user id 5 of tenant A and tenant B would share a counter | T through the cache prefix |
| Cache keys without a tenant | `platform:settings`, `content.announcements.live.{audience}`, `salary-insight:{hash}`, `training:enrollments:{hash}`, review summary key by company id, dashboard keys | collide across tenants on a shared Redis | T through the cache prefix (one change, no key edits) |
| `pg_advisory_xact_lock(hashtext('recruitment-video-rooms'))` and the voice twin | `DatabaseVideoRoomRepository`, `DatabaseCallRepository` | an advisory lock is per database, so it serialises one tenant only, while the Janus and Asterisk **capacity** is shared by all tenants | F4: the capacity check moves to the landlord |
| Code catalogs | see 8.1 | none | S |
| Static, boot-time | `LocationCatalog::$levels`, `MasterCatalog::$definitions`, `ErrorCatalog::$self`, `ApiDocumentation::configure` | none | S / L |
| `LegacyImport` (`SkillTextMatcher::$index`, `DatabaseLocationLookup::$cache`, `DatabaseLegacySource`) | tool | one run, one tenant | S, out of scope |

### 8.5 Other places the tenant must reach (found while reading)

- **Framework managers remember their first resolve.** On a tenant switch (and reset after a job) the switch must: purge the default database connection
  (`DatabaseManager::purge`) or point it at the tenant database; change `cache.prefix` and forget the cache store (`CacheManager::forgetDriver`); forget the
  filesystem disks (`FilesystemManager::forgetDisk`) after changing the disk `root`; forget the mailers (`MailManager::forgetMailers`, the always-from address is
  set when a mailer is first built); rebuild the `Encrypter` with the tenant key (`app.key`); reset the auth guards (cached user). All six go in one `TenantSwitcher`
  in `Shared/Infrastructure` (T3), tested with two tenants in one process.
- **`APP_KEY` per tenant (decided).** Users of `app.key`: the `Encrypter` (`AdminModel` `two_factor_secret`, `DatabaseUserTwoFactors`, `DatabaseVideoRoomRepository`
  PIN and secret, `EncryptedPortalPreviewTokens`), and four HMAC users (`HmacUnsubscribeTokens`, `HashedVisitorKeys`, `HmacJoinTokenFactory`, the verification
  code hasher). Data stays readable only with its tenant key; moving a tenant to self-hosted takes its derived key along; ending a contract can destroy the key.
  `APP_PREVIOUS_KEYS` becomes a per-tenant list for rotation.
- **Real-time channels (decided):** broadcast events name channels `conversations.{id}`, `interview-video.{id}`, `company-presence.{companyId}`, with numeric ids that
  repeat in every tenant database. Names get the prefix `t.{slug}.`; the authorisation callbacks in `MessagingChannels` and `RecruitmentChannels` keep working
  because the API checks the signed-in user in the tenant database it resolved from the host. `ReverbCompanyPresence` builds the same prefixed name. The front end
  takes channel names from the API, never builds them.
- **Webhooks and daemons have no host (decided: shared, tenant in the URL):** the Janus webhook carries only a room id, the Asterisk ARI listener only a channel
  id. The webhook route becomes `/webhooks/{tenant}/janus` (the path picks the tenant; the secret stays shared); the Janus and Asterisk side must be told the new
  URL when a room or trunk is created. The ARI listener is a single long-lived process: one listener per tenant (started by `tenants:run`) or one listener that maps
  an event to a tenant through the tenant slug carried in the SIP username. Chosen in T4. Capacity (rooms, calls) is counted across tenants (F4).
- **Queue:** jobs, 92 classes use `ShouldQueue` (69 queued listeners, 19 queued mailables, the rest jobs); none stores config values, so one
  payload field (the tenant slug) plus a job middleware that switches, and the reset after each job, covers all of them (T4). `QueueName` and
  `TemplatedMail::onConnection` read only the connection names (L).
- **Scheduler:** 43 scheduled commands in 16 provider files, all with `withoutOverlapping()->onOneServer()`; the overlap and one-server locks go through the cache,
  so the prefix must be the **landlord** prefix while the scheduler decides, and the tenant prefix while a command runs (T4, `tenants:run`). 10 tasks use the literal
  zone `Asia/Jakarta` (S).
- **Documentation and tooling:** `scramble.*` is L; `/docs/api` is served per host and shows the same document for every tenant.

### 8.6 Findings and open items

Decision gate: findings that go against Laravel or DDD practice are listed here and wait for the user's answer; nothing was changed.

- **F1 (confirmed by the user 2026-11-02, fix in T3):** singletons that hold an injected `ConnectionInterface` or `Connection` (the store, the nine registries, and what
  they hold) cannot work with a database per tenant on a long-lived worker. Proposed fix, T3: bind them with `scoped` (Laravel flushes scoped instances before
  each job and each request) and make `TenantSwitcher` flush the scoped instances on a switch inside one process. No change to the domain or the use cases.
- **F2:** `AriEventListener` and any other long-lived process read config once at start (8.3); they need a tenant at start (T4).
- **F3:** a single `JANUS_WEBHOOK_SECRET` for all tenants is acceptable because the secret authenticates ONESIS' own Janus, not a tenant; the tenant is chosen by the path.
- **F4:** the capacity checks (`recruitment-video-rooms`, the voice twin) count rooms and calls inside one database; with shared Janus and Asterisk, the count
  and its lock must live in the landlord (a table `tenancy_media_capacity` or a Redis counter). Decide in T4.
- **Closed 1 (2026-11-02):** `GOOGLE_CLIENT_IDS` is a tenant setting: Google OAuth lists exact JavaScript origins (no wildcard), so each tenant host needs its own client id.
- **Open (T5):** `API_CONTACT_EMAIL` of `scramble.php` and the Console "Pengaturan" keys that today copy an `.env` value are not yet checked one by one against
  `SettingsCatalog`; T5 does it when the 5.4 values move.
