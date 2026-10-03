# Phase 0 schema design (draft for review)

Status: **draft, not reviewed**. Table lists were approved per context; the columns below are a proposal.
Nothing here is created in the database yet.

## Conventions

- Table prefix per bounded context (`identity_`, `platform_`, `notification_`, `reference_`, `content_`).
  Laravel framework tables keep their default names (`migrations`, `personal_access_tokens`, `notifications`, `jobs`, `failed_jobs`, `cache`).
- Test objects use the `test_` prefix in front of the context prefix (`test_identity_users`).
- Primary keys: `bigint unsigned` auto-increment. Timestamps: `created_at`, `updated_at` (nullable `timestamp`).
- Booleans: `tinyint(1)`. Enumerations: `varchar` with a check constraint (no native `enum`, so values can evolve by migration).
- Translatable text (`id`, `en`): one `json` column per field, shape `{"id": "...", "en": "..."}`.
- No foreign keys across bounded contexts unless noted; cross-context references are plain indexed columns.
- Money, when it appears later: integer Rupiah, no currency column.
- Coordinates: `decimal(10,7)`.

## Identity (`identity_`)

### `identity_users`
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
| created_at, updated_at | timestamp null | |

Indexes: unique(email), index(type, status).

### `identity_social_accounts`
id, user_id (FK identity_users, cascade), provider varchar(20) (`google`), provider_user_id varchar(191), email varchar(255) null, timestamps.
Unique(provider, provider_user_id), unique(user_id, provider).

### `identity_admins`
id, name, email (unique), password, email_verified_at null, status varchar(20), avatar_path null, last_login_at null, timestamps.

### RBAC (Spatie permission, custom table names)
- `identity_roles`: id, name, guard_name, timestamps; unique(name, guard_name)
- `identity_permissions`: id, name, guard_name, group_name varchar(100) null, timestamps; unique(name, guard_name)
- `identity_role_permissions`: permission_id, role_id; primary key (permission_id, role_id)
- `identity_admin_roles`: role_id, model_type, model_id; primary key (role_id, model_id, model_type)
- `identity_admin_permissions`: permission_id, model_type, model_id; primary key (permission_id, model_id, model_type)

### `identity_verification_codes`
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

### `identity_terms_acceptances`
id, user_id (FK identity_users, cascade), document_type varchar(20) (`terms`, `privacy`), document_version unsigned int, locale char(2), accepted_at timestamp, ip_address varchar(45) null.
Unique(user_id, document_type, document_version). No FK to `content_legal_documents` (cross-context).

## Platform (`platform_`)

### `platform_settings`
`key` varchar(128) PK, `value` json, updated_by_admin_id bigint unsigned null, timestamps.
Catalog (key, type, default, validation) lives in code; the table stores only changed values.

### `platform_setting_overrides`
id, `key` varchar(128), scope_type varchar(20) (`company`), scope_id bigint unsigned, `value` json, updated_by_admin_id null, timestamps.
Unique(key, scope_type, scope_id). No FK on scope_id (company table belongs to another context).

### `platform_email_template_overrides`
id, template_key varchar(128), locale char(2), subject varchar(255), body mediumtext, updated_by_admin_id null, timestamps. Unique(template_key, locale). Default templates live in code.

### `platform_audit_logs`
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

Indexes: (actor_type, actor_id, occurred_at), (action, occurred_at), (subject_type, subject_id). Append-only.

## Notification (`notification_`)

### `notification_preferences`
id, user_id (FK identity_users, cascade), `key` varchar(64) (e.g. `application_update`), channel varchar(10) (`email`, `push`), enabled tinyint(1), timestamps. Unique(user_id, key, channel). In-app notifications are always delivered.

### `notification_device_tokens`
id, user_id (FK identity_users, cascade), token varchar(512), platform varchar(10) (`android`, `ios`, `web`), last_used_at null, timestamps. Unique(token). No anonymous tokens.

### `notifications` (framework table, default name)
Standard Laravel database notifications table.

## ReferenceData (`reference_`)

### Location (Indonesia only, BPS codes)
| Table | Columns |
|---|---|
| `reference_provinces` | id, bps_code varchar(10) unique, name varchar(150), latitude/longitude decimal(10,7) null |
| `reference_cities` | id, province_id FK, bps_code unique, name, type (`city`, `regency`), latitude/longitude null |
| `reference_subdistricts` | id, city_id FK, bps_code unique, name, latitude/longitude null |
| `reference_villages` | id, subdistrict_id FK, bps_code unique, name, latitude/longitude null |
| `reference_postal_codes` | id, village_id FK, code char(5); unique(village_id, code) |

Location names are proper names and are **not** translated.

### Masters (17)
Common columns: id, `name` json (id/en), slug varchar(150) unique, sort_order smallint default 0, is_active tinyint(1) default 1, timestamps.

| Table | Extra columns |
|---|---|
| `reference_job_categories` | image_path varchar(255) null |
| `reference_job_roles` | (merged roles and professions) |
| `reference_job_types` | employment relationship (contract, permanent, internship, freelance) |
| `reference_job_levels` | |
| `reference_work_times` | working pattern (full-time, part-time, shift) |
| `reference_experience_levels` | min_years, max_years smallint null (used by matching) |
| `reference_education_levels` | rank smallint (minimum-education comparisons) |
| `reference_salary_periods` | |
| `reference_skills` | kind varchar(10): `hard` or `soft` |
| `reference_tags` | |
| `reference_benefits` | |
| `reference_industries` | |
| `reference_organization_types` | |
| `reference_team_sizes` | min_size, max_size int null |
| `reference_religions` | |
| `reference_nationalities` | |
| `reference_languages` | code varchar(10) (spoken languages for candidate profiles) |

## Content (`content_`)

### `content_legal_documents`
id, type varchar(20) (`terms`, `privacy`), version unsigned int, title json, body json (id/en), status varchar(20) (`draft`, `published`, `archived`), published_at null, created_by_admin_id null, timestamps. Unique(type, version); at most one `published` per type (enforced in the application).

### `content_faq_categories`
id, name json, sort_order, is_active, timestamps.

### `content_faqs`
id, category_id (FK content_faq_categories, cascade), question json, answer json, sort_order, is_active, timestamps.

## Open questions (not decided)

1. **Public profile URLs**: production uses `users.username` (`/candidates/{username}`, `/employer/{username}`). Keep a username/slug, or use opaque ids?
2. **Account deletion**: hard delete, soft delete, or anonymisation? (affects `deleted_at` and retention of applications.)
3. **Tag popularity**: production has `tags.show_popular_list`. Keep a flag, or compute popularity?
4. **Company-specific benefits**: production `benefits.company_id` allows per-company benefits. Keep, or make benefits global only?
5. **Location names** are proposed as untranslated proper names.
6. **Retention** of audit logs and who may read them.
7. **Migration mapping** from production tables is designed in Phase 1 (no production data is read without explicit approval).
