# Handoff (written 2026-10-03)

Read this first when continuing in a new session. It is self-contained; the other documents hold the details.
(The user communicates in Indonesian; project artifacts are English.)

## 1. What this project is

A new **job portal REST API** (Laravel 13, DDD) in `/usr/local/src/jobportal`, replacing the production web application
**karir.pkp** (`/usr/share/nginx/html/karir.pkp`, live at `https://karir.satu.solutions`). The term `karir.pkp` always means that legacy
source. It is a **reference only**, never ported 1:1, and read-only.

History in one paragraph: a first port of the legacy REST API (92 endpoints, 277 tests) was built and then **deliberately discarded**
(commit `ae6736c` still contains it; do not reuse it). The user established that the legacy API is stale and the real flows live in the
web application, so the project restarted from analysis: flow map, benchmark research, gap analysis, then design decisions.
The repository now contains only the Laravel 13 skeleton, a small shared kernel, and design documents.

## 2. Working rules (mandatory, set by the user)

1. **Work alone and strictly sequentially**: no sub-agents, no Workflow, no background tasks, and **one tool call per step** (no parallel tool calls).
2. **Decision gate**: findings in karir.pkp that violate best practice (security, bugs/correctness, architecture/code quality, performance)
   and any schema assumption are **reported/confirmed with the user first**. Never silently fix or copy them. Never assume that a table
   or column does or does not exist; read the current production schema and confirm.
3. **Rule conflicts**: there is no fixed priority between rules. If two rules conflict, stop and ask.
4. **Code standard**: Laravel best practice with DDD is mandatory (layers Domain/Application/Infrastructure/Interface, one use case per class,
   thin controllers, FormRequests, no global helpers, Pint and PHPStan clean).
5. **English everywhere in the project**: identifiers, comments, documentation, commit messages, API error messages. Indonesian terms the user
   gives are mapped to common English job-portal terms (lamaran = application, lowongan = job posting/job, pelamar = applicant/candidate,
   perusahaan = employer/company, etc.). Communication with the user stays in Indonesian. New table/column/field/route names are designed freely
   (not bound to legacy names).
6. **Source of truth**: flows come from the web application (source `routes/web.php`, `website.php`, `admin.php` and the live site). Current schema is read
   from the production database through a **read-only account**.
7. **Benchmarks** for flows (all features): Glints, JobStreet, LinkedIn, karir.com, loker.id. Record only facts verified from public sources; mark the rest as unverified.
8. **Out of scope** this phase: plans, billing, payments, invoices, pay-per-job and featured/highlight promotion.
9. **Database objects**: schema is *hybrid* (only needed tables), table prefix per bounded context. **All test-related objects** (tables, triggers, functions,
   procedures, events) must use the **`test_` prefix** in front of the context prefix; tests run inside `jobportal_v2` and must never touch non-`test_` objects.
10. **Ask one question at a time** and do not decide for the user. The user answers through option prompts; record each answer.

## 3. Environment and access

| Item | Value |
|---|---|
| New project | `/usr/local/src/jobportal` (Laravel 13.34, PHP 8.3, Pint, Larastan, PHPUnit 12). Git repo, last commit `fbb8afa` |
| New database | MariaDB 11.8 at `127.0.0.1:3306`, database `jobportal_v2`, user `jobportal_v2` (full rights on that DB only); **currently empty (0 tables)** |
| Production read-only | user `jobportal_v2_read` (SELECT on `karir_pkp` and `jobportal_v2`); **schema only, never read data** without explicit approval |
| Credentials | only in the git-ignored `/usr/local/src/jobportal/.env` (`DB_*` for the new DB; `KARIR_PKP_DB_*` for the read-only production account). **Never copy secrets into docs, memory or commits** |
| Live site accounts | the user supplied one job seeker, one recruiter and one admin account in chat; **not stored anywhere**. Ask the user again if needed |
| Live site protocol | login and read-only browsing; ask before any action that creates/changes data; end sessions with a **POST logout** and delete cookie files; never open the admin GET routes that change data (`jobs/delete-selected`, `company/change/status`, `company/verify/status`, `candidate/change/status`, `generate/sitemap`, `sync/language/*`, `settings/delete/*`) |
| Memory | `/home/jobportal/.claude/projects/-home-jobportal/memory/` (index `MEMORY.md`) holds the rules and decisions; loaded automatically when the session starts in `/home/jobportal` |

Useful details:

