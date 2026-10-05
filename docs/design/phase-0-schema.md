# Phase 0 schema design (approved)

Status: **column design reviewed and APPROVED by the user on 2026-10-03 for all five contexts** (Identity, Platform, Notification, ReferenceData,
Content). Nothing is created in the database yet; migrations are written next, confirming every table with the user (the database is shared with the
`test_` objects). Details that remain open are listed at the end of this document.

## Conventions

- Table prefix per bounded context (`identity_`, `platform_`, `notification_`, `reference_`, `content_`).
  Laravel framework tables keep their default names (`migrations`, `personal_access_tokens`, `notifications`, `jobs`, `failed_jobs`, `cache`).
- Test objects use the `test_` prefix in front of the context prefix (`test_identity_users`).
- Primary keys: every table has a numeric `id` `bigint unsigned` auto-increment (Laravel convention; **confirmed by the user 2026-10-03**: a primary key
  whose content is a number is `bigint`). Exceptions: the three RBAC pivot tables keep the composite primary key of the Spatie package, and framework
  tables keep their default structure (`notifications` UUID, `cache` string key). Timestamps: `created_at`, `updated_at` (nullable `timestamp`).
- Booleans: `tinyint(1)`. Enumerations: `varchar` with a check constraint (no native `enum`, so values can evolve by migration).
- Translatable text (`id`, `en`): one `json` column per field, shape `{"id": "...", "en": "..."}`. **Confirmed by the user 2026-10-03:** keep JSON columns
  (no `*_translations` tables) managed with `spatie/laravel-translatable`, used only in Infrastructure Eloquent models (Domain and Application do not
  know the package). Compatibility with Laravel 13 is verified when the package is added to `composer.json`. Static texts from code use `lang/` files or
  the code catalog.
- **Foreign keys (rule changed 2026-10-05, decided by the user; the earlier line "no foreign keys across bounded contexts" was written by Claude and approved only as part of this document):** a foreign key
  is a database feature, so it applies across contexts too. Columns that reference **reference data (`reference_*`) and companies (`company_companies`)** have a foreign key with `RESTRICT` (those rows
  are never deleted: reference data is deactivated, companies are suspended). Columns that reference **accounts and admins** (`user_id`, `created_by_user_id`, `*_admin_id`) keep **no foreign key**
  (accounts are anonymised, and these columns record who acted). Polymorphic columns (`notifiable_id`, `tokenable_id`, `model_id`, audit `actor_id` and `subject_id`) cannot have one. The application
  still validates references first (ports such as `LocationReferences` and `ReferenceValues`) so a client gets a clear 422, not a database error. Two foreign keys from Notification to `identity_users`
  existed from the start and stay. Applied to the Company and Job tables (and `reference_benefits.proposed_by_company_id`) by migrations `000059` to `000061`, run against the `test_` tables and then, on the user's explicit
  approval, against the real tables (2026-10-05; the foreign keys of the real and `test_` tables are identical, 47 each; no row changed).
- Money, when it appears later: integer Rupiah, no currency column.
- Coordinates: `decimal(10,7)`.

## Identity (`identity_`)

### `identity_users` — **APPROVED by the user (2026-10-03)**
| Column | Type | Notes |
|---|---|---|
| id | bigint unsigned PK | |
| type | varchar(20) | `job_seeker` or `company_member` |
| name | varchar(255) | |
| email | varchar(255) | unique |
| password | varchar(255) null | null for social-only accounts |
| email_verified_at | timestamp null | |
| status | varchar(20) | `active` (default), `suspended`, `deactivated` |
| avatar_path | varchar(255) null | |
| locale | char(2) | default `id` |
| registration_campaign_code | varchar(64) null | source attribution (replaces `candidates.campaign_channel`) |
| last_login_at | timestamp null | |
| deletion_requested_at | timestamp null | set when the user requests deletion; starts the 30-day grace period; cleared when the user cancels the deletion through the cancellation endpoint |
| anonymized_at | timestamp null | set when the account is anonymised (account deletion = anonymisation); no `deleted_at` / soft delete |
| created_at, updated_at | timestamp null | |

Indexes: unique(email), index(type, status).

Contact data (phone, WhatsApp, address) is **not** stored here (**decided 2026-10-03, user**): it belongs to the job seeker profile and the company profile
in Phase 1. Production (schema verified read-only on 2026-10-03) spreads contact data over `candidates` (`phone`, `whatsapp_number`, `address`),
`contact_infos` (`phone`, `secondary_phone`, `email`, `secondary_email`, per user) and `companies` (`address`); `users` itself has no phone column.

