# Architecture

Status: **cross-cutting decisions confirmed one at a time with the user on 2026-10-03**; no code implements them yet. The previous
`docs/ARCHITECTURE.md` described the discarded first port and was deleted. Only confirmed decisions are written here. Details that depend on a context
that is not designed yet are listed in section 3 and decided with that context.

The project is a **REST API only** (Laravel 13, PHP 8.3). Other parties build web and mobile clients, so the API is the contract.

## 1. Code standard (fixed earlier by the user)

- Laravel best practice with DDD is mandatory.
- Layers per bounded context: **Domain**, **Application**, **Infrastructure**, **Interface**.
- One use case per class, thin controllers, FormRequests for input validation, no global helpers of our own.
- Pint and PHPStan stay clean; tests run in the dedicated database `jobportal_v2_test` (since 2026-10-06; see `docs/HANDOFF.md`, section 3).
- Everything in the project is written in English: identifiers, comments, documentation, commit messages and API error messages.
- Bounded contexts of Phase 0: Identity, Platform, Notification, ReferenceData, Content. Code lives in `src/<Context>/...`
  (namespace `Src\<Context>\...`); the shared kernel is `src/Shared`.

## 2. Confirmed decisions

### 2.1 API responses (decided 2026-10-03)

- **Success:** Laravel `JsonResource` and `ResourceCollection`, wrapped in `{"data": ...}`; paginated lists carry the standard `links` and `meta`.
- **Errors:** one standard shape for every error, **RFC 9457 Problem Details** (`application/problem+json`: `type`, `title`, `status`, `detail`, plus
  `errors` for validation failures with status 422). A single mapping class turns exceptions into problem responses and replaces the old `ApiResponse`
  helper, which only existed for compatibility with the legacy API (`{data}` / `{error}`).
- **Status mapping (decided 2026-10-03):** a business-rule violation is **422** (a syntactically valid request refused by a rule, for example exceeding a
  daily limit); a conflict with the current state of a resource is **409** (for example applying to a job already applied to, or moving an application out of
  a final stage); not found 404, forbidden 403, unauthenticated 401, validation 422 with `errors`. The domain has two subclasses, `BusinessRuleViolation`
  and `StateConflict`, and every problem response carries a machine-readable `type`. The old mapping of business rules to 400 is dropped.
- **Problem `type` (decided 2026-10-03):** a URN of the form `urn:jobportal:error:<code>`, for example `urn:jobportal:error:already-applied`, with kebab-case
  codes registered in one catalog in code (the identifier does not depend on a domain name and needs no documentation page at that address).
- Still to decide: the contents of the error catalog (written together with the use cases that raise each error).

### 2.2 Authentication (decided 2026-10-03)

- **Token type:** Laravel Sanctum opaque personal access tokens, stored hashed in the framework table `personal_access_tokens` (polymorphic: public users from
  `identity_users`, admins from `identity_admins`, two separate authentication guards). One token per device or session; tokens are revoked on logout,
  password change and account deletion. No refresh tokens and no JWT.
