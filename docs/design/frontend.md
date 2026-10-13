# Front end

Design of the web front end that consumes this API. Code is not started.

## Decisions

- 2026-10-13: the front end is designed now and coded after the legacy import rehearsal; go-live readiness keeps priority ([detail](#1-timing)).
- 2026-10-13: Vue with TypeScript; the public application on Nuxt (server-side rendering), the admin panel a single-page application on Vite ([detail](#2-stack)).
- 2026-10-13: the front end lives in its own repository, separate from the API ([detail](#3-repository-and-applications)).
- 2026-10-13: two applications: the public site with job seekers and company dashboards, and a separate admin panel on its own domain ([detail](#3-repository-and-applications)).
- 2026-10-13: development domains `karir.satu.solutions` (public) and `admin-karir.satu.solutions` (admin); production domains later ([detail](#4-domains)).
- 2026-10-13: the admin panel keeps its token in `sessionStorage`, with a strict CSP ([detail](#5-authentication)).
- 2026-10-13: public application authentication through the Nuxt server: the API token stays in a sealed httpOnly cookie and every API call goes through a Nuxt proxy route; the client-IP consequence is a separate open point ([detail](#52-public-application)).
- 2026-10-13: the rest of the public flow agreed: CSRF by `SameSite=Lax` plus an `Origin` check, central handling of 401/409/403, area chosen from `GET /me`, Google Identity Services, sign-out, Reverb authorisation through the proxy ([detail](#52-public-application)).
- 2026-10-13: the token is kept inside a sealed cookie with `nuxt-auth-utils` (no server-side session store); the Nuxt server runs on the same host as the API behind nginx ([detail](#52-public-application)).
- Postponed to front-end coding: client IP behind the Nuxt proxy (trusted proxy or not; the rate-limiter rules stay unchanged) ([detail](#52-public-application)).
- 2026-10-13: page map of the public site, account pages and job seeker area agreed (findings in 7.4 still open) ([detail](#7-page-map-public-site-and-job-seekers)).
- 2026-10-13: public URLs in Indonesian; English under the `/en` prefix with `hreflang`, Indonesian unprefixed by default ([detail](#7-page-map-public-site-and-job-seekers)).
- 2026-10-13: a public company list is added to the API (`GET /companies`, new endpoint, coded after the import rehearsal together with the front end) for a company directory page ([detail](#74-findings-while-mapping)).
- 2026-10-13: a training course detail endpoint is added to the API (`GET /training/courses/{id}`, coded with the front end) for an indexed course page ([detail](#74-findings-while-mapping)).
- 2026-10-13: the partner psychologist portal (`/api/v1/provider/**`) is a separate area of the admin application, with its own sign-in and menu ([detail](#3-repository-and-applications)).
- 2026-10-13: the job seeker area (`/me/**`) renders in the browser only (`ssr: false`); data still goes through the Nuxt proxy ([detail](#7-page-map-public-site-and-job-seekers)).
- 2026-10-13: company dashboard page map agreed (C1 to C25), under the `/rekruter` prefix, browser-rendered ([detail](#8-page-map-company-dashboard)).
- 2026-10-13: entry for employers: a static `/untuk-perusahaan` page with contact details **and** a company application form backed by a new API endpoint and an admin review queue (new feature, designed separately, coded after the import rehearsal) ([detail](#81-findings-while-mapping)).
- 2026-10-13: admin panel (D1 to D20) and psychologist area (M1 to M6) page maps agreed; the psychologist area lives under `/mitra` on the admin domain (findings in 9.3 still open) ([detail](#9-page-map-admin-panel-and-psychologist-area)).
- 2026-10-13: admin endpoints for public user accounts are added (list, search, detail, suspend, reactivate, audited, new permission), new API work after the import rehearsal; adds page D21 ([detail](#93-findings-while-mapping)).
- 2026-10-13: admin accounts and roles stay console-only; no admin-management pages ([detail](#93-findings-while-mapping)).
- 2026-10-13: one front-end repository as a pnpm workspace (`apps/public`, `apps/admin`, `packages/ui`, `packages/api`, `packages/i18n`), Node 24 LTS ([detail](#10-technical-foundation)).
- 2026-10-13: API types generated from the Scramble OpenAPI document with `openapi-typescript`, called through `openapi-fetch` ([detail](#10-technical-foundation)).
- 2026-10-13: the front end is built on the server by the user; a session only prepares the commands ([detail](#10-technical-foundation)).
- 2026-10-13: English public paths are translated (`/en/jobs`, `/en/companies`), Indonesian unprefixed (`/lowongan`) ([detail](#7-page-map-public-site-and-job-seekers)).
- 2026-10-13: API error text is translated in the API (`lang/id`: validation messages, attribute names, problem `detail`); the front end shows the API text and decides by `code` only. New API work, done with front-end coding ([detail](#10-technical-foundation)).
- 2026-10-13: rest of the technical foundation agreed: central problem+json handling, Pinia with `useFetch` and TanStack Vue Query, ESLint/Prettier/`vue-tsc`/Vitest/Playwright, release folders with a `current` symlink, Nuxt as a systemd service, admin as static files with a strict CSP ([detail](#10-technical-foundation)).
- 2026-10-13: the five new API pieces are designed in `phase-8-company-directory.md`, `phase-8-training-course-detail.md`, `phase-8-api-translations.md`, `phase-8-user-management.md` and `phase-8-company-registrations.md` (adds admin page D22).
- 2026-10-13: UI on the user's Able Pro Vue 1.7.0 template (Vuetify 3); the admin panel starts from its `seed`, the public Nuxt application ports its layouts and components through `vuetify-nuxt-module` ([detail](#6-ui-template)).

## 1. Timing

Decided by the user on 2026-10-13. The design of the front end (stack, application split, authentication, pages) is worked out now through questions and
answers, recorded here. Coding waits until the legacy import rehearsal is done, so go-live readiness is not delayed and the front end can be built against
the imported data.

## 2. Stack

Decided by the user on 2026-10-13: Vue with TypeScript.

- **Public application:** Nuxt with server-side rendering, so job and company pages can be indexed by search engines and the API token can be kept in an
  httpOnly cookie on the Nuxt server instead of in browser storage.
- **Admin panel:** a single-page application on Vite; it needs no indexing and is served as static files.

## 3. Repository and applications

Decided by the user on 2026-10-13:

- **Repository:** separate from the API (`jobportal_v2`), so the API and the front end deploy independently and the API working folder, which is also the
  live site, is not touched by Node builds.
- **Applications:** two. One serves the public pages, job seekers and company dashboards; the admin panel is a second application on its own domain, because
  admins have their own guard, TOTP and 12-hour tokens and the panel should not be exposed on the public domain.
- **Partner psychologists (decided by the user on 2026-10-13):** the fifth role (`/api/v1/provider/**`: sign-in with two-factor, test authoring, norms,
  report templates, reviewing and signing results) gets a separate area in the admin application, with its own sign-in and menu. It is back office like
  the admin panel (two-factor, no indexing, token in `sessionStorage`), so the split stays at two applications.

## 4. Domains

Decided by the user on 2026-10-13: `karir.satu.solutions` for the public application and `admin-karir.satu.solutions` for the admin panel during
development, next to the API at `api-karir.satu.solutions`. Both origins go into `CORS_ALLOWED_ORIGINS`. Production domains are decided later.

## 5. Authentication

### 5.1 Admin panel

- **Decided by the user on 2026-10-13:** the 12-hour admin token is kept in `sessionStorage`, so it is dropped when the tab closes, together
  with a strict Content Security Policy. The API needs no change.

### 5.2 Public application

The whole flow below was agreed by the user on 2026-10-13 (the pattern, items 1 and 2, first; items 3 to 8 afterwards). One point is postponed.

**API contract it builds on** (read from the code, 2026-10-13):

- `POST /api/v1/auth/register` (job seekers only: `name`, `email`, `password`, optional `device_name`, `campaign_code`), `POST /auth/login` (`email`,
  `password`, `device_name`) and `POST /auth/google` (Google `id_token`) all return `{token, token_type: "Bearer", expires_at, user}`. A public token lives
  30 days from creation; there is no refresh token.
- Company members do not register themselves: they are created by an admin or join through `GET|POST /auth/invitations/{token}[/accept]`.
- `POST /auth/email/verify` (six-digit `code`) and `/auth/email/resend`; an unverified account can sign in and edit its profile, other actions answer 409
  `email-not-verified`. Google accounts are verified from the start.
- Terms gate: until the active terms are accepted, almost every endpoint answers 409 `terms-not-accepted`.
- Sign-in refusals: 403 `account-suspended`, `account-deactivated` (restored through `POST /auth/account/restore` during the grace period).
- `POST /auth/password/forgot`, `/auth/password/reset`, `/auth/logout`, `/auth/logout-all`; `GET /me` is the current account.

**Flow:**

1. **Backend for front end.** The browser never sees the API token. The Nuxt server calls the sign-in endpoints, keeps the token in a sealed (encrypted and
   signed) httpOnly, `Secure`, `SameSite=Lax` session cookie whose lifetime ends at the token's `expires_at`, and returns only the user to the browser.
2. **API calls go through a Nuxt server route** (`/api/**` on `karir.satu.solutions`) that adds `Authorization: Bearer`, `Accept-Language` and forwards the
   request to `api-karir.satu.solutions`, streaming bodies both ways (resume and avatar files, video answer chunks, downloads). Server-side rendering calls
   the API the same way. The public application then needs no CORS entry; only the admin origin calls the API from a browser.
3. **CSRF:** the cookie is `SameSite=Lax` and the proxy refuses a state-changing request whose `Origin` is not the application's own.
4. **Route guard and account states**, handled centrally in the proxy and the client:
   - 401: the session cookie is cleared and the user is sent to sign in, returning to the page afterwards.
   - 409 `terms-not-accepted`: redirect to the terms acceptance page.
   - 409 `email-not-verified`: show the verification prompt (code entry and resend) without leaving the page.
   - 403 `account-suspended` / `account-deactivated`: an explanation page; deactivated accounts get the restore action.
   - Other 403s are shown as "not allowed" and never sign the user out (see the template fix in section 6).
5. **Role routing after sign-in:** `GET /me` decides between the job seeker area and the company dashboard.
6. **Google:** Google Identity Services in the browser returns the ID token, which is posted to the Nuxt server, which calls `/auth/google`.
7. **Sign-out** calls `/auth/logout` and clears the cookie; "sign out everywhere" calls `/auth/logout-all`.
8. **Real-time (when Reverb runs):** the browser connects to Reverb directly; channel authorisation (`/api/v1/broadcasting/auth`) goes through the proxy,
   which adds the token.

**Postponed:**

- **Client IP behind the proxy (decision gate).** Every API request would come from the Nuxt server's IP. The API's IP-based rate limiters (sign-in, job
  views, salary insight, campaigns, reviews, training, newsletter) would then share one bucket for all visitors, and audit and consent records would hold
  the server's IP. Fixing it needs an API change: trust the Nuxt server as a proxy (`trustProxies` for its address only) and forward `X-Forwarded-For`.
  The rate-limiter rules themselves stay unchanged either way (the user does not want them changed). **Postponed by the user on 2026-10-13** until front-end
  coding starts; it must be decided before the public application goes live.

**Session and hosting (decided by the user on 2026-10-13):**

- The token is kept inside the sealed cookie with `nuxt-auth-utils`; there is no server-side session store, so several Nuxt processes need no shared state.
- The Nuxt server runs on the same host as the API, behind nginx (`karir.satu.solutions` to a local port), and reaches the API over the local network.
  If the trusted proxy is chosen later, the only address to trust is the loopback address.

## 6. UI template

Decided by the user on 2026-10-13, after both templates the user owns were read (files only, nothing run; both folders are read-only):

- **Chosen:** Able Pro Vue 1.7.0 (`/usr/local/src/able-pro-vue-v1.7.0`): Vue 3.5, TypeScript 5.9, Vite 7, Vuetify 3.10, Pinia, vue-router, vue-i18n,
  vee-validate. `seed` is the clean starting point (dashboard layout, vertical and horizontal sidebar, header, customizer, i18n); `full-version` is the
  source of pages copied when needed (authentication, account profile, chat, calendar, kanban, server-side tables, form wizard, landing, error pages).
- **Not chosen:** Able Pro Bootstrap 9.6.2 (`/usr/local/src/able-pro-bootstrap-v9.6.2`), plain HTML with vanilla and jQuery plugins that would have to be
  rewritten as Vue components.
- **Admin panel:** starts from `seed` as a Vite SPA.
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
  the same as the SPA; only the first paint can differ. To avoid a flash, the theme choice (light, dark, system) and the layout settings of the customizer
  are kept in a cookie the server reads; Vuetify's `ssr.clientWidth` is set so the first paint on a phone uses the mobile layout; ApexCharts and
  perfect-scrollbar render inside `<ClientOnly>`.

## 7. Page map: public site and job seekers

Built on 2026-10-13 from the API routes (`php artisan route:list`) and agreed by the user the same day, together with:

- **URL language:** public and account paths are Indonesian (keywords Indonesian users search for). The English version lives under `/en` with `hreflang`
  links; the locale is passed to the API as `Accept-Language`. **Decided by the user on 2026-10-13:** the segments after `/en` are translated (`/en/jobs`,
  `/en/companies`, `/en/sign-in`), so each public and account route has an Indonesian and an English name (`@nuxtjs/i18n` custom route names).
- **Rendering:** public pages render on the server; the job seeker area `/me/**` renders in the browser only (`routeRules` `ssr: false`), like the Able
  Pro SPA, so it needs less porting work and has no first-paint flash. Its paths stay `/me/...` (not indexed).

Every page calls the API through the Nuxt proxy (section 5.2). "Index" means rendered on the server and open to search engines; every other page sends
`noindex`.

### 7.1 Public pages (no account needed)

| # | Page | Path | API | Notes |
|---|------|------|-----|-------|
| P1 | Home | `/` | `GET jobs` (latest), `GET job-categories`, `POST newsletter/subscribers` | Index. Search box leads to P2; signed-in job seekers also see `GET me/recommendations`. |
| P2 | Job search | `/lowongan` | `GET jobs` with `filter[q, work_mode, salary_min, salary_period_id, category_id, role_id, job_type_id, job_level_id, experience_level_id, education_level_id, work_time_id, province_id, city_id, skill_id, company_id]`, `sort`, `page`; masters for the filter lists | Index. Filters live in the query string so a search can be shared and indexed; save toggle and match percentage when signed in. |
| P3 | Job detail | `/lowongan/{job}` | `GET jobs/{job}`, `POST jobs/{job}/view`, `GET salary-insight`, `GET jobs/{job}/screening-questions` | Index, with `JobPosting` structured data. Apply button leads to J4 (sign-in first). |
| P4 | Company profile | `/perusahaan/{slug}` | `GET companies/{slug}`, `/logo`, `/banner`, `GET jobs?filter[company_id]`, `GET companies/{slug}/reviews`, `POST companies/{slug}/reviews`, `POST reviews/{id}/report` | Index. Tabs: about, open jobs, reviews. Writing a review needs a job seeker who applied to the company. |
| P5 | Salary insight | `/gaji` | `GET salary-insight`, masters | Index. |
| P6 | Training | `/pelatihan` | `GET training/courses` | Index. |
| P6b | Training course | `/pelatihan/{course}` | `GET training/courses/{id}` (new endpoint, 7.4) | Index. Links to the course on Moodle. |
| P7 | Campaign landing | `/kampanye/{code}` | `GET campaigns/{code}` | Index if the campaign is public. Keeps `campaign_code` for registration and Google sign-in. |
| P8 | Legal documents | `/kebijakan/{type}`, `/kebijakan/{type}/{version}` | `GET legal-documents/{type}[/versions/{version}]` | Index. Markdown rendered and sanitised on the client side of the proxy. |
| P9 | FAQ | `/faq` | `GET faq` | Index, with `FAQPage` structured data. |
| P10 | Newsletter confirm and unsubscribe | `/newsletter/konfirmasi`, `/newsletter/berhenti/{token}` | `POST newsletter/confirm`, `POST newsletter/unsubscribe[/{token}]` | Links from emails. |
| P12 | Company directory | `/perusahaan` | `GET companies` (new endpoint, 7.4) | Index. Links to P4. |
| P13 | For employers | `/untuk-perusahaan` | new company application endpoint (8.1) | Index. Static content, contact details, application form. |
| P11 | Errors | 404, 500, maintenance | none | From the Able Pro error pages. |

### 7.2 Account pages (blank layout, `noindex`)

| # | Page | Path | API |
|---|------|------|-----|
| A1 | Sign in (password and Google) | `/masuk` | `POST auth/login`, `POST auth/google` |
| A2 | Register (job seekers) | `/daftar` | `POST auth/register`, `POST auth/google` |
| A3 | Forgot and reset password | `/lupa-kata-sandi`, `/atur-ulang-kata-sandi` | `POST auth/password/forgot`, `POST auth/password/reset` |
| A4 | Verify email | `/verifikasi-email` | `POST auth/email/verify`, `POST auth/email/resend` |
| A5 | Accept terms | `/persetujuan` | `GET me/terms-acceptance`, `POST me/terms-acceptance`, `GET legal-documents/{type}` |
| A6 | Suspended or deactivated account, restore | `/akun/diblokir` | `POST auth/account/restore` |
| A7 | Company invitation | `/undangan/{token}` | `GET auth/invitations/{token}`, `POST auth/invitations/{token}/accept` (leads into the company dashboard) |

### 7.3 Job seeker area (Able Pro dashboard layout, `noindex`)

| # | Page | Path | API |
|---|------|------|-----|
| J1 | Dashboard | `/me` | `GET me/dashboard`, `GET me/recommendations`, `GET me/profile/views` |
| J2 | Profile | `/me/profile` | `GET`/`PATCH me/profile`; experiences and educations (CRUD); `PUT me/skills`, `me/languages`, `me/links`; avatar (`GET`/`POST`/`DELETE me/avatar`) |
| J3 | Resumes and CV generator | `/me/resumes` | `GET`/`POST me/resumes`, `PATCH`/`DELETE me/resumes/{id}`, `GET me/resumes/{id}/download`, `GET me/cv/templates`, `POST me/cv` |
| J4 | Apply | `/lowongan/{job}/lamar` | `GET jobs/{job}/screening-questions`, `POST jobs/{job}/applications` (uploaded resume or a CV generated at that moment) |
| J5 | Applications and detail | `/me/applications`, `/me/applications/{id}` | list, detail, `withdraw`, `resume`; interview `confirm`/`decline`/`propose`, `recording-consent/withdraw`; offer `accept`/`decline` |
| J6 | Video interview room | `/me/applications/{id}/interview` | `GET .../interview/video/lobby`, `POST .../interview/video/join` (Janus; client only, full screen) |
| J7 | Assessments and test runner | `/me/assessments`, `/me/assessments/{id}`, `/me/assessments/{id}/attempt` | invitations, `start`, `decline`, attempt (answers, cursor, columns, sections, drawing, integrity events, video takes in chunks, `submit`), `explanation`; runner is client only and full screen |
| J8 | Company invitations to apply | `/me/invitations` | `GET me/invitations[/{id}]`, `POST me/invitations/{id}/decline` |
| J9 | Saved jobs | `/me/saved-jobs` | `GET me/saved-jobs`, `PUT`/`DELETE me/saved-jobs/{id}` |
| J10 | Job alerts | `/me/job-alerts` | CRUD, `pause`, `resume`, `GET me/job-alerts/{id}/jobs` |
| J11 | Messages | `/me/messages` | conversations (list, create, detail, messages, send, read, unread count), `DELETE me/messages/{id}`, attachments; real time through Reverb when it runs (Able Pro chat page) |
| J12 | Notifications and preferences | `/me/notifications`, `/me/settings/notifications` | list, read, read all, delete, unread count; `GET`/`PUT me/notification-preferences` |
| J13 | My company reviews | `/me/reviews` | `GET me/reviews[/{id}]`, `PUT`/`DELETE me/reviews/{id}` |
| J14 | My training | `/me/training` | `GET me/training/courses` |
| J15 | Account settings | `/me/settings` | `PUT me/password`, `POST auth/logout-all`, `POST me/deletion` |

Not mapped to a page: `POST`/`DELETE me/device-tokens` (push tokens for a future mobile app or web push), `broadcasting/auth` (proxy only).

### 7.4 Findings while mapping

- **No public company directory:** the API has `GET companies/{slug}` but no company list. **Decided by the user on 2026-10-13: add the endpoint**
  (`GET /api/v1/companies`, paginated, active companies only; filters such as industry and location to be designed with it). It is new API work, done
  after the import rehearsal together with front-end coding, and it adds page P12 "Company directory" at `/perusahaan`.
- **No training course detail:** the API only lists courses. **Decided by the user on 2026-10-13: add `GET /api/v1/training/courses/{id}`**, new API
  work done with front-end coding, so each course gets its own indexed page (P6b `/pelatihan/{course}`).
- **A fifth role, the partner psychologist (`/api/v1/provider/**`):** placed in the admin application on 2026-10-13 (section 3); its pages are mapped
  with the admin panel.

## 8. Page map: company dashboard

Built on 2026-10-13 from the API routes (`/api/v1/company/**`) and agreed by the user the same day. Part of the public Nuxt application, rendered in the
browser only like `/me/**`, with the Able Pro dashboard layout. **Prefix `/rekruter`** (decided 2026-10-13; for example `/rekruter/lowongan`,
`/rekruter/pelamar/{id}`), which does not clash with the public company profile `/perusahaan/{slug}`.

**Roles shape the menu** (from `phase-1-company.md` and `phase-1-jobs.md`): one company per member account (no company switcher). A **company admin**
sees every job and manages the profile, documents, team, stages, campaigns and settings; a **recruiter** creates jobs and sees only the jobs assigned to
them, and gets 403 `company-admin-required` elsewhere. The menu hides what the role cannot open; the API stays the authority.

| # | Page | API | Role |
|---|------|-----|------|
| C1 | Dashboard | `GET company/dashboard`, `GET company/me` | both |
| C2 | Analytics | `GET company/analytics/{report}` | both (report list from the API) |
| C3 | Jobs list | `GET company/jobs` (`filter[q, status, assigned_to_me]`, `sort`) | both (recruiter: assigned only) |
| C4 | Job editor (create, edit) | `POST`/`PATCH`/`DELETE company/jobs[/{id}]`, masters, `GET`/`PUT company/jobs/{id}/screening-questions`, `GET`/`PUT .../interview-kits/{stageId}`, `GET`/`PUT .../assignees` (company admin) | both |
| C5 | Job detail and lifecycle | `GET company/jobs/{id}`, `publish`, `close`, `reopen`, `clone`, `GET .../performance` | both |
| C6 | Applicant pipeline of a job | `GET company/jobs/{id}/applications`, `POST company/applications/{id}/move`, `reject`; bulk `move`, `reject`, `message`; `GET .../applications/export` | both; kanban view from the Able Pro kanban page plus a table view |
| C7 | Application detail | `GET company/applications/{id}`, `resume`; notes (CRUD); `PUT`/`DELETE rating`; offer (`PUT offer`, `GET offers`, `withdraw`); interview (`PUT interview`, `GET interviews`, `accept-proposal`, `cancel`, `complete`); assessment (`PUT company/assessment/applications/{id}/invitation`, `GET .../invitations`) | both |
| C8 | Interview workspace | `GET`/`PUT company/interviews/{id}/participants`, `questions`, `scorecard` (draft, `submit`), `GET scorecards`, revisions, `attendance`, `calls`, `recordings` (list, file, delete) | both |
| C9 | Video interview room | `GET .../interview/video/lobby`, `join`, `admit`, `recording/start`, `stop` (Janus; full screen) | both |
| C10 | Voice interview | `POST .../interview/voice/join`, `call`, `hangup`, `recording/start`, `stop` (Asterisk browser account) | both |
| C11 | Candidate search and suggestions | `GET company/candidates[/{slug}]`, avatar, resumes; `GET company/jobs/{id}/candidates` | both |
| C12 | Invitations to apply | `GET`/`POST company/jobs/{id}/invitations` | both |
| C13 | Talent pool | `GET`/`POST company/talent-pool`, `PATCH`/`DELETE .../{id}`, categories (CRUD) | both |
| C14 | Messages | `company/conversations` (list, create, detail, messages, send, read, close, reopen, unread count), `DELETE company/messages/{id}`, attachments | both |
| C15 | Assessments: tests | `company/assessment/tests` (CRUD, `publish`, `archive`, `restore`, `clone`), sections and questions (CRUD, order), `GET library`, `GET catalog/{id}`, `GET batteries` | both |
| C16 | Assessments: results | `GET company/assessment/invitations/{id}`, `result`, `report.pdf`, `integrity-events`, `videos/{questionId}`, `scoring` and `PUT scoring/questions/{questionId}`, `cancel` | both |
| C17 | Interview kits | `company/interview-kits` (CRUD) | both |
| C18 | Screening question bank | `company/screening-questions` (CRUD, `archive`, `restore`) | both |
| C19 | Pipeline stages | `company/stages` (CRUD, `PUT order`) | company admin |
| C20 | Company profile | `GET`/`PATCH company/profile`, `PUT company/slug`, logo and banner (`GET`/`POST`/`DELETE`) | company admin |
| C21 | Verification documents | `GET company/documents`, `POST`/`DELETE .../{typeId}`, `GET .../{typeId}/file` | company admin |
| C22 | Team | `GET company/members`, `PUT .../{userId}/role`, `DELETE .../{userId}`; `GET`/`POST`/`DELETE company/invitations` | company admin |
| C23 | Campaigns | `company/campaigns` (CRUD), `GET .../qr`, `GET .../results` | company admin |
| C24 | Reviews of the company | `GET company/reviews`, `PUT`/`DELETE .../reply`, `POST .../report` | company admin |
| C25 | Notifications and account | shared with the job seeker pages J12 and J15 (`/me/notifications`, `/me/password`, `logout-all`); the avatar is `me/avatar` | both |

### 8.1 Findings while mapping

- **No self-registration for companies** (existing decision, `phase-1-company.md`): an admin creates the company and the first company admin joins
  through the invitation (A7). **Decided by the user on 2026-10-13:** the public site gets a static page `/untuk-perusahaan` (P13: the service and
  contact details) **with a company application form** posted to a new API endpoint; applications wait in an admin review queue, and an approved one
  leads to the existing flow (admin creates the company, the first company admin is invited). This is a new API feature (endpoint, storage, admin
  queue, notification, anti-spam); its fields and rules are designed in their own document and coded after the import rehearsal.
- **Role per page** comes from the API rules above; the exact recruiter rights on C15 to C18 should be confirmed against the API when coding (403s
  are handled as "not allowed", section 5.2).

## 9. Page map: admin panel and psychologist area

Built on 2026-10-13 from the API routes (`/api/v1/admin/**`, `/api/v1/provider/**`) and agreed by the user the same day. Vite SPA on
`admin-karir.satu.solutions` from the Able Pro `seed`, token in `sessionStorage` (section 5.1). Two sign-ins in one application: `/masuk` for admins,
`/mitra/masuk` for psychologists, whose pages all live under `/mitra/...` (decided 2026-10-13); each has its own layout and menu, and the token of one is never sent to the other's endpoints.

### 9.1 Admin panel

The menu follows the admin's permissions (from `GET admin/me`): `dashboard.view`, `analytics.view`, `companies.manage`, `jobs.manage`, `reviews.moderate`,
`campaigns.manage`, `newsletter.manage`, `faqs.manage`, `legal_documents.manage`, `email_templates.manage`, `cv_templates.manage`, `reference_data.manage`,
`settings.manage`, `audit_logs.view`, `assessment_library.manage`, `assessment_providers.manage`. The API stays the authority (403 is "not allowed").

| # | Page | API | Permission |
|---|------|-----|------------|
| D1 | Sign in and two-factor | `POST admin/auth/login`, `POST admin/auth/two-factor` | none |
| D2 | Dashboard | `GET admin/dashboard` | `dashboard.view` |
| D3 | Analytics | `GET admin/analytics/{report}` | `analytics.view` |
| D4 | Companies: list, create, detail | `GET`/`POST admin/companies`, `GET .../{id}`, `suspend`, `restore`, `POST .../{id}/invitations` (first company admin) | `companies.manage` |
| D5 | Company verification documents | `GET .../{id}/documents`, `GET .../{typeId}/file`, `approve`, `reject` | `companies.manage` |
| D6 | Company setting overrides | `GET .../{id}/settings`, `PUT`/`DELETE .../settings/{key}` | `companies.manage` |
| D7 | Job moderation | `GET admin/jobs[/{id}]`, `approve`, `reject`, `take-down`, `restore` | `jobs.manage` |
| D8 | Review moderation | `GET admin/reviews[/{id}]`, `approve`, `reject`, `remove`; `GET admin/review-replies`, `approve`, `reject`, `remove`; `GET admin/review-reports`, `dismiss` | `reviews.moderate` |
| D9 | Campaigns | `admin/campaigns` (CRUD), `qr`, `results` | `campaigns.manage` |
| D10 | Newsletter | `admin/newsletter/issues` (CRUD, `test`, `send`), `GET`/`DELETE admin/newsletter/subscribers` | `newsletter.manage` |
| D11 | FAQ | `admin/faq-categories`, `admin/faqs` (CRUD) | `faqs.manage` |
| D12 | Legal documents | `admin/legal-documents` (CRUD, `publish`) | `legal_documents.manage` |
| D13 | Email templates | `GET admin/email-templates[/{key}]`, `PUT`/`DELETE .../{key}/{locale}`, `POST .../{key}/preview` | `email_templates.manage` |
| D14 | CV templates | `GET admin/cv-templates`, `PATCH .../{key}` | `cv_templates.manage` |
| D15 | Reference data (one generic page per master) | benefits, education and experience levels, industries, job categories, levels, roles and types, languages, nationalities, organization types, religions, salary periods, skills, team sizes, work times, company document types (`GET`/`POST`/`PUT`) | `reference_data.manage` (document types: `companies.manage`, to be checked) |
| D16 | Settings | `GET admin/settings`, `PUT`/`DELETE .../{key}` | `settings.manage` |
| D17 | Audit log | `GET admin/audit-logs` | `audit_logs.view` |
| D18 | Assessment library | `admin/assessment/library/tests` (CRUD, `publish`, `archive`, `restore`, `clone`), sections and questions (CRUD, order); `GET admin/assessment/batteries`; per company `admin/assessment/companies/{id}/batteries` | `assessment_library.manage` |
| D19 | Psychologist providers | `admin/assessment/providers` (CRUD, `suspend`, `restore`), invitations, psychologists (`PUT`, `suspend`, `restore`, `reset-two-factor`) | `assessment_providers.manage` |
| D21 | Users (job seekers and company members) | `admin/users` (`phase-8-user-management.md`) | `users.manage` |
| D22 | Company registrations (queue, detail, correct, approve, reject) | `admin/company-registrations` (`phase-8-company-registrations.md`) | `companies.manage` |
| D20 | My account | `GET admin/me`, `PUT admin/me/password`, `POST admin/me/two-factor/recovery-codes`, `logout`, `logout-all` | none |

### 9.2 Psychologist area

| # | Page | API |
|---|------|-----|
| M1 | Invitation (set password, enrol two-factor) | `GET`/`POST provider/invitations/{token}[/accept]` |
| M2 | Sign in and two-factor | `POST provider/auth/login`, `POST provider/auth/two-factor` |
| M3 | Tests: list and editor | `provider/tests` (CRUD, `publish`, `archive`, `restore`, `clone`), sections and questions (CRUD, order) |
| M4 | Norms and report template | `GET`/`PUT provider/tests/{id}/norms`, `GET`/`PUT provider/tests/{id}/report-template` |
| M5 | Result reviews: queue, detail, drawings, sign | `GET provider/reviews[/{id}]`, `PUT provider/reviews/{id}`, `GET`/`PUT .../drawings/{questionId}`, `POST .../sign` |
| M6 | My account | `GET provider/me`, `logout`, `logout-all` |

### 9.3 Findings while mapping

- **No admin endpoints for public user accounts.** `architecture.md` 2.8 says an admin suspends and reactivates accounts (`account-suspended`), but
  there is no route to list or search job seekers and company members, nor to suspend, reactivate or deactivate them; `AccountStatus` exists in the
  domain only. **Decided by the user on 2026-10-13: add the admin endpoints**: list and search job seekers and company members, detail, suspend
  (revokes every token), reactivate, each audited, behind a new permission (for example `users.manage`). New API work, designed in detail and coded
  after the import rehearsal with the front end. Adds page D21 "Users" to the admin panel.
- **No admin endpoints for admin accounts and roles.** Admins are created with `identity:create-admin` and permissions synced with
  `identity:sync-permissions` (console only); there is no page to invite admins, assign roles or reset another admin's two-factor (only the console
  command `identity:reset-admin-two-factor`). **Decided by the user on 2026-10-13: it stays console-only**: granting admin rights is the most
  sensitive action and is limited to people with server access; the panel has no admin-management pages.
- **Training (Moodle):** no admin route; courses come from Moodle, so nothing to manage in the panel.

## 10. Technical foundation

Agreed by the user on 2026-10-13 (repository, API client, build and error language chosen from options; the rest agreed as proposed).

- **Repository (decided):** one front-end repository as a pnpm workspace, Node 24 LTS (installed on the server):
  - `apps/public`: Nuxt, public site, job seeker area and company dashboard;
  - `apps/admin`: Vite SPA from the Able Pro `seed`, admin panel and psychologist area;
  - `packages/ui`: the Able Pro theme, SCSS, layouts and shared components;
  - `packages/api`: generated API types, the client and the problem parser;
  - `packages/i18n`: shared `id` and `en` interface messages and the wording of in-app notifications per `type` (error text comes from the API).
- **API client (decided):** `openapi-typescript` generates the types from the API's OpenAPI 3.1 document (Scramble, 284 paths on 2026-10-13);
  `openapi-fetch` is the typed client. A copy of the document (`php artisan scramble:export`, `api.json`, git-ignored in the API) is kept in
  `packages/api` and refreshed by a script; the check fails when the generated types are stale against that copy.
- **Error language (decided by the user on 2026-10-13):** the API sets the request language from `Accept-Language`, but has no `lang/id` files, so
  validation messages and the problem `detail` are English for every user. The API gets Indonesian translations (Laravel validation messages, attribute
  names, problem `detail` per code); the front end shows the text the API returns and never keeps its own text per error code. New API work, done with
  front-end coding.
- **Errors (agreed):** one parser turns `application/problem+json` into `{code, status, detail, errors, retryAfter}`, with `code` taken from
  `urn:jobportal:error:<code>`; the union of codes (for decisions, not for text) is generated from `docs/api/error-catalog.md`. A 422 `errors` map goes onto the vee-validate fields, a
  429 shows the wait from `Retry-After`; 401, 403 and 409 are handled centrally (section 5.2).
- **Data and state (agreed):** Pinia for the session and UI settings; server-rendered public pages use Nuxt `useFetch`; the browser-rendered areas
  (`/me`, `/rekruter`) and the admin application use TanStack Vue Query for caching and invalidation. `@nuxtjs/i18n` with `prefix_except_default` and
  default `id`; the admin application uses vue-i18n. Dates with `date-fns` (`moment` dropped), Rupiah with `Intl.NumberFormat`.
- **Quality (agreed):** ESLint and Prettier (from the template), `vue-tsc`, Vitest with Vue Test Utils, Playwright for the main flows (sign-in, apply,
  pipeline move, admin two-factor).
- **Build (decided):** on the server, **run by the user**; a session prepares the commands only. The server has 7 GB of memory with about 1 GB free
  (2026-10-13) while a Nuxt and Vuetify build can take 2 to 4 GB, so the build commands limit Node's heap and may need swap; build when traffic is low.
- **Hosting (agreed):** each build goes into its own release folder and a `current` symlink is switched, so the live site never runs from the working
  folder. The public application runs Nuxt's `node-server` output as a systemd service on `127.0.0.1` behind nginx (the unit needs root: user lingering
  is off for `jobportal`). The admin application is static files served by nginx with a strict CSP: `script-src 'self'`; `style-src` needs
  `'unsafe-inline'` because Vuetify injects its theme as an inline style and a static site cannot give it a nonce.