Account deletion (anonymisation), **decided 2026-10-03 (user):**
- **Grace period 30 days.** On request, `deletion_requested_at` is set and the account is deactivated (`status` = `deactivated`). During the
  30 days sign-in stays **rejected** while the account is deactivated, but the error response explains the scheduled deletion. The user cancels
  through a dedicated cancellation endpoint (valid credentials plus explicit confirmation), which clears `deletion_requested_at` and reactivates the
  account (**decided 2026-10-03, user**). After 30 days a scheduled job anonymises the account permanently.
- **Anonymisation:** name, password, avatar and other personal data are removed or scrambled; `email` becomes the placeholder
  `anonymized-{id}@anonymized.invalid` (unique, frees the original address for a new registration); `anonymized_at` is set; the row stays so
  applications, stage history and statistics remain intact. Social accounts and device tokens are deleted; uploaded files are purged.
- **Marker:** an anonymised account is identified by `anonymized_at` only. `status` gets no `anonymized` value (anonymised accounts are `deactivated`).
- **Decided 2026-10-03 (user), Identity part:** the row keeps `id`, `type`, `locale`, `registration_campaign_code`, `status` (`deactivated`), `deletion_requested_at`,
  `created_at`; it clears `name` (becomes `Deleted user`), `email` (placeholder), `password` (null), `avatar_path` (null), `email_verified_at` (null) and
  `last_login_at` (null) and sets `anonymized_at`. Also deleted: social accounts, access tokens and verification codes; the `ip_address` of terms acceptances is
  set to null. Requesting the deletion needs the current password plus an explicit confirmation (accounts without a password must set one first); it
  deactivates the account and revokes every token; cancelling needs valid credentials plus confirmation and signs the user in. The scheduled job
  `identity:anonymize-due-accounts` runs daily at 02:00 (without overlap, on one server). Other contexts erase their own data by listening to the event
  `UserAnonymized` (Notification: device tokens and notifications, implemented).
- **Decided 2026-10-03 (user), the rest:** `notification_preferences` rows are deleted too. For submitted applications the rows, stage history and statistics
  stay, while every file owned by the applicant (avatar, resumes, generated CVs, chat attachments) is deleted and profile data and screening answers with
  personal data are cleared; the applicant appears as `Deleted user`. Audit entries: `account.deletion_requested`, `account.deletion_cancelled` and
  `account.anonymized` (system actor), plus `auth.login`, `auth.login_failed` and `auth.password_reset`.
- Still to design: the exact Phase 1 profile columns and the resume and chat file purge (written with those features, following the principle above).

### `identity_social_accounts` — **APPROVED by the user (2026-10-03)**
id, user_id (FK identity_users, cascade), provider varchar(20) (`google`), provider_user_id varchar(191), email varchar(255) null, timestamps.
Unique(provider, provider_user_id), unique(user_id, provider).

### `identity_admins` — **APPROVED by the user (2026-10-03)**
id, name, email (unique), password, email_verified_at null, status varchar(20) (`active` default, `suspended`, `deactivated`), avatar_path null, last_login_at null, timestamps.
Token authentication uses the framework table `personal_access_tokens`.

### RBAC (Spatie permission, custom table names) — **APPROVED by the user (2026-10-03)**
- `identity_roles`: id, name, guard_name, timestamps; unique(name, guard_name)
- `identity_permissions`: id, name, guard_name, group_name varchar(100) null, timestamps; unique(name, guard_name)
- `identity_role_permissions`: permission_id, role_id; primary key (permission_id, role_id)
- `identity_admin_roles`: role_id, model_type, model_id; primary key (role_id, model_id, model_type)
- `identity_admin_permissions`: permission_id, model_type, model_id; primary key (permission_id, model_id, model_type)

### `identity_verification_codes` — **APPROVED by the user (2026-10-03)** (code lifetime and attempt limit are decided at implementation)
| Column | Type | Notes |
|---|---|---|
| id | bigint unsigned PK | |
| subject_type | varchar(10) | `user` or `admin` |
| subject_id | bigint unsigned | |
| purpose | varchar(30) | `email_verification`, `password_reset` |
| code_hash | varchar(255) | **hashed**, never the plain code |
| expires_at | timestamp | |
| consumed_at | timestamp null | single use |
| attempts | tinyint unsigned | default 0, limits brute force |
| created_at | timestamp null | |

Index(subject_type, subject_id, purpose).