- **Lifetime (absolute, counted from creation):** public users **30 days**, admins **12 hours**. Because the two differ, the expiry is set per token when it
  is created (Sanctum's `createToken` accepts an expiry date; to be verified against the installed version) instead of through the single global
  `sanctum.expiration` setting. After expiry the user signs in again.
- **No abilities (decided 2026-10-03):** a token carries the full rights of its account. Account-state requirements, such as "has not accepted the active
  terms version" or "email not verified", are enforced by middleware that checks the account in the database, with the endpoints allowed in that state listed
  explicitly. The account state stays the single source of truth and no token has to be re-issued when it changes. Authorization is covered in the next
  section, not by token scopes.
- Still to decide: which account states block which endpoints (the email verification rule is not decided yet).

### 2.3 Authorization (decided 2026-10-03)

- **Hybrid enforcement (decided 2026-10-03):** coarse permissions (an admin holds `audit_logs.view`, the account type is `company_member`) are checked in route
  middleware through Laravel gates that wrap the RBAC interface (Spatie permission behind an interface in Infrastructure, see `decisions.md`). Rules that
  depend on data (a recruiter only manages applications of jobs assigned to them, a company admin only acts inside their own company) are written as plain
  PHP policy classes in Domain/Application and called by the use cases, so they apply the same way to HTTP requests, queue jobs and console commands.
- **Actor (decided 2026-10-03):** the controller builds an `Actor` value object (account kind, id, and company membership and role when relevant) from the
  authenticated account and passes it explicitly in the use case input. There is no ambient "current user" in the Application layer and the use cases never
  receive Eloquent models. Queue jobs and console commands build their own actor (for example a system actor).
- Still to decide: how company roles and per-job assignments are represented in the policies (decided together with the Company context in Phase 1).

### 2.4 Request language (decided 2026-10-03)

- The only per-request preference is the **language**, `id` or `en`; the country and currency of the old `RequestContext` are dropped (Rupiah only, no
  country list).
- Resolution order: the standard `Accept-Language` header (only `id` and `en` are supported, other values fall through); then the `locale` stored on the
  authenticated account (`identity_users.locale`); then `id`. Responses carry `Content-Language`. Emails and other asynchronous notifications always use the
  `locale` of the recipient's account, never a request header.

### 2.5 URL layout (decided 2026-10-03)

- **Versioning (decided 2026-10-03):** URL prefix `/api/v1/...` for every route (for example `/api/v1/job-categories`); a breaking change adds `/api/v2` and
  leaves `v1` running. Admin endpoints live under `/api/v1/admin/...`, with their own authentication guard and permissions. The route loading of
  `ContextServiceProvider`, which currently uses the prefix `api`, is adapted accordingly.
- **Naming (decided 2026-10-03):** plural kebab-case nouns (`/applications`, `/job-categories`) with plain CRUD on GET, POST, PUT, PATCH and DELETE.
  Business transitions are `POST` requests to an action sub-resource, for example `POST /applications/{id}/withdraw`, `POST /applications/{id}/stage`,
  `POST /jobs/{id}/close`; the rules of a transition (such as final stages) live in the use case, not in a generic `PATCH` of a status column.
- **Pagination (decided 2026-10-03):** ordinary lists (jobs, applicants, master data, admin tables) use offset pagination with `page` and `per_page` and the
  standard `links` and `meta` including the total. High-volume feeds that keep growing (notifications, chat messages, audit logs) use cursor pagination
  (`cursor`, no total). Each list endpoint states which one it uses.
- **Page size (decided 2026-10-03):** default **20**, maximum **100**; a larger `per_page` is rejected with 422 instead of being silently capped.
  **Minimum (decided 2026-10-04):** `page` and `per_page` must be at least **1**; a smaller value is also rejected with 422.
  **Implemented 2026-10-04:** `PageRequest`/`Page` (Application), `PaginatedRequest` and `PaginatedResources` (Interface); first used by the skills, job roles and tags lists.
  **Cursor pagination implemented 2026-10-04** (`CursorRequest`/`CursorPage`, same `per_page` limits, no total, `links.next` and `meta.next_cursor`); first used by the audit log.
  **Public reference data** is cacheable (`max-age=3600` and an ETag), so every API response carries `Vary: Accept-Language, Authorization`.
- **Filtering and sorting (decided 2026-10-03):** filters are written inside `filter[...]` (for example
  `?filter[province]=11&filter[work_mode]=remote&filter[q]=developer`) and ordering uses `sort` with a `-` prefix for descending order and commas between
  keys (`?sort=-created_at`). Every list endpoint declares its allowed filters and sortable columns, and any other value is rejected with 422. These names
  never collide with `page`, `per_page` and `cursor`.

### 2.6 Domain model and persistence (decided 2026-10-03)

- **Models (decided 2026-10-03):** writes go through plain PHP entities or aggregates in Domain, with repository interfaces in Domain and Eloquent
  implementations in Infrastructure (`Infrastructure/Persistence/Eloquent`); Eloquent models never leave Infrastructure and Domain does not depend on the
  framework. Reads and lists go through query services in Infrastructure that build DTOs or API resources straight from queries without loading entities.
  Contexts with real rules (Identity now, Application and Recruitment later) use the full pattern; pure CRUD contexts (ReferenceData, Content) may consist of
  query services and simple use cases only.
- **Transactions (decided 2026-10-03):** the use case wraps its changes in one transaction through a `TransactionManager` interface in Application, implemented in
  Infrastructure with `DB::transaction`. One use case is one unit of work; repositories never open their own transaction and read-only use cases do not
  use it. Tests can use a fake implementation.
- **Domain events (decided 2026-10-03):** aggregates record domain events (for example `ApplicationMovedToStage`); after the transaction commits, the use case
  dispatches them through an `EventDispatcher` interface implemented with Laravel events. Listeners in other contexts (for example Notification) run on the queue.
  Contexts do not call each other's use cases and do not know each other, and side effects never run for a rolled-back transaction. Eloquent observers are
  not used for business logic.
- Still to decide: nothing further in this section; queue, scheduler and file storage conventions follow in the next one.

### 2.7 Queue, scheduler and files (decided 2026-10-03)

- **Queue driver (decided 2026-10-03):** the `database` driver first (tables `jobs`, `failed_jobs`, no extra infrastructure, workers run under Supervisor,
  which is installed on the server), with Redis as a later configuration change if volume requires it. Facts checked on 2026-10-03: a Redis server runs on this
  machine, but the PHP `redis` extension is not installed (Predis would be needed) and the instance may be shared with the legacy application, so any use of
  it needs its own key prefix.
- **Queues (decided 2026-10-03):** three named queues processed in this order: `critical` (emails a user is waiting for, such as verification codes and
  password resets, and application stage notifications), `default` (everything else) and `bulk` (mass sends: newsletter, bulk chat messages, periodic
  emails), so that mass mail never delays a verification code.
- **Retries and failures (decided 2026-10-03):** each job is tried at most 3 times with a growing delay (for example 10 seconds, 1 minute, 5 minutes) set
  through `backoff`, then moves to `failed_jobs`. Jobs must be idempotent. Permanent failures are logged and failed jobs can be listed and retried with the
  artisan commands. `bulk` jobs send per recipient so one failure does not repeat the whole batch.
- **Scheduler (decided 2026-10-03):** each context registers its own schedule in its service provider, and each task calls one use case through a thin
  artisan command. The system cron runs `schedule:run` every minute. Every task uses `withoutOverlapping` and `onOneServer` and is idempotent. Tasks already
  implied by earlier decisions: anonymising accounts whose 30-day grace period ended, purging audit logs older than 12 months, purging notifications older
  than 6 months, refreshing the popular tags, and sending job alerts and periodic recommendation emails.
- **File storage (decided 2026-10-03):** one private Laravel filesystem disk (local, switchable to S3-compatible object storage through configuration). No file
  has a direct public URL: files are served through API endpoints that check access (streamed) or through temporary signed URLs. Stored names are random,
  never the client's file name; the type is checked from the content, not from the extension; size limits come from the admin settings. Avatars and logos
  take the same route (public caching is only considered later). This also closes the legacy unauthenticated upload finding (S2) for the new project.
  **Implemented (2026-10-05):** `FileStorage` (`put`, `get`, `delete`) and `ImageProcessor` are shared ports; images are checked by content and encoded again (see
  `phase-1-company.md`, slice 4); Laravel's built-in `/storage/{path}` route for the local disk is switched off (`'serve' => false`), so the only way out is an endpoint
  that checks access. The first use of a public cache is the company logo and banner (`public, max-age=3600` with a `?v=<version>` in the URL).
- Still to decide: nothing further in this section.

### 2.8 Account states that restrict the API (decided 2026-10-03)

- **Email verification (decided 2026-10-03):** an account with an unverified email can sign in and manage its own profile, but actions that affect other
  parties (applying, creating or publishing jobs, sending messages, inviting colleagues) are refused with 409 and a dedicated problem `type` until the email is
  verified. Resending the verification code stays available. Accounts that sign in with Google are verified from the start (the email comes from the
  verified ID token).
- **Terms gate (decided 2026-10-03):** an account that has not accepted the active version of the terms and privacy documents is refused on every endpoint with
  409 and a dedicated problem `type`, except: reading the active legal documents, accepting them, viewing and editing the basic account profile, signing out and
  deleting the account (the user can always leave and exercise the right over their data). Migrated accounts and every new version go through this gate.
  **Implemented 2026-10-04** (`EnforceTermsAcceptance`; details and the two scope decisions, public users only and "never-published types are not required", in
  `docs/HANDOFF.md`).
- **Suspended and deactivated accounts (decided 2026-10-03):** when an admin suspends an account, all its tokens are revoked and sign-in is refused with 403 and
  the problem `type` `urn:jobportal:error:account-suspended`, until an admin reactivates it. A deactivated account behaves the same way (including the one waiting
  for anonymisation, see `phase-0-schema.md`) with `account-deactivated`; the response for the grace period explains the scheduled deletion and the user
  restores the account through the cancellation endpoint. The difference is only the message and who can restore the account (an admin for suspended,
  the user for deactivated).
- **Legacy toggle (decided 2026-10-03):** the `email_verification` on/off admin setting is dropped; the email rule above is always on and is not in the settings
  catalog.
- Still to decide: nothing further in this section.

### 2.9 Translated text, bounded lists and the permission catalog (decided 2026-10-03)

- **Translated text in responses (decided 2026-10-03):** a public endpoint returns one string in the request language (resolution order of 2.4), falling back to the
  other language when the translation is empty; an admin endpoint returns the full object `{"id": "...", "en": "..."}` so it can be edited. Domain code uses the
  value object `Src\Shared\Domain\TranslatedText`; Eloquent models keep the JSON columns with `spatie/laravel-translatable`.
- **Bounded lists (decided 2026-10-03):** lists whose size is naturally limited (legal documents, FAQ, master data) are returned whole in `data`, without
  `links` or `meta`, and each list endpoint says so; offset pagination (2.5) is for lists that can grow (jobs, applicants, large admin tables).
  **Limit (decided 2026-10-04):** a list may be unpaginated only if, by design, it never exceeds **200 items**; a list that can grow beyond that must be
  paginated or filtered (for example villages, about 83,000 rows).
- **Admin permissions (decided 2026-10-03):** every context registers its permissions in the shared `PermissionCatalog` from its service provider (name and group,
  for example `legal_documents.manage` in group `content`). `php artisan identity:sync-permissions` (idempotent, run after every deployment that adds one) creates
  them with guard `admin` and keeps the role `super_admin` holding all of them; permissions that left the catalog are not deleted. Routes check them with
  `can:<permission>` after `auth:admin`. The first administrator is created with `php artisan identity:create-admin` (password read from a hidden prompt).

### 2.10 Settings and email templates (decided 2026-10-04)

- **Settings:** each context registers its settings (key `area.name`, type `integer`, `boolean`, `text` or `choices`, default, limits, `overridable`) in the shared `SettingsCatalog`; they are read through the
  `Settings` port (company override, else global value, else default), cached and invalidated on write; admins manage the global values with `settings.manage`, every change is audited, and settings never hold secrets.
  Company overrides have storage and a reader now and an admin API with the Company context.
- **Email templates:** each context registers its templates in the shared `EmailTemplateCatalog` and renders through the `EmailTemplates` port; admins override subject and body per language
  (`email_templates.manage`) in Markdown with `{{variable}}` placeholders, validated against the template's variables, previewed with samples, audited. Raw HTML in a body is never executed.

## 3. Details left to the context that needs them

None of these blocks the start of Phase 0 code; each is decided one question at a time together with the context concerned.

- The contents of the error catalog (`urn:jobportal:error:<code>`): written with the use cases that raise each error.
- How company roles and per-job assignments are represented in the policies (Company context, Phase 1).
- Cursor or offset pagination per list endpoint (the rule is fixed; each endpoint states its choice).
- Compatibility checks when the packages are added: Sanctum per-token expiry in the installed version, Spatie permission with the custom table names,
  `spatie/laravel-translatable` with Laravel 13.

## 4. Shared kernel cleanup (done 2026-10-03, not committed)

- `ApiResponse` was removed. `Src\Shared\Interface\Http\Problem\ProblemDetailsRenderer` is the single mapping from exceptions to RFC 9457 problem
  responses (validation 422 with `errors`, domain errors, 401, framework HTTP exceptions such as 404 and 405, and a generic 500 that hides internals), registered
  in `bootstrap/app.php`. Success responses use Laravel resources (added with the first endpoints).
- Domain exceptions: `DomainException` carries a kebab-case error code (`errorCode()`, a per-class default or a specific code passed by the use case);
  `BusinessRuleViolation` is 422, the new `StateConflict` is 409, `AccessDenied` is 403, `EntityNotFound` is 404. The catalog of specific codes is still
  written with the use cases (section 3).
- `RequestContext` holds only the language (`id`/`en`, default `id`). The resolution middleware (header, then account locale) arrives with Identity.
- `ContextServiceProvider` loads context routes under `api/v1` (`API_PREFIX`). All comments in `src` are English.
- Covered by `tests/Feature/Shared/ProblemDetailsTest.php`.
- **Reporting (decided 2026-10-05):** a `DomainException` is an expected answer to a client (404, 403, 409, 422, 429...), so it is rendered and **never logged**; before this, every one
  was logged as `ERROR` with a stack trace, which let anyone fill the disk by guessing invitation tokens or failing sign-ins. `ExternalServiceUnavailable` (503) and every other
  exception are still reported. Configured in `bootstrap/app.php` (`dontReportWhen`), covered by `tests/Feature/Shared/ExceptionReportingTest.php`. The test run no longer writes
  logs (`LOG_CHANNEL=null` in `phpunit.xml`), so test noise cannot be mistaken for production errors.
- Still missing: API routes, authentication middleware, and the packages needed by the decisions above (Sanctum, Spatie permission, Spatie translatable;
  Predis is not needed while the queue driver is `database`). Package compatibility is checked when each is added (section 3).
