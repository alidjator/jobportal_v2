# ONESIS Career modes: marketplace and company (designed 2026-10-25, not coded)

Extends `frontend.md` (section 7, the public application) and relates to `self-hosted.md` and `erp-direction.md`. It changes no code and no schema. Plans,
billing, payments, orders and invoices stay out of scope.

## Decisions

- 2026-10-25: **one Career application with two modes**, `marketplace` (today: every company, the portal is the operator) and `company` (decided by the
  user; [Modes](#1-modes)).
- 2026-10-25: **company mode is a job board owned and managed by one company (or one group of legal entities), with no relation to any other company**: its jobs only, its brand, its legal
  documents, no discovery of other companies (the user's definition; [Company mode](#3-what-company-mode-changes)).
- 2026-10-25: the marketplace features stay frozen (`erp-direction.md` section 1); company mode never shows them, so they are not a blocker.
- 2026-10-25: **company mode is the mode of every company portal, the internal PKP companies and each external client alike; each company has its own
  separate portal, and the portal of PKP is never joined with the portal of a client** (decided by the user; question 2 of
  [section 8](#8-open-questions) answered). Each portal shows one company, or the legal entities of one group, and nothing of any other customer.
- 2026-10-25: **one installation per company**: a portal has its own API process, database, files and accounts, built from the same code; PKP's portal and
  each client's portal share nothing (decided by the user; [section 6](#6-where-the-data-of-a-portal-lives)).
- 2026-10-25: **the company admin sets the brand in Workspace** (logo, banner, welcome text and colours from a fixed set), as proposed (decided by the user;
  [section 4](#4-brand)).
- 2026-10-25: **a subdomain of ours first** (`<company>.karir.satu.solutions`, one wildcard certificate); the company's own domain (CNAME, DNS
  verification, a certificate per host) comes after company mode is stable (decided by the user; [section 2](#2-where-the-host-and-the-mode-meet)).

- 2026-10-25: **company mode has a scope, `group` or `company`**: a portal shows one company or one group (all the legal entities of the group), never
  anything of another customer; a customer with several legal entities (PKPGROUP: PT PKP Kuliner, PT PKP Kurir, PT PKP Cleaning Service) gets a **group
  site**, with the legal entity shown on every job and a filter by it (decided by the user; [section 3a](#3a-scope-group-or-company)).
- 2026-10-25: **a group site first; a site of one legal entity only when asked** (decided by the user).

- 2026-10-25: **publishing jobs to other job boards (multiposting) is added to the design** as a later part, for both modes (decided by the user; the shape
  is a proposal, the questions are open: [section 10](#10-publishing-to-other-job-boards)).

- 2026-10-25: **`karir.satu.solutions` keeps its address and switches to company mode**: it serves the group of its own installation (the demo group today, PKPGROUP
  on the PKP installation), not the marketplace; the marketplace gets its own host when it is launched, which is not decided (decided by the user;
  [section 2](#2-where-the-host-and-the-mode-meet)).

- 2026-10-25: **the portal is configured in the application, not only in the environment** (decided by the user; [section 11](#11-portal-settings-in-the-application)):
  the identity of a portal (mode, scope, companies) by the platform admin in Console, the brand by the company admin (group admin for a group site) in Workspace,
  with the environment kept as the fallback and the override; the welcome text and slogan in Indonesian and English; a change is a draft, previewed, then published.

All questions of sections 1 to 9 are answered; [section 8](#8-open-questions) keeps them as history. Section 10 has its own open questions.

## 1. Modes

| | `marketplace` | `company` |
|---|---|---|
| Owner and operator | ONESIS | the company (or group) |
| Jobs shown | every live job of every company | the live jobs of one company, or of every legal entity of one group |
| Company pages | directory, profile, reviews (frozen parts excluded) | one page, "About us" (a group site: the group page with a page per legal entity) |
| Brand | ONESIS | the company's logo, colours, banner and welcome text |
| Legal documents | those of the portal | those of the company |
| Sign-in | job seeker; company members are sent to Workspace | the same; the Workspace link is the company's own Workspace |
| Indexing | `robots.txt` and sitemap of the portal (`frontend.md` 11.4) | the company's own sitemap, `robots.txt` and canonical addresses |

- The same pages, components and routes serve both modes (P1 to P4, J1 to J5, the apply flow, interviews, offers). The mode changes what they load, what
  they show and the brand, never the flow.
- The mode is **runtime configuration of one deployment**, not a build: `NUXT_PUBLIC_CAREER_MODE=marketplace|company` and, for `company`, the scope
  `NUXT_PUBLIC_CAREER_SCOPE=group|company` with its slug (`NUXT_PUBLIC_CAREER_SLUG`). A deployment serves one mode, so a company site is its own process and host (the pattern of
  `apps/public/deploy/`), next to the marketplace's.
- A request that cannot be one of the company's (another company's job, company page or review) answers 404 in company mode.

## 2. Where the host and the mode meet

- One host, one mode, one company or group. **`karir.satu.solutions` runs company mode** (decided 2026-10-25) for the group of the installation behind it; the
  mode is the configuration of that deployment, so switching it is a change of environment and a restart, not of code. The marketplace is a separate
  deployment on a host of its own, chosen when it is launched. Another customer's site has its own host: a subdomain of ours first
  (`<company>.karir.satu.solutions`, one wildcard certificate), the customer's own domain later (decided 2026-10-25).
- Today the API behind `karir.satu.solutions` (`api-karir.satu.solutions`) holds the demo data, which has one group, "Grup Demo Nusantara", of two legal
  entities (PT Demo Sinar Teknologi, PT Demo Arunika Ritel): the group site can be built and tried on it before PKPGROUP exists in an installation.
- The session cookie is per host (`packages/server`), so a sign-in on one site never reaches another.
- `NUXT_SITE_ORIGIN`, `hreflang` base, the canonical address, the sitemap and `robots.txt` come from the host of the deployment, not from a constant.

## 3. What company mode changes

| Page | Change |
|---|---|
| P1 Home | the company's banner, logo, welcome text and its categories; no "Lowongan terbaru" from other companies |
| P2 Search | scope `company`: `filter[company_id]` is fixed and not shown; scope `group`: the ids of the group's tenants are fixed, and "Perusahaan" filters among them; companies of other customers never appear |
| P3 Job | "Lihat profil perusahaan" leads to "About us"; "Lowongan serupa" and "Lowongan lain dari ..." list only this company's jobs (or are removed) |
| P4 Company | becomes "About us" (the company profile, locations and open jobs); on a group site the group page lists its entities, each leads to its own profile; reviews, "Ikuti" and the verification badge stay hidden |
| Header and footer | the company's brand; no link to the marketplace, no newsletter, no portal-wide legal links |
| A1 to A6, J1 to J5, apply, interview, offer | unchanged |

## 3a. Scope: group or company

- A customer with several legal entities has **one group** per installation (`self-hosted.md`); each entity is an internal tenant of it
  (`erp-organization.md`: group, legal entity, tenant). The "no relation to other companies" of company mode is between customers, not between the entities
  of one group.
- **Group site (first form):** the portal lists the jobs of every tenant of the group. Each job card and page names its legal entity (name, logo, place); the
  search has a "Perusahaan" filter by entity (not a locked filter); "About us" is the group page and lists its entities, each with a page of its own (the P4
  profile of that tenant). The brand is the group's, an entity may show its own logo on its jobs.
- **Company site (later, on request):** the scope `company` of section 1: one tenant, the filter fixed.
- An application goes to the legal entity that owns the job and stays in its pipeline as today. The apply page says it ("Anda melamar ke PT PKP Kurir"),
  because the entity that receives the application is the controller of the data, not the group; the talent pool consent stays per company. A job seeker has
  one account in the installation and sees the applications to every entity in one list; an entity sees only the applications to itself, and
  `group.recruitment.view` (read only, audited) reads across the group, as today.
- Search already takes several companies (`filter[company_id]`, up to 20 ids); a group site starts on the ids of the tenants of its group, kept as
  installation configuration. A public way to read the group and its legal entities (a group slug) is added when the site is built (section 5).

## 4. Brand

- The brand (logo, banner, colours, welcome text) is data of the company, kept by the company and shown by Career. The company profile already has a logo
  and a banner (`company/profile`); colours and the welcome text are new fields. **The company admin edits them in Workspace** (decided 2026-10-25).
- Colours come as a small fixed set (the tokens of the template theme, `packages/ui`), not free CSS, so a company cannot break contrast or the layout.

## 5. API

- Today a company's jobs are `GET jobs?filter[company_id]=`, a company's profile is `GET companies/{slug}`; company mode can start on these.
- A group site needs a public way to read a group and its legal entities (a group slug and the ids of its tenants); today only the company
  profile is public. Until then the ids are configuration of the installation.
- **The API must enforce the lock**, not only hide in the interface: a request of a company site for another company's data is refused. That needs a way to
  tell the API which company a site belongs to (a header or a token claim signed by the Nuxt server; not decided), and a rule that the answers of
  `jobs`, `jobs/{job}`, `jobs/{job}/similar` and `companies/{slug}` stay within it. Designed when the first company site is built.
- Applications made on a company site belong to that company as they do today. A portal is its own installation (section 6), so a job seeker has no
  applications on another portal.

## 6. Where the data of a portal lives

**Decided 2026-10-25: one installation per company.** A portal has its own API process, database, files and accounts, built from the same code. PKP's portal
and each client's portal share nothing, so the API lock of section 5 reduces to "the company of this installation" (for PKP, its one group); there is no
cross-company data to hold back.

- This is the shape of `self-hosted.md` ("one installation = one customer"). It is run by the customer (the self-hosted package, which waits for the Spring
  Boot migration) or by ONESIS as a **dedicated instance** (the same deployment files, a host and a database per customer); the dedicated instance does
  not wait for Spring Boot.
- The marketplace stays a separate deployment of the same application, on a host of its own (not `karir.satu.solutions`, which runs company mode); nothing of a company portal reaches it.
- Consequence for the work: the shared-database variant with a lock across companies is **not built**.

## 7. Search engines

- Each site is its own site: its own sitemap, canonical addresses and `JobPosting` data. A job shown in the marketplace and on a company site has one
  canonical address (decided with the address, section 2).
- Company mode on a development host stays `noindex` until the site goes live.

## 8. Questions (all answered 2026-10-25)

1. ~~Where the data lives~~ one installation per company (section 6).
2. ~~First customer~~ company mode serves the internal PKP companies and external clients, each as its own separate portal.
3. ~~Brand~~ the company admin, in Workspace (section 4).
4. ~~Address~~ a subdomain of ours first (section 2).

## 9. Order of work

1. Career `company` mode in the frontend with the scope `group` first (configuration, the locks and the brand of sections 3 and 3a, the legal entity on jobs
   and in the apply page, tests), run as its own deployment: first for PKPGROUP.
2. The brand fields (colours from the fixed set, welcome text) in the API and their screen in Workspace; the public read of a group and its entities.
3. The site of an installation: host and wildcard certificate, deployment files per customer (`apps/public/deploy/` parameterised by customer), the check that
   the installation holds only its company or group.
4. External clients as dedicated instances run by ONESIS; the self-hosted package when that edition is built. The scope `company` (a site of one legal
   entity) when a customer asks.

## 10. Publishing to other job boards

A company that owns its portal still wants its jobs where job seekers already look (JobStreet, Glints, Kalibrr, Indeed, Jooble, Google for Jobs). The portal of the
company stays the source of the job and the place of the application; the boards are channels. This part is designed now and built after company mode
(section 9); **the shape below is a proposal, nothing in it is decided except that it belongs to the design.**

### 10.1 Kinds of channel

| Kind | How the job reaches the board | Depends on |
|---|---|---|
| **Search engines** | `JobPosting` structured data on P3 and the sitemap (`frontend.md` 11.4); the Google Indexing API is considered after go-live | nothing outside the portal |
| **Feeds** | a public feed of the live jobs (XML or JSON) that a board or aggregator fetches (Indeed, Jooble, Talent.com style), one feed per installation, within its scope (company or group) | the board accepts a feed; some need an agreement |
| **Partner connectors** | a push or pull through the board's own API, for boards that have one | an agreement and credentials per board |
| **Links** | a share link with `ref=<channel>` that the company puts on a board by hand (`ApplicationSource` already has `shared`) | nothing |

### 10.2 What every channel follows

- **One job, one place of truth:** the portal. A board shows a summary and sends the job seeker to the job page of the portal (the canonical address of
  section 7) to apply; applications are made on the portal, with its account, its form and its pipeline. Receiving applications through a board's API is
  not planned: it duplicates the apply flow, the screening questions and the consent.
- **The company chooses per job** which channels it goes to (a "Tayangkan di" choice in the job editor, C4); the default is the portal alone. A job shown
  in the marketplace and on a company site follows section 7.
- **The state of a job is read from the job:** closing, expiring or taking down a job removes it from every feed at once and tells connectors to take it
  down; the cache lifetime of a feed is short (a closed job disappears within a minute, as on the public page).
- **Attribution:** an application records the channel that sent the job seeker (`ref=<channel>` on the link of the feed), so the company sees where its
  applicants come from (the source report of `phase-4-business-intelligence.md`).
- **Data:** only what the public page already shows leaves the installation; no contact data, no applicant data. Which board receives a job is the choice of
  the company, and the company is told which boards it has chosen (legal review).
- **Licence:** channels are a part of the module entitlements of `self-hosted.md` (an add-on or part of Career); prices and billing stay out of scope.

### 10.3 Proposed order

1. Structured data and the sitemap for every site (already planned in `frontend.md` 11.4), and `ref=<channel>` links with the source report.
2. A public feed of the live jobs of an installation, and the per-job choice in C4.
3. Connectors one board at a time, only where an agreement and an API exist.

### 10.4 Open questions

1. Which boards first? Each has its own terms; JobStreet, Glints and Kalibrr need an agreement, Indeed and similar aggregators take a feed.
2. Feed format: one neutral JSON feed plus an XML variant for the aggregators, or only what the first board needs?
3. Does the marketplace (its own host, section 2) count as a channel of a company portal (a job shown on both), and who decides: the company per job, or always?
4. Who pays or signs for a board's agreement, ONESIS for all customers or each customer for itself?

## 11. Portal settings in the application

Decided 2026-10-25 (the user agreed to the proposal and to its three recommended answers): the settings of a portal can be changed in the application.

### 11.1 What is set where

| Setting | Who changes it | Where |
|---|---|---|
| mode (`marketplace` or `company`), scope (`group` or `company`), the companies of the portal | the platform admin only (it changes the whole site) | Console |
| name, slogan (id, en), welcome text (id, en), colour preset 1 to 10 | the company admin; for a group site the group admin (`group.settings.manage`) | Workspace |
| `NUXT_API_BASE_URL`, `NUXT_SESSION_PASSWORD`, `NUXT_SITE_ORIGIN` | the operator of the installation | the environment (needed before the API can be asked) |

- The environment variables of section 2 stay as the **fallback and the override**: a deployment with no portal settings in the API behaves as it does today (the
  marketplace, or what the environment says), so stage 1 is not thrown away.
- A change is saved as a **draft**, previewed and then **published** (decided 2026-10-25, [section 11.6](#116-screens-and-a-portal-that-is-set-wrongly); shape in 11.7).

### 11.2 Shape (confirmed in 11.3)

- **No new table for the brand.** The settings system of the Platform context already layers a value for the platform, the group and the company
  (`SettingDefinition` with `companyEditable` and `groupEditable`, edited on the existing company and group settings pages, audited). The brand is a set of new
  keys: `portal.name`, `portal.tagline.id`, `portal.tagline.en`, `portal.welcome.id`, `portal.welcome.en` (text) and `portal.preset` (integer 1 to 10). Their
  labels and hints go to the two languages of Workspace.
- **The identity needs an action of its own, not a generic setting.** The companies of a portal are chosen among existing tenants (of one group for a group
  site) and the settings types have no single choice and no list of ids: `PUT admin/portal` (mode, scope, company ids) validates that the companies exist,
  are live and are of one group when the scope is `group`, and stores the values; it is audited. The Console screen is a small form (a switch, a choice, a
  company picker).
- **The logo is the logo of the main company** (the first company of the portal, or the company of the scope `company`), already stored by `company/profile`;
  no new upload. A group site with its own logo is a later addition.
- **One public read:** `GET portal` (no sign-in, cached like the public job page, one minute) answers `{ mode, scope, brand: { name, tagline, welcome, preset,
  logo_url }, companies: [{ id, slug, name, logo_url }] }`, the text of the language of the request. Without portal settings it answers the marketplace.
- **Career reads it on the server** (the plugin of stage 1 reads the profile of each company one by one; it now reads `GET portal` once, kept ten minutes in
  memory like the master lists), and the environment overrides what it reads.
- **Where the code lives:** the settings keys and the `PUT admin/portal` action in the Platform context (which owns settings and the admin panel); the public
  read joins the portal with the public company profile through a port, so Platform does not import Company (`architecture.md`).

### 11.3 Decisions on the shape (2026-10-25, the user agreed to all three proposals)

1. **The existing settings system, no new table** (the brand as keys under `portal.`; the identity by the action `PUT admin/portal`).
2. **The logo of the main company**; no upload for the portal.
3. **`GET portal`, `PUT admin/portal` and the brand keys under `portal.`**; the permission name of `PUT admin/portal` is an admin permission of the platform
   admin role, chosen when coded.

### 11.4 A company without a group

- A portal of one company needs no group: the scope is `company`, the platform admin picks the company in Console, the brand is the company's own setting
  (the layers are platform, then company), the logo is the company's logo, and "Tentang kami" leads to its profile.
- A group exists only for internal tenants (legal entities); a client with several legal entities holds its own group **in its own installation**
  (one group per installation, one installation per company). So one company means the scope `company`, and several legal entities of one group mean the
  scope `group`.
- `PUT admin/portal` refuses what does not fit: the scope `company` needs exactly one live company; the scope `group` needs live companies that are all tenants of
  the same group, and refuses a company without a group; the mode `marketplace` takes no companies.

### 11.5 Order of work

1. API: the brand keys, `PUT admin/portal`, `GET portal`, tests; the API cache (`scramble:cache`) and the generated types of the frontend.
2. Console: the screen of the identity. Workspace: the labels of the brand keys, with a colour choice for the preset.
3. Career: read `GET portal` (environment as override) instead of reading each company.

### 11.6 Screens and a portal that is set wrongly

Decided 2026-10-25 (the user):

- **The screens are detailed and carry a preview**: Console for the identity (mode, scope, companies) and Workspace for the brand (name, slogan, welcome text in two
  languages, colour preset). The preview shows the header, the home banner and a job card as the portal would draw them with the values on the screen.
- **A company of the portal that is inactive or deleted:** Career stays up, the missing company is **skipped** (its jobs and its page are gone, the others stay), and
  the **platform admin is warned**. A portal left with no company shows the notice of an empty portal, not an error page.

Decided 2026-10-25 (the user agreed to the warning; the preview was **corrected by the user the same day**):

- ~~The preview is a live panel~~ **corrected 2026-10-25: draft, then publish.** A change is saved as a **draft**, previewed on the real portal through an address
  that only the admin can open, and then **published**; it is not live until published.
- **The warning to the platform admin is a notice and a daily email**: the Console portal screen names each company that was skipped and why (inactive,
  deleted), and a daily check of the scheduler emails the operations address (`platform.operations_email`, as the scheduler alerts do) while the portal has a
  missing company, once per change. `GET portal` leaves the missing company out and says nothing else to the public.

### 11.7 Draft and publish: proposed shape (confirmed 2026-10-25)

The settings system has no draft, so this **replaces the decision "no new table" of 11.3 for the draft** (the live values stay in the settings system and the
identity as decided; only the draft is new).

- **One new table, `portal_drafts`** (Platform context): `owner_type` (`platform`, `group`, `company`), `owner_id`, `payload` (the draft values as JSON: the identity
  for the platform, the brand for a group or a company), `updated_by`, `updated_at`, unique on the owner. At most one draft per owner; saving again replaces it.
  The migration is run by the user.
- **Who drafts what:** the platform admin the identity (mode, scope, companies); the company admin the brand of the company; the group admin the brand of the
  group (`group.settings.manage`). Each publishes only his own draft; the identity and the brand are two drafts, not one.
- **Publish** validates the draft as `PUT admin/portal` and the settings pages validate today (11.4), writes the live values in one transaction, records the audit
  entry (who, from what to what), and deletes the draft. A draft that no longer validates (a company that became inactive) cannot be published, and says why.
- **Preview** is a signed, short-lived token (for example two hours) that the API makes for an authorised admin (`POST ...portal/draft/preview`) and that opens the
  portal at its address with `?pratinjau=<token>`. Career keeps the token in its own cookie for the visit, sends it to `GET portal` (`X-Portal-Preview`), and the
  API answers the portal **as the draft would make it** (the live values overlaid with the draft of that owner), `private, no-store`, never cached. The preview
  pages answer `noindex` and carry a banner "Pratinjau, belum diterbitkan".
- **The screen** (Console, Workspace): the form, "Simpan draf", "Pratinjau" (opens the portal in a new tab; showing it inside the screen is a later addition, it
  needs the portal to allow framing by the two hosts), "Terbitkan" and "Buang draf"; a notice while a draft differs from the live values.

Decided 2026-10-25 (the user agreed to all three): (1) the new table `portal_drafts` instead of a second layer of settings keys; (2) the preview opens in a new
tab first, not inside the screen; (3) the identity and the brand are separate drafts, each published by its own admin.

Section 11 is ready to code in the order of 11.5; the work starts with the API.

## 12. API plan (2026-10-25, to confirm before coding)

Written after reading the Platform code (`SettingsStore`, `StoredSettings`, `EditCompanySettings`, `EditGroupSettings`, `PublicCompanies`, `CompanyDirectory`,
`GroupDirectory`, the scheduler and its alert emails). It finds one problem with section 11.3 that the draft decision of 11.7 causes.

### 12.1 The problem: the settings system cannot hold a portal that needs a draft

- The generic pages (`company/settings`, `organization/settings`, `admin/settings`) are driven by flags on the definitions. A brand key flagged for the company
  admin would be changed there **live, with no draft**; a raw identity key (`portal.mode`, `portal.company_ids`) would be editable on the generic admin page
  **without the validation of `PUT admin/portal`**. The flags cannot hide a key from a generic page.
- So the live values of the portal move into **two small tables** next to the draft table. The settings system is not used for the portal. This replaces point 1
  of 11.3 for the live values (the brand as keys under `portal.`); points 2 and 3 stand.

### 12.2 Tables (Platform context, prefix `platform_`; the migration is run by the user)

| Table | Holds |
|---|---|
| `platform_portal_identity` | one row: `mode`, `scope`, `company_ids` (JSON), `alert_signature` (what the last warning email named), `updated_by`, `updated_at` |
| `platform_portal_brands` | per owner (`group` or `company`, unique): `name`, `tagline_id`, `tagline_en`, `welcome_id`, `welcome_en`, `preset`, `updated_by`, `updated_at` |
| `platform_portal_drafts` | per owner (`platform`, `group`, `company`, unique): `payload` (JSON), `updated_by`, `updated_at` |

Without a row the portal is the marketplace (no identity row) or has the default brand (the name of the company or of the group, the first preset, no slogan).

### 12.3 Endpoints

| Who | Endpoint | Does |
|---|---|---|
| public | `GET portal` | the portal for the language of the request: mode, scope, brand, the companies that may be shown; with `X-Portal-Preview` the portal as the draft would make it, `private, no-store` |
| platform admin (`portal.manage`, a new admin permission) | `GET admin/portal` | live identity, its draft, the warnings, the companies to pick from |
| | `PUT admin/portal/draft`, `DELETE admin/portal/draft` | save, discard |
| | `POST admin/portal/draft/publish`, `POST admin/portal/draft/preview` | publish after validation (11.4); a preview token and address |
| company admin (`company.profile`) | `GET`/`PUT`/`DELETE company/portal/draft`, `POST company/portal/draft/publish`, `POST company/portal/draft/preview`, `GET company/portal` | the brand of the company |
| group admin (`group.settings.manage`) | the same under `organization/portal` | the brand of the group |

### 12.4 Pieces

- **Application (Platform):** `PortalIdentity`, `PortalBrand`, `PortalView`; use cases `GetPortal`, `GetPortalForAdmin`, `SavePortalDraft`, `DiscardPortalDraft`,
  `PublishPortalIdentity`, `PublishPortalBrand`, `CreatePortalPreview`, and `PortalHealth` (the companies that are skipped and why).
- **Ports in `Shared`:** `PublicCompanies::cards` (already there) decides who is shown; `CompanyDirectory::summaries` names the reason (inactive, hidden, missing);
  `GroupDirectory` gets the name of a group (a new method, provided by Organization).
- **Preview token:** an encrypted payload (owner, expiry two hours) made with the application key behind a `PortalPreviewTokens` port; Platform never reads another
  context's tables.
- **Audit:** `PORTAL_IDENTITY_PUBLISHED` and `PORTAL_BRAND_PUBLISHED` in `AuditAction`, with the values before and after.
- **Daily check:** a command `portal:check` (03:30, `withoutOverlapping`, `onOneServer`) emails the operations address with the new template `platform.portal_company_missing`
  (Indonesian and English) when the set of skipped companies differs from `alert_signature`, and again when it is empty (recovered).
- **Tests:** feature tests per endpoint (permissions, validation of 11.4, draft then preview then publish, the skipped company), unit tests for the overlay of a draft and the
  warning signature; no test runs while coding (the user runs them).

### 12.5 Order of coding

1. Migration, models, repositories, `PortalIdentity`, `PortalBrand`, the validation of 11.4.
2. `GetPortal` and `GET portal` (live values only), the missing-company rule.
3. Drafts, publish, audit, for the identity (Console) and then the brand (company, group).
4. Preview token and the preview read.
5. `portal:check` and its email template.
6. Tests, `scramble:clear` and `scramble:cache`, the generated types of the frontend (`pnpm api:refresh`).

### 12.6 Built (2026-10-25)

Steps 1 to 5 of 12.5 are coded; the tests of step 6 are written and wait for the user to run them. Where the code differs from 12.4:

- The brand screens of a company and a group go through the Shared port `PortalBrandEditor` (provided by Platform): `ManageCompanyPortal` (Company, `company.profile`) and `ManageGroupPortal`
  (Organization, `group.settings.manage`) check who may act, as the settings pages do. `GET company/portal` and `GET organization/portal` need the same permission as the changes.
- `GroupDirectory` got `nameOfGroup`. The preview answer is `{token, expires_at}`; the career site builds its own address (`?pratinjau=<token>`), because the API does not know the host of the career site.
- A second template `platform.portal_recovered` carries the "recovered" email; `platform.portal_company_missing` carries the warning. Both go out in English like the scheduler alerts (`OperationsMail`).
- The Console picks companies through the existing admin company list; `GET admin/portal` returns the live identity, the draft and the skipped companies, not a list of companies to pick from.
- `GET portal` is cached a minute (public, ETag); with `X-Portal-Preview` it is `private, no-store`. It varies on `Accept-Language` and `X-Portal-Preview`.