### `identity_terms_acceptances` — **APPROVED by the user (2026-10-03)**
id, user_id (FK identity_users, cascade), document_type varchar(20) (`terms`, `privacy`), document_version unsigned int, locale char(2), accepted_at timestamp, ip_address varchar(45) null.
Unique(user_id, document_type, document_version). No FK to `content_legal_documents` (cross-context).
On anonymisation the rows stay as proof of acceptance but `ip_address` is set to null (personal data); decided 2026-10-03 by the user.

## Platform (`platform_`)

### `platform_settings` — **APPROVED by the user (2026-10-03)** (revised: numeric id + unique key)
id bigint unsigned PK, `key` varchar(128) unique, `value` json, updated_by_admin_id bigint unsigned null, timestamps.
Catalog (key, type, default, validation) lives in code; the table stores only changed values.

### `platform_setting_overrides` — **APPROVED by the user (2026-10-03)**
id, `key` varchar(128), scope_type varchar(20) (`company`), scope_id bigint unsigned, `value` json, updated_by_admin_id null, timestamps.
Unique(key, scope_type, scope_id). No FK on scope_id (company table belongs to another context).

### `platform_email_template_overrides` — **APPROVED by the user (2026-10-03)**
id, template_key varchar(128), locale char(2), subject varchar(255), body mediumtext, updated_by_admin_id null, timestamps. Unique(template_key, locale). Default templates live in code.

### `platform_audit_logs` — **APPROVED by the user (2026-10-03)**
| Column | Type | Notes |
|---|---|---|
| id | bigint unsigned PK | |
| actor_type | varchar(10) | `user`, `admin`, `system` |
| actor_id | bigint unsigned null | |
| action | varchar(128) | e.g. `auth.login`, `auth.login_failed`, `export.applicants` |
| subject_type | varchar(64) null | |
| subject_id | bigint unsigned null | |
| metadata | json null | no sensitive values |
| ip_address | varchar(45) null | |
| user_agent | varchar(255) null | |
| occurred_at | timestamp(6) | |

Indexes: (actor_type, actor_id, occurred_at), (action, occurred_at), (subject_type, subject_id), (occurred_at) for the retention purge. Append-only
for the application (no update or delete endpoints); the only deletion is the scheduled purge of rows older than **12 months**. The purge runs daily in batches of 1,000 and records itself as one `audit.purged` entry (decided 2026-10-04). Readable only through
the admin API by admins holding the `audit_logs.view` permission. How the purge itself is recorded is still to be designed.

## Notification (`notification_`)

### `notification_preferences` — **APPROVED by the user (2026-10-03)**
id, user_id (FK identity_users, cascade), `key` varchar(64) (e.g. `application_update`), channel varchar(10) (`email`, `push`), enabled tinyint(1), timestamps. Unique(user_id, key, channel). In-app notifications are always delivered.

### `notification_device_tokens` — **APPROVED by the user (2026-10-03)**
id, user_id (FK identity_users, cascade), token varchar(512), platform varchar(10) (`android`, `ios`, `web`), last_used_at null, timestamps. Unique(token). No anonymous tokens.

### `notifications` (framework table, default name) — **APPROVED by the user (2026-10-03)**
Standard Laravel database notifications table (UUID primary key, polymorphic notifiable, `data` text, `read_at`); unmodified. Notifications of an anonymised
user are deleted. Retention (**decided 2026-10-03, user**): **6 months**, read or unread; a scheduled job deletes older rows. The framework table has
no index on `created_at`; the purge job needs one, so the migration adds `index(created_at)` to the standard structure in the same migration
(**decided 2026-10-03, user**).

## ReferenceData (`reference_`)

### Location (Indonesia only, BPS codes) — **APPROVED by the user (2026-10-03)** (timestamps per convention; data source for seeding is decided when the seeder is written)
| Table | Columns |
|---|---|
| `reference_provinces` | id, bps_code varchar(10) unique, name varchar(150), latitude/longitude decimal(10,7) null |
| `reference_cities` | id, province_id FK, bps_code unique, name, type (`city`, `regency`), latitude/longitude null |
| `reference_subdistricts` | id, city_id FK, bps_code unique, name, latitude/longitude null |
| `reference_villages` | id, subdistrict_id FK, bps_code unique, name, latitude/longitude null |
| `reference_postal_codes` | id, village_id FK, code char(5); unique(village_id, code) |

Location names are proper names and are **not** translated.