- Dump the current production schema (no data): `mariadb-dump --no-data --skip-comments --no-tablespaces --skip-triggers --skip-lock-tables`
  with the `KARIR_PKP_DB_*` values (the read-only account has no LOCK TABLES right, hence `--skip-lock-tables`). 125 tables on 2026-10-03.
- `php -l` segfaults on this server; use `php -n -l <file>`.
- A private MariaDB used for the first port's tests was shut down; no local test database exists now.
- The repository still contains stale test configuration: `phpunit.xml` and `.gitlab-ci.yml` point to a `test_jobportal` database; they must be reworked to use
  `jobportal_v2` with a `test_` table prefix (and a guard that aborts when the prefix is not active).
- `config/auth.php` points to `App\Models\User`, which does not exist yet (the user model arrives with the Identity context).
- The Laravel skeleton ships `AGENTS.md`/`CLAUDE.md` that ask to install Laravel Boost; they were removed because they are not the user's instructions.
- Code in the repo: `src/Shared` (`ApiResponse`, domain exceptions, `RequestContext`, `ContextServiceProvider`, `ApiController`),
  `bootstrap/app.php` (exception rendering), `app/Providers/AppServiceProvider.php`, `tests/TestCase.php`, `tests/FeatureTestCase.php`.
  The exception mapping and `ApiResponse` envelope come from the discarded port and may be revisited.

## 4. Document index

| File | Content |
|---|---|
| `docs/analysis/legacy-flow-map.md` | Web application flows by actor (from source and live site) |
| `docs/analysis/legacy-findings.md` | Best-practice findings in karir.pkp and its schema (reported; no per-item decision yet) |
| `docs/analysis/benchmark-notes.md` | Verified benchmark facts per platform, with verification level |
| `docs/analysis/gap-analysis.md` | Flow gaps vs benchmarks with production-schema evidence |
| `docs/design/decisions.md` | **All confirmed design decisions** (stages, screening, gap scope, foundation, delivery, phases) |
| `docs/design/architecture.md` | Cross-cutting architecture decisions (API contract, authentication, authorization, persistence, queues, files, account states) |
| `docs/design/phase-0-schema.md` | Column-level design of the Phase 0 tables, **reviewed and approved by the user (2026-10-03)**, plus the list of details still open |

## 5. Status

Done: step 1 (flow map), step 2 (benchmarks, five platforms), step 3 (gap analysis), scope of every gap, sequencing (five phases, approved),
Phase 0 concept decisions, the approved list of Phase 0 tables, the seven open schema questions (all answered), and the **column design of all Phase 0
tables, reviewed table by table and approved by the user** (Identity, Platform, Notification, ReferenceData, Content). `decisions.md` and
`phase-0-schema.md` are in sync with every decision taken so far.

Key conventions fixed in that review (details in `phase-0-schema.md`): every project table has `id` `bigint unsigned` auto-increment (exceptions: three
RBAC pivot tables with the Spatie composite key, and framework tables); translatable text is JSON (`id`/`en`) via `spatie/laravel-translatable` in
Infrastructure models only; account deletion is anonymisation with a 30-day grace period; contact data lives in Phase 1 profiles, not in `identity_users`.
The section "Details still open" of `phase-0-schema.md` lists what was deliberately left undecided (do not assume those).

