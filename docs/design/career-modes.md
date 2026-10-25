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
- Applications made on a company site belong to that company as they do today; whether the job seeker also sees the application in another site is open
  question 1.

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