### Masters (17) — **APPROVED by the user (2026-10-03)** (common columns and all extra columns; initial values are seeded later)
Common columns: id, `name` json (id/en), slug varchar(150) unique, sort_order smallint default 0, is_active tinyint(1) default 1, timestamps.
The slug is generated from the `en` name (fallback `id` when `en` is empty); **decided 2026-10-03, user**. Text search on names queries the JSON directly (`JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')) LIKE ...`), with no extra generated columns (**decided
2026-10-03, user**); revisit per table if performance requires it.

| Table | Extra columns |
|---|---|
| `reference_job_categories` | image_path varchar(255) null |
| `reference_job_roles` | (merged roles and professions) |
| `reference_job_types` | employment relationship (contract, permanent, internship, freelance) |
| `reference_job_levels` | |
| `reference_work_times` | working pattern (full-time, part-time, shift) |
| `reference_experience_levels` | min_years, max_years smallint **unsigned** null (used by matching; made unsigned by migration `000044`, decided 2026-10-03) |
| `reference_education_levels` | rank smallint **unsigned** (minimum-education comparisons; migration `000044`) |
| `reference_salary_periods` | |
| `reference_skills` | kind varchar(10): `hard` or `soft` |
| `reference_tags` | popularity_override varchar(10) null: `pinned` (always in the popular list), `hidden` (never), null = automatic (computed from usage by active jobs, cached; computed in Phase 1 when jobs exist). Column shape approved |
| `reference_benefits` | status varchar(10) default `active`: `active`, `pending` (company proposal), `rejected`; `rejection_reason` varchar(255) null; `proposed_by_company_id` bigint unsigned null (plain indexed column, Company context arrives in Phase 1). Only `active` benefits are global and selectable; `is_active` only hides an already approved global benefit. Proposal storage shape approved |
| `reference_industries` | |
| `reference_organization_types` | |
| `reference_team_sizes` | min_size, max_size int **unsigned** null (migration `000044`) |
| `reference_religions` | |
| `reference_nationalities` | |
| `reference_languages` | code varchar(10), **unique** (decided 2026-10-03, user; added by migration `000043`) (spoken languages for candidate profiles) |

## Content (`content_`)

### `content_legal_documents` — **APPROVED by the user (2026-10-03)** (`body` is Markdown per language; clients render and sanitise it)
id, type varchar(20) (`terms`, `privacy`), version unsigned int, title json, body json (id/en), status varchar(20) (`draft`, `published`, `archived`), published_at null, created_by_admin_id null, timestamps. Unique(type, version); at most one `published` per type (enforced in the application).

### `content_faq_categories` — **APPROVED by the user (2026-10-03)**
id, name json, sort_order, is_active, timestamps.

### `content_faqs` — **APPROVED by the user (2026-10-03)** (`answer` is Markdown per language)
id, category_id (FK content_faq_categories, cascade), question json, answer json, sort_order, is_active, timestamps.

## Decisions on the original open questions (all confirmed by the user on 2026-10-03)

1. **Public profile URLs:** a unique, changeable `slug` (not an opaque id). The slug column lives on the job seeker profile and company tables (Phase 1),
   not on `identity_users`. Production uses `users.username`.
2. **Account deletion:** anonymisation with a 30-day grace period (see `identity_users`).
3. **Tag popularity:** computed automatically from usage by active jobs, with an admin override (`popularity_override` on `reference_tags`).
4. **Company-specific benefits:** global benefits plus company proposals approved by an admin (`status` on `reference_benefits`).
5. **Location names:** untranslated proper names.
6. **Audit log:** retained 12 months, readable only by admins holding `audit_logs.view`.
7. **Migration mapping:** deferred to Phase 1; no production data is read without explicit approval.

## Details still open (decided later, not assumed)

| Topic | Open detail | When |
|---|---|---|
| Profile slug | generation and collision suffix, reserved words, change rules, redirect history for old slugs | Phase 1 (profile and company tables) |
| Anonymisation | full list of columns and files cleared (including Phase 1 profile data), the scheduled job, audit-log entries | Phase 1 / implementation |
| Tag popularity | size of the popular list, refresh interval; the usage count needs the jobs table | Phase 1 |
| Benefit proposals | visibility of a pending proposal to the proposing company, approval flow | Phase 1 (Company context) |
| Audit-log purge | batch size, how the purge itself is recorded | implementation |
| Verification codes | code lifetime and attempt limit | implementation |
| Location and masters | data source for BPS location and postal codes, initial values of each master | when seeders are written |
| `spatie/laravel-translatable`, Spatie permission | compatibility with Laravel 13 (and extra checks on RBAC pivots) | when the packages are added |