Migrations: the **ten Identity migrations are written** (`database/migrations/2026_10_03_000001` to `000010`, one per table, each confirmed with the user) and
have been run **only against the `test_` tables** (`php artisan test:database:migrate`). The real (non-prefixed) tables have **not** been created
(`php artisan migrate` was not run). Lessons: MariaDB limits identifiers to 64 characters, so long index names need an explicit name (done for
`identity_verification_codes` and `identity_terms_acceptances`); DDL is not transactional, so a failed migration leaves a partial table that must be
dropped by hand before retrying. The two RBAC pivots `admin_roles` and `admin_permissions` also have an extra `(model_id, model_type)` index copied from the
Spatie structure (not in the approved design; the user was told). The four Platform migrations (`000011` to `000014`) are also written and run against the `test_` tables only; the `updated_by_admin_id` columns have a plain
index (cross-context convention) and the long index names in `platform_email_template_overrides` are explicit. The Notification (`000015` to `000017`, with the
`created_at` index inside the `notifications` migration) and ReferenceData (`000018` to `000039`: five location tables with `RESTRICT` foreign keys, 17
masters) migrations are also written and run against the `test_` tables only, and so are the three Content migrations (`000040` to `000042`). **All 42 Phase 0
migrations exist and the **real (non-prefixed) tables were created on 2026-10-03 with `php artisan migrate`, on the user's explicit approval** (`jobportal_v2`
now holds 43 real tables including `migrations`, and 43 `test_` tables; compared column by column, FK count 13 and CHECK count 42 are identical). Tables are empty.
**Master seeders (2026-10-03):** 16 seeders under `database/seeders/Reference/` (one per master, on a shared `MasterSeeder` that inserts by slug and never
overwrites existing rows) plus `ReferenceDataSeeder`, called by `DatabaseSeeder` (192 rows in total). Their values come from the legacy master tables read
with the user's explicit approval (SELECT only), tidied and approved per master; English/Indonesian names, ranges, kinds and codes not present in the legacy
data were written for this project and approved by the user. `reference_tags` is deliberately not seeded (the legacy tags are mostly test data; numeric
names suggest ids stored as names, unverified in the legacy code). Benefits (12), nationalities (25) and part of the languages (25) are our own lists because
the legacy tables are empty or hold other data. **Run against the real tables on 2026-10-03 with `php artisan db:seed`, on the user's explicit approval** (the 16 target
tables were empty beforehand; afterwards they hold the 192 rows, the `test_` tables stay empty, `reference_tags` and the location tables remain empty). The
seeders are safe to run again (they only add missing slugs). **Location data (2026-10-03):** `LocationSeeder` loads the BPS administrative hierarchy from five CSV files in `database/data/locations/` (38 provinces,
514 cities/regencies, 7,285 subdistricts, 83,762 villages, 83,761 postal codes; exported once, SELECT only, from the legacy tables with the user's approval).
Decisions: names stay upper case as in the BPS source; the KABUPATEN/KOTA prefix moved into `type` (98 city, 416 regency; the two legacy "KAB ..." names read as
regencies); no coordinates (the legacy tables have none); village 1101012001 has no postal code. It runs after `ReferenceDataSeeder` from `DatabaseSeeder`, and
was **run against the real tables on 2026-10-03 on the user's explicit approval** (tables were empty; verified counts, no broken chains, `test_` tables
untouched). Its test loads about 175,000 rows twice, so the whole suite takes about 24 seconds. Not done: legal documents and FAQ content, domain code. From now on every new migration must be run twice: `php artisan test:database:migrate`
(test tables) and `php artisan migrate` (real tables, only with the user's approval). Open points noticed while writing the migrations: `reference_languages.code` was made unique
(decided 2026-10-03, migration `000043`), and `min_years`/`max_years`/`rank` and the team size `min_size`/`max_size` became unsigned (decided
2026-10-03, migration `000044`). Both migrations ran against the `test_` tables first and were then **applied to the real tables on 2026-10-03 on the user's explicit approval**
(44 migrations recorded; the real and `test_` tables have identical columns again).
**Sanctum (2026-10-03):** `laravel/sanctum` v4.3.3 installed (per-token `expiresAt` in `createToken` verified); migration `000045` (`personal_access_tokens`, the
bundled structure unchanged) ran against the `test_` table and, on the user's explicit approval, against the real table (44 real tables; columns identical).
Not done yet: guards, `config/sanctum.php`, token issuing code. **Spatie permission (2026-10-03):** `spatie/laravel-permission` v8.3.0 installed (supports Laravel 13); its bundled migration is **not** published because the
Identity migrations `000004` to `000008` already create the tables; `config/permission.php` maps the five package tables to `identity_roles`,
`identity_permissions`, `identity_admin_permissions`, `identity_admin_roles` and `identity_role_permissions` (teams off). Verified by
`tests/Feature/Shared/SpatiePermissionCompatibilityTest.php` (roles, direct permissions, composite pivots and the `test_` prefix, with a throwaway model).
Not done: the RBAC interface in Infrastructure, the admin model with `HasRoles` and its guard name, seeding roles and permissions. **Spatie translatable (2026-10-03):** `spatie/laravel-translatable` v6.14.1 installed (supports Laravel 13, no config published); verified by
`tests/Feature/Shared/SpatieTranslatableCompatibilityTest.php` (JSON `{"id","en"}` column of `reference_job_categories`, locale read, fallback to `en`,
`where('name->id', ...)` search). All three packages of the architecture decisions are now installed and checked. Not done: the real Eloquent models.
**Identity foundation (2026-10-03, user chose "account foundation" as slice 1):** shared kernel gained `TransactionManager`, `EventDispatcher`
(Laravel implementations bound in `AppServiceProvider`), `Actor`/`ActorKind` (kind and id only; company membership comes with the Company context),
`DomainEvent` and `AggregateRoot`. `src/Identity`: Domain (`User`, `Admin`, `AccountStatus`, `UserType`, repository interfaces; sign-in refusal is
`AccessDenied` with codes `account-suspended` / `account-deactivated`, the grace-period message names the deletion date), Infrastructure (`UserModel`,
`AdminModel` with Sanctum tokens and Spatie roles under guard `admin`, Eloquent repositories, `IdentityServiceProvider`). `config/auth.php` was rewritten:
two Sanctum guards `user` and `admin` with separate providers (no session guard, **no framework password broker**: resets use verification codes);
`config/sanctum.php` published with `guard => []` and no global `expiration` (expiry is set per token). Tests prove a user token is refused on admin routes and
vice versa, expired tokens are refused, and repositories round-trip. **Sign-in slice DONE (2026-10-03):** `POST /api/v1/auth/login`, `POST /api/v1/auth/logout`, `GET /api/v1/me` and the same three under
`/api/v1/admin/...` (use cases `SignInUser`, `SignInAdmin`, `SignOut`, `GetCurrentUser`, `GetCurrentAdmin`). Decided with the user: at most **5 failed
attempts per minute per scope+email+IP**, then 429 `too-many-requests` with `Retry-After`, no account lockout, a success clears the counter; a wrong password and an
unknown email give the identical 401 `invalid-credentials` (and cost one hash check), a suspended/deactivated status is revealed only after the password is
verified (403); tokens last 30 days (users) and 12 hours (admins), set per token; logout revokes only the token used. Login **audit is deferred to the Platform
context**: the use cases dispatch `SignedIn` and `SignInFailed` events (scope, account id, reason code, IP, user agent; no email) and nothing listens yet. New shared
pieces: `Clock`, `ActorProvider`/`ActorResolver`, exceptions `AuthenticationFailed` (401) and `TooManyAttempts` (429). Response shape of login:
`{data: {token, token_type: "Bearer", expires_at, user|admin}}`; `/me` returns the resource under `data`. `/admin/me` lists `roles` and `permissions` as flat, sorted name lists (decided with
the user; permissions are effective: from roles plus direct grants, no duplicates; `AdminAccessReader` port with a Spatie implementation; the login response
carries only the basic admin fields). The two sign-in use cases are near-duplicates (one per account kind). Legacy password hashes are not handled yet (the hasher throws on a hash
that is not bcrypt: decide with the data migration). **Registration slice DONE (2026-10-03):** `POST /api/v1/auth/register` (job seekers only; 201 with the same body as login),
`POST /api/v1/auth/email/verify {code}` (204) and `POST /api/v1/auth/email/resend` (204), the last two behind `auth:user`. Decided with the user: code = 6 digits,
valid **15 minutes**, single use, **5 wrong attempts** burn the code (then `verification-code-expired`; a wrong code is 422 `invalid-verification-code`);
a new code only **60 seconds** after the previous one and **at most 5 per hour** (429 `verification-code-cooldown` / `verification-code-limit-reached`, with
`Retry-After`), a new code consumes every older one; registration signs the user in (30-day token); password **minimum 8 characters only** (no complexity rule, no
breach check); terms acceptance is **not** recorded at registration (the terms gate applies to new accounts like migrated ones; the acceptance endpoint
comes with Content); the verification email goes through the port `VerificationCodeSender` (Laravel `Mailable` `EmailVerificationMail` on queue `critical`, 3 tries,
backoff 10/60/300 s, texts in `lang/en|id/identity.php`, recipient locale; admin template overrides and preferences come with Platform/Notification). Codes are
stored as HMAC-SHA-256 with the app key (`HmacVerificationCodeHasher`); the plain code only travels inside the queued mail payload. The duplicate email race is
closed by the unique index (`StateConflict email-already-registered`). Also added: `ResolveRequestLanguage` middleware on the `api` group (header, then account
locale, then `id`; sets the locale, `RequestContext` and `Content-Language`), `QueueName` enum. **Open points discussed and settled with the user (2026-10-03):** (1) registration is rate limited, **no CAPTCHA**:
at most **10 attempts per IP and 3 per email per hour** (every attempt counts, also for an existing email; invalid requests do not), 429 `too-many-requests`
with `Retry-After`; (2) the framework tables `jobs` and `failed_jobs` (standard structure, migrations `000046` and `000047`) were created, first in the `test_`
tables and then, on the user's explicit approval, in the real tables (46 real tables; columns identical); `.env.example` now says `QUEUE_CONNECTION=database` and
`MAIL_MAILER=smtp` with comments, and its `DB_DATABASE` was corrected from the legacy `karir_pkp` to `jobportal_v2`; the user's local `.env` was **not** changed
(it still has `sync` and `log`, fine for development; the production `.env` must use smtp, the `database` queue and workers for `critical,default,bulk`);
(3) registration keeps revealing an existing email (409), accepted and limited by the rate limits. Still open: the `cache` table or another shared cache store
(the sign-in and registration limiters use the cache; the current `file` store is fine for one server only). **Password reset DONE (2026-10-03):** `POST /api/v1/auth/password/forgot {email}` and `POST /api/v1/auth/password/reset {email, code, password}`, both
204 and unauthenticated. Decided with the user: two endpoints (code and new password in one call, no intermediate token); `forgot` is **neutral** (always 204, no
email for unknown, suspended or deactivated accounts, nothing revealed); throttling = **10 requests per IP per hour** (visible 429, independent of the email)
plus the per-account limits (60 s apart, 5 per hour) applied **silently** (204 but no email); a successful reset sets the password, **marks the email verified**,
**revokes every token** of the account and does **not** sign the user in; public users only (admins have no self-service reset, to be decided with admin
management); social-only accounts (no password) may set one this way; only accounts for which `User::canResetPassword()` holds can reset: `active` ones **and accounts waiting for their deletion** (`deactivated` with
`deletion_requested_at` set and not anonymised; decided with the user after the consequence was discussed: otherwise a user who forgot the password could never
cancel the deletion, which needs valid credentials). Such an account keeps its `deactivated` status after the reset and signs in only after the deletion is
cancelled with the new credentials (endpoint still to be written). Suspended, otherwise deactivated, anonymised and unknown accounts get the same 422
`verification-code-expired` as a missing code. Reset codes are separate from verification codes (purpose), same 15-minute / 5-attempt rules. Refactors:
`VerificationCodeIssuer` (per purpose, replaces `EmailVerificationCodeIssuer`), `VerificationCodeChecker` (shared wrong-attempt logic), `VerificationCodeMail`
base with `EmailVerificationMail` and `PasswordResetMail`, event `PasswordReset`, `AccessTokenIssuer::revokeAllForUser`.
**Account deletion DONE (2026-10-03):** `POST /api/v1/me/deletion {password, confirm: true}` (auth:user, 200 with `data.scheduled_for`),
`POST /api/v1/auth/account/restore {email, password, confirm: true}` (unauthenticated, returns a sign-in token like login) and the scheduled command
`identity:anonymize-due-accounts` (daily 02:00, `withoutOverlapping`, `onOneServer`; `AnonymizeDueAccounts`, batches of 100, one transaction per account).
Decided with the user: requesting needs the **current password + explicit confirmation** (wrong password 422 `invalid-password`, limited like sign-in; an account
without a password gets 422 `password-required` and must set one through the reset flow first); it deactivates the account and **revokes every token**; cancelling
(`deletion-not-requested` 409 when nothing is pending) logs the user in and shares the sign-in failed-attempt limit; the anonymisation clears personal data and
keeps statistical columns (list in `phase-0-schema.md`); the original email can register again afterwards. Cross-context cleanup: event `UserAnonymized` and a
minimal **Notification context** (`NotificationServiceProvider`, use case `PurgeUserNotificationData`, queued listener on `default`) deleting device tokens and
notifications. New convention: **morph map** `user` / `admin` (`IdentityServiceProvider::MORPH_MAP`) so polymorphic columns (`personal_access_tokens`,
`notifications`, RBAC `model_type`) store short names, not class names. Bug found and fixed by the new tests: `VerificationCodeMail` lost its properties when queued
(`SerializesModels` ignores private properties of a parent class); the trait was removed and a regression test now runs the mails through the sync queue.
**Three open points settled with the user (2026-10-03):** (1) `notification_preferences` rows are **deleted** on anonymisation (added to the Notification
purger). (2) **Principle for submitted applications of an anonymised account:** the application rows, stage history and statistics stay, but every file owned by
the applicant (avatar, uploaded resumes, generated CVs, chat attachments) is deleted, profile data and screening answers holding personal data are cleared, and the
applicant shows as `Deleted user`; each context erases its own data by listening to `UserAnonymized`. Implemented now for the avatar: shared port
`FileStorage::delete` (`LaravelFileStorage`, default private disk) used by `AnonymizeDueAccounts` as the **last step inside the transaction** (a failure rolls back and
the next run retries). Resumes, chat files and Phase 1 profile data are written with Phase 1/2. (3) **Audit log, scope:** login success and failure, password
reset, and the account deletion lifecycle (requested, cancelled, anonymised); registration and email verification are **not** audited. Implemented now in the
Platform context: `RecordAuditEntry` / `AuditLogRepository` (append-only, `DatabaseAuditLogRepository`), `AuditAction` names (`auth.login`, `auth.login_failed`,
`auth.password_reset`, `account.deletion_requested`, `account.deletion_cancelled`, `account.anonymized`) and the queued listener `RecordIdentityAuditEntries`
(`default` queue); entries carry the actor, subject, IP address and user agent (cut to 255), metadata only `reason` / `scheduled_for`, never the typed email, a
password or a code; anonymisation is recorded with the `system` actor. `PasswordReset`, `AccountDeletionRequested` and `AccountDeletionCancelled` events now carry the
IP address and user agent. Not written yet: the audit read endpoint (`audit_logs.view`) and the 12-month purge job.
**Google sign-in DONE (2026-10-03):** `POST /api/v1/auth/google {id_token, device_name?, campaign_code?}` returns the sign-in body plus
`data.is_new_user` (200 for both). The API verifies the ID token itself with **`google/auth` v1.55.1** (chosen by the user over `google/apiclient` and a manual
JWKS check); it needs **`phpseclib/phpseclib` ^3.0.35**, which `google/auth` lists only as a dev dependency, so it was added to `composer.json` (without it every
verification fails). `GoogleAuthIdentityVerifier` checks signature, issuer and expiry through the library and **always** checks the audience itself against
`services.google.client_ids` (env `GOOGLE_CLIENT_IDS`, comma separated: web, Android, iOS; **empty = feature off, 503 `google-sign-in-unavailable`**), because the
library skips the audience check when none is given; Google's public keys are cached in `storage/framework/cache/google-auth` (file pool, one server). The
production `.env` needs `GOOGLE_CLIENT_IDS`; no real Google call has been made (tests use a locally generated RSA key and a fake certificate endpoint, plus a fake
verifier for the endpoint). Decided with the user: the Google account is identified by its **subject** (`identity_social_accounts`), the email can change; an account
with the same email is **linked only because Google vouches for the email** (`email_verified` must be true, else 401 `google-email-not-verified`); if that account's
email was **never verified**, its **password is removed, the email is marked verified and its tokens are revoked** (blocks the pre-registration takeover; the owner
sets a password later through the reset flow); an account that already has another Google account linked gets 409 `social-account-conflict`; new accounts are job
seekers, verified, without a password, language from the request, optional campaign code; company members are never created this way but can link; suspended and
deactivated accounts are refused as at normal sign-in; invalid tokens count against a per-IP limit (5 failures per minute, shared with sign-in) and are audited as
`auth.login_failed`. New shared exception `ExternalServiceUnavailable` (503). Not done: unlinking Google, linking from account settings.
**Content context DONE (2026-10-03):** legal documents and FAQ, public reading plus admin management, with the admin RBAC foundation.
Decided with the user: public endpoints return **one language** (request language, fallback to the other), admin endpoints the full `{id, en}` objects; lists of
bounded size are **not paginated**; legal document lifecycle = **draft is free, publishing locks it forever, a newer version replaces the active one** (the previous
one is archived in the same transaction; the type is locked first), publishing needs title and body in **both** languages (422 `legal-document-incomplete`), no manual
archive endpoint, version numbers are the highest plus one per type; a FAQ category that still has questions **cannot be deleted** (409 `faq-category-not-empty`;
deactivate it instead); RBAC = permission **catalog in code** (`PermissionCatalog`, registered by each context's provider) + `identity:sync-permissions` + role
`super_admin` + operator command `identity:create-admin` (hidden password prompt; the role must exist, so run the sync first). Endpoints: public `GET /api/v1/legal-documents/{terms|privacy}`,
`GET /api/v1/legal-documents/{type}/versions/{n}` (published or archived only, so users can read what they accepted), `GET /api/v1/faq` (active categories with their
active questions in order; **categories without a visible question are left out**, my choice); admin (guard `admin`, permission `legal_documents.manage`)
`GET|POST /api/v1/admin/legal-documents` (`filter[type]`), `GET|PUT|DELETE .../{id}`, `POST .../{id}/publish`; admin (permission `faqs.manage`) CRUD on
`/api/v1/admin/faq-categories` and `/api/v1/admin/faqs` (`filter[category_id]`). `LegalDocumentPublished` is dispatched (nobody listens yet; the terms gate and a
future audit listener will). Details in architecture.md section 2.9. **Not run against the real tables:** `identity:sync-permissions` and `identity:create-admin` (they
write to the real `identity_` tables: ask the user first); no legal text or FAQ content was written (it is the owner's content: enter it through the admin API).
**Terms gate DONE (2026-10-04):** `GET /api/v1/me/terms-acceptance` (what is in force and what the user accepted: `data.accepted`, `data.documents[]`) and
`POST /api/v1/me/terms-acceptance {terms_version?, privacy_version?}` (204). Decided with the user: the gate concerns **public users only** (admins are not asked);
the user sends the **version of every document in force that they saw**; a version that is no longer in force is 409 `legal-document-outdated` (the body lists the
versions in force), a document in force that is left out is 422 `legal-document-missing`, a type that is not in force is 422 `legal-document-not-active`; a type
that was **never published is not required** (until a document is published nobody is gated); acceptances are recorded once per user, type and version with the
language and IP address, and kept when a new version is published. The gate (`EnforceTermsAcceptance`, in the `api` middleware group) refuses a signed-in public user
who has not accepted every document in force with **409 `terms-not-accepted`** plus `required_documents: [{type, version}]` (new `extensions` on `DomainException`,
rendered into the problem body). It acts on every route that needs `auth:user`, so new routes are protected by default; public routes are never gated even when the
client sends a token. **Exempt routes (exactly five, guarded by a test):** `GET /me`, `GET|POST /me/terms-acceptance`, `POST /auth/logout`, `POST /me/deletion`. Email
verification and resending are gated (the decided exception list did not include them). A route opts out with `->withoutMiddleware(EnforceTermsAcceptance::class)`.
Content provides the versions in force through the shared port `ActiveLegalVersions` (`DatabaseActiveLegalVersions`, one light query per request, plus one query for the
acceptances: add caching if it ever shows up in profiling). Registration does not record acceptance; a new account is gated like a migrated one.
**ReferenceData context DONE (2026-10-04):** public reading of the 17 masters and the 5 location levels, and admin management of the masters, with the
**first paginated lists** and the shared pagination component. Decided with the user: **skills, job-roles and tags are paginated** (offset, `page` from 1, `per_page` 1 to 100, default 20,
else 422; `links` and `meta` with the total; `filter[q]` name search, `sort`, and `filter[kind]` on skills), the other 14 masters are returned whole (bounded, under 200 items,
`page` parameters are ignored there); admins can **create, update and deactivate but not delete** items (permission `reference_data.manage`; **tags are not admin-managed yet**, Phase 1);
public GET responses carry **`Cache-Control: public, max-age=3600` and an `ETag`** (304 on revalidation), and every API response now carries `Vary: Accept-Language, Authorization`.
Endpoints: `GET /api/v1/{job-categories|job-roles|job-types|job-levels|work-times|experience-levels|education-levels|salary-periods|skills|tags|benefits|industries|organization-types|team-sizes|religions|nationalities|languages}`
(active items; the name in the request language; extra columns at the top level: `kind`, `rank`, `min_years`, `max_years`, `min_size`, `max_size`, `code`; **benefits only while `status` is `active`**;
`image_path`, `popularity_override` and the proposal columns are not exposed), `GET /api/v1/{provinces|cities|subdistricts|villages|postal-codes}` (every level below the provinces **requires** its
parent: `filter[province_id]`, `filter[city_id]`, `filter[subdistrict_id]`, `filter[village_id]`, else 422 `location-parent-required`; names are not translated; postal codes have only `code`);
admin `GET|POST /api/v1/admin/{master}` and `PUT /api/v1/admin/{master}/{id}` (full `{id, en}` names, inactive items included, `sort_order`, `is_active`): both names required, the **slug comes from the
English name once and never changes**, a taken slug gets `-2`, `-3`, a unique language `code` is 409 `reference-value-taken`. Implementation: masters are **data, not 17 classes** (`MasterCatalog`,
`LocationCatalog`, one `DatabaseMasterRepository` driven by the definition). **Finding:** the JSON `name` columns have the binary collation `utf8mb4_bin`, so `LIKE` was case-sensitive and sorting was
by byte order; search and name sorting now name `COLLATE utf8mb4_unicode_ci` explicitly (checked read-only against the real seeded data). **Not done:** `image_path` of job categories (needs file
upload), admin management of locations (BPS data is not editable), benefit proposals and tag popularity (Phase 1). `identity:sync-permissions` must be run on the real database to create
`reference_data.manage` (ask first).
**Real database, 2026-10-04 (on the user's explicit approval):** `php artisan identity:sync-permissions` was run against the real tables. They now hold 3 permissions (`faqs.manage`
and `legal_documents.manage` in group `content`, `reference_data.manage` in group `reference_data`, guard `admin`), the role `super_admin` and its 3 role-permission links; nothing else
changed (0 admins, 0 users, `test_` tables untouched). **Still not run on the real database:** `identity:create-admin` (needed before anyone can use the admin API; it writes `identity_admins` and
`identity_admin_roles`, password from a hidden prompt). Run `identity:sync-permissions` again after every deployment that adds a permission (ask first).
**Not done yet:** account-state middleware (email verified: 409 on actions that affect other parties, decided in architecture.md 2.8, to be added with the first such
endpoints), audit read endpoint and purge job, audit of admin actions (publishing a legal document), purging resumes and Phase 1 profile data on anonymisation (with
those features), real-world check of Google sign-in with the actual client IDs.
Commits (branch `phase-0-foundation`): `27cb3e1` (all Phase 0 code, tests, migrations and config) and `f7b19e9` (documentation). Nothing is uncommitted after them.

## 6. Suggested next steps

1. **Test setup rework: DONE (2026-10-03), code not committed.** `phpunit.xml` and `.gitlab-ci.yml` use `jobportal_v2` with `DB_PREFIX=test_`
   (forced); `config/database.php` reads `DB_PREFIX`; `Src\Shared\Infrastructure\Testing\TestDatabaseGuard` fails closed (prefix must be exactly `test_`,
   environment `testing`, no `RefreshDatabase`/`DatabaseMigrations`) and is called from `tests/TestCase.php`; `php artisan test:database:migrate`
   (`MigrateTestDatabaseCommand`) forces the prefix, runs the guard and a plain `migrate` (never `migrate:fresh`); feature tests use
   `DatabaseTransactions`. Unit tests for the guard and a smoke feature test pass; Pint and PHPStan are clean. **Not yet run:**
   `php artisan test:database:migrate` itself (it would create `test_migrations` in `jobportal_v2`; run it once the first migration exists).
2. Write the Phase 0 migrations for `jobportal_v2` from `phase-0-schema.md`, **confirming every table with the user** (the database is shared with the
   `test_` objects). Write foreign key columns explicitly as `bigint unsigned` (the design document does not spell out their type). Add the
   `created_at` index on the framework `notifications` table through a separate migration (confirm when writing it).
3. Verify package compatibility with Laravel 13 when adding `spatie/laravel-translatable` and the Spatie permission package (custom table names, composite
   pivot keys).
4. **Architecture document: DONE (2026-10-03).** `docs/design/architecture.md` holds the cross-cutting decisions (RFC 9457 errors and 422/409 statuses,
   Sanctum tokens with 30-day and 12-hour lifetimes and no abilities, hybrid authorization with an explicit `Actor`, request language, `/api/v1` URLs, pagination
   and filtering, pure domain entities with query services, `TransactionManager`, domain events after commit, three queues, per-context scheduler, private
   files, account states). **Shared-kernel cleanup DONE (2026-10-03):** `ProblemDetailsRenderer` replaces `ApiResponse`, `StateConflict` added, `RequestContext` reduced to
   the language, routes load under `api/v1`, all `src` comments in English (72 tests, Pint and PHPStan clean; see architecture.md section 4). The rest of the
   decisions (auth, packages, routes) have no code yet.
5. Audit and translate any remaining non-English comments/docs in the repository (the user has asked to be consulted before doing it).
6. Then start Phase 0 implementation by bounded context: Identity, Platform, Notification, ReferenceData, Content. Open details (seed data and the BPS
   source, verification-code lifetime, purge jobs) are decided when reached, one question at a time.
7. Commit the `docs/` files only when the user asks.

## 7. Items waiting for the user

- Decisions on the legacy findings (`legacy-findings.md`), especially S1 to S3 which are issues in the **running production application**
  (unauthenticated `migrate`, unauthenticated upload, unauthenticated cache clear). The user decides whether to report them to the maintainers.
- Approval to run the data migration against real production data (not before Phase 1; explicit approval is required at that moment).
- Legal review of the religion/nationality filtering decision.

## 8. Suggested first message for the new session

> Read `/usr/local/src/jobportal/docs/HANDOFF.md` and `docs/design/decisions.md`, check the memory index, then continue with the Phase 0
> migrations (confirm every table with me, one question at a time).
