# Phase 5: API security review (designed 2026-10-10; route audit done; waiting for `composer audit`)

First hardening item of Phase 5 (`phase-5-scope.md`). Goal: review the whole API against a fixed checklist, record each finding here, and fix only what the
user confirms. Every fix gets tests.

## Decisions

- 2026-10-10: Method: OWASP API Security Top 10 (2023) over every context and every route; findings are recorded first, fixed after the user confirms ([Review checklist](#review-checklist)).
- 2026-10-10: P1: a global baseline limiter on the `api` group, per account (per IP without sign-in); the existing named limiters stay on top ([P1](#p1-baseline-rate-limit)).
- 2026-10-10: P4: passwords `Password::min(8)->uncompromised()` (Have I Been Pwned, k-anonymity; skipped when the service is down) ([P4](#p4-password-rule)).
- 2026-10-10: P5: CORS limited to the origins in `CORS_ALLOWED_ORIGINS` (`.env`) ([P5](#p5-cors)).
- 2026-10-10: P1 numbers: 120 requests per minute per account, 60 per minute per IP without sign-in; in configuration, overridable from `.env` ([P1](#p1-baseline-rate-limit)).
- 2026-10-10: P2: `sanctum:prune-expired --hours=24` scheduled nightly ([P2, P3, P6](#p2-p3-p6)).
- 2026-10-10: P3: the `sanctum/csrf-cookie` route is switched off (`Sanctum::ignoreRoutes()`) ([P2, P3, P6](#p2-p3-p6)).
- 2026-10-10: P6: security headers set by a middleware on the `api` group; HSTS stays in nginx ([P2, P3, P6](#p2-p3-p6)).
- 2026-10-10: P7: add password change for users and admins and sign-out from every device; admin password reset stays on the console; a wrong current password counts in the sign-in throttle; both actions audited; 409 `password-not-set` for an account without password ([P7](#p7-password-change-and-sign-out-everywhere)).
- 2026-10-10: A1: sign-in throttle gets two more layers, no lockout: 20 failures per hour per account from any IP, 30 failures per hour per IP over any account ([Identity](#identity-checked-2026-10-10)).
- 2026-10-10: A2: TOTP second factor, mandatory for admins, with one-time recovery codes; reset through the console; `pragmarx/google2fa`; two-step sign-in with a 5-minute challenge; forced enrolment at the next sign-in; 10 recovery codes, regenerable; schema: three columns on `identity_admins` and two new tables ([A2](#a2-admin-totp)).
- 2026-10-10: A3: the API documentation is shown only when `API_DOCS_ENABLED=true` (default off), whatever `APP_ENV` is; the dev site moves to `APP_ENV=staging` (the user edits `.env`) ([Other contexts](#the-other-contexts-checked-2026-10-10)).
- 2026-10-10: Dependency audit: the user runs `composer audit` and pastes the result ([Coding plan](#coding-plan)).

## Inventory (2026-10-10)

- 373 routes; 319 behind `auth` (Sanctum, two guards), 124 also with a route-level `Authorize` gate; the data-dependent rules sit in the use-case policies (`architecture.md` 2.3).
- 54 routes without authentication: sign-in, registration, password reset, Google sign-in, account restore, invitation token (read, accept), reference lists, public jobs,
  companies, reviews, campaigns, training courses, salary insight, newsletter, `/`, `/up`, `/docs/api*` (local only), `sanctum/csrf-cookie`.
- Rate limits: 33 routes carry a named limiter (job view, salary insight, saved jobs, applicant export, candidate search, chat send, campaign, training, newsletter, reviews,
  talent pool). Sign-in, registration and password-reset requests are throttled inside their use cases. Verification codes: 6 digits, unusable after 5 wrong attempts.
- Tokens: per-token expiry (30 days users, 12 hours admins); `sanctum.expiration` is `null`; random tokens for invitations and newsletter (32 bytes), stored files use random names.
- Headers: `nosniff` and `default-src 'none'` on file downloads in code; `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, HSTS in the nginx config only (not in git).
- Passwords: `min:8`, `max:255`, nothing else; bcrypt with 12 rounds.
- CORS: no `config/cors.php`, so the framework default applies (paths `api/*`, every origin).
- Production is served straight by nginx, no proxy or CDN (user, 2026-10-10): the client IP in the limiters is real.

## Preliminary findings (to confirm)

| # | Finding | OWASP API 2023 |
|---|---|---|
| P1 | No baseline limit: about 340 routes have none, among them `GET /jobs`, `GET /companies/{slug}`, the invitation token routes, account restore and every authenticated write | API4 |
| P2 | Expired personal access tokens are never pruned (`sanctum:prune-expired` is not scheduled) | hygiene |
| P3 | `GET /sanctum/csrf-cookie` is registered with the `web` group although the API has no sessions; it sets a session cookie | API8 |
| P4 | Password rule is only a length of 8; no check against known breached passwords | API2 |
| P5 | CORS allows every origin | API8 |
| P6 | Security headers live only in the nginx config, outside git | API8 |
| P7 | No password change while signed in (users and admins) and no sign-out from every device; admins set a password only through `CreateAdminCommand` | API2 |

## P1 baseline rate limit

Decided 2026-10-10 (user): one limiter named `api` appended to the `api` middleware group, keyed by the account (guard and id) or, without sign-in, by the IP.
The named limiters of the contexts keep applying on top. Numbers (decided 2026-10-10, user): 120 per minute per account, 60 per minute per IP; read from
configuration with `.env` overrides (not admin settings). A 429 uses the existing problem rendering with `Retry-After`.

## P4 password rule

Decided 2026-10-10 (user): every rule that sets a password (registration, invitation acceptance, password reset, the `CreateAdminCommand` console command; there is no change-password endpoint) uses
`Password::min(8)->uncompromised()`, kept in one place in `Identity`. The check sends only the first 5 characters of the SHA-1 hash; when the service cannot
be reached the password is accepted (framework behaviour). Sign-in keeps no rule beyond `max:255`.

## P5 CORS

Decided 2026-10-10 (user): `config/cors.php` with paths `api/*`, the origins read from `CORS_ALLOWED_ORIGINS` (comma separated), no credentials (bearer tokens,
no cookies). Added to `.env.example`.

## P2, P3, P6

- **P2 (decided 2026-10-10, user):** the Identity schedule runs `sanctum:prune-expired --hours=24` nightly, `withoutOverlapping` and `onOneServer` like every task.
- **P3 (decided 2026-10-10, user):** `Sanctum::ignoreRoutes()` in the Identity provider; the route list then has no route in the `web` group except the docs (local only).
- **P6 (decided 2026-10-10, user):** a Shared middleware appended to the `api` group sets `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`,
  `Referrer-Policy: no-referrer` and, on JSON answers, `Content-Security-Policy: default-src 'none'; frame-ancestors 'none'`. File downloads keep their own headers.
  HSTS stays in nginx (it belongs to TLS). The three other nginx `add_header` lines are removed when the middleware is deployed: a header sent twice
  (`X-Frame-Options: DENY, DENY`) is treated as invalid by some browsers.

## P7 password change and sign-out everywhere

Decided 2026-10-10 (user):

- `PUT /api/v1/me/password` (user guard) and `PUT /api/v1/admin/me/password` (admin guard): body `current_password`, `password`, `password_confirmation`; the new password
  follows P4. On success every **other** token of the account is revoked; the token of the request stays valid (`architecture.md` 2.2: tokens are revoked on
  password change). A wrong current password answers 422 and counts in the sign-in throttle of the account; the change and the sign-out from every device are written to the audit
  log (who, when, IP; `decisions.md`) (both decided 2026-10-10, user).
- An account created with Google has no password (`identity_users.password` is nullable): it sets one through the password reset flow. The change endpoint answers 409 with the problem `type` `urn:jobportal:error:password-not-set` (decided 2026-10-10, user); it is added to `docs/api/error-catalog.md`.
- `POST /api/v1/auth/logout-all` and `POST /api/v1/admin/auth/logout-all`: revoke every token of the account, the current one included.
- Out: a forgot/reset flow for admins (stays on the console).

## Review checklist

OWASP API Security Top 10 (2023), run context by context over every route:

1. **API1 object level:** every route with an id or token reaches only objects of the actor (company, own account, assigned job); a foreign id answers 404.
2. **API2 authentication:** guards, token expiry and revocation, throttles on sign-in, codes and tokens, account states (`architecture.md` 2.8).
3. **API3 property level:** resources expose no field the actor may not see (emails, phone numbers, sensitive profile fields, internal ids, notes); requests accept no field the actor may not set.
4. **API4 resource consumption:** limiters, page size caps, upload sizes, export and bulk sizes, expensive queries.
5. **API5 function level:** admin routes behind the admin guard and a permission; company roles on company routes.
6. **API6 sensitive flows:** registration, applications, invitations, reviews, newsletter subscribe: abuse by automation.
7. **API7 SSRF:** outgoing calls (Moodle, Google, breached-password check) use fixed hosts from configuration only.
8. **API8 misconfiguration:** headers, CORS, debug, docs route, error bodies, the `web` group routes.
9. **API9 inventory:** no unused or undocumented route; the Scramble export matches the routes.
10. **API10 unsafe consumption:** answers of Moodle and Google are validated before use.
11. **Dependencies:** `composer audit`.

## Audit by context

Order: Identity, Recruitment, JobSeeker, Company, Messaging, then the others. Each context gets one subsection: routes checked, findings (`A<n>`), the user's answers.

### Identity (checked 2026-10-10)

19 routes. Checked without finding: the timing of an unknown email is equalised with a dummy hash; sign-in, account restore and Google sign-in share the
sign-in throttle; password reset requests reveal nothing and are limited per address and per account; reset codes are 6 digits, dead after 5 wrong attempts,
and a reset revokes every token; the Google ID token is checked for signature, issuer, expiry and audience (the library returns `false` on an expired or
forged token); a Google sign-in on an unverified account removes the password and revokes its tokens; sign-out revokes only the current token; the
resources expose no secret. Registration answers 409 `email-already-registered` (an accepted enumeration, throttled per IP).

| # | Finding | OWASP |
|---|---|---|
| A1 | The sign-in throttle key is email + IP (5 failures per minute): one account can be guessed from many IPs without limit, and one IP can try one password on many accounts (only the P1 baseline of 60 per minute applies) | API2 |
| A2 | Admin accounts (12-hour tokens, every admin permission) sign in with a password only; there is no second factor | API2 |

**Answers (2026-10-10, user):**

- **A1:** keep 5 failures per minute per email + IP; add 20 failures per hour per account (any IP) and 30 failures per hour per IP (any account), each
  answering 429 with `Retry-After` until its window ends. No permanent lockout. Applies to user sign-in, admin sign-in, account restore, and the wrong current
  password of P7. Google sign-in keeps its per-IP key.
- **A2:** TOTP for admins, mandatory; designed in [A2 admin TOTP](#a2-admin-totp).

#### A2 admin TOTP

Decided 2026-10-10 (user): every admin signs in in two steps (password, then a 6-digit code from an authenticator app); one-time recovery codes; a lost device
is reset through the console.

- **Library (2026-10-10, user):** `pragmarx/google2fa` behind a port in `Identity`; the secret is stored encrypted with the application key; a used
  time step is remembered so a code cannot be replayed; the QR code is drawn with the installed `bacon/bacon-qr-code`.
- **Sign-in (2026-10-10, user):** `POST /admin/auth/login` with a right password answers a challenge token (5 minutes, single use, opens no other endpoint);
  `POST /admin/auth/two-factor` with the challenge and a code (or a recovery code) answers the 12-hour admin token. Wrong codes count in the A1 throttle.
- **Enrolment (2026-10-10, user):** an admin without a confirmed second factor gets, after the right password, an "enrolment required" answer with the secret
  and the QR code; the first valid code confirms it, issues the token and shows the recovery codes once.
- **Recovery codes (2026-10-10, user):** 10, single use, stored hashed; regenerated through an endpoint that asks for a current TOTP code; every use is audited.
- **Schema (2026-10-10, user):** a new migration (the real database is at `000110`, so existing migrations are not edited):
  `identity_admins` gets `two_factor_secret` (text, encrypted, nullable; set at enrolment), `two_factor_confirmed_at` (timestamp, nullable) and
  `two_factor_last_step` (bigint, nullable). New `identity_admin_recovery_codes` (`id`, `admin_id` FK `RESTRICT` to `identity_admins`, `code_hash`, `used_at`
  nullable, `created_at`) and `identity_admin_two_factor_challenges` (`id`, `admin_id` FK `RESTRICT`, `token_hash` unique, `expires_at`, `used_at` nullable,
  `created_at`); expired challenges are deleted nightly.

### Recruitment (checked 2026-10-10)

49 routes, no finding. Every team use case finds the application through `TeamApplicationLookup` (company and job assignment, a foreign or unassigned
application is a 404) or checks the job with `RecruitmentAccess::job`; bulk actions check that every id is an application of that job in the member's
company and cap the selection; stage and screening question writes need a company admin; note edits are the author's, deletes the author's or a company
admin's; applicant use cases read through `ApplicantGuard` with the user id in the query. Text inputs without a request `max` are capped in the domain
(cover letter 2000, notes 2000, withdrawal and rejection notes 1000); the meeting link is `http`/`https` only; the CSV export escapes formula cells;
resume downloads send `nosniff` and `default-src 'none'`. Profile links (JobSeeker) are `https` only with no IP, credentials or shorteners.

### JobSeeker (checked 2026-10-10)

28 routes, no finding. Own records (educations, experiences, resumes) are found and deleted by user id and id together; candidate routes carry the
candidate-search limiter; a candidate's resume must belong to that candidate; contact data shows only when `ApplicantRelations::canSeeApplicant` allows it,
and the protected attributes never leave the context (`phase-3-candidate-search.md`); CV generation has an hourly quota (`CvQuota`); the CV template admin
routes need `cv_templates.manage`; uploaded resumes are checked as PDF by content and refused with active content (`ResumePdf`).

### Company and Messaging (checked 2026-10-10)

61 routes, no finding. Company writes (profile, slug, images, documents, team, invitations) go through `CompanyAdminGuard` (member, company admin role,
company not suspended); reads of the own company through `CompanyMemberGuard`; admin routes need `companies.manage`. Messaging use cases check the
participant and that the conversation is open (`ConversationAccess`); starting a conversation checks the audience (a company may only write to a candidate
it can see); writes need a verified email and the chat-send limiter; the private channels use the same checks (`ChannelAuthorizer`). Invitation tokens are
32 random bytes; the public invitation routes fall under the P1 baseline. Note: the team, job and invitation routes have no `RequireVerifiedEmail`, which
`architecture.md` 2.8 asks for; this holds because a company member account is created verified (`User::createCompanyMember`, the invitation proves the inbox).

### The other contexts (checked 2026-10-10)

Job, JobInvitation, Review, TalentPool, Campaign, Newsletter, Training, Dashboard, Notification, Platform, Content, ReferenceData, JobAlert, Matching:
no object or function level finding. Admin routes carry the admin guard and a permission; reviews go through `ReviewGate` and `OwnReviews` (a company
replies and reports only on its own page); job alerts, notifications and device tokens are found by the user id (notifications also by the user morph
type, so an admin id never reaches them); the company dashboard and analytics read the actor's own company. Public resources expose no account data
except the review author's name, which `phase-4-company-reviews.md` (J10b) decided to show. Outgoing calls (Moodle, Google) use hosts from configuration.

| # | Finding | OWASP |
|---|---|---|
| A3 | The public dev site runs with `APP_ENV=local`; the API documentation is exposed whenever the environment is not `production` (`ApiDocumentation::configure`), so `https://api-karir.satu.solutions/docs/api` and `/docs/api.json` answer 200 to anyone, with every admin route | API8, API9 |

**Answer (2026-10-10, user):** A3: `ApiDocumentation` exposes the documentation only when `config('scramble.enabled')` (from `API_DOCS_ENABLED`, default
`false`) is true, independent of the environment; `RestrictedDocsAccess` is replaced by that check. The dev site's `.env` moves to `APP_ENV=staging` (done by the
user). If the frontend team needs the documentation on the dev site, the flag is switched on there and nginx puts basic authentication in front of `/docs`.
`scramble:export` keeps working with the flag off.

## Coding plan

One slice per line, each with its tests (written with the code; the user runs the checks):

1. P1 baseline limiter; P2 token pruning; P3 `Sanctum::ignoreRoutes()`; P6 security headers middleware; P5 `config/cors.php`; A3 documentation flag.
2. P4 password rule in one place, used by registration, invitation acceptance, reset and `CreateAdminCommand`.
3. A1 sign-in throttle layers (per account, per IP).
4. P7 password change and sign-out everywhere (users and admins), the `password-not-set` problem type, audit entries.
5. A2 admin TOTP: migration (three columns, two tables), `pragmarx/google2fa`, challenge, enrolment, recovery codes, nightly purge of expired challenges, console reset.
6. `composer audit`: the user runs it; findings are recorded here before any upgrade.

New migration in slice 5: the user runs it on the test database, then on the real one with approval. New `.env` keys: `API_RATE_LIMIT_USER`,
`API_RATE_LIMIT_GUEST`, `CORS_ALLOWED_ORIGINS`, `API_DOCS_ENABLED` (added to `.env.example`). After slice 1 the user removes the three `add_header` lines from the
nginx config (root-owned) and sets `APP_ENV=staging` on the dev site.
