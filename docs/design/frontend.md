# Front end

Design of the web front end that consumes this API. Code is not started.

## Decisions

- 2026-10-13: the front end is designed now and coded after the legacy import rehearsal; go-live readiness keeps priority ([detail](#1-timing)).
- 2026-10-13: Vue with TypeScript; the public application on Nuxt (server-side rendering), Console a single-page application on Vite ([detail](#2-stack)).
- 2026-10-13: the front end lives in its own repository, separate from the API ([detail](#3-repository-and-applications)).
- 2026-10-20: application names: **ONESIS Career** (public), **ONESIS Workspace** (company and group members, was "back office"), **ONESIS Console** (platform admins, was "admin panel") with **Portal Mitra** for psychologists (was "psychologist area", `/mitra` unchanged); identifiers `public`, `workspace`, `console`; in the UI the platform role reads "Operator platform" or "Admin platform", never bare "Admin"; API paths `/api/v1/admin/**`, admin permissions and page numbers stay ([detail](#3-repository-and-applications), `naming.md`).
- 2026-10-21: development data from a new demo seeder (fictitious accounts and data, refused in production), not from the legacy rehearsal ([detail](#131-before-step-0)); designed and coded as `demo:seed` (`phase-8-demo-data.md`).
- 2026-10-21: client IP: the API trusts the loopback address as a proxy and the Nuxt servers forward the visitor's IP; coded in step 2 ([detail](#131-before-step-0)).
- 2026-10-21: go-live scope of the front end: steps 0 to 5 of section 13; complex screens, ERP pages and Portal Mitra after go-live ([detail](#131-before-step-0)).
- 2026-10-21: coding order: foundation, then a thin slice Console → Workspace → Career tried end to end, then the go-live scope, complex screens, ERP pages and Portal Mitra last ([detail](#13-coding-order)).
- 2026-10-21: hosting paths: release folders `/usr/local/src/jobportal-frontend/releases/<app>/current`, Workspace's Node service on `127.0.0.1:3101` (Career takes 3100), a static "Segera hadir" page until the first build; nginx files for Workspace and Console written ([detail](#10-technical-foundation)).
- 2026-10-20: one development domain per application: `karir.satu.solutions`, `workspace.satu.solutions` (was `preview.satu.solutions`), `console.satu.solutions` (was `admin-karir.satu.solutions`); API unchanged; `CORS_ALLOWED_ORIGINS` and the link contract follow ([detail](#4-domains), [links](#122-link-contract)).
- 2026-10-21: template rules for front-end coding (copy markup and classes, no new styles, reusable components, Vue in place of template JavaScript, ask before elements not in the template), in the front-end `CLAUDE.md`; the contrast adjustment of 11.2 is withdrawn ([detail](#6-ui-template)).
- 2026-10-19: **three applications**: the company dashboard and the ERP pages move into one SPA, ONESIS Workspace; the public Nuxt application keeps visitors and job seekers only; Console stays ([detail](#3-repository-and-applications)).
- 2026-10-19: Workspace runs on Nuxt with `ssr: false`; its token stays in a sealed httpOnly cookie behind its own Nuxt proxy, as in 5.2 ([detail](#3-repository-and-applications)).
- 2026-10-19: back-office development domain `preview.satu.solutions`, workspace folder `apps/backoffice`; **changed 2026-10-20:** `workspace.satu.solutions`, `apps/workspace` ([detail](#3-repository-and-applications)).
- 2026-10-19: Workspace has its own sign-in, two-factor, password reset and team invitation pages; the public sign-in sends company and group members there, no token passes between the applications ([detail](#3-repository-and-applications)).
- 2026-10-19: the Workspace menu follows the permissions of `GET company/me` and the group membership, with a landing page per preset ([detail](#8-page-map-workspace)).
- 2026-10-19: one page-map session before front-end coding rewrites sections 7 to 9 and 11.1 for three applications, and adds the link contract for emails, notifications and `.ics` ([detail](#3-repository-and-applications)).
- 2026-10-19: the front-end working folder is `/usr/local/src/jobportal-frontend/onesis-talent` (the workspace root) ([detail](#3-repository-and-applications)).
- 2026-10-19: page-map session done: sections 2, 5.2, 7 to 10, 11.1 rewritten for three applications, shared components 11.7, links and targets section 12 ([detail](#3-repository-and-applications)).
- 2026-10-19: one page numbering: P, A, J (public), B, C, E (Workspace), D, M (Console); old numbers kept, C5b, C27, C28 as designed, new pages take the next number, "#N" marks designed-not-coded elements; design references to the pipeline as "C5" corrected to C6 ([detail](#7-page-map-public-site-and-job-seekers)).
- 2026-10-19: Workspace paths are Indonesian without a prefix and without `/en` ([detail](#8-page-map-workspace)).
- 2026-10-19: the language of the job seeker area, Workspace and Console comes from a cookie sent as `Accept-Language`; links never carry `/en` ([detail](#111-layouts-and-navigation)).
- 2026-10-19: the API's API-shaped team links (`COMPANY_APP_URL` + `/company/...`, `/review-requests/{id}`) are answered by link-entry routes of Workspace that redirect to pages ([detail](#123-link-entry-in-workspace)).
- 2026-10-19: links: signed out → sign-in of the target application and back; wrong kind of account → refused with a link to the right application and the equivalent path; not theirs → the API's 404 or 403 ([detail](#121-rules-for-every-link)).
- 2026-10-19: announcement banner as a strip above the header of the public and Workspace layouts; `info` closable, `important` always ([detail](#111-layouts-and-navigation)).
- 2026-10-19: Workspace menu is one sidebar grouped per module (Ringkasan, Talent, Workforce, Organisasi, Perusahaan) ([detail](#111-layouts-and-navigation)).
- 2026-10-19 (decision gate): the team's interview `.ics` carries the job seeker's page link; fixed in the API as work the front end asks for ([detail](#125-findings-while-mapping-the-links)).
- 2026-10-19 (decision gate): the `my_scoring` task points at a list endpoint that does not exist; the endpoint is added for page C31 ([detail](#85-findings-while-mapping)).
- 2026-10-13: two applications (replaced by three on 2026-10-19): the public site with job seekers and company dashboards, and a separate Console on its own domain ([detail](#3-repository-and-applications)).
- 2026-10-13: development domains `karir.satu.solutions` (public) and `admin-karir.satu.solutions` (admin); **changed 2026-10-20:** the admin application moves to `console.satu.solutions`; production domains later ([detail](#4-domains)).
- 2026-10-13: Console keeps its token in `sessionStorage`, with a strict CSP ([detail](#5-authentication)).
- 2026-10-13: public application authentication through the Nuxt server: the API token stays in a sealed httpOnly cookie and every API call goes through a Nuxt proxy route; the client-IP consequence is a separate open point ([detail](#52-public-application-and-workspace)).
- 2026-10-13: the rest of the public flow agreed: CSRF by `SameSite=Lax` plus an `Origin` check, central handling of 401/409/403, area chosen from `GET /me`, Google Identity Services, sign-out, Reverb authorisation through the proxy ([detail](#52-public-application-and-workspace)).
- 2026-10-13: the token is kept inside a sealed cookie with `nuxt-auth-utils` (no server-side session store); the Nuxt server runs on the same host as the API behind nginx ([detail](#52-public-application-and-workspace)).
- Postponed to front-end coding: client IP behind the Nuxt proxy (trusted proxy or not; the rate-limiter rules stay unchanged); **decided 2026-10-21:** trusted loopback proxy ([detail](#131-before-step-0)).
- 2026-10-13: page map of the public site, account pages and job seeker area agreed; its findings were decided the same day ([detail](#7-page-map-public-site-and-job-seekers)).
- 2026-10-13: public URLs in Indonesian; English under the `/en` prefix with `hreflang`, Indonesian unprefixed by default ([detail](#7-page-map-public-site-and-job-seekers)).
- 2026-10-13: a public company list is added to the API (`GET /companies`, new endpoint, coded after the import rehearsal together with the front end) for a company directory page ([detail](#74-findings-while-mapping)).
- 2026-10-13: a training course detail endpoint is added to the API (`GET /training/courses/{id}`, coded with the front end) for an indexed course page ([detail](#74-findings-while-mapping)).
- 2026-10-13: the partner psychologist portal (`/api/v1/provider/**`) is a separate area of Console, with its own sign-in and menu ([detail](#3-repository-and-applications)).
- 2026-10-13: the job seeker area (`/me/**`) renders in the browser only (`ssr: false`); data still goes through the Nuxt proxy ([detail](#7-page-map-public-site-and-job-seekers)).
- 2026-10-13: company dashboard page map agreed (C1 to C25), under the `/rekruter` prefix, browser-rendered; moved to Workspace without the prefix on 2026-10-19 ([detail](#8-page-map-workspace)).
- 2026-10-13: entry for employers: a static `/untuk-perusahaan` page with contact details **and** a company application form backed by a new API endpoint and an admin review queue (new feature, designed separately, coded after the import rehearsal) ([detail](#85-findings-while-mapping)).
- 2026-10-13: Console (D1 to D20) and Portal Mitra (M1 to M6) page maps agreed; Portal Mitra lives under `/mitra` on the Console domain; its findings were decided the same day ([detail](#9-page-map-console-and-portal-mitra)).
- 2026-10-13: admin endpoints for public user accounts are added (list, search, detail, suspend, reactivate, audited, new permission), new API work after the import rehearsal; adds page D21 ([detail](#93-findings-while-mapping)).
- 2026-10-13: admin accounts and roles stay command-line only (artisan commands on the server); no admin-management pages in Console ([detail](#93-findings-while-mapping)).
- 2026-10-13: one front-end repository as a pnpm workspace (`apps/public`, `apps/admin` (renamed `apps/console` on 2026-10-20), `packages/ui`, `packages/api`, `packages/i18n`), Node 24 LTS ([detail](#10-technical-foundation)).
- 2026-10-13: API types generated from the Scramble OpenAPI document with `openapi-typescript`, called through `openapi-fetch` ([detail](#10-technical-foundation)).
- 2026-10-13: the front end is built on the server by the user; a session only prepares the commands ([detail](#10-technical-foundation)).
- 2026-10-13: English public paths are translated (`/en/jobs`, `/en/companies`), Indonesian unprefixed (`/lowongan`) ([detail](#7-page-map-public-site-and-job-seekers)).
- 2026-10-13: API error text is translated in the API (`lang/id`: validation messages, attribute names, problem `detail`); the front end shows the API text and decides by `code` only. New API work, done with front-end coding ([detail](#10-technical-foundation)).
- 2026-10-13: rest of the technical foundation agreed: central problem+json handling, Pinia with `useFetch` and TanStack Vue Query, ESLint/Prettier/`vue-tsc`/Vitest/Playwright, release folders with a `current` symlink, Nuxt as a systemd service, admin as static files with a strict CSP ([detail](#10-technical-foundation)).
- 2026-10-13: the five new API pieces are designed in `phase-8-company-directory.md`, `phase-8-training-course-detail.md`, `phase-8-api-translations.md`, `phase-8-user-management.md` and `phase-8-company-registrations.md` (adds admin page D22).
- 2026-10-13: layouts and navigation agreed: four layouts, menus per area, no customizer (light/dark only), vertical sidebar, bottom navigation for job seekers on phones ([detail](#111-layouts-and-navigation)).
- 2026-10-13: common states agreed; public lists scroll on with crawlable `?page=N` links, times in the device's zone with a label, WCAG 2.1 AA ([detail](#112-common-states)).
- 2026-10-13: key page layouts agreed; the applicant pipeline opens as a table, kanban one click away ([detail](#113-key-page-layouts)).
- 2026-10-13: search-engine design agreed: structured data, sitemaps, only category and location filter pages indexed, brand name later, Google Indexing API after go-live ([detail](#114-search-engines)).
- 2026-10-13: complex screens agreed: test runner on every device, video room on Janus with screen sharing, voice through JsSIP, interview panel inside the room ([detail](#115-complex-screens)).
- 2026-10-13: shared screens are not recorded (the Janus converter leaves out screen tracks, new work done with the front end); every participant may share, one screen at a time ([detail](#115-complex-screens)).
- 2026-10-13: texts agreed: a notification catalog from the API with a front-end coverage test, static pages as Markdown in the front-end repository, the session drafts and the user reviews ([detail](#116-texts)).
- 2026-10-13: four legacy admin features stay out: candidate export, a cross-company application list, job bulk import and clone by an admin, platform-wide interview question templates ([detail](#93-findings-while-mapping)).
- 2026-10-13: the team page shows the jobs each member is assigned to: new `GET company/members/{userId}/jobs` (company admin; every status with its status, newest first; company admins too, only their own assignments), coded the same day.
- 2026-10-14: a recruitment calendar page C26 and a note for the candidate on interviews, designed in `phase-8-interview-calendar.md`.
- 2026-10-13: company members get per-feature permissions (preset plus checkboxes; presets Company admin, Recruiter, Interviewer), designed in `phase-8-company-permissions.md`, coded before go-live; the company menu and the Role column of section 8 then follow `GET company/me` permissions ([detail](#8-page-map-workspace)).
- 2026-10-13: UI on the user's Able Pro Vue 1.7.0 template (Vuetify 3); Console starts from its `seed`, the public Nuxt application ports its layouts and components through `vuetify-nuxt-module` ([detail](#6-ui-template)).
- 2026-10-23: the actions of a whole page sit at the top in a sticky action bar (`StickyActionCard`); actions of one item stay with the item ([detail](#113-key-page-layouts)).
- 2026-10-23: the API of the job editor C4 is extended before the page is built (user): `summary`, `vacancies_visible`, `salary_note`, `reapply_after_days`, `location_ids` (several company locations, the first is the main one), `GET company/jobs/{id}/preview`
  with `meta.publish_check`, `GET`/`PUT`/`DELETE company/jobs/{id}/assessments[/{stageId}]` (default test per assessment stage with the advance rule), more filters, sorts and columns on the job list C3 ([detail](phase-8-job-posting.md), [locations](company-locations.md), [assessments](phase-8-assessment-automation.md), [waiting period](phase-8-rejections.md)).
- 2026-10-23: buttons carry icons selectively ("With Icons" buttons of Able Pro): the action buttons of work pages and actions with a well-known icon do; account pages, cancel buttons and text buttons do not ([detail](#112-common-states)).

## 1. Timing

Decided by the user on 2026-10-13. The design of the front end (stack, application split, authentication, pages) is worked out now through questions and
answers, recorded here. Coding waits until the legacy import rehearsal is done, so go-live readiness is not delayed and the front end can be built against
the imported data.

## 2. Stack

Decided by the user on 2026-10-13: Vue with TypeScript. Three applications since 2026-10-19 (section 3):

- **Public application** (visitors and job seekers): Nuxt with server-side rendering, so job and company pages can be indexed by search engines and
  the API token can be kept in an httpOnly cookie on the Nuxt server instead of in browser storage; the job seeker area renders in the browser.
- **ONESIS Workspace** (company and group members, added 2026-10-19): Nuxt with `ssr: false`, a single-page application in the browser whose Nuxt server
  only holds the token in a sealed cookie and proxies the API (section 5.2).
- **ONESIS Console** (platform admins and Portal Mitra): a single-page application on Vite; it needs no indexing and is served as static files.

## 3. Repository and applications

Decided by the user on 2026-10-13:

- **Repository:** separate from the API (`jobportal_v2`), so the API and the front end deploy independently and the API working folder, which is also the
  live site, is not touched by Node builds.
- **Working folder (decided by the user on 2026-10-19):** `/usr/local/src/jobportal-frontend/onesis-talent`, owned by `jobportal`; it is the root of
  the pnpm workspace (section 10). The neighbouring `talent` folder (a plain copy of the Able Pro template) and the template copy next to it are not used.
- **Applications (replaced on 2026-10-19 by three applications, next point):** two. One serves the public pages, job seekers and company dashboards; Console is a second application on its own domain, because
  admins have their own guard, TOTP and 12-hour tokens and Console should not be exposed on the public domain.
- **Changed 2026-10-19 (decided by the user, option (c) of `docs/analysis/frontend-gap-analysis.md` section 5): three applications.** The company
  dashboard leaves the public Nuxt application and joins the ERP pages (group structure, approvals, workforce plans, requisitions, read across tenants,
  support grants; FEG1) in **one SPA, ONESIS Workspace** (Nuxt with `ssr: false`, see below) for all company and group work (ONESIS Talent, Workforce and Core). The public Nuxt
  application keeps only visitors and job seekers (sections 7.1 to 7.3); Console with Portal Mitra stays as it is. Why: company
  members and group members are the same accounts, these pages need no server rendering, and `naming.md` already splits ONESIS (ERP side) from
  ONESIS Career (job seekers).
- **Workspace token (decided by the user on 2026-10-19):** Workspace runs on **Nuxt with `ssr: false`** (still a single-page application in
  the browser) so that it reuses the public flow of section 5.2: the token stays in a sealed httpOnly cookie (`nuxt-auth-utils`) and every API call
  goes through its own Nuxt proxy route. Chosen over `sessionStorage` (a new tab would ask to sign in again, and recruiters open applicants in new
  tabs) and over a Sanctum cookie session (API change). Cost: a second Node service next to the public one.
- **Workspace domain and folder (decided by the user on 2026-10-19, changed 2026-10-20):** `workspace.satu.solutions` during development (section 4,
  was `preview.satu.solutions`); workspace folder `apps/workspace` (section 10, was `apps/backoffice`).
- **Workspace sign-in (decided by the user on 2026-10-19):** Workspace has its own account pages (sign-in with password and Google,
  two-factor, forgot and reset password, the team invitation, now B1 to B5 of section 8.1), all on `workspace.satu.solutions`; `COMPANY_INVITATION_URL` points there. The public
  sign-in (A1) refuses a company or group member after `GET me` (the token is revoked at once) and shows a link "Masuk ke Workspace";
  no token is ever passed between the applications, and neither cookie is set on the parent domain.
- **Page-map session (decided by the user on 2026-10-19):** one design session, before any front-end coding, rewrites sections 7 to 9 and 11.1 for
  the three applications from the routes of that day and the "Front end" rows of the phase 8 designs (FEG3, FEG9 to FEG15 of
  `docs/analysis/frontend-gap-analysis.md`), with one page numbering. **Done on 2026-10-19:** sections 2, 5.2 and 7 to 10 and 11.1 describe the
  three applications, 11.7 adds the shared components and section 12 the links and targets; the gap analysis marks every FEG as covered.
- **Link contract (decided by the user on 2026-10-19):** the same session adds a table of every link the API sends in emails, in-app
  notifications and `.ics` files: the `.env` setting, the target path in which application, and what happens when the reader is signed out or is
  the wrong kind of user. The API settings are pointed at those paths; no new API code (FEG4). Written in section 12; the two API fixes found while
  writing it (12.5, 8.5) were decided by the user as endpoints the front end asks for.
- **Partner psychologists (decided by the user on 2026-10-13):** the fifth role (`/api/v1/provider/**`: sign-in with two-factor, test authoring, norms,
  report templates, reviewing and signing results) gets a separate area in Console, with its own sign-in and menu. It is an internal tool like
  the platform admin pages (two-factor, no indexing, token in `sessionStorage`), so it stays in Console (also after the 2026-10-19 change to
  three applications).
- **Names (decided by the user on 2026-10-20):** industry-standard product names, one per application, recorded in `naming.md`:
  **ONESIS Career** (public site and job seekers, `apps/public`), **ONESIS Workspace** (company and group members, `apps/workspace`, UI text such
  as "Masuk ke Workspace"), **ONESIS Console** (platform admins, `apps/console`) and inside it **Portal Mitra** for partner psychologists (paths
  under `/mitra`). Why not "Admin": in the user stories "Admin" means the company admin (`CLAUDE.md` rule 8), so the Console UI names the platform
  role "Operator platform" or "Admin platform", never bare "Admin". Unchanged: the API paths `/api/v1/admin/**`, the admin permissions, the admin
  guard and the page numbers (D1 to D28, M1 to M8).

## 4. Domains

Decided by the user on 2026-10-13: `karir.satu.solutions` for the public application and a separate domain for the admin application during
development, next to the API at `api-karir.satu.solutions`. Both origins go into `CORS_ALLOWED_ORIGINS`. Production domains are decided later.
**Added 2026-10-19 (user):** the company and group application (section 3) runs on its own domain during development; it also goes into
`CORS_ALLOWED_ORIGINS`.
**Changed 2026-10-20 (user): one domain per application, named after it** (names in section 3):

| Application | Development domain | Was |
|---|---|---|
| ONESIS Career (public, `apps/public`) | `karir.satu.solutions` | unchanged |
| ONESIS Workspace (`apps/workspace`) | `workspace.satu.solutions` | `preview.satu.solutions` |
| ONESIS Console with Portal Mitra (`apps/console`) | `console.satu.solutions` | `admin-karir.satu.solutions` |
| API | `api-karir.satu.solutions` | unchanged |

`CORS_ALLOWED_ORIGINS` (development): `https://karir.satu.solutions,https://workspace.satu.solutions,https://console.satu.solutions`.

## 5. Authentication

### 5.1 Console

- **Decided by the user on 2026-10-13:** the 12-hour admin token is kept in `sessionStorage`, so it is dropped when the tab closes, together
  with a strict Content Security Policy. The API needs no change.

### 5.2 Public application and Workspace

The whole flow below was agreed by the user on 2026-10-13 (the pattern, items 1 and 2, first; items 3 to 8 afterwards). One point is postponed.
**Since 2026-10-19 it applies to both Nuxt applications** (section 3): the public application (`karir.satu.solutions`) and Workspace
(`workspace.satu.solutions`) each have their own sealed cookie on their own host (never the parent domain), their own proxy route and their own
sign-in; a token never passes from one to the other. Where they differ, the item says so.

**API contract it builds on** (read from the code, 2026-10-13):

- `POST /api/v1/auth/register` (job seekers only: `name`, `email`, `password`, optional `device_name`, `campaign_code`), `POST /auth/login` (`email`,
  `password`, `device_name`) and `POST /auth/google` (Google `id_token`) all return `{token, token_type: "Bearer", expires_at, user}`. A public token lives
  30 days from creation; there is no refresh token. **Since #7 (`erp-sso.md`):** members of internal tenants get 12-hour tokens.
- **Two-factor (coded with `phase-8-company-admin.md`, FEG5):** `auth/login` and `auth/google` may answer `{status: two_factor_required |
  two_factor_enrolment_required, challenge_token, enrolment?}` (5-minute, single-use challenge) instead of a token; `POST /auth/two-factor` with the
  code or a recovery code (or the first code of the enrolment) returns the token, and the recovery codes once at enrolment. Required for company
  admins, under `company.require_two_factor` and for internal tenants (group setting); optional for job seekers (`me/two-factor`).
- Company members do not register themselves: they are created by an admin or join through `GET|POST /auth/invitations/{token}[/accept]`.
- `POST /auth/email/verify` (six-digit `code`) and `/auth/email/resend`; an unverified account can sign in and edit its profile, other actions answer 409
  `email-not-verified`. Google accounts are verified from the start.
- Terms gate: until the active terms are accepted, almost every endpoint answers 409 `terms-not-accepted`.
- Sign-in refusals: 403 `account-suspended`, `account-deactivated` (restored through `POST /auth/account/restore` during the grace period).
- `POST /auth/password/forgot`, `/auth/password/reset`, `/auth/logout`, `/auth/logout-all`; `GET /me` is the current account.

**Flow:**

1. **Backend for front end.** The browser never sees the API token. The Nuxt server calls the sign-in endpoints, keeps the token in a sealed (encrypted and
   signed) httpOnly, `Secure`, `SameSite=Lax` session cookie whose lifetime ends at the token's `expires_at`, and returns only the user to the browser.
2. **API calls go through a Nuxt server route** (`/api/**` on the application's own host) that adds `Authorization: Bearer`, `Accept-Language` and forwards the
   request to `api-karir.satu.solutions`, streaming bodies both ways (resume and avatar files, video answer chunks, downloads). Server-side rendering calls
   the API the same way. Neither Nuxt application then needs a CORS entry; only the Console origin calls the API from a browser. The first sign-in
   step and the two-factor step both go through the Nuxt server, so the challenge token never reaches the page's script either.
3. **CSRF:** the cookie is `SameSite=Lax` and the proxy refuses a state-changing request whose `Origin` is not the application's own.
4. **Route guard and account states**, handled centrally in the proxy and the client:
   - 401: the session cookie is cleared and the user is sent to sign in, returning to the page afterwards.
   - 409 `terms-not-accepted`: redirect to the terms acceptance page.
   - 409 `email-not-verified`: show the verification prompt (code entry and resend) without leaving the page.
   - 403 `account-suspended` / `account-deactivated`: an explanation page; deactivated accounts get the restore action. In Workspace also
     403 `company-suspended` and `group-suspended` (B5).
   - Other 403s are shown as "not allowed" and never sign the user out (see the template fix in section 6); in Workspace a 403 also reloads
     the permissions (section 8.0).
5. **Role routing after sign-in (changed 2026-10-19):** `GET /me` decides whether the account belongs in this application. The public application
   keeps job seekers (to `/me`) and refuses company and group members: the token is revoked at once and A1 links to B1 (section 12.1). The back
   office keeps company members (group members are always company members of a tenant) and sends them to their landing page (section 8.0); it
   refuses job seekers the same way. A link being followed (`?ke=`) wins over the landing page.
6. **Google:** Google Identity Services in the browser returns the ID token, which is posted to the Nuxt server, which calls `/auth/google`.
7. **Sign-out** calls `/auth/logout` and clears the cookie; "sign out everywhere" calls `/auth/logout-all`.
8. **Real-time (when Reverb runs):** the browser connects to Reverb directly; channel authorisation (`/api/v1/broadcasting/auth`) goes through the proxy,
   which adds the token.

**Postponed:**

- **Client IP behind the proxy (decision gate).** Every API request would come from the Nuxt server's IP. The API's IP-based rate limiters (sign-in, job
  views, salary insight, campaigns, reviews, training, newsletter) would then share one bucket for all visitors, and audit and consent records would hold
  the server's IP. Fixing it needs an API change: trust the Nuxt server as a proxy (`trustProxies` for its address only) and forward `X-Forwarded-For`.
  The rate-limiter rules themselves stay unchanged either way (the user does not want them changed). **Postponed by the user on 2026-10-13** until front-end
  coding starts; it must be decided before the public application goes live. **Decided by the user on 2026-10-21:** trust the loopback proxy
  (section 13.1, point 2).

**Session and hosting (decided by the user on 2026-10-13):**

- The token is kept inside the sealed cookie with `nuxt-auth-utils`; there is no server-side session store, so several Nuxt processes need no shared state.
- The Nuxt server runs on the same host as the API, behind nginx (`karir.satu.solutions` to a local port), and reaches the API over the local network.
  If the trusted proxy is chosen later, the only address to trust is the loopback address. **2026-10-19:** Workspace is a second Nuxt
  service on the same host (`workspace.satu.solutions` to another local port), with the same rules; the client-IP point above covers both.

## 6. UI template

**Template rules (mandatory for front-end coding, set by the user on 2026-10-21; kept in the front-end repository's `CLAUDE.md`):** copy the
template's markup and classes exactly and change only the content; import its CSS as it is and write no new styles (no exception, also not for
contrast); split pages into reusable Vue/Nuxt components; replace template JavaScript or jQuery with Vue/Nuxt equivalents; ask the user before
adding an element that is not in the template. Elements this design already decided (the `focus` layout, the bottom navigation, the announcement
banner, the pickers of 11.7, the complex screens of 11.5) count as approved, but the Able Pro or Vuetify component each is built from is shown to
the user before it is written.

Decided by the user on 2026-10-13, after both templates the user owns were read (files only, nothing run; both folders are read-only):

- **Chosen:** Able Pro Vue 1.7.0 (`/usr/local/src/able-pro-vue-v1.7.0`): Vue 3.5, TypeScript 5.9, Vite 7, Vuetify 3.10, Pinia, vue-router, vue-i18n,
  vee-validate. `seed` is the clean starting point (dashboard layout, vertical and horizontal sidebar, header, customizer, i18n); `full-version` is the
  source of pages copied when needed (authentication, account profile, chat, calendar, kanban, server-side tables, form wizard, landing, error pages).
- **Not chosen:** Able Pro Bootstrap 9.6.2 (`/usr/local/src/able-pro-bootstrap-v9.6.2`), plain HTML with vanilla and jQuery plugins that would have to be
  rewritten as Vue components.
- **Console:** starts from `seed` as a Vite SPA.
- **Public application:** stays on Nuxt (server-side rendering); the Able Pro layouts and components are ported with `vuetify-nuxt-module`: vue-router routes
  become Nuxt pages, and browser-only code (`localStorage`, `window`) is guarded for server rendering.
- **Template code that must be replaced, not kept:**
  - `stores/auth.ts` keeps the user and token in `localStorage`; replaced by the decided storage (admin `sessionStorage`, public httpOnly cookie).
  - `utils/helpers/fetch-wrapper.ts` signs out on 401 **and 403** and reads `data.message`. Only 401 signs out (403 is "forbidden"; a suspended account
    has its own problem `type`), and errors are read as problem+json (`type`, `title`, `detail`, validation `errors`).
  - The mock APIs (`_mockApis`) and `fakeBackend()` are removed.
  - Locales: the template ships `en`, `fr`, `ro`, `zhHans`; the applications need `id` and `en` (the API language follows `Accept-Language`).
  - Overlapping dependencies (`moment` and `date-fns`, two table libraries) are reduced to one each.
- **Same look under server rendering (agreed by the user on 2026-10-13):** the Nuxt application renders the same Vuetify components and SCSS, so it looks
  the same as the SPA; only the first paint can differ. To avoid a flash, the theme choice (light, dark, system; the customizer is removed, 11.1) is kept
  in a cookie the server reads; Vuetify's `ssr.clientWidth` is set so the first paint on a phone uses the mobile layout; ApexCharts and
  perfect-scrollbar render inside `<ClientOnly>`.

## 7. Page map: public site and job seekers

Built on 2026-10-13 from the API routes and agreed by the user the same day; **rewritten on 2026-10-19 in the page-map session** (section 3) for the
three applications, from `php artisan route:list --path=api/v1` of that day (589 paths) and the "Front end" rows of the phase 8 designs. This is
the public Nuxt application (`apps/public`, `karir.satu.solutions`): visitors and job seekers only. Company and group members use Workspace
(section 8); admins and psychologists Console (section 9).

**How to read the page maps (sections 7 to 9):**

- A page or element without a mark uses endpoints that are **coded**.
- **#N** marks an element whose API is **designed, not coded** (phase 8 item N of `phase-8-index.md`); it is built when that item is coded, not
  before. **frozen** marks a frozen design (marketplace features), **after import** a design coded after the legacy import.
- Numbers are stable (decided by the user on 2026-10-19): P, A, J for this application; B, C, E for Workspace; D, M for the admin
  application. A retired number is not reused. Pages added after 2026-10-13 take the next free number (C5b, C27 and C28 keep the numbers their
  designs gave them).

**Rules of this application** (2026-10-13, unchanged):

- **URL language:** public and account paths are Indonesian (keywords Indonesian users search for). The English version lives under `/en` with `hreflang`
  links; the locale is passed to the API as `Accept-Language`. **Decided by the user on 2026-10-13:** the segments after `/en` are translated (`/en/jobs`,
  `/en/companies`, `/en/sign-in`), so each public and account route has an Indonesian and an English name (`@nuxtjs/i18n` custom route names).
- **Rendering:** public pages render on the server; the job seeker area `/me/**` renders in the browser only (`routeRules` `ssr: false`), like the Able
  Pro SPA, so it needs less porting work and has no first-paint flash. Its paths stay `/me/...` (not indexed). **Decided by the user on 2026-10-19:**
  the interface language of `/me/**` comes from the language cookie (section 11.1), not from the path, so a link from an email (always without
  `/en`, section 12) opens in the language the reader chose last.

Every page calls the API through the Nuxt proxy (section 5.2). "Index" means rendered on the server and open to search engines; every other page sends
`noindex`. Every layout of this application shows the announcement banner (section 11.1).

### 7.1 Public pages (no account needed)

| # | Page | Path | API | Notes |
|---|------|------|-----|-------|
| P1 | Home | `/` | `GET jobs` (latest), `GET job-categories`, `POST newsletter/subscribers` | Index. Search box leads to P2; signed-in job seekers also see `GET me/recommendations`. |
| P2 | Job search | `/lowongan` | `GET jobs` with `filter[q, work_mode, salary_min, salary_period_id, category_id, role_id, job_type_id, job_level_id, experience_level_id, education_level_id, work_time_id, province_id, city_id, skill_id, company_id, posted_within]`, `sort` (`relevance` by default with a keyword, `match` when signed in, newest, salary), `page`; masters for the filter lists. **frozen:** `filter[disability_friendly]`. **#20:** jobs with several locations of `company_locations`. | Index. Filters live in the query string so a search can be shared and indexed; save toggle and match percentage when signed in; "Simpan sebagai notifikasi lowongan" (J10). Each row shows its `locations`. |
| P3 | Job detail | `/lowongan/{job}` | `GET jobs/{job}` (with `locations`), `POST jobs/{job}/view` (`source=shared` when opened from a share link), `GET salary-insight`, `GET jobs/{job}/screening-questions`, `GET jobs/{job}/similar` ("Lowongan serupa", also on its 404), `POST jobs/{id}/reports` ("Laporkan lowongan": reason choice and note, sign-in first, then back to the dialog). **#20:** apply state with its reason and fix (`GET me/jobs/{id}/apply-state`: "Verifikasi email", "Lengkapi profil", extra required profile items). **#14:** "Anda dapat melamar lagi mulai {date}" (`reapply_on`). **frozen:** disability badge; "Ikuti" the company. | Index, with `JobPosting` structured data. Share with Web Share, WhatsApp and copy link. Apply button leads to J4 (sign-in first). The same detail component renders the company's preview in Workspace (C4, C5, #20). |
| P4 | Company profile | `/perusahaan/{slug}` | `GET companies/{slug}`, `/logo`, `/banner`, `GET jobs?filter[company_id]`, `GET companies/{slug}/reviews`, `POST companies/{slug}/reviews`, `POST reviews/{id}/report`. **frozen:** "Ikuti" (`me/followed-companies`, `followed`). | Index. Tabs: about (with the company's active locations), open jobs, reviews. Writing a review needs a job seeker who applied to the company. |
| P5 | Salary insight | `/gaji` | `GET salary-insight`, masters | Index. |
| P6 | Training | `/pelatihan` | `GET training/courses` | Index. |
| P6b | Training course | `/pelatihan/{course}` | **after import:** `GET training/courses/{id}` (`phase-8-training-course-detail.md`) | Index. Links to the course on Moodle. |
| P7 | Campaign landing | `/kampanye/{code}` | `GET campaigns/{code}` | Index if the campaign is public. Keeps `campaign_code` for registration and Google sign-in. Target of `CAMPAIGN_REGISTRATION_URL` and its QR code (section 12). |
| P8 | Legal documents | `/kebijakan/{type}`, `/kebijakan/{type}/{version}` | `GET legal-documents/{type}[/versions/{version}]` | Index. Markdown rendered and sanitised on the client side of the proxy. |
| P9 | FAQ | `/faq` | `GET faq` | Index, with `FAQPage` structured data. |
| P10 | Newsletter confirm and unsubscribe | `/newsletter/konfirmasi/{token}`, `/newsletter/berhenti/{token}` | `POST newsletter/confirm`, `POST newsletter/unsubscribe[/{token}]` | Links from emails (section 12); the token is in the path because the API templates put `{token}` there (changed 2026-10-19 from `/newsletter/konfirmasi`). |
| P11 | Errors | 404, 500, maintenance | none | From the Able Pro error pages. |
| P12 | Company directory | `/perusahaan` | **frozen, after import:** `GET companies` (`phase-8-company-directory.md`) | Index. Links to P4. |
| P13 | For employers | `/untuk-perusahaan` | **frozen, after import:** company application form (`phase-8-company-registrations.md`) | Index. Static content and contact details now; the form when its API lands. |
| P14 | Report verification | `/verifikasi-laporan` | **#23:** `GET verify/assessment-reports?number=` | `noindex`. A number field; shows whether a psychological report is valid, without personal data. |
| P15 | Invitation from a company (manual candidate) | `/undangan-lamaran/{token}` | **#31:** `GET email-invitations/{token}`, decline ("Bukan saya / tolak", no sign-in), `POST me/email-invitations/{token}/claim` (signed in, verified email) | `noindex`. Target of the claim link of #31 (section 12). Leads to J4 with "Gunakan CV yang dikirim ke perusahaan". |

### 7.2 Account pages (blank layout, `noindex`)

Job seekers only. Company and group members sign in on Workspace (B1 to B5, section 8.1).

| # | Page | Path | API |
|---|------|------|-----|
| A1 | Sign in (password and Google), second step | `/masuk` | `POST auth/login`, `POST auth/google`; when the answer is `{status: two_factor_required, challenge_token}`, the code or recovery-code step `POST auth/two-factor` on the same page (job seekers who switched two-factor on in J15). After the token, `GET me`: a company or group member is refused (the token is revoked at once with `POST auth/logout`) with "Masuk ke Workspace", a link to B1 carrying the Workspace equivalent of the page asked for when there is one (section 12.3). |
| A2 | Register (job seekers) | `/daftar` | `POST auth/register`, `POST auth/google` |
| A3 | Forgot and reset password | `/lupa-kata-sandi`, `/atur-ulang-kata-sandi` | `POST auth/password/forgot`, `POST auth/password/reset` (a six-digit code from the email, no link) |
| A4 | Verify email | `/verifikasi-email` | `POST auth/email/verify`, `POST auth/email/resend` |
| A5 | Accept terms | `/persetujuan` | `GET me/terms-acceptance`, `POST me/terms-acceptance`, `GET legal-documents/{type}` |
| A6 | Suspended or deactivated account, restore | `/akun/diblokir` | `POST auth/account/restore` |
| A7 | (retired 2026-10-19) | | The company invitation moved to Workspace as B4. |

### 7.3 Job seeker area (Able Pro dashboard layout, `noindex`)

| # | Page | Path | API |
|---|------|------|-----|
| J1 | Dashboard | `/me` | `GET me/dashboard`, `GET me/recommendations`, `GET me/profile/views`. **#15:** "Perlu tindakan" (`GET me/tasks`) and "Jadwal mendatang" (`GET me/agenda`, the next items; all of them in J16). |
| J2 | Profile | `/me/profile` | `GET`/`PATCH me/profile` (address with the address picker, section 11.7); experiences and educations (CRUD); `PUT me/skills`, `me/languages`, `me/links`; avatar (`GET`/`POST`/`DELETE me/avatar`). **#25:** "Terbuka untuk tawaran" switch and the activity note. |
| J3 | Resumes and CV generator | `/me/resumes` | `GET`/`POST me/resumes`, `PATCH`/`DELETE me/resumes/{id}`, `GET me/resumes/{id}/download`, `GET me/cv/templates`, `POST me/cv` |
| J4 | Apply | `/lowongan/{job}/lamar` | `GET jobs/{job}/screening-questions`, `POST jobs/{job}/applications` (uploaded resume or a CV generated at that moment; the talent pool consent checkbox, `phase-8-talent-pool.md`). **#20:** apply state before the form and the extra required profile items. **#14:** `already-applied` with `reapply_on`. **#31:** "Gunakan CV yang dikirim ke perusahaan" after a claim (P15). |
| J5 | Applications and detail | `/me/applications`, `/me/applications/{id}` | list, detail with the stage history; `withdraw` with the reason choices (`phase-8-withdrawals.md`); `resume`; talent pool consent switch (`PUT`/`DELETE me/applications/{id}/talent-pool-consent`); the contact notice when the company's contact is hidden (`contact_hidden`, 403 `contact-not-available`); the hired state with the join date and note (`hired`, notification `application.hire_updated`). **Interview card** (`GET me/applications/{id}/interview`): date and time in the device zone with its label, format, place with its address and a map link (`location`, `company-locations.md`) or the meeting link, interviewer names, the company's note, `.ics`; `confirm` (phone number for a voice interview), `decline` with the reason choices, `propose` another time (counter of the two proposals), **"Minta jadwal ulang"** on a confirmed interview (`propose` with a reason) until `reschedule_until`, after which the button hides with the reason (409 `reschedule-too-late`); the missed-interview state (`interview.no_show`) with the company's next step; `recording-consent/withdraw`. Offer: a card that opens J17. **#15:** closed-job label, "sedang ditinjau" notice, the company's rejection message. **#32:** consent card and schedule per pre-employment check. |
| J6 | Video interview room | `/me/applications/{id}/interview` | `GET .../interview/video/lobby`, `POST .../interview/video/join` (Janus; client only, full screen) |
| J7 | Assessments and test runner | `/me/assessments`, `/me/assessments/{id}`, `/me/assessments/{id}/attempt` | invitations, `start`, `decline`, the company's attachment (`GET .../attachment`), attempt (answers, cursor, columns, sections, drawing, integrity events, video takes in chunks, `submit`), `explanation`; runner is client only and full screen. **#15:** practice question (`GET .../practice`) and the requirements before the start. **#24:** the institution's contact on the explanation. |
| J8 | Company invitations to apply | `/me/invitations` | `GET me/invitations[/{id}]`, `POST me/invitations/{id}/decline` |
| J9 | Saved jobs | `/me/saved-jobs` | `GET me/saved-jobs`, `PUT`/`DELETE me/saved-jobs/{id}` |
| J10 | Job alerts | `/me/job-alerts` | CRUD, `pause`, `resume`, `GET me/job-alerts/{id}/jobs` |
| J11 | Messages | `/me/messages` | conversations (list, create, detail, messages, send, read, unread count), `DELETE me/messages/{id}`, attachments; block and unblock the company (`POST`/`DELETE me/conversations/{id}/block`), report (`POST me/conversations/{id}/report`); real time through Reverb when it runs (Able Pro chat page) |
| J12 | Notifications and preferences | `/me/notifications`, `/me/settings/notifications` | list, read, read all, delete, unread count; `GET me/notification-preferences`, `PUT me/notification-preferences/{key}/{channel}`. A notification opens its target (section 12.4). **#33:** WhatsApp card with the code dialog. |
| J13 | My company reviews | `/me/reviews` | `GET me/reviews[/{id}]`, `PUT`/`DELETE me/reviews/{id}` |
| J14 | My training | `/me/training` | `GET me/training/courses` |
| J15 | Account settings | `/me/settings` | `PUT me/password`; two-factor (`GET`/`POST me/two-factor`, `POST me/two-factor/confirm`, `POST me/two-factor/disable`, `POST me/two-factor/recovery-codes`; the recovery codes shown once); "Unduh data saya" (`POST`/`GET me/data-export`, `GET me/data-export/download`); `POST auth/logout-all`; `POST me/deletion`. **#25:** blocked companies (`me/blocked-companies`). |
| J16 | Agenda | `/me/agenda` | **#15:** `GET me/agenda` (interviews, tests, offer deadlines, pre-employment checks of #32) |
| J17 | Offer | `/me/applications/{id}/offer` | `GET me/applications/{id}/offer` (terms, deadline countdown), `GET .../offer/letter.pdf` in a PDF viewer, `accept`, `decline` with a note. **#12 section 3:** agreement checkbox, password or Google confirmation, `letter_sha256`, `evidence.pdf`. **#32:** the condition box of the pre-employment checks. |
| J18 | Followed companies | `/me/followed-companies` | **frozen:** `me/followed-companies` |

Not mapped to a page: `POST`/`DELETE me/device-tokens` (push tokens for a future mobile app or web push), `broadcasting/auth` (proxy only).

### 7.4 Findings while mapping

- **No public company directory:** the API has `GET companies/{slug}` but no company list. **Decided by the user on 2026-10-13: add the endpoint**
  (`GET /api/v1/companies`, paginated, active companies only; filters such as industry and location to be designed with it). It is new API work, done
  after the import rehearsal together with front-end coding, and it adds page P12 "Company directory" at `/perusahaan`.
- **No training course detail:** the API only lists courses. **Decided by the user on 2026-10-13: add `GET /api/v1/training/courses/{id}`**, new API
  work done with front-end coding, so each course gets its own indexed page (P6b `/pelatihan/{course}`).
- **A fifth role, the partner psychologist (`/api/v1/provider/**`):** placed in Console on 2026-10-13 (section 3); its pages are mapped
  with Console.
- **2026-10-19 (page-map session):** the newsletter pages carry the token in the path (P10); the job seeker's two-factor (A1 second step, J15),
  data export, blocking and reporting in chat, talent pool consent, the reschedule request, the no-show and hired states, the offer page (J17) and the
  withdraw and decline reasons are coded API without a page until now (FEG5, FEG9); they are placed above.

## 8. Page map: Workspace

Built on 2026-10-13 as the company dashboard of the public application (C1 to C25, under `/rekruter`); **rewritten on 2026-10-19** (page-map
session) as Workspace: `apps/workspace`, Nuxt with `ssr: false` on `workspace.satu.solutions` (section 3), token in a sealed cookie behind
its own proxy (section 5.2), the Able Pro dashboard layout, every page `noindex` (`robots.txt` disallows everything). It holds the recruitment
work of the company (ONESIS Talent, pages C) and the ERP pages (ONESIS Workforce and Core, pages E) for every company and group member.

**Paths (decided by the user on 2026-10-19):** Indonesian, without a prefix (the domain is Workspace's own) and without an `/en` variant;
the interface language comes from the language cookie and is sent as `Accept-Language` (section 11.1). The paths of the API's own links
(`/company/...`, `/review-requests/{id}`) are served by the link-entry routes of section 12.3, not by pages.

### 8.0 Access and menu

**Decided by the user on 2026-10-19 (FEG2): menus by permission.** After sign-in Workspace reads `GET me` (a job seeker is refused, B1),
`GET company/me` (`role`, `is_owner`, `permissions`) and `GET organization/group` (403 `not-a-group-member`: no group role; otherwise
`my_permissions`). They are kept in Pinia and read again after a 403 and when the in-app notification `company.permissions_changed` arrives. A
menu item shows only when its "Access" below is held; a menu group with no visible item is hidden; the API stays the authority (403 is "not
allowed", section 11.2). One company per member account: there is no company switcher.

- **Company permissions** are the 29 of `GET company/permissions` (`CompanyPermission`: jobs, applicants, sourcing, library, company, approvals,
  workforce). A company admin (`role: company_admin`) holds every permission and alone opens the team, activity and settings pages ("company admin"
  below).
- **Group permissions** (`GroupPermission`): `group.structure.manage`, `group.members.manage`, `group.recruitment.view`, `group.audit.view`,
  `group.settings.manage`, `group.approvals.manage`, `group.workforce.view`, `group.workforce.manage` (and `group.assessment.manage` with #24). "group
  member" below means any group role.
- **Landing page** (the preset rule decided on 2026-10-19, read from the permissions because presets are labels, not stored): without
  `applications.view` but with `interviews.participate` (Interviewer preset) → C30 "Wawancara saya"; with `applications.review` but without
  `applications.manage` (Hiring Manager preset) → C28 "Review kandidat"; everyone else (Recruiter, Company admin, custom sets) → C1. A link
  (section 12) always wins over the landing page.

### 8.1 Account pages (blank layout)

Decided by the user on 2026-10-19 (section 3): Workspace has its own sign-in; no token passes between the applications.

| # | Page | Path | API |
|---|------|------|-----|
| B1 | Sign in (password and Google), two-factor, forced enrolment | `/masuk` | `POST auth/login`, `POST auth/google`. The answer may be `{status: two_factor_required, challenge_token}` → code or recovery-code step (`POST auth/two-factor`), or `{status: two_factor_enrolment_required, challenge_token, enrolment}` (company admins, `company.require_two_factor`, the group setting of internal tenants) → enrolment step on the same page: QR and secret, first code, then the recovery codes shown once before the token is used. After the token, `GET me`: a job seeker is refused (token revoked with `POST auth/logout`) with a link to the public site. Tokens of internal tenants live 12 hours, others 30 days (`erp-sso.md`); the cookie ends with `expires_at`. |
| B2 | Forgot and reset password | `/lupa-kata-sandi`, `/atur-ulang-kata-sandi` | `POST auth/password/forgot`, `POST auth/password/reset` |
| B3 | Accept terms | `/ketentuan` | `GET`/`POST me/terms-acceptance`, `GET legal-documents/{type}` (the path is not `/persetujuan`, which is E1) |
| B4 | Team invitation (was A7) | `/undangan/{token}` | `GET auth/invitations/{token}` (company, preset, inviter), `POST auth/invitations/{token}/accept` (set a password or Google); a company admin's invitation ends in the enrolment step of B1, not a token. Target of `COMPANY_INVITATION_URL` (section 12). |
| B5 | Suspended or deactivated account, suspended company | `/akun/diblokir` | `POST auth/account/restore`; 403 `company-suspended` and `group-suspended` explained without restore |

### 8.2 Talent: recruitment pages

"Access" is the permission that shows the page; actions inside need the permissions named in the API cell (from the catalog of
`phase-8-company-permissions.md`, `phase-8-hiring-managers.md`, `phase-8-interviewers.md`). `applications.view` reads the assigned jobs only;
`jobs.view_all` widens it to every job.

| # | Page | Path | API | Access |
|---|------|------|-----|--------|
| C1 | Dashboard | `/` | `GET company/dashboard` (only the cards whose permission is held), `GET company/me`, `GET company/tasks` ("Perlu tindakan": one line per task key with its count; each opens its target, section 12.3) | every member |
| C2 | Analytics | `/analitik` | `GET company/analytics/{report}` (report list from the API) | `analytics.view` |
| C3 | Jobs list | `/lowongan` | `GET company/jobs` (`filter[q, status, assigned_to_me, applications, deadline_to]`, `sort`; hires and `filled`), `GET company/jobs/status-counts` (tab counts, same visibility and filters except `status`, coded 2026-10-22); "Ekspor karyawan baru" (`GET company/hires/export?from=&to=`, with `applications.export`). **#20:** new filters and columns. | `applications.view` |
| C4 | Job editor (create, edit) | `/lowongan/baru`, `/lowongan/{id}/ubah` | `POST`/`PATCH`/`DELETE company/jobs[/{id}]`, masters, place from `GET company/locations`, `GET`/`PUT .../screening-questions` (bank C18), interview kit per stage (`GET .../interview-kits`, `PUT`/`DELETE .../interview-kits/{stageId}`), hiring team: `GET`/`PUT .../assignees` (`jobs.assign`) and the hiring manager's stage boundary (`PUT .../hiring-manager-boundary`, `jobs.create`); "Buat dari permintaan" (`POST company/requisitions/{id}/jobs`, required when `recruitment.requisition_required`; E5). **#20:** "Pratinjau" (`GET company/jobs/{id}/preview`, rendered with the P3 component and a banner), summary with counter, Markdown outline, vacancies switch, salary note, several locations, requirements picker. **#14:** waiting period (`reapply_after_days`). **#22:** default test per stage with the advance rule (`company/jobs/{id}/assessments`), "Acak soal" label. | `jobs.create` |
| C5 | Job detail and lifecycle | `/lowongan/{id}` | `GET company/jobs/{id}` (with the moderation state: held, rejected, taken down, with the reason), `publish`, `close`, `reopen`, `clone`, `fill` ("Tandai terpenuhi"), `GET .../performance`; "Bandingkan hasil interview" (`GET company/jobs/{id}/interview-results`, anchors as header tooltips). **#22:** "Bandingkan hasil tes" (`GET .../assessment-results`). **#15:** close dialog with "Tolak pelamar yang masih aktif" (`reject-remaining`). **#31:** "Tambah kandidat" and the "Menunggu kandidat" list. **#20:** "Pratinjau". | `applications.view`; actions `jobs.publish`, `jobs.create` |
| C5b | All applicants | `/pelamar` | `GET company/applications` (keyword, filters, facets, sorts across the jobs the member reads) | `applications.view` |
| C6 | Applicant pipeline of a job | `/lowongan/{id}/pelamar` | `GET company/jobs/{id}/applications` (filter panel with facets, score column `match_score`, rating, time in stage and the stale mark, review answers, interview and RSVP state badges); `POST company/applications/{id}/move` (hire dialog with join date and note when moving to hired; `meta.job_filled` → "Tandai terpenuhi" prompt), `reject`; bulk `move` (interview slots), `reject`, `message`; `GET .../applications/export` (`applications.export`); "Minta review" from the selection (`POST`/`GET company/jobs/{id}/review-requests`, due date and message); "Tandai mengundurkan diri" (C7). A bound hiring manager sees only the stages from the boundary, with "Lamaran lain dikirim oleh recruiter". **#14:** restrict option in reject, restricted badge, "Batalkan" on pending rejections (`DELETE .../pending-rejection`, `DELETE company/rejection-batches/{batchId}`; reject answers 202 with `commit_at`). **#15:** candidate message in the reject dialogs. | `applications.view`; actions `applications.manage` |
| C7 | Application detail | `/pelamar/{id}` | `GET company/applications/{id}` (stage history, withdrawal reason and actor, `reviews[]`, `review_summary`, other applications of the candidate, `retention_due_on` notice), `resume`; notes (CRUD); `PUT`/`DELETE rating`; `PATCH .../hire` (join date and note, changed later); `POST .../withdraw` ("Tandai mengundurkan diri" with reason and note); "Simpan ke talent pool" (`POST company/talent-pool`); offer dialog (`PUT offer` with terms, benefits, allowances, deadline, the generated letter or an own PDF through `offer-letter-uploads`; `GET offers`, `GET offers/{offerId}/letter.pdf`, `withdraw`); interview (`PUT`/`PATCH interview` with the place from the company locations or a link, the panel and the note; `GET interviews`; proposal banner "Terima / Tolak / Jadwalkan lain" (`accept-proposal`, `refuse-proposal`); reschedule-request banner; `cancel`; `complete`; "Tidak hadir" after the start (`no-show`)); the note of the schedule, test and offer dialogs prefilled from `company/message-defaults` with "Pratinjau email" (`POST .../email-preview`); assessment (`PUT company/assessment/applications/{id}/invitation` with window, assessors and attachment, `GET .../invitations`). A member who reaches the application only through an interview panel (`interviews.participate`, `phase-8-interviewers.md`) gets the limited layout: resume and files, screening answers, assessment results, the interview; no notes, ratings, offer or stage actions. **#14:** restriction (`POST .../restriction`). **#15:** candidate message. **#32:** "Cek pra-kerja" panel and the condition box in the offer form. **#12 section 3:** `evidence.pdf`. | `applications.view` or a panel seat; actions per permission (`applications.manage`, `offers.manage`, `interviews.manage`, `assessments.send`, `talent_pool.manage`) |
| C8 | Interview workspace | `/wawancara/{id}` | `GET`/`PUT company/interviews/{id}/participants` (panel with RSVP badges), `questions`, `scorecard` (draft, `submit`; scale anchors), `GET scorecards`, revisions, `attendance`, `calls`, `recordings` (list, file, delete); RSVP buttons (`POST .../rsvp`); `GET .../calendar.ics` | panel seat (`interviews.participate`) or `interviews.manage` |
| C9 | Video interview room | `/pelamar/{id}/wawancara/video` | `GET .../interview/video/lobby`, `join`, `admit`, `recording/start`, `stop` (Janus; full screen) | panel seat or `interviews.manage` |
| C10 | Voice interview | `/pelamar/{id}/wawancara/telepon` | `POST .../interview/voice/join`, `call`, `hangup`, `recording/start`, `stop` (Asterisk browser account) | panel seat or `interviews.manage` |
| C11 | Candidate search and suggestions | `/kandidat`, `/kandidat/{slug}` | `GET company/candidates[/{slug}]`, avatar, resumes; `GET company/jobs/{id}/candidates`. **#25:** "Terbuka untuk tawaran" and activity badges, `filter[open_to_offers]`, `sort=-last_active`. | `candidates.search` |
| C12 | Invitations to apply | `/lowongan/{id}/undangan` | `GET`/`POST company/jobs/{id}/invitations`, `POST .../invitations/bulk` (from a selection of candidates or of the pool) | `candidates.search` |
| C13 | Talent pool | `/talent-pool`, `/talent-pool/{id}` | `GET`/`POST company/talent-pool` (summaries, search, sort), entry page `GET`/`PATCH`/`DELETE .../{id}`, categories (CRUD), bulk invite to a job (C12) | `talent_pool.manage` |
| C14 | Messages | `/pesan` | `company/conversations` (list, create, detail, messages, send, read, close, reopen, unread count, `report`), `DELETE company/messages/{id}`, attachments | `messages.send` |
| C15 | Assessments: tests | `/asesmen/tes` | `company/assessment/tests` (CRUD, `publish`, `archive`, `restore`, `clone`), sections and questions (CRUD, order), `GET library`, `GET catalog/{id}`, `GET batteries`. **#22:** "Acak soal" for `draw_count`. **#23:** the catalogue's example report. | `assessments.author` (catalogue read: `assessments.send`) |
| C16 | Assessments: result | `/asesmen/hasil/{id}` | `GET company/assessment/invitations/{id}`, `result`, `report.pdf`, `integrity-events`, `videos/{questionId}`, the attachment (`GET`/`POST`/`DELETE .../attachment`), `scoring` and `PUT scoring/questions/{questionId}`, `cancel`. **#23:** recommendation on the signed report. **#24:** "Perkiraan selesai" and the question thread with the institution. | `assessments.send` (scoring: assessors of the job only, `not-an-assessor`) |
| C17 | Interview kits | `/kit-wawancara` | `company/interview-kits` (CRUD, scale anchors), "Duplikat" (`POST .../duplicate`) | `interview_kits.manage` (read with `jobs.create` or `interviews.manage`) |
| C18 | Screening question bank | `/pertanyaan-screening` | `company/screening-questions` (CRUD, `archive`, `restore`) | read: every member; write: `screening_questions.manage` |
| C19 | Pipeline stages | `/tahapan` | `company/stages` (CRUD, `PUT order`) | `stages.manage` |
| C20 | Company profile | `/perusahaan/profil` | `GET`/`PATCH company/profile` (with `main_location`), `PUT company/slug`, logo and banner (`GET`/`POST`/`DELETE`); name change request (`GET`/`POST company/name-change-requests` with its document, `POST .../{id}/cancel`, the outcome) | `company.profile` |
| C21 | Verification documents | `/perusahaan/dokumen` | `GET company/documents`, `POST`/`DELETE .../{typeId}`, `GET .../{typeId}/file` (rejection reason shown) | `company.profile` |
| C22 | Team | `/perusahaan/tim`, `/perusahaan/tim/{userId}` | `GET company/members` (one paginated, searchable list of members and invitations, `two_factor` per row, "preset lama" mark, row `actions`), `GET company/permissions`; invitation dialog with the preset picker (Company admin, Recruiter, Hiring manager, Interviewer) and grouped checkboxes (`POST company/invitations`; 422 `permissions-required`, `permission-requires-view`), `PATCH`/`DELETE company/invitations/{id}`, `resend`; member detail `GET .../{userId}` with activity (`.../activity`) and jobs (`.../jobs`); edit dialog (`PATCH .../{userId}`), `suspend`, `reactivate`, `reset-two-factor`, `make-owner` (the owner's password), remove (`DELETE` with the recipient of the member's jobs); allowed email domains (`GET`/`PUT company/team/email-domains`); team history tab (`GET company/team/history`) | company admin |
| C23 | Campaigns | `/kampanye` | `company/campaigns` (CRUD), `GET .../qr`, `GET .../results` | `campaigns.manage` |
| C24 | Reviews of the company | `/ulasan` | `GET company/reviews`, `PUT`/`DELETE .../reply`, `POST .../report` | `reviews.reply` |
| C25 | Notifications and account | `/notifikasi`, `/akun` | `me/notifications` (list, read, read all, delete, unread count; a notification opens its target, section 12.4); `GET me/notification-preferences`, `PUT .../{key}/{channel}` (team preferences, daily digest); `PUT me/password`; two-factor (`me/two-factor`: status, enrol, confirm, recovery codes; switching off refused with 409 `two-factor-required` when required); `me/avatar`; `POST auth/logout-all`. **#33:** WhatsApp card. | every member |
| C26 | Calendar | `/kalender` | `GET company/calendar` (interviews, assessment due times, offer deadlines, start dates; month, week, day, list; type and job filters, "Hanya saya"); `.ics` of an interview (`GET company/interviews/{id}/calendar.ics`) | `applications.view` or a panel seat |
| C27 | Activity log | `/perusahaan/log-aktivitas` | `GET company/activity` (filters), `GET company/activity/export` | company admin |
| C28 | Review kandidat (hiring manager) | `/review`, `/review/{id}` | `GET company/review-requests` (`filter[state]`, `filter[role]`), `GET .../{id}`, `PUT .../{id}/applications/{applicationId}/review` (Lanjut / Tidak / Mungkin with a note), `POST .../{id}/close` | `applications.review` |
| C29 | Company settings | `/perusahaan/pengaturan` (a tab beside C20, `phase-8-company-admin.md`) | `GET`/`PATCH company/settings` (`company.require_two_factor`, stale days, `recruitment.applicant_retention_months`), `GET`/`PUT company/message-defaults` ("Teks default pesan", "Pesan lamaran diterima"). **#15:** the rejection default. **#14:** restricted candidates (`company/restrictions`). **#32:** check types. | company admin |
| C30 | Wawancara saya | `/wawancara` | `GET company/interviews` (upcoming and past; `filter[rsvp]`, `filter[my_scorecard]`, `filter[proposal]`), RSVP from the row | `interviews.participate` |
| C31 | Penilaian tes saya | `/asesmen/penilaian` | `GET company/assessment/invitations?filter[my_scoring]=pending` (**coded 2026-10-19**, section 8.5) | assessors |
| C32 | Work locations | `/perusahaan/lokasi` | `GET`/`POST company/locations`, `PATCH`/`DELETE .../{id}`, `main`, `activate`, `deactivate` (address picker, section 11.7) | `company.profile` (the list is read by every member for the pickers) |

### 8.3 Workforce and organisation: ERP pages

Coded with #5 and #6 (`erp-organization.md`, `erp-requisitions.md`, `erp-governance.md`). Group pages need a group role (8.0); the reads
across tenants are audited per applicant, which the page says.

| # | Page | Path | API | Access |
|---|------|------|-----|--------|
| E1 | Approvals inbox | `/persetujuan`, `/persetujuan/{id}` | `GET company/approvals` (`?as=approver`, the default; `?as=requester`), detail with steps, places, who decided for whom, `can_decide`, `can_replace`; `approve`, `reject` and `return` (reason 5 to 500 characters), replace an approver (`PUT .../approvers/{slotId}`) | every member (the list holds only the member's own steps and requests; group role holders decide their tenants' group steps) |
| E2 | Delegations | `/persetujuan/delegasi` | `GET`/`POST company/approval-delegations`, `DELETE .../{id}` (a member of a tenant of the same group, 1 to 90 days) | every member |
| E3 | Approval flows | `/persetujuan/alur` | tab "Perusahaan": `GET company/approval-flows`, `PUT`/`DELETE .../{subjectType}` (with `source`: company, group or none); tab "Grup": `GET organization/approval-flows`, `PUT`/`DELETE .../{subjectType}` | tab per permission: `approvals.manage`, `group.approvals.manage` |
| E4 | Workforce plans | `/rencana-tenaga-kerja`, `/rencana-tenaga-kerja/{id}` | `GET`/`POST company/workforce/plans`, `GET`/`PUT .../{id}`, `submit`, `revise`; costs only with `workforce.costs.view`; the approval state (E1) | `workforce.plans.view` (write `workforce.plans.manage`) |
| E5 | Requisitions | `/permintaan`, `/permintaan/{id}` | `GET`/`POST company/requisitions`, `GET`/`PUT .../{id}`, `submit`, `amend`, `cancel`, `close`, `POST .../{id}/jobs` (leads to C4); place from the company locations; the approval state; the job it became | `workforce.requisitions.view` (write `workforce.requisitions.create`) |
| E6 | Group and legal entities | `/organisasi` | `GET organization/group` (legal entities, `my_permissions`), `GET organization/tenants`; legal entities `POST`, `PATCH`, `deactivate`, `reactivate` | group member (write `group.structure.manage`) |
| E7 | Units and unit types | `/organisasi/unit`, `/organisasi/unit/{id}` | `organization/units` (`GET`, `POST`, `PATCH`, `deactivate`, `reactivate`, `GET .../versions`, `as_of`), `organization/unit-types` (same actions); a tree view | group member (write `group.structure.manage`) |
| E8 | Positions | `/organisasi/jabatan`, `/organisasi/jabatan/{id}` | `organization/positions` (`GET`, `POST`, `PATCH` with a reason, `deactivate`, `reactivate`, `GET .../versions`) | group member (write `group.structure.manage`) |
| E9 | Grades and cost centres | `/organisasi/golongan`, `/organisasi/pusat-biaya` | `organization/grades`, `organization/cost-centres` (`GET`, `POST`, `PATCH`, `deactivate`, `reactivate`) | group member (write `group.structure.manage`) |
| E10 | Group members | `/organisasi/anggota` | `GET organization/members`, `PUT`/`DELETE .../{userId}` (never the own role, 422 `cannot-change-own-group-role`) | `group.members.manage` |
| E11 | Group settings | `/organisasi/pengaturan` | `GET`/`PATCH organization/settings` (two-factor of internal tenants and the other group values) | `group.settings.manage` |
| E12 | Group activity log | `/organisasi/log-aktivitas` | `GET organization/activity` | `group.audit.view` |
| E13 | Group workforce plans and requisitions | `/organisasi/rencana-tenaga-kerja`, `/organisasi/permintaan` | `organization/workforce/plans` (`GET`, `POST`, `PUT`, `submit`, `revise`), `GET organization/requisitions[/{id}]` | `group.workforce.view` (write `group.workforce.manage`) |
| E14 | Recruitment across tenants (read only) | `/organisasi/rekrutmen`, `/organisasi/rekrutmen/lowongan/{id}`, `/organisasi/rekrutmen/pelamar/{id}` | `GET organization/read/jobs[/{id}]`, `.../applications`, `.../performance`, `GET organization/read/applications/{id}[/resume]`, `GET organization/read/analytics/{report}`; a tenant filter; no action buttons | `group.recruitment.view` |
| E15 | Support access | `/organisasi/akses-dukungan` | `GET organization/support-admins`, `GET`/`POST organization/support-grants` (admin, 1 to 72 hours), `DELETE .../{id}`; the admins' reads in E12 | `group.members.manage` |
| E16 | Assessment providers of the group | `/organisasi/penyedia-asesmen` | **#24 section 8:** `organization/assessment-providers`, `organization/assessment-batteries`, `organization/companies/{id}/batteries` | `group.assessment.manage` |

### 8.4 Not mapped to a page

`company/me` and `company/permissions` (read by the shell, 8.0), `GET company/locations` (pickers), `broadcasting/auth` (proxy only).

### 8.5 Findings while mapping

- **No self-registration for companies** (existing decision, `phase-1-company.md`): an admin creates the company and the first company admin joins
  through the invitation (B4). **Decided by the user on 2026-10-13:** the public site gets a static page `/untuk-perusahaan` (P13: the service and
  contact details) **with a company application form** posted to a new API endpoint; applications wait in an admin review queue, and an approved one
  leads to the existing flow (admin creates the company, the first company admin is invited). This is a new API feature (endpoint, storage, admin
  queue, notification, anti-spam); its fields and rules are designed in their own document and coded after the import rehearsal.
- **The `my_scoring` task has no list endpoint (decision gate, decided by the user on 2026-10-19).** `GET company/tasks` and the daily digest point
  the task at `company/assessment/invitations?filter[my_scoring]=pending` (`phase-8-recruiter-workday.md`), but only
  `GET company/assessment/invitations/{id}` is coded. **Decided:** add the list endpoint as an endpoint the front end asks for (allowed during the
  limited coding of `decisions.md` section 5) for page C31; until it lands, the task link opens C1.
  **Coded (2026-10-19):** `GET company/assessment/invitations?filter[my_scoring]=pending`, decided by the user the same day: `filter[my_scoring]=pending`
  is **required** (missing or any other filter is 422) and the member needs no permission beyond being an assessor of the invitation (the set the task counts: the
  company's `submitted` invitations whose result is `pending_scoring`). **Offset-paginated** (`page`, `per_page`; the standard `links` and `meta` with `total`, as
  `company/applications`); **longest waiting first** (the attempt's `submitted_at`, then id). Each row is the fields of `GET company/assessment/invitations/{id}`
  plus `candidate {id, name}`, `job {id, title}` and `submitted_at`; no score and no answer. The front end links a row to the existing `invitations/{id}/scoring`
  calls. The task link no longer needs the C1 fallback (12.3).
- **Page numbers of the designs** (FEG3, decided by the user on 2026-10-19): the pipeline is C6 and the job detail C5; the designs that called the
  pipeline "C5" (`phase-8-applicant-search.md`, `phase-8-recruiter-workday.md`) and the default test per stage "C5"
  (`phase-8-assessment-automation.md`, which belongs in the job editor C4) were corrected the same day.
- **C26 is coded** (`phase-8-interview-calendar.md`, 2026-10-17); the 2026-10-13 table still said "designed".

## 9. Page map: Console and Portal Mitra

Built on 2026-10-13 from the API routes (`/api/v1/admin/**`, `/api/v1/provider/**`) and agreed by the user the same day; **rewritten on
2026-10-19** (page-map session) from the routes of that day. Vite SPA on `console.satu.solutions` from the Able Pro `seed` (`apps/console`),
token in `sessionStorage` (section 5.1). Two sign-ins in one application: `/masuk` for admins, `/mitra/masuk` for psychologists, whose pages all
live under `/mitra/...` (decided 2026-10-13); each has its own layout and menu, and the token of one is never sent to the other's endpoints. Paths
are Indonesian without an `/en` variant; the language comes from the language cookie (section 11.1).

### 9.1 Platform admin pages

The menu follows the admin's permissions (from `GET admin/me`): `dashboard.view`, `analytics.view`, `companies.manage`, `jobs.manage`, `reviews.moderate`,
`messages.moderate`, `campaigns.manage`, `newsletter.manage`, `faqs.manage`, `legal_documents.manage`, `email_templates.manage`, `cv_templates.manage`,
`announcements.manage`, `reference_data.manage`, `settings.manage`, `audit_logs.view`, `assessment_library.manage`, `assessment_providers.manage`,
`users.view` (and `users.manage` after import), `groups.support`. The API stays the authority (403 is "not allowed").

| # | Page | Path | API | Permission |
|---|------|------|-----|------------|
| D1 | Sign in and two-factor | `/masuk` | `POST admin/auth/login`, `POST admin/auth/two-factor` | none |
| D2 | Dashboard | `/` | `GET admin/dashboard` with its operations card (failed and waiting queue jobs, the scheduler watch, `phase-8-platform-admin.md`); **#30:** queue watch; **#33:** WhatsApp counts | `dashboard.view` |
| D3 | Analytics | `/analitik` | `GET admin/analytics/{report}` | `analytics.view` |
| D4 | Companies: list, create, detail | `/perusahaan`, `/perusahaan/{id}` | `GET`/`POST admin/companies`, `GET admin/companies/status-counts` (tab counts, `filter[q]`, `filter[verified]`), `GET .../{id}`, `suspend`, `restore`, `POST .../{id}/invitations` (first company admin), `PUT .../{id}/owner` | `companies.manage` |
| D5 | Company verification documents | `/perusahaan/{id}/dokumen` | `GET .../{id}/documents`, `GET .../{typeId}/file`, `approve`, `reject` | `companies.manage` |
| D6 | Company setting overrides | `/perusahaan/{id}/pengaturan` | `GET .../{id}/settings`, `PUT`/`DELETE .../settings/{key}` | `companies.manage` |
| D7 | Job moderation | `/lowongan`, `/lowongan/{id}` | `GET admin/jobs[/{id}]`, `GET admin/jobs/status-counts` (tab counts, `filter[q]`), `approve`, `reject`, `take-down`, `restore`; moderation flags | `jobs.manage` |
| D8 | Review moderation | `/ulasan` | `GET admin/reviews[/{id}]`, `approve`, `reject`, `remove`; `GET admin/review-replies`, `approve`, `reject`, `remove`; `GET admin/review-reports`, `dismiss` | `reviews.moderate` |
| D9 | Campaigns | `/kampanye` | `admin/campaigns` (CRUD), `qr`, `results` | `campaigns.manage` |
| D10 | Newsletter | `/newsletter` | `admin/newsletter/issues` (CRUD, `test`, `send`), `GET`/`DELETE admin/newsletter/subscribers` | `newsletter.manage` |
| D11 | FAQ | `/faq` | `admin/faq-categories`, `admin/faqs` (CRUD) | `faqs.manage` |
| D12 | Legal documents | `/dokumen-legal` | `admin/legal-documents` (CRUD, `publish`) | `legal_documents.manage` |
| D13 | Email templates | `/template-email` | `GET admin/email-templates[/{key}]`, `PUT`/`DELETE .../{key}/{locale}`, `POST .../{key}/preview` | `email_templates.manage` |
| D14 | CV templates | `/template-cv` | `GET admin/cv-templates`, `PATCH .../{key}` | `cv_templates.manage` |
| D15 | Reference data (one generic page per master) | `/data-referensi/{master}` | benefits, education and experience levels, industries, job categories, levels, roles and types, languages, nationalities, organization types, religions, salary periods, skills, tags, team sizes, work times (`GET`/`POST`/`PUT`); company document types (`admin/company-document-types`) | `reference_data.manage`; company document types `companies.manage` |
| D16 | Settings | `/pengaturan` | `GET admin/settings`, `PUT`/`DELETE .../{key}` | `settings.manage` |
| D17 | Audit log | `/log-audit` | `GET admin/audit-logs` | `audit_logs.view` |
| D18 | Assessment library | `/asesmen/pustaka` | `admin/assessment/library/tests` (CRUD, `publish`, `archive`, `restore`, `clone`), sections and questions (CRUD, order); `GET admin/assessment/batteries`; per company `admin/assessment/companies/{id}/batteries` | `assessment_library.manage` |
| D19 | Psychologist providers | `/asesmen/penyedia`, `/asesmen/penyedia/{id}` | `admin/assessment/providers` (CRUD, `suspend`, `restore`, `PUT .../auto-issue`), invitations, psychologists (`PUT`, `suspend`, `restore`, `reset-two-factor`). **#23:** the register. **#24:** licence queue, lead role, `filter[scope]`. | `assessment_providers.manage` |
| D20 | My account | `/akun` | `GET admin/me`, `PUT admin/me/password`, `POST admin/me/two-factor/recovery-codes`, `logout`, `logout-all` | none |
| D21 | Users (job seekers and company members) | `/pengguna`, `/pengguna/{id}` | `GET admin/users`, `GET admin/users/{id}` (search and read, coded with #17). **after import:** suspend and reactivate (`phase-8-user-management.md`, `users.manage`). | `users.view` |
| D22 | Company registrations (queue, detail, correct, approve, reject) | `/pengajuan-perusahaan` | **frozen, after import:** `admin/company-registrations` (`phase-8-company-registrations.md`) | `companies.manage` |
| D23 | Reports: jobs and messages | `/laporan` | `GET admin/job-reports`, `GET admin/jobs/{id}/reports`, `POST .../reports/dismiss`; `GET admin/message-reports[/{id}]`, `POST .../{id}/action` (note), `POST .../{id}/dismiss` | `jobs.manage`, `messages.moderate` |
| D24 | Company name changes | `/perubahan-nama` | `GET admin/company-name-changes[/{id}]`, `GET .../{id}/document`, `approve`, `reject` | `companies.manage` |
| D25 | Undelivered email | `/email-tidak-terkirim` | `GET`/`POST admin/email-suppressions`, `DELETE .../{id}` | `settings.manage` |
| D26 | Announcements | `/pengumuman` | `admin/announcements` (CRUD) | `announcements.manage` |
| D27 | Groups and tenants | `/grup`, `/grup/{id}` | `GET`/`POST admin/groups`, `GET .../{id}`, `GET .../{id}/members`, `PUT`/`DELETE .../members/{userId}` (first group role), `PUT`/`DELETE .../legal-entities/{entityId}/tenant`, `suspend`, `restore` | `companies.manage` |
| D28 | Support access reads | `/dukungan`, `/dukungan/{groupId}` | `GET admin/support/groups` (groups with a running grant for this admin), `GET admin/support/group`, `units[/{id}[/versions]]`, `unit-types`, `positions[/{id}[/versions]]`, `grades`, `cost-centres`, `activity` (read only, under the grant; the remaining time shown) | `groups.support` |

### 9.2 Portal Mitra

| # | Page | Path | API |
|---|------|------|-----|
| M1 | Invitation (set password, enrol two-factor) | `/mitra/undangan/{token}` | `GET`/`POST provider/invitations/{token}[/accept]`; target of `PSYCHOLOGIST_INVITATION_URL` (section 12) |
| M2 | Sign in and two-factor | `/mitra/masuk` | `POST provider/auth/login`, `POST provider/auth/two-factor` |
| M3 | Tests: list and editor | `/mitra/tes`, `/mitra/tes/{id}` | `provider/tests` (CRUD, `publish`, `archive`, `restore`, `clone`), sections and questions (CRUD, order) |
| M4 | Norms, report and explanation templates, auto-issue | `/mitra/tes/{id}/norma` (tabs) | `GET`/`PUT provider/tests/{id}/norms`, `report-template`, `explanation-template`, `PUT .../auto-issue`. **#23:** norm groups. |
| M5 | Result reviews: queue, detail, drawings, sign | `/mitra/tinjauan`, `/mitra/tinjauan/{id}` | `GET provider/reviews[/{id}]`, `PUT provider/reviews/{id}`, `GET`/`PUT .../drawings/{questionId}`, `POST .../mark-reviewed`, `POST .../sign`, `POST .../revise` (a signed report). **#23:** candidate context, claim and release, recommendation. **#24:** due dates, overdue, revise with a reason, report questions from the company. |
| M6 | My account | `/mitra/akun` | `GET provider/me`, `logout`, `logout-all`. **#24:** licences (`provider/me/licences`), signature (`provider/me/signature`). |
| M7 | Register of reports | `/mitra/register` | **#23:** `GET provider/register` and its export |
| M8 | Institution: profile and team | `/mitra/lembaga` | **#24:** `provider/institution` and logo, `provider/team` (list, invitations, suspend, restore, role) |

The psychologist API has no password change or recovery-code endpoint today (FEG13 "account security"): M6 shows none until one is designed.

### 9.3 Findings while mapping

- **No admin endpoints for public user accounts.** `architecture.md` 2.8 says an admin suspends and reactivates accounts (`account-suspended`), but
  there is no route to list or search job seekers and company members, nor to suspend, reactivate or deactivate them; `AccountStatus` exists in the
  domain only. **Decided by the user on 2026-10-13: add the admin endpoints**: list and search job seekers and company members, detail, suspend
  (revokes every token), reactivate, each audited, behind a new permission (for example `users.manage`). New API work, designed in detail and coded
  after the import rehearsal with the front end. Adds page D21 "Users" to Console. (**2026-10-19:** the read part, `users.view`, was coded
  with #17; D21 shows it now.)
- **No admin endpoints for admin accounts and roles.** Admins are created with `identity:create-admin` and permissions synced with
  `identity:sync-permissions` (command line only); there is no page to invite admins, assign roles or reset another admin's two-factor (only the artisan
  command `identity:reset-admin-two-factor`). **Decided by the user on 2026-10-13: it stays command-line only**: granting admin rights is the most
  sensitive action and is limited to people with server access; Console has no admin-management pages. The artisan commands that make this workable (roles, role permissions, an admin's role, suspension, password reset) were
  added on 2026-10-13 (`phase-8-admin-console.md`).
- **Training (Moodle):** no admin route; courses come from Moodle, so nothing to manage in Console.
- **Legacy admin features without an endpoint** (checked against `legacy-flow-map.md` on 2026-10-13; every `admin/*` route is mapped above).
  **Decided by the user on 2026-10-13: not built**:
  - Candidate export (CSV, Excel): personal data of every job seeker would leave the platform; D21 (search, profile summary) and the D3 CSV
    aggregates stay the only admin views. The applicant export stays per job for the company (R13).
  - A cross-company list of applications: applications stay between the company and the applicant; the admin sees counts in D2 and D3.
  - Job bulk import and clone by an admin: jobs are created by companies, which already clone (`POST company/jobs/{id}/clone`).
  - Platform-wide interview question templates: interview kits stay company templates (`phase-7-interview-kits.md`).

## 10. Technical foundation

Agreed by the user on 2026-10-13 (repository, API client, build and error language chosen from options; the rest agreed as proposed). Updated on
2026-10-19 for the three applications (section 3).

- **Repository (decided):** one front-end repository as a pnpm workspace, Node 24 LTS (installed on the server), at
  `/usr/local/src/jobportal-frontend/onesis-talent`:
  - `apps/public`: Nuxt (server-side rendering), public site and job seeker area (section 7);
  - `apps/workspace`: Nuxt with `ssr: false`, ONESIS Workspace for company and group members (section 8), added 2026-10-19;
  - `apps/console`: Vite SPA from the Able Pro `seed`, Console and Portal Mitra (section 9);
  - `packages/ui`: the Able Pro theme, SCSS, layouts and shared components (section 11.7), including the job detail component that P3 and the
    Workspace preview share;
  - `packages/api`: generated API types, the client and the problem parser;
  - `packages/i18n`: shared `id` and `en` interface messages, the wording of in-app notifications per `type` and their targets (section 12.4)
    (error text comes from the API).
- **API client (decided):** `openapi-typescript` generates the types from the API's OpenAPI 3.1 document (Scramble, 284 paths on 2026-10-13, 589 on
  2026-10-19); `openapi-fetch` is the typed client. A copy of the document (`php artisan scramble:export`, `api.json`, git-ignored in the API) is kept in
  `packages/api` and refreshed by a script; the check fails when the generated types are stale against that copy.
- **Error language (decided by the user on 2026-10-13):** the API sets the request language from `Accept-Language`, but has no `lang/id` files, so
  validation messages and the problem `detail` are English for every user. The API gets Indonesian translations (Laravel validation messages, attribute
  names, problem `detail` per code); the front end shows the text the API returns and never keeps its own text per error code. New API work, done with
  front-end coding.
- **Errors (agreed):** one parser turns `application/problem+json` into `{code, status, detail, errors, retryAfter}`, with `code` taken from
  `urn:onesis:error:<code>`; the union of codes (for decisions, not for text) is generated from `docs/api/error-catalog.md`. A 422 `errors` map goes onto the vee-validate fields, a
  429 shows the wait from `Retry-After`; 401, 403 and 409 are handled centrally (section 5.2).
- **Data and state (agreed):** Pinia for the session, the permissions (section 8.0) and UI settings; server-rendered public pages use Nuxt `useFetch`; the browser-rendered areas
  (`/me`, Workspace) and Console use TanStack Vue Query for caching and invalidation. `@nuxtjs/i18n` with `prefix_except_default` and
  default `id` in the public application; Workspace and Console use vue-i18n with the language cookie (section 11.1). Dates with `date-fns` (`moment` dropped), Rupiah with `Intl.NumberFormat`.
- **Quality (agreed):** ESLint and Prettier (from the template), `vue-tsc`, Vitest with Vue Test Utils, Playwright for the main flows (sign-in with
  two-factor, apply, pipeline move, Workspace sign-in and landing per preset, admin two-factor). Two coverage tests: every notification type of the
  catalog has a sentence and a target (11.6, 12.4), and every task key of the API has a link-entry mapping (12.3).
- **Build (decided):** on the server, **run by the user**; a session prepares the commands only. The server has 7 GB of memory with about 1 GB free
  (2026-10-13) while a Nuxt and Vuetify build can take 2 to 4 GB, so the build commands limit Node's heap and may need swap; build when traffic is low,
  and build one application at a time.
- **Hosting (agreed):** each build goes into its own release folder and a `current` symlink is switched, so the live site never runs from the working
  folder. The public application and Workspace each run Nuxt's `node-server` output as a systemd service on `127.0.0.1` (two ports) behind
  nginx (the units need root: user lingering is off for `jobportal`). Console is static files served by nginx with a strict CSP:
  `script-src 'self'`; `style-src` needs `'unsafe-inline'` because Vuetify injects its theme as an inline style and a static site cannot give it a nonce.
  **Paths (decided by the user on 2026-10-21):** releases under `/usr/local/src/jobportal-frontend/releases/<app>/<release>` with
  `releases/<app>/current` as the live symlink (`workspace`, `console`, later `public`); Workspace's service listens on `127.0.0.1:3101`, Career's
  on `127.0.0.1:3100`. Until a release exists (or while the Workspace service is down), nginx serves a static "Segera hadir" page from
  `/usr/local/src/jobportal-frontend/placeholder/<app>/index.html`. The nginx files are kept in `/home/jobportal/nginx/` (copies of the live ones
  in `/etc/nginx/conf.d/`, installed by the user); the Workspace systemd unit is written with the first build.


## 11. Interface design

Worked out with the user from 2026-10-13, after the page maps and the technical foundation. Order: layouts and navigation (11.1), common states
(11.2), then page layouts, SEO, the complex screens, and texts.

### 11.1 Layouts and navigation

Agreed by the user on 2026-10-13; rewritten on 2026-10-19 for the three applications (menus, banner and language decided that day).

Built from the Able Pro parts (`full-version/src/layouts`, `views/pages/landingpage`, `views/authentication/auth1`, `views/pages/maintenance`).

| Layout | Used by | From Able Pro |
|---|---|---|
| `public` | P1 to P15 | landing page `AppBarMenu` and `FooterSection` |
| `blank` | A1 to A6, B1 to B5, D1, M1, M2, error pages | `BlankLayout`, `auth1` pages, `maintenance/error` |
| `dashboard` | `/me`, Workspace, Console, `/mitra` | `DashboardLayout` with the vertical sidebar and header (`NotificationDD`, `LanguageDD`, `ProfileDD`) |
| `focus` | J4 apply, J6 and C9 video rooms, J7 test runner, C10 voice call | new: no sidebar, a top bar with the title and an exit; full screen where the page needs it |

**Announcement banner (decided by the user on 2026-10-19, FEG8):** a strip above the header in the `public` and `dashboard` layouts of the public
application and Workspace (not in Console, not in `focus` or `blank`), from `GET announcements` (anonymous: audience `all`;
job seekers and company members get their audience too). At most two show, `important` first; `info` can be closed (its id remembered in
`localStorage`), `important` shows again on each visit; `link_url` becomes a button.

**Language (decided by the user on 2026-10-19):** Indonesian by default; the ID/EN switch in the header is kept in a cookie per application and sent
as `Accept-Language`. Public indexed pages keep the `/en` paths of section 7; the job seeker area, Workspace and Console have
no language in the path, and links from emails never carry one (section 12).

**Public header:** logo; Lowongan, Perusahaan, Gaji, Pelatihan; Untuk Perusahaan; language switch (ID/EN); Masuk and Daftar, with "Masuk perusahaan"
(to B1) under Masuk. Signed in (job seekers only): notification bell and the avatar menu (Dasbor, Profil, Lamaran, Keluar). On a phone the links fold
into a drawer.
**Public footer:** about and contact, legal documents (P8), FAQ, report verification (P14, with #23), the newsletter form (P1 `POST newsletter/subscribers`), language switch.

**Job seeker sidebar (`/me`):** Dasbor · *Cari kerja:* Cari lowongan (to `/lowongan`), Lowongan tersimpan, Notifikasi lowongan, Undangan melamar ·
*Lamaran:* Lamaran saya, Jadwal (J16, #15), Asesmen · *Profil:* Profil, CV dan resume · *Komunikasi:* Pesan (unread count), Notifikasi (unread count) ·
*Lainnya:* Ulasan saya, Pelatihan saya, Perusahaan diikuti (frozen) · Pengaturan akun.

**Workspace sidebar (decided by the user on 2026-10-19: one sidebar, grouped per module).** Each item shows only with its access of section 8
(8.0); a group with no visible item is hidden. The header holds the company name, the notification bell, the language switch and the avatar menu
(theme, Akun, Keluar).

- *Ringkasan:* Dasbor (C1), Wawancara saya (C30), Review kandidat (C28, count waiting), Penilaian tes saya (C31), Kalender (C26), Analitik (C2).
- *Talent:* Lowongan (C3), Semua pelamar (C5b), Cari kandidat (C11), Talent pool (C13), Pesan (C14, unread count) · *Alat:* Tes asesmen (C15),
  Kit wawancara (C17), Pertanyaan screening (C18), Tahapan (C19), Kampanye (C23).
- *Workforce:* Persetujuan (E1, count waiting), Rencana tenaga kerja (E4), Permintaan (E5), Delegasi (E2), Alur persetujuan (E3). Persetujuan and
  Delegasi show for a member who holds an approvals or workforce permission or a group role, or who has an approval waiting.
- *Organisasi* (group role only): Grup dan entitas (E6), Unit (E7), Jabatan (E8), Golongan dan pusat biaya (E9), Anggota grup (E10), Rencana dan
  permintaan grup (E13), Rekrutmen grup (E14), Penyedia asesmen (E16, #24), Pengaturan grup (E11), Log grup (E12), Akses dukungan (E15).
- *Perusahaan:* Profil (C20), Lokasi (C32), Dokumen verifikasi (C21), Ulasan (C24), Tim (C22), Pengaturan (C29), Log aktivitas (C27).
- Notifikasi, Akun (C25).

The pipeline (C6), application detail (C7), interview workspace (C8), results (C16) and the rooms (C9, C10) are reached from a job, an
application or an interview, not from the menu.

**Console sidebar:** each item shown only with its permission (9.1): *Ringkasan:* Dasbor, Analitik · *Perusahaan:* Perusahaan, Pengajuan perusahaan
(D22), Perubahan nama (D24), Grup (D27), Jenis dokumen · Pengguna (D21) · *Moderasi:* Lowongan, Ulasan, Laporan (D23) · *Pemasaran:* Kampanye,
Newsletter · *Konten:* Pengumuman (D26), FAQ, Dokumen legal, Template email, Template CV · *Asesmen:* Pustaka tes, Penyedia psikolog · *Sistem:* Data
referensi, Pengaturan, Email tidak terkirim (D25), Log audit · Dukungan grup (D28) · Akun saya. A group with no visible item is hidden.

**Psychologist sidebar (`/mitra`):** Tes, Tinjauan hasil (count waiting), Register (M7, #23), Lembaga (M8, #24), Akun saya.

**Decided by the user on 2026-10-13:**

- **No customizer for users:** the Able Pro customizer is removed; the avatar menu offers light, dark or system theme, kept in a cookie (section 6). The
  layout, colours and menu direction are fixed by the application.
- **Vertical sidebar** in every dashboard; on a phone it becomes the drawer.
- **Bottom navigation on phones for `/me`:** Dasbor, Cari (to `/lowongan`), Lamaran, Pesan (unread count), Profil; the drawer keeps the full menu. A new
  component (Vuetify `v-bottom-navigation`), not in the template. Workspace, Console and Portal Mitra use the drawer only.

### 11.2 Common states

Agreed by the user on 2026-10-13.

One pattern per situation, used by every page of every application.

- **Loading:** a skeleton (`v-skeleton-loader`) shaped like the content on a first load in the browser; server-rendered public pages arrive filled. A
  button that sends shows a spinner and is disabled until the answer, so a form cannot be sent twice.
- **Empty:** an icon, one sentence and the next step ("Belum ada lamaran" with "Cari lowongan"). An empty result of a filter says so and offers to reset
  the filters.
- **Errors** (from the problem parser, section 10):
  - 422 with `errors`: the message under each field, focus on the first one; a 422 or 409 without fields: an alert above the form.
  - 403: a "not allowed" panel inside the layout (no sign-out).
  - 404: the not-found page (public pages answer a real 404 status for search engines).
  - 429: a snackbar with the wait from `Retry-After`; the button stays disabled until then.
  - 503 (for example `training-unavailable`): a card in place of the content with "Coba lagi"; the API in maintenance mode shows the maintenance page.
  - No connection or 500: a snackbar with "Coba lagi"; the form keeps what was typed.
- **Success:** a short snackbar; a new item opens or is highlighted.
- **Snackbar (decided by the user on 2026-10-23):** one `NoticeSnackbar` of `packages/ui` per application (Workspace and Console), copied from the
  "With Close + Action" snackbar of Able Pro (`ui-elements/advance/UiSnackbar.vue`): outlined in the colour of the message, bottom right, an icon per
  kind (success, error, warning, info), "Coba lagi" when the notice has a retry, and a close button.
- **Confirmation:** a dialog naming the thing for every destructive action (withdraw an application, reject, delete); account deletion asks the user
  to type a word.
- **Unsaved changes:** long forms (profile, job editor, test editor, email templates, company profile) warn before leaving.
- **Session end:** public 401 goes to sign-in and back to the page (5.2); the admin's 12-hour token ends with a dialog that leads to sign-in and then to
  the same page.
- **Buttons (decided by the user on 2026-10-23, selective icons):** the text is the label; an icon is added only where it helps recognise the action,
  as a "With Icons" button of Able Pro (`forms/components/VButtons.vue`): an 18px icon in the `prepend` slot, or in the `append` slot for a button that
  moves forward (move to a stage, a menu's chevron).
  - **With an icon:** the action buttons of work pages (toolbars, card headers, bulk actions of a table) and actions with a well-known icon (add,
    delete, edit, save, export or download, reject, sign out).
  - **Without an icon:** the buttons of the account pages (sign-in, password reset, invitation, terms, blocked account), cancel and close in dialogs,
    text-style buttons (back, reset filters, clear selection, switch code method) and a lone call to action (error pages, "Coba lagi").
  - **Dialog confirm buttons:** an icon (`confirm-icon` of `ConfirmDialog` and `ActionFormDialog`, none by default) only for a destructive action or
    one with a well-known icon, the same icon as the button that opened it.
  - Main buttons side by side are either all with or all without icons (a cancel or text-style button beside them stays plain); one action has one icon across the applications; icon-only buttons keep their
    `aria-label`.
- **Updates:** small toggles (save a job, mark as read) change at once and roll back on error; everything else waits for the API.

**Decided by the user on 2026-10-13:**

- **Public lists scroll on** (jobs, companies, training): the next page loads as the visitor reaches the end. So that search engines still reach every
  page (P2 and P12 are indexed), it follows the crawlable pattern: the server renders `?page=N` on its own, the end of the list carries a real link to
  `?page=N+1`, and the address bar follows the page in view (`history.replaceState`), so a shared link opens where the visitor was. Dashboard tables
  keep page numbers (`v-data-table-server`); messages and notifications load older items at the end (cursor lists).
- **Time zone:** times come from the API in UTC and are shown in the device's zone with its label (WIB, WITA, WIT; other zones by their offset). An
  interview time also shows the zone in the invitation and the confirmation.
- **Accessibility:** WCAG 2.1 AA: keyboard use, visible focus, labels on every field, errors announced to screen readers, and the Able Pro colours
  used as they are (**changed 2026-10-21 by the user:** no theme adjustment for contrast, template rule 2 of section 6).

### 11.3 Key page layouts

Agreed by the user on 2026-10-13.

Wireframes were shown to the user for P2, P3, J1 and C6; the other pages follow the closest Able Pro page.

**Sticky action bar (decided by the user on 2026-10-23):** the actions of a whole page of Workspace sit at the top in the sticky action bar of Able
Pro (`forms/layouts/StickyActionBar.vue`), as the shared `StickyActionCard` of `packages/ui`: an outlined card holding the page, headed by the toolbar
that stays under the header (`top` 74px, the header's height) while the page scrolls. The bar has the template's background (`bg-containerBg`), the buttons on the right and no title; it is hidden when the viewer has no action; on a phone it is compact and secondary
buttons become icon buttons with an `aria-label`. Actions of one item (a table row, a note, the member's own rating, a row of the security tab) and
the bulk actions of a selection stay next to their item. The buttons of a small form stay under the form (the password
form of the account page, kept so by the user the same day). Used by C5 (applicants and lifecycle actions) and C7 (resume, the action of the open
tab, the stage actions with the less common ones in an "Aksi lain" menu).

- **P1 Home:** search box (keyword, location) over the banner; job categories; latest jobs; verified companies (from P12); salary insight and training
  teasers; newsletter in the footer. Signed-in job seekers see their recommendations first.
- **P2 Job search:** search bar with sort (relevant, newest, salary); filters in a left column (a bottom sheet on a phone) with "save as job alert";
  job cards (logo, title, company with badge, location, salary when shown, tags, match percentage and save when signed in); scroll-on with crawlable
  page links (11.2).
- **P3 Job detail:** main column (header facts, description, qualifications, benefits, skills, salary insight, about the company, other jobs of the
  company); a side card that stays in view with salary, Apply, save and share (a fixed bar at the bottom on a phone).
- **P4 Company profile:** banner and logo, name, badge, facts; tabs About, Open jobs, Reviews (rating summary, list, write a review).
- **J1 Dashboard:** profile completeness with the next step, active applications with status, upcoming interviews and assessments, recommendations; on
  a phone the bottom navigation (11.1).
- **J2 Profile:** one page of sections (basics, experience, education, skills, languages, links, avatar), each edited in a dialog; completeness shown
  at the top.
- **C6 Pipeline:** job header with the switch between table and kanban; **the table is the default (decided by the user on 2026-10-13)**:
  `v-data-table-server` with selection for bulk move, reject and message; kanban (Able Pro kanban, one column per stage with counts, cards dragged to
  move) one click away, and the last choice is remembered per user in the browser. Filters and export on both.
- **C7 Application detail:** candidate column (profile, resume viewer, screening answers) and a work column with tabs: notes, rating, interviews,
  assessments, offer, history; stage actions at the top.
- **Console lists** (D4, D7, D8, D21, D22): the same server table with filters on top and the detail in a page, actions in the detail header.


### 11.4 Search engines

Agreed by the user on 2026-10-13.

- **Titles and descriptions** per page in both languages, from templates, for example P3 "{title} di {company}, {city} | {brand}" and a description
  from the first sentences of the job; P4 "{company}: lowongan dan ulasan | {brand}". Open Graph and Twitter cards with the company logo or a default
  image.
- **Structured data (JSON-LD):** `JobPosting` on P3 (`datePosted`, `validThrough` from the deadline, `employmentType` from the job type,
  `hiringOrganization`, `jobLocation` or `applicantLocationRequirements` for remote work, `baseSalary` only when the company shows it, `directApply`
  true); `Organization` on P4; `Course` on P6b; `FAQPage` on P9; `BreadcrumbList` on detail pages; `WebSite` with `SearchAction` on P1.
- **Closed jobs:** the API answers 404 for a job that is no longer live (`GetPublicJob`), so the page answers 404 too, with similar jobs to continue;
  search engines then drop it, as Google asks for expired job postings.
- **Sitemaps:** an index served by Nuxt (`@nuxtjs/sitemap`) with live jobs, companies (P12 endpoint), courses, legal documents and the static pages, in
  both languages with `hreflang` alternates; rebuilt from the API on request and cached for an hour.
- **`robots.txt`:** public application disallows `/me`, `/api`, the account pages and their `/en` versions; Workspace and the Console domain disallow
  everything and send `noindex` on every response (changed 2026-10-19 for Workspace).
- **`hreflang`:** `id`, `en` and `x-default` (Indonesian) on every indexed page.

**Decided by the user on 2026-10-13:**

- **Brand name (decided 2026-10-15, `naming.md`):** `{brand}` is **ONESIS Career** on the job seeker site and **ONESIS** on the ERP side; titles,
  emails and structured data read it from configuration (`APP_NAME=ONESIS`).
  It must be set before go-live (deployment checklist).
- **Indexed filter pages:** only `/lowongan` with a category, a location (province or city), or both. Those pages carry a canonical URL with the
  parameters in a fixed order and their own title ("Lowongan IT di Bandung"); every other combination (keyword, salary, level, sort, more than one value)
  answers `noindex, follow`. The sitemap lists the indexable combinations that have live jobs.
- **Google Indexing API:** not at go-live; the sitemap first, the Indexing API considered after go-live as separate API work.

### 11.5 Complex screens

Agreed by the user on 2026-10-13.

Built on `phase-6-assessments.md` and `phase-7-tele-interview.md`; all run in the `focus` layout (11.1) in the browser only.

**J7 test runner:**

- **Before the start:** instructions per section, what is recorded (tab switch, full-screen exit, paste and IP changes; never a webcam image), the
  consent box (and the separate recording consent when the test has video questions), a camera and microphone check for video questions, then
  "Mulai", which asks for full screen.
- **During:** a top bar with the section, a countdown from the server time in `GET attempt` (corrected on every answer, since the timer runs on the
  server and keeps running when the window closes), the question navigator (`PUT attempt/cursor`) and the progress. Answers are saved as they change
  (short debounce) with a "saved" mark; when the connection drops, unsent answers wait in memory and are sent again, with a warning that time keeps
  running.
- **Integrity signals:** `visibilitychange` sends `tab_switch`, leaving full screen sends `fullscreen_exit` (with a prompt to return), `paste` sends
  `paste`; the candidate is not shown a tally.
- **Kraepelin columns:** a screen of its own with the column, the digits typed by keyboard or an on-screen number pad, a column timer, and each column
  sent when its slice ends (within the 3-second grace).
- **Drawing:** a canvas with pen, eraser, undo and clear (pointer events, so a finger or stylus works), or a photo upload; sent as PNG.
- **Video answer:** think time, then recording with `MediaRecorder` (WebM, or MP4 on Safari), upload in 5 MB chunks with progress, retakes up to the
  limit (a rejected take is deleted at once); the practice question stays in the browser.
- **Section end and submit:** "next section" and "submit" ask for confirmation with the number of unanswered questions; when time runs out the server
  closes the section and the runner loads the next state.

**J6 and C9 video room (Janus):**

- **Before joining:** device check (camera and microphone choice, preview, microphone level, a connection test through TURN) and the recording consent
  state.
- **Candidate:** a waiting screen in the lobby (Reverb event, REST polling as fallback) until admitted, then the room.
- **Room:** up to five tiles (four company participants and the candidate), microphone and camera toggles, a recording indicator for everyone, leave.
  Company participants also get admit, start and stop recording, and end. After a dropped connection the page rejoins inside the window.
- **Library:** the official `janus.js` with `webrtc-adapter`.

**C10 voice call (Asterisk):** the team member's browser registers over WebSocket (JsSIP) with the credentials the API returns; the screen shows the
candidate, call attempts left, call, ringing and connected states, mute, hang up, start and stop recording (Asterisk announces it to the candidate), and
the call time.

**Decided by the user on 2026-10-13:**

- **Assessments on every device:** the runner is responsive, phones included: Kraepelin with the on-screen number pad, drawing with a finger or stylus,
  video answers with the front camera. No test is refused on a phone.
- **Interview panel inside the room:** company participants in C9 and C10 get a side panel with the kit questions and their scorecard (C8 endpoints:
  draft and submit), hidden behind a button on small screens. The candidate never sees it.
- **Screen sharing in video interviews: yes.** Janus 1.1 (the Debian 12 package in `deploy/janus/Dockerfile`) is multistream, so the shared screen is a
  second video stream of the same publisher (`getDisplayMedia`): the room's limit of five publishers (`VideoSessions::MAX_PARTICIPANTS`) does not change.
  **Shared screens are not recorded (decision gate, decided by the user on 2026-10-13):** a recording holds the cameras and the sound only, because a
  shared screen may show personal data outside what the candidate agreed to, and the converter's 640x360 columns would make it unreadable anyway. The
  browser publishes the screen as a stream with the description `screen`, and the converter (`deploy/janus/converter/converter.py`) leaves out the
  tracks of those streams; how the converter learns which track is a screen is settled when coding. This is new work on the Janus deployment, done
  with the front end; the recording consent text stays as it is. **Every participant may share** (candidate and company participants), one screen at
  a time: a new share replaces the one in progress, and everyone sees who is sharing.

### 11.6 Texts

Agreed by the user on 2026-10-13 as proposed.

- **In-app notifications:** the API stores a `type` and its `data` and leaves the wording to the client (`*NotificationType` classes in eight contexts
  plus the assessment and interview types, about 60 in all). The OpenAPI document types `data` as a plain string, so the front end cannot learn the
  list from it. An artisan command writes a notification catalog (type, recipients, `data` keys) like `api:error-catalog`, with `--check` (new API work, `phase-8-api-translations.md`);
  `packages/i18n` holds an `id` and `en` sentence per type, and a front-end test fails when a type of the catalog has no sentence. **2026-10-19:**
  each type also has a target page (section 12.4), checked by the same test.
- **Static pages** (P13 "Untuk perusahaan", about, contact, the home banner and footer texts): Markdown files per language in the front-end repository,
  changed through a deployment. Legal documents and the FAQ already come from the API (P8, P9).
- **Wording:** a session drafts the interface texts, the notification sentences and the static pages with the glossary of `phase-8-api-translations.md`;
  the user reviews them. Contact details (email, phone, address, the WhatsApp number if any) come from the user.

### 11.7 Shared components

Added on 2026-10-19 (page-map session, FEG7 and FEG8); built once in `packages/ui` and used by every application.

- **Address picker:** cascading fields province → city → sub-district → village (`GET provinces`, `cities`, `subdistricts`, `villages`, each
  filtered by its parent level), each searchable, plus a postal-code field (`GET postal-codes`) that fills the chain when one code matches; a free
  address line. Used by J2 (profile), C32 (work locations) and every form that writes an address.
- **Location picker:** one active company location from `GET company/locations` (main location first) or "online"; used by the interview dialog
  (C6, C7), the job editor (C4) and requisitions (E5). Shows the address and a map link.
- **Announcement banner** (11.1), **permission gate** (shows a menu item, button or tab only with its permission, section 8.0), **interview card**
  (the job seeker's J5 card and the team's C7 and C8 header share the time, zone label, place and state badges).

## 12. Links and targets

Added on 2026-10-19 in the page-map session (FEG4, FEG6), decided by the user as recommended. The API builds every link from a `.env` value; the
values below point at the pages of sections 7 to 9 and go into `.env.example` and `docs/deployment-checklist.md` (production domains replace the
development ones later, section 4). No new API code is needed for them, apart from the two fixes of 12.5.

### 12.1 Rules for every link

Decided by the user on 2026-10-19:

- **Signed out:** the target application's sign-in (A1, B1, D1 or M2) opens and returns to the link's path and query afterwards (`?ke=<path>`,
  only a path of the same application is accepted).
- **Wrong kind of account:** the public sign-in (A1) refuses a company or group member and offers "Masuk ke Workspace" to B1 with the
  Workspace equivalent of the path when there is one (12.3); the Workspace sign-in (B1) refuses a job seeker and links to the public site. An
  admin or psychologist token never exists in the Nuxt applications.
- **Right kind, not theirs:** the API decides; 404 shows the not-found page, 403 the "not allowed" panel (`company-permission-required` and the
  like), never a sign-out (section 5.2).
- **Language:** links carry no `/en`; the page opens in the reader's cookie language (11.1).
- **Tokens in links** (invitations, newsletter, the manual-candidate claim) open their page without sign-in; the page shows the API's answer for an
  expired or used token.

### 12.2 Link contract

| Link | Sent in | `.env` value (development) | Opens (application, page) | Signed out | Wrong kind of account |
|---|---|---|---|---|---|
| Company invitation | invitation email (`InvitationMail`) | `COMPANY_INVITATION_URL=https://workspace.satu.solutions/undangan/{token}` | Workspace B4 | works (token) | works (the token decides; the API answers for an email it cannot accept) |
| Psychologist invitation | provider invitation email | `PSYCHOLOGIST_INVITATION_URL=https://console.satu.solutions/mitra/undangan/{token}` | Console M1 | works (token) | — |
| Campaign registration | campaign link and QR code (C23, D9) | `CAMPAIGN_REGISTRATION_URL=https://karir.satu.solutions/kampanye/{code}` | public P7 | works (public); registration keeps the code | public page; only job seekers register |
| Profile reminder | profile reminder email | `JOB_SEEKER_PROFILE_URL=https://karir.satu.solutions/me/profile` | public J2 | A1, then back | A1 refuses a company member (to B1) |
| The job seeker's application | "lamaran diterima" email; the job seeker's interview `.ics` (`URL` and description) | `JOB_SEEKER_APPLICATION_URL=https://karir.satu.solutions/me/applications/{application_id}` | public J5 | A1, then back | A1 refuses a company member, with "Masuk ke Workspace" to `/pelamar/{application_id}` (C7) |
| The team's application | panel invitation and cancellation, panel answers, withdrawal and event emails to the team | `COMPANY_APPLICATION_URL=https://workspace.satu.solutions/pelamar/{application_id}` | Workspace C7 (limited layout for a panel seat) | B1, then back | B1 refuses a job seeker (to the public site); a member without access gets 403 |
| Review request | `review.requested`, reminder and overdue emails | `COMPANY_APP_URL=https://workspace.satu.solutions` + `/review-requests/{id}` | Workspace link entry → C28 `/review/{id}` | B1, then back | as above |
| Daily team digest | digest email lines | `COMPANY_APP_URL` + the task's `filter` (`/company/...`) or `/company/applications/{id}` | Workspace link entry → the pages of 12.3 | B1, then back | as above |
| Newsletter confirmation | confirmation email | `NEWSLETTER_CONFIRM_URL=https://karir.satu.solutions/newsletter/konfirmasi/{token}` | public P10 | works (token) | works |
| Newsletter unsubscribe | every issue | `NEWSLETTER_UNSUBSCRIBE_URL=https://karir.satu.solutions/newsletter/berhenti/{token}` | public P10 | works (token) | works |
| Manual-candidate claim | **#31** email to the person | setting named when #31 is coded; value `https://karir.satu.solutions/undangan-lamaran/{token}` | public P15 | page works; the claim needs A1 | A1 refuses a company member |
| Team interview `.ics` | panel invitation `.ics`, `GET company/interviews/{id}/calendar.ics`, the cancellation on a withdrawal | `COMPANY_APPLICATION_URL` (coded 2026-10-19, 12.5) | Workspace C7 | B1, then back | files made before the fix still open J5, and A1 sends the member on to C7 |

Not a link of ours: the meeting link of an online interview (set by the company), an announcement's `link_url` (set by an admin), Moodle course
links (`MOODLE_COURSE_URL`). No link by design: password reset and email verification (six-digit codes), the data export email (download while
signed in, J15), approval emails (approving from an email is FG15, an API gap).

### 12.3 Link entry in Workspace

**Decided by the user on 2026-10-19:** the API's team links have the shape of API paths. Workspace answers them with link-entry routes that
only translate the path and its filters to a page and redirect (`replace`, no history entry); they render nothing themselves. The same table serves
the "Perlu tindakan" card of C1, whose items carry the same `filter`, and the A1 refusal (public path → Workspace path).

| Incoming path (after `COMPANY_APP_URL`) | Task key | Page |
|---|---|---|
| `/review-requests/{id}` | (review emails) | C28 `/review/{id}` |
| `/company/review-requests?filter[state]=pending` | `my_reviews` | C28 `/review?state=pending` |
| `/company/review-requests?filter[role]=requester&filter[state]=overdue` | `reviews_overdue` | C28 `/review?role=requester&state=overdue` |
| `/company/applications/{id}` | (digest interviews) | C7 `/pelamar/{id}` |
| `/company/applications` | (digest new applications) | C5b `/pelamar?sort=newest` |
| `/company/applications?filter[state]=offer_pending` | `offers_waiting` | C5b with the same filter |
| `/company/applications?filter[state]=offer_pending&filter[offer_expiring]=1` | `offers_expiring` | C5b with the same filters |
| `/company/applications?filter[stale]=1` | `stale_applications` | C5b with the same filter |
| `/company/jobs?filter[status]=draft` | `jobs_draft` | C3 `/lowongan?status=draft` |
| `/company/jobs?filter[status]=active&filter[deadline_to]=…` | `jobs_closing` | C3 with the same filters |
| `/company/jobs?filter[status]=active&filter[applications]=0` | `jobs_without_applicants` | C3 with the same filters |
| `/company/interviews?filter[rsvp]=pending` | `my_rsvp` | C30 `/wawancara?rsvp=pending` |
| `/company/interviews?filter[rsvp]=declined` | `panel_declined` | C30 with the same filter |
| `/company/interviews?filter[my_scorecard]=pending` | `my_scorecards` | C30 with the same filter |
| `/company/interviews?filter[proposal]=open` | `reschedule_requests` | C30 with the same filter |
| `/company/assessment/invitations?filter[my_scoring]=pending` | `my_scoring` | C31 (endpoint coded 2026-10-19, 8.5; the C1 fallback is no longer needed) |
| anything else under `/company/` | | C1 with the notice "Tautan tidak dikenali" |

The task keys are read from the code of 2026-10-19 (`src/*/Infrastructure/Tasks`); the coverage test of section 10 fails when the API gains a key
without a row. The A1 refusal maps `/me/applications/{id}` to `/pelamar/{id}`; any other public path leads to B1's landing page.

### 12.4 Notification targets

In-app notifications carry a `type` and `data` and no URL (11.6); clicking one in J12 or C25 opens the page below, built from the ids in `data`
(the catalog command of `phase-8-api-translations.md` names the keys; until then the coding session reads them from the notifiers). A type without
a target opens nothing and stays readable in the list. Types read from the code on 2026-10-19:

| Types | Reader | Target |
|---|---|---|
| `application.moved_to_interview`, `.moved_to_offer`, `.hired`, `.hire_updated`, `.rejected`, `.withdrawn_by_company`; `interview.scheduled`, `.rescheduled`, `.updated`, `.cancelled`, `.proposal_accepted`, `.proposal_refused`, `.reminder`, `.starting_soon`, `.no_show`, `.call_missed` | job seeker | J5 `/me/applications/{application_id}` |
| `offer.sent`, `.revised`, `.withdrawn`, `.expiring` | job seeker | J17 `/me/applications/{application_id}/offer` |
| `assessment.invited`, `.reminder`, `.expired`, `.cancelled`, `.explanation_ready`, `.explanation_revised` | job seeker | J7 `/me/assessments/{invitation_id}` |
| `invitation.received` | job seeker | J8 |
| `job_alert.matches` | job seeker | J10 (the alert's jobs) |
| `profile_reminder` | job seeker | J2 |
| `message.received` | both | J11 or C14, the conversation |
| `review.published`, `.rejected`, `.removed`, `.reply_published` (company reviews) | job seeker | J13 |
| `account.data_export_ready` | job seeker | J15 |
| `report.reviewed` | both | none (the outcome is the text) |
| `application.received`, `.withdrawn`, `offer.accepted`, `.declined` | team | C7 `/pelamar/{application_id}` |
| `job.filled`, `job.assigned`, `.expired`, `.approved`, `.rejected`, `.taken_down`, `.restored`, `.held` | team | C5 `/lowongan/{job_id}` (`job.unassigned`: C3) |
| `interview.confirmed`, `.proposed`, `.declined`, `.cancelled_for_team`, `.recording_withdrawn`, `.scorecard_reminder`, `.panel_invited`, `.panel_declined`, `.scorecards_missing` | team | C8 `/wawancara/{interview_id}` (C7 when only the application id is present) |
| `assessment.submitted`, `.declined`, `.report_signed`, `.report_revised` | team | C16 `/asesmen/hasil/{invitation_id}` |
| `invitation.declined` | team | C12 of the job |
| `review.received` (a company review) | team | C24 |
| `review.requested`, `.reminder`, `.overdue`, `.completed` (shortlist reviews) | team | C28 `/review/{request_id}` |
| `approval.requested`, `.reminder`, `.granted`, `.rejected`, `.returned`, `.approver_vacated` | team | E1 `/persetujuan/{approval_id}` |
| `company.document_approved`, `.document_rejected`, `.verified`, `.verification_withdrawn` | team | C21 |
| `company.name_change_approved`, `.name_change_rejected` | team | C20 |
| `company.permissions_changed` | team | C1 (the permissions and menu are read again, 8.0) |
| `company.member_renamed`, `company.two_factor_reset` | team | C25 |

Preference keys (`application_update`, `job_alert`, `invitation`, `chat_message`, `approval_task`, `approval_update`, `team_reviews`) are not
types. The coverage test of 11.6 also fails when a type has no row here.

### 12.5 Findings while mapping the links

- **The team's `.ics` points at the job seeker's page (decision gate, decided by the user on 2026-10-19).** `InterviewCalendar` takes one page
  template, `JOB_SEEKER_APPLICATION_URL`, so the panel invitation `.ics`, the downloaded team `.ics` and the team's cancellation `.ics` carry the
  job seeker's J5 link. **Decided: fix it in the API** as work the front end asks for (the team's files use `COMPANY_APPLICATION_URL`); until then
  the A1 refusal sends a member on to C7 (12.3).
  **Coded (2026-10-19):** `InterviewCalendar::forTeam()` writes `COMPANY_APPLICATION_URL` into the panel invitation and cancellation `.ics`, the
  `GET company/interviews/{id}/calendar.ics` download and the team's withdrawal cancellation; the job seeker's files keep `JOB_SEEKER_APPLICATION_URL`. An empty
  `COMPANY_APPLICATION_URL` leaves the team's files without a link (the job seeker's page is never used for them). The A1 refusal to C7 stays valid for old
  files already in calendars.
- **`my_scoring` has no list endpoint:** see 8.5 (decided the same day: the endpoint is added for C31).

## 13. Coding order

Agreed by the user on 2026-10-21. Foundation first, then one thin slice through the three applications that can be tried end to end (Console prepares
a company, Workspace posts a job, a job seeker applies on Career, the recruiter moves the applicant), then widened. The slice finds problems in the
sign-in, the proxy and the permissions before many pages depend on them. The three points of section 13.1 are decided before step 0 starts.

| Step | Application | Work | Why here |
|---|---|---|---|
| 0 | all | pnpm workspace (Node 24); `packages/api` (types generated from `api.json`, `openapi-fetch`, problem parser), `packages/ui` (Able Pro theme, layouts, shared components 11.7), `packages/i18n`; ESLint, Prettier, `vue-tsc`, Vitest; build and release commands for `releases/<app>/current`. API alongside: the Indonesian translations (`lang/id.json`, `phase-8-api-translations.md`, not coded) | Every form shows the API's text (section 10) |
| 1 | Console | D1, D20, D2, the menu by permission; then D4, D5 (companies and their documents), D7 (job moderation) | The simplest application (static SPA, `sessionStorage`, no proxy) tests the shared packages; it also prepares the companies the next steps need |
| 2 | Workspace | Nuxt proxy with the sealed cookie, `Origin` check, central 401, 403 and 409 (5.2); B1 to B5; the shell of 8.0 (permissions, menu, landing page per preset); C25; the link-entry routes of 12.3; the systemd unit on `127.0.0.1:3101` | The proxy pattern is reused by Career |
| 3 | Workspace | Recruitment core: C1, C3, C4, C5, C6, C7, C5b; the settings they rely on: C19, C18, C17, C20, C21, C32, C22, C29 | The daily work of a recruiter |
| 4 | Career | Server-rendered public pages P1, P2, P3, P4, P8, P11; A1 to A6; J1, J2, J3, J4 (apply), J5. Then the end-to-end flow is tried and the Playwright tests of the main flows (section 10) are written | Closes the slice |
| 5 | all | The rest needed to replace karir.pkp. Workspace: C8, C26, C30, C28, C14, C11 to C13, C15, C16, C31, C23, C24, C2, C27. Career: P5 to P7, P9, P10, J8 to J17, the search-engine work of 11.4. Console: D3, D6, D8 to D17, D21, D23 to D26 | Go-live scope |
| 6 | Career, Workspace | Complex screens (11.5): test runner J7, video rooms J6 and C9 (Janus), voice C10 (JsSIP) | Waits for the root setup of Janus, coturn and Asterisk |
| 7 | Workspace, Console | ERP pages E1 to E15, D27, D28; Portal Mitra M1 to M6 | New features, not part of replacing the legacy site |

Throughout: an element marked **#N** is built with its page once its API item is coded (#38 to #44 wait for the front end); **frozen** and **after
import** elements (P6b, P12, P13, D22, the suspend and reactivate actions of D21) come last.

### 13.1 Before step 0

Three points, decided by the user on 2026-10-21 before coding starts:

1. **Development data: a demo seeder.** `jobportal_v2` holds reference data only (0 accounts) and the seeders add no accounts. A new artisan command
   (API, step 0) fills the development database with fictitious data: a group with two companies, one account per preset (company admin, recruiter,
   hiring manager, interviewer), job seekers, jobs and applications at several stages. It refuses to run in production. No real personal data reaches
   the development domains, and the database is emptied with `migrate:fresh` before the final import. Not chosen: the rehearsal data in a separate
   database (real personal data on the development domains, accounts that need activation) or in `jobportal_v2` itself. Coded on 2026-10-21 as `php artisan demo:seed` (`phase-8-demo-data.md`).
2. **Client IP behind the Nuxt proxy (5.2): trust the loopback proxy.** The API trusts `127.0.0.1` only (`trustProxies`); the Nuxt servers of Career
   and Workspace call the API over the loopback address and set `X-Forwarded-For` to the visitor's IP that nginx gives them (`X-Real-IP`), never
   passing on a header sent by the browser. Other requests are unchanged: their `REMOTE_ADDR` is the client's own address. The rate limiters,
   audit and consent records then hold the visitor's IP; the rate-limiter rules stay as they are. Coded in step 2 (the API part is work the front
   end asks for). Not chosen: no trust (one shared bucket for every visitor, the server's IP in the audit) or postponing again.
   **Coded on 2026-10-22** in `bootstrap/app.php` (`trustProxies(at: ['127.0.0.1'], headers: HEADER_X_FORWARDED_FOR)`; tests `TrustedProxyTest`). Found with it: a guest
   request without a JSON `Accept` answered 500 ("Route [login] not defined"); `redirectGuestsTo(null)` makes it the 401 problem document.
3. **Go-live scope: steps 0 to 5.** They replace karir.pkp. Steps 6 (online tests, video and voice interviews) and 7 (ERP pages, Portal Mitra) come
   after go-live, so those features are not available at go-live.
