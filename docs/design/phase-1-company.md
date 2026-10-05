# Phase 1: Company context (design in progress)

Status: concept decisions confirmed by the user on 2026-10-05; **no tables, endpoints or code yet**. Gap codes (R1, R1b) refer to
`docs/analysis/gap-analysis.md`; the earlier decisions are in `decisions.md` section 3 (R1) and section 5 (phases).

## Order inside Phase 1

The user chose **Company and team first** (2026-10-05). It is the base for jobs, stages and the pipeline, and it brings company membership
into `Actor` and the per-company setting overrides (reader and storage already exist in Platform; the admin endpoints come with this context).

## Confirmed decisions

- **Company profile (`companies`):** core fields with a structured address. Name, public slug (unique, changeable), logo, banner, website,
  founding date, bio and vision (Markdown, one text, not translated), industry, organization type, team size, street address plus province, city,
  subdistrict, village and postal code from the location masters, contact (email, phone), verification status and public visibility.
  **No coordinates and no view counter.** The legacy `companies` table (read from the production schema on 2026-10-05) has no name column;
  the legacy name appears to live on the account row (`users`), **to be confirmed when the migration mapping is written**.
- **One company per company-member account.** A token is bound to that one company, so there is no active-company selector.
- **Invitations accept only an email without an account.** An email that already belongs to a job seeker or to another company member is refused
  (409 `email-already-registered`); a job-seeker account is never converted. The invitee opens the link, sets name and password, and the company
  member account is created with a verified email.
- **Invitation rules:** valid **7 days**, single use, token stored as a hash, role `company_admin` or `recruiter`; **one active invitation per
  email** (inviting again revokes the old one and sends a new one, at least 60 seconds apart); a company admin can **revoke** a pending invitation.
- **Company creation:** an admin creates the company and gives the email of the first company admin; the system sends the **same invitation**
  with the company admin role. The admin never sees a password, and can invite again when it expires.
- **Removing a member:** the membership is released and the account is **deactivated** (all tokens revoked), **not anonymised**, so notes, ratings and
  stage history under that name stay intact. The **last company admin cannot be removed** (409 `last-company-admin`), including by removing
  themselves. The member's job assignments are released; the company reassigns them (rules arrive with Jobs).

- **Company status:** two values, `active` and `suspended`. While suspended: the profile and all its jobs disappear from public view, members cannot use
  company features (403 `company-suspended`; signing in still works so the message is clear), existing applications are unchanged and job seekers are not
  told. The admin gives a reason (kept in the audit log). Restoring brings everything back.
- **Slug:** generated from the name when the company is created (lower case letters, digits and hyphens; a clash gets `-2`, `-3`). A company admin can change it
  (3 to 60 characters, unique, reserved words such as `admin`, `api`, `login` refused; 409 `slug-taken`). **Old slugs are not redirected**: old links stop working and
  clients use the id for stable references.

## Tables (`company_`) — column design **APPROVED by the user (2026-10-05)**

Conventions as in `phase-0-schema.md`: `bigint unsigned` auto-increment `id`, enumerations as `varchar` with a check constraint. Foreign keys: **changed 2026-10-05** (see `phase-0-schema.md`): the
reference-data columns of `company_companies` (industry, organization type, team size and the five location columns) have `RESTRICT` foreign keys (migration `000059`); references to accounts and admins
(`user_id`, `created_by_admin_id`, `invited_by_*`, `reviewed_by_admin_id`) have none. The text below describes the original design.

### `company_companies`
id; name varchar(255); slug varchar(60) unique; status varchar(20) default `active` (check: `active`, `suspended`); logo_path, banner_path varchar(255) null;
website varchar(255) null; established_on date null; bio text null; vision text null; industry_id, organization_type_id bigint unsigned (index); team_size_id null (index);
address_line varchar(255) null; province_id, city_id, subdistrict_id, village_id, postal_code_id bigint unsigned null (index); contact_email varchar(255) null;
contact_phone varchar(32) null; is_visible tinyint(1) default 1 (the company admin controls public visibility); verified_at timestamp null (filled by the Phase 2
verification process); created_by_admin_id bigint unsigned null (index); created_at, updated_at.

### `company_members`
id; company_id (FK `company_companies`, restrict); user_id bigint unsigned **unique** (one company per account; indexed, no cross-context FK); role varchar(20)
(check: `company_admin`, `recruiter`); created_at, updated_at. Index (company_id, role).

### `company_invitations`
id; company_id (FK `company_companies`, cascade); email varchar(255); role varchar(20) (same check); token_hash char(64) unique (the plain token only travels in
the email); invited_by_user_id null (a company admin); invited_by_admin_id null (a platform admin); sent_at timestamp (for the 60-second gap); expires_at timestamp;
accepted_at null; revoked_at null; created_at, updated_at. Indexes (company_id), (email). "One active invitation per email" is enforced by the use case inside a
transaction with a row lock, **not** by a unique index, because old invitations stay as history.

**Migrations (2026-10-05):** `2026_10_05_000048` to `000050` (one per table) are written and were run **only against the `test_` tables**
(`php artisan test:database:migrate`). The user chose to **postpone** the run against the real tables; `php artisan migrate` has not been run for them.

## Slice 1: onboarding (decided and coded 2026-10-05, branch `phase-1-company`, not yet committed)

**Done:** `POST /api/v1/admin/companies`, `GET /api/v1/auth/invitations/{token}`, `POST /api/v1/auth/invitations/{token}/accept` (201) and `GET /api/v1/company/me`;
24 new tests, whole suite 453 passed, Pint and PHPStan clean. Choices made while coding, **all confirmed by the user on 2026-10-05** (suspended company,
rate limit, audit scope and terms gate, as listed):
- Accepting or previewing an invitation of a **suspended company** is 403 `company-suspended`.
- Invitation lookups and acceptances are limited to **20 per IP per hour** (every call counts; a real invitee needs two).
- The two admin-facing failures: an unknown or inactive industry or organization type is 422 `unknown-industry` / `unknown-organization-type`; an email that already has
  an account is 409 `email-already-registered`.
- **Audit:** `company.created` (actor admin) and `company.member_joined` (actor the new user, metadata `role`), never an email address.
- `company/me` is gated by the terms like every `auth:user` route (the five exempt routes are unchanged).
- Cross-context ports added in Shared: `CompanyMemberAccounts` (Identity provides it: email check, account creation, sign-in), `CompanyMembershipResolver` (Company provides it;
  `ActorResolver` fills `Actor::$membership`), `ReferenceValues` (ReferenceData provides it). New email template `company.invitation`, new permission `companies.manage`,
  new config `app.invitation_url` (env `COMPANY_INVITATION_URL`).
- **Not done in this slice:** inviting again, revoking and listing invitations, the team endpoints, the profile endpoints, company suspension endpoints, company
  override endpoints for settings. `identity:sync-permissions` must be run on the real database to create `companies.manage`, and the three `company_` tables do not exist there yet.

### Decisions

Chosen as the first slice: create a company, invite the first company admin, accept the invitation.

- **Create company:** admin API `POST /api/v1/admin/companies` (guard `admin`, new permission `companies.manage`). The admin gives only the **required minimum**: `name`,
  `industry_id`, `organization_type_id` and `admin_email` (the first company admin). The rest of the profile is completed by the company admin after signing in.
  201 with the company and the invitation (never the token).
- **Preview and accept (public):** `GET /api/v1/auth/invitations/{token}` returns the company name, the invited email and the role; a token that is unknown, expired,
  revoked or already used is always 404 `invitation-not-found` (no distinction). `POST /api/v1/auth/invitations/{token}/accept {name, password}` creates the verified
  company-member account and answers like sign-in (30-day token); rate limited per IP like registration.
- **Company context:** a separate endpoint `GET /api/v1/company/me` (company members only) returns the company (id, name, slug, status) and the account's role. Identity's
  `/me` does not change, so Identity does not know Company. The `Actor` gets the company id and role through a Company port read by `ActorResolver`, not through the token.
- **Invitation link:** the email carries a client URL template from config `app.invitation_url` (env `COMPANY_INVITATION_URL`, for example
  `https://example.test/invitations/{token}`; the API replaces `{token}`). When it is empty, creating an invitation fails with 503 `invitation-link-unavailable`
  so no broken email is sent. The invitation email is a registered email template (editable by admins like the others).

## Slice 2: team (decided and coded 2026-10-05, branch `phase-1-company`, not yet committed)

**Done:** all seven endpoints below; 27 new tests (company suite 51), whole suite 480 passed, Pint and PHPStan clean. Implementation notes: changes to a company's team and
invitations lock the company row (and the invitation rows) inside a transaction, so two removals, or a revocation and an acceptance, at the same moment cannot break the
rules; the shared port `CompanyMemberAccounts` gained `deactivate` and `summaries`; `invited_by` shows the inviting member's name, or `null` when a platform admin invited
(the client can label that "platform"); a platform admin may invite again even while the company is suspended (acceptance stays refused until it is restored); an
invitation to an email that already has a pending invitation from another company is allowed (the cooldown and replacement are per company). `company/*` team routes are
gated by the terms like every `auth:user` route.

### Decisions

All endpoints are for **company admins** of the caller's own company (a recruiter gets 403 `company-admin-required`), except the last one, which is for platform admins.

- `POST /api/v1/company/invitations {email, role}`, `GET /api/v1/company/invitations` (pending ones), `DELETE /api/v1/company/invitations/{id}`. Inviting an email that already has a
  pending invitation **from this company** revokes the old one and sends a new one, at least **60 seconds** after the previous (429 `invitation-cooldown` with `Retry-After`).
  An email that already has an account is 409 `email-already-registered`.
- `GET /api/v1/company/members`, `DELETE /api/v1/company/members/{userId}`, `PUT /api/v1/company/members/{userId}/role {role}`.
- `POST /api/v1/admin/companies/{id}/invitations {email}` (permission `companies.manage`): invites a **company admin** again, for example when the first invitation expired.
- **Who can be removed or changed:** any member, including other company admins and oneself, as long as at least one company admin remains (409 `last-company-admin`).
- **Lists** are not paginated. Members: `user_id`, `name`, `email`, `role`, `status` of the account, `joined_at`, `last_login_at`, ordered by role then name; removed members are not listed.
  Pending invitations: `id`, `email`, `role`, `sent_at`, `expires_at`, `invited_by` (a name), newest first.
- **A removed member cannot be invited again for now** (their email stays registered, 409 `email-already-registered`); restoring a deactivated account is decided later with
  account management by admins. The membership row is deleted and the account is deactivated with every token revoked.
- **Audit:** `company.member_removed` (metadata: old role) and `company.member_role_changed` (metadata: old and new role); actor = the company admin or platform admin, subject = the
  company, the affected account id in the metadata. **Changed the same day (2026-10-05, user agreed after advice): sending and revoking invitations are audited too**,
because an invitation grants access to a company's data and a compromised company admin would start by inviting accounts. `company.invitation_sent` (actor: the company admin,
or the platform admin for the first invitation at company creation and for inviting again; metadata `invitation_id` and `role`) and `company.invitation_revoked` (metadata
`invitation_id`); never the email address (look it up through `invitation_id` in `company_invitations`). A replaced invitation is audited as a new one sent. Refused requests
(409, 422, 403, 429) leave no entry.

## Slice 3: profile (decided and coded 2026-10-05, branch `phase-1-company`, not yet committed)

**Done:** the four endpoints below plus `PUT /company/slug`; 55 new tests (whole suite 537 passed, Pint and PHPStan clean). Implementation notes: a badly formed slug is 422
`invalid-slug` (a reserved one 422 `slug-reserved`); a slug race between two companies is settled by the unique index and reported as 409 `slug-taken`; only a *changed*
classification is checked against the masters, so a master item an admin deactivated later neither blocks unrelated edits nor loses its name on the public page; the public route only
matches `[a-z0-9-]+`; `Company` now holds a `CompanyProfile` value object and `CompanySlug` holds the slug rules. The terms gate applies to the `company/*` routes as before.

- **Scope:** the text profile, the slug and the public page. **Logo and banner upload is the next slice** (the first file feature: content check, size limit from settings, random
  names, served through the API; avatars and resumes will reuse it). `logo_path` and `banner_path` stay empty and are not exposed yet.
- **Endpoints (company admin edits, any member of the company may read):** `GET /api/v1/company/profile` (every profile column as stored: ids for the classifications and the
  location, raw Markdown; works while the company is suspended, like `company/me`), `PATCH /api/v1/company/profile` (send only what changes; `null` clears an optional
  field; name, industry and organization type cannot be cleared; 204) and `PUT /api/v1/company/slug {slug}` (204). Public: `GET /api/v1/companies/{slug}`.
- **Validation (422 per field):** name 2 to 255; website an http or https URL up to 255; founding date not in the future; bio and vision Markdown up to 5,000 characters
  each; address line up to 255; contact email a valid email; contact phone digits with `+`, spaces, hyphens and parentheses, 6 to 32 characters; industry, organization type and
  team size must be **active** master items (422 `unknown-industry`, `unknown-organization-type`, `unknown-team-size`); the location must be **one matching chain**
  (the city belongs to the province, the subdistrict to the city, the village to the subdistrict, the postal code to the village; a lower level needs the upper levels), else 422
  `invalid-location`. When only part of the chain is sent, it is checked together with the stored rest, so changing the province means sending the new city too.
- **Slug:** 3 to 60 characters, lower case letters, digits and hyphens, no leading, trailing or double hyphen. Refused as 422 `slug-reserved` when it is one of: `admin`, `api`,
  `auth`, `login`, `register`, `company`, `companies`, `jobs`, `job`, `me`, `search`, `about`, `help`, `faq`, `terms`, `privacy`, `support`, `www`; 409 `slug-taken` when another
  company has it. Setting the slug already in force does nothing (204). **No limit on how often it changes** (decided with the user); old links are not redirected.
- **Public profile (no contact data):** name, slug, bio, vision, website, founding date, industry, organization type and team size (names in the request language), location
  (province to village names, street address, postal code) and the verification badge (`verified_at`). Email and phone are **not** public (spam and scraping; applicants
  reach the company through chat). 404 when the slug is unknown, the company is suspended or `is_visible` is false. `Cache-Control: public, max-age=60` and an `ETag`.
- **Audit:** profile edits and slug changes are **not** audited (the audit log is not a log of all data changes).
- New shared ports: `LocationReferences` (chain check and names, provided by ReferenceData) and `ReferenceValues::name` (the name of a master item in a language).

## Slice 4: logo and banner upload (decided and coded 2026-10-05, branch `phase-1-company`, not yet committed)

The first file feature; the mechanism is shared (image processing, file storage) so avatars and resumes can reuse it. **Done:** 32 new tests (11 for the image processor, 21 for
the endpoints), whole suite 569 passed, Pint and PHPStan clean. **Deviation from the first agreement, decided with the user the same day:** the upload is a **multipart `POST`**,
not a `PUT`, because PHP does not read a multipart body on PUT (the file would never arrive). The shared pieces are `ImageProcessor` (`GdImageProcessor`), and `FileStorage` which
gained `put` and `get`. The settings `company.logo_max_size_kb` and `company.banner_max_size_kb` now appear in the admin settings list.

- **Endpoints (company admin of an active company):** `POST /api/v1/company/logo` and `POST /api/v1/company/banner` (multipart, field `file`, 204) and `DELETE` on the same
  paths (204, also when there is no image). Reading: public `GET /api/v1/companies/{slug}/logo|banner` (no sign-in; 404 when the company is not public or has no image;
  `Cache-Control: public, max-age=3600` and an `ETag`) and own `GET /api/v1/company/logo|banner` (any member, also while the company is hidden or suspended; private cache).
  The profiles carry `logo_url` and `banner_url` (absolute API URLs ending in `?v=<version>` so a replaced image breaks the cache; `null` when there is none); the own profile
  points to the own routes, the public profile to the public ones.
- **Processing:** the type comes from the content (JPEG, PNG or WebP, never the extension or the client's type), else 422 `invalid-image`; the image is decoded and **encoded
  again with GD**, which drops all metadata (EXIF, GPS) and anything hidden in the file; JPEG orientation is applied first. Logos are shrunk to fit **512x512**, banners **1600x600**
  (never cropped or enlarged). Output is PNG when the result has any transparency, JPEG (quality 85) otherwise. A source above 16 million pixels is refused (decompression bomb).
- **Size limits** are admin settings registered by Company: `company.logo_max_size_kb` (default 2048) and `company.banner_max_size_kb` (default 5120), both 100 to 10,240;
  larger files are 422 `file-too-large`.
- **Storage:** the private disk, random names (`company/logos/<random>.png|jpg`, `company/banners/...`), never the client's name. Replacing stores the new file, updates the
  row, then deletes the old file (a failed delete is logged and does not fail the upload). Not audited.
- The PHP limits `upload_max_filesize` and `post_max_size` must be at least the largest configured limit, or the request is cut before the API sees it.

## Slice 5: admin (decided and coded 2026-10-05, branch `phase-1-company`, not yet committed)

**Done:** 38 new tests (23 for the list, detail, suspend and restore; 15 for the setting overrides), whole suite 618 passed, Pint and PHPStan clean. Migration `000051` (`suspended_at` and
`suspension_reason` on `company_companies`) was run **only against the `test_` tables**; it was later applied to the real tables too, together with `000048` to `000050` (2026-10-05, with the user's approval). (While `.env` was `production`, the
test-table command needed `APP_ENV=testing`, because its guard refuses to run in production; `.env` is `local` now.) Implementation notes: suspending and restoring lock the company row, so two admins at the same moment cannot both succeed; the admin detail leaves out
the picture URLs of the company's own profile (an admin cannot call the company's own routes) and has `has_logo` and `has_banner` instead; the override audit entries use subject type `company`
and the company id (`old` is the value that applied to the company before the change, `new` the new one; a reset logs the removed override as `old`); the shared port `CompanyDirectory`
(provided by Company) answers "does the company exist" for Platform.

Four wishes were named; **company document verification becomes its own slice right after this one** (it needs a company-side document upload, new tables and open decisions: document
types and sizes, the list of rejection reasons). This slice holds three parts, all guard `admin`:

- **List and detail (permission `companies.manage`):** `GET /api/v1/admin/companies` is **offset-paginated** (`page`, `per_page` 1 to 100, default 20, with the total) with `filter[q]`
  (name or slug, case-insensitive), `filter[status]=active|suspended`, `filter[verified]=true|false` and `sort` (`name`, `-name`, `created_at`, `-created_at`; default newest first). Each
  row: `id`, `name`, `slug`, `status`, `verified`, `members_count`, `created_at`. `GET /api/v1/admin/companies/{id}` adds the whole profile (as the company sees it), pending invitations count,
  `created_by_admin_id`, and `suspended_at` and `suspension_reason` while suspended.
- **Suspend and restore (permission `companies.manage`):** `POST .../{id}/suspend {reason}` (reason **required**, 5 to 1,000 characters) and `POST .../{id}/restore {note}` (note optional, up
  to 1,000 characters, audit only), both 204. Suspending a suspended company is 409 `company-already-suspended`; restoring an active one 409 `company-not-suspended`; unknown company 404.
  **The reason is stored on the company** (new columns `suspended_at` and `suspension_reason`, cleared on restore) **and audited** (`company.suspended` with the reason, `company.restored`
  with the note); **only admins see it**, members just get 403 `company-suspended`. Member tokens are **not** revoked: they can still sign in and see the 403.
- **Per-company setting overrides (permission `settings.manage`, built in Platform):** `GET /api/v1/admin/companies/{id}/settings` (the overridable settings: global value, the company's
  override and the effective value), `PUT .../{key} {value}` and `DELETE .../{key}` (back to the global value). Validation and cache handling stay with Platform's settings; "the company
  exists" comes from a shared port provided by Company. A setting that is not overridable is 422 `setting-not-overridable`; an unknown company or key is 404. Changes are audited
  (`settings.updated` and `settings.reset` carry the company id).

## Slice 6: company document verification (design in progress, started 2026-10-05; no code yet)

Decisions confirmed by the user, one question at a time:

- **Document types are managed by admins as data** (a master list with a required/optional flag per type), not a fixed list in code.
- **Where:** a table of the Company context itself (working name `company_document_types`: slug, names `id`/`en`, required flag, active, order), with its own admin
  endpoints under permission `companies.manage`. ReferenceData is not touched.
- **Accepted files:** PDF, JPEG, PNG and WebP; the type is checked from the content, never the extension or the client's type. Images are re-encoded like logos
  (metadata dropped); a PDF is stored as uploaded after its signature is checked.
- **Size limit:** admin setting `company.document_max_size_kb`, default 5120 KB, range 100 to 10,240, overridable per company.
- **Statuses and re-submission:** one row per (company, document type) with status `pending`, `approved` or `rejected`. After a rejection the company uploads a new file that
  replaces the old one (the old file is deleted) and the status returns to `pending`. The history of decisions lives in the audit log.
- **Rejection reasons:** the admin picks one code from a **fixed list in code** and may add an optional free note (up to 500 characters); clients translate the codes. The codes
  (confirmed): `unreadable`, `expired`, `wrong_document`, `company_mismatch`, `incomplete`, `other` (`other` requires the note).
- **Badge:** `verified_at` is filled **automatically** when every required document type is `approved`. It is withdrawn when a company replaces an approved document, or when an
  admin adds a new required type, until everything is approved again. **No expiry dates** and no scheduled job.
- **File access:** files stay on the private disk and are served only through endpoints: platform admins (permission `companies.manage`) and the company's own company admins; never
  public, recruiters cannot see them. Each opening by a platform admin is audited (`company.document_viewed`, no content). Approvals and rejections are audited too.
- **Notifications:** in-app only, to every company admin of the company: `company.document_approved`, `company.document_rejected` (data: document type, reason code, note) and
  `company.verified` when the badge is given. No email for now (SMTP is not configured); email and push preferences can be added later.
- **Initial types (seeded):** `business_registration` (NIB, required), `tax_id` (NPWP, required) and `deed_of_establishment` (company deed, optional); the `id`/`en` names are
  written for this project and still to be approved. Admins can change the list afterwards. Until a required type exists, no company can be verified.
- **Type lifecycle:** types are **never deleted**; an admin creates, edits, deactivates and switches required/optional. An inactive type does not count for the badge and accepts no new
  upload, but existing documents are kept. Every change of the list recalculates the badge of the affected companies.
- **Endpoints (approved):** company admin: `GET /api/v1/company/documents` (active types with the company's document status), `POST /api/v1/company/documents/{typeId}` (multipart,
  upload or replace), `GET .../{typeId}/file` (own file), `DELETE .../{typeId}` (only while `pending` or `rejected`; an `approved` document can only be replaced, never deleted
  without a replacement). Platform admin (`companies.manage`): `GET /api/v1/admin/companies/{id}/documents`, `GET .../{typeId}/file`, `POST .../{typeId}/approve`,
  `POST .../{typeId}/reject {reason, note?}`, and `GET|POST|PUT /api/v1/admin/company-document-types[/{id}]`.
- **Tables (columns approved):** `company_document_types` (id; slug varchar(60) unique; name JSON `id`/`en`; is_required, is_active tinyint(1); sort_order int unsigned; timestamps) and
  `company_documents` (id; company_id FK `company_companies` restrict; document_type_id FK `company_document_types` restrict; status varchar(20) check `pending`/`approved`/`rejected`;
  file_path varchar(255); original_name varchar(255), display only; mime_type varchar(100); size_bytes int unsigned; rejection_reason varchar(30) null; rejection_note varchar(500) null;
  submitted_at; reviewed_at null; reviewed_by_admin_id null, plain index; timestamps; unique (company_id, document_type_id); index (status)). Migrations are run against the `test_`
  tables first; the real tables only with the user's approval.
- **While a company is suspended:** its company admins get 403 `company-suspended` on every document endpoint (upload, delete, read); platform admins can still view, approve and
  reject. The badge is not hidden separately (the public profile is already gone during a suspension).

All design questions of this slice are answered. **Migrations (2026-10-05):** `000052` (`company_document_types`) and `000053` (`company_documents`) are written and were run against the
`test_` tables and, **on the user's explicit approval, against the real tables** (53 migrations recorded; columns identical in both sets; the CHECK on `status` and the JSON validity check
of `name` exist; both real tables hold 0 rows). **Seeder (2026-10-05):** `Database\Seeders\Company\CompanyDocumentTypeSeeder` (called by `DatabaseSeeder`; inserts by slug, never overwrites admin changes; slugs are explicit and use
hyphens like the other masters, decided with the user) creates `business-registration` ("Nomor Induk Berusaha (NIB)" / "Business Registration Number (NIB)", required), `tax-id`
("Nomor Pokok Wajib Pajak (NPWP)" / "Tax Identification Number (NPWP)", required) and `deed-of-establishment` ("Akta Pendirian Perusahaan" / "Deed of Establishment", optional); the names
were approved by the user. 3 tests; Pint and PHPStan clean. **Run against the real table on 2026-10-05 on the user's explicit approval, with this seeder only** (3 rows; `test_` table
stays empty). No endpoint or use case code yet.

**Admin side of the document types (started 2026-10-05; the user chose to start with the admin side):** decisions so far:
- **Slug:** generated from the English name when a type is created (a clash gets `-2`, `-3`; at most 60 characters), never sent by the admin and **never changed afterwards**.
- **Audit:** `company_document_type.created`, and `company_document_type.updated` only when `is_required` or `is_active` changes (metadata: slug, old and new values); edits of names or order
  alone are not audited, because they do not affect who is verified.
- **Badge calculation is built in this slice:** one shared use case recalculates the badge (every active required type `approved` => `verified_at` filled, keeping an existing date;
  otherwise cleared; no active required type => nobody is verified). It runs for all companies when a type is created or when `is_required` / `is_active` changes, and is reused by the
  upload and review slices. Notifying companies about a badge gained or lost is **left to the review slice** (the in-app notification writer does not exist yet).
- **Endpoints (approved; guard `admin`, permission `companies.manage`):** `GET /api/v1/admin/company-document-types` (whole list including inactive types, not paginated, ordered by
  `sort_order` then id; row: `id`, `slug`, `name {id,en}`, `is_required`, `is_active`, `sort_order`), `POST` (201; `name.id` and `name.en` required, max 255; `is_required` default false;
  `is_active` default true; `sort_order` 0 to 65,535, default 0) and `PUT /{id}` (the full body as in POST; the slug stays; 404 when unknown). No DELETE.
- **Done (2026-10-05, branch `phase-1-company`, not committed):** the three endpoints, the badge calculation and the audit. 18 new tests (whole suite 639 passed, Pint and PHPStan clean).
  Implementation notes: the badge calculation is the port `CompanyVerificationBadge` (`recalculateAll`, `recalculateFor`), implemented with two set-based updates (withdraw, then grant), and
  it bumps `updated_at` with `verified_at`; creating a type recalculates only when it is required and active; an update recalculates only when `is_required` or `is_active` changed; the
  audit actions are `company_document_type.created` and `company_document_type.updated` (subject type `company_document_type`; `updated` carries `old` and `new` flags). A name that yields no
  slug characters gets the slug `document` (`CompanySlugGenerator::make` gained a `$fallback` parameter). No migration and no new permission, so nothing needs to run on the real database.
  (Open point settled in the review slice below: companies are now notified when a recalculation grants or withdraws their badge.)

**Company side of the documents (started 2026-10-05; the user chose it before the review side):** decisions so far:
- **`GET /api/v1/company/documents`** (company admin of an active company): one entry per **active** type in `sort_order`: `type {id, slug, name (request language), is_required}` and
  `document` (null when none; otherwise `status`, `original_name`, `mime_type`, `size_bytes`, `submitted_at`, `reviewed_at`, and `rejection_reason` + `rejection_note` only while rejected),
  plus `verified` (boolean, from the badge). Not paginated.
- **Serving a file:** as a download (`Content-Disposition: attachment` with a safe name, `X-Content-Type-Options: nosniff`, `Content-Security-Policy: default-src 'none'`), so a PDF is never
  rendered by the browser from the API origin; clients show it with their own viewer.
- **Audit:** `company.document_submitted` (metadata `document_type_id`, `replaced`) and `company.document_deleted` (metadata `document_type_id`, `status` when deleted); actor the company
  admin, subject the company; never the file name or content. Refused requests leave no entry.
- **Images:** re-encoded with the shared `ImageProcessor` (metadata dropped), shrunk only when the longest side exceeds **3508 px** (A4 at 300 dpi), never enlarged or cropped; the 16 million
  pixel limit still applies.
- **Upload rules (approved):** `POST /api/v1/company/documents/{typeId}` (multipart, field `file`). Unknown type 404; inactive type 422 `document-type-inactive`; not a PDF or image 422
  `invalid-document`; too large 422 `file-too-large`. No document yet: created `pending` (201). A document exists (pending, rejected or approved): the file is **replaced**, the status returns to
  `pending`, the rejection reason and note are cleared, `submitted_at` is renewed, the old file is deleted and the badge is recalculated (replacing an approved document withdraws it); 204.
- **File names:** the client's name is stored as `original_name` for display only (path removed, control characters dropped, cut to 255, empty becomes `document`). On disk the file has a random
  name (`company/documents/<random>.pdf|jpg|png`). A download is named `<type-slug>.<extension>` (for example `tax-id.pdf`), never the client's name.
- **Delete:** `DELETE /api/v1/company/documents/{typeId}` (204) only while the document is `pending` or `rejected`; an `approved` one is 409 `document-approved`; an unknown type or no document
  is 404 `not-found`. The file is deleted; the badge is **not** recalculated, because only a non-approved document can be deleted, so deleting never changes it. `GET .../{typeId}/file` returns the company's own file (404 when none). All four routes are for company admins of an
  active company (403 `company-admin-required` / `company-suspended`); the size limit is the admin setting `company.document_max_size_kb` (default 5120, 100 to 10,240, **overridable per company**).
- **Done (2026-10-05, branch `phase-1-company`, not committed):** the four endpoints, the audit and the setting. 28 new tests (whole suite 672 passed, Pint and PHPStan clean). Implementation notes: the
  list answer is `{data: {verified, documents: [{type, document}]}}`; a new document is 201 with no body, a replacement 204; uploads and deletes lock the company row, so two requests of one company
  run one after the other; the badge is recalculated only when the replaced document was approved; a PDF is accepted when it starts with `%PDF-` (nothing deeper is checked, as decided); the size
  is checked on the uploaded bytes before processing, as for images. **Finding fixed with the user's approval:** `CompanyRepository::save` wrote `verified_at` from the entity loaded at the start
  of the request, so a slow request (a logo upload decoding an image) could overwrite a badge granted or withdrawn meanwhile; `save` no longer writes `verified_at` (only
  `CompanyVerificationBadge` does), with two regression tests. Not done: the review side (approve, reject, admin file access, notifications), which is the next slice.

**Review side of the documents (started 2026-10-05):** decisions so far, on top of the ones listed for the whole slice (endpoints, reason codes, audit of file views, in-app notifications):
- **Only a `pending` document can be reviewed** (approve or reject); anything else is 409 `document-not-pending`. A decision is final until the company uploads a new file, which puts the
  document back to `pending`.
- **Version check:** approve and reject must send the `submitted_at` of the document the admin saw (from the admin list). If the company replaced the file since, the answer is 409
  `document-changed` and nothing changes; the admin reloads and reviews the latest file. A finding of this design (the company can replace a file while an admin reviews it), fixed with
  the user's choice; no new column is needed.
- **Admin list** `GET /api/v1/admin/companies/{id}/documents` (permission `companies.manage`; works while the company is suspended; unknown company 404): `data: {verified, documents: [{type
  {id, slug, name {id,en}, is_required, is_active}, document}]}`. It covers every active type and every inactive type that has a document. `document` is null when there is none; otherwise
  `status`, `original_name`, `mime_type`, `size_bytes`, `submitted_at`, `reviewed_at`, `reviewed_by_admin_id`, `rejection_reason` and `rejection_note`. Not paginated.
- **Which events notify (in-app, every company admin of the company):** `company.document_approved`, `company.document_rejected`, `company.verified` (the badge is granted, by an approval or by a
  recalculation after the type list changed) and `company.verification_withdrawn` (the badge is withdrawn by a change of the type list). A withdrawal caused by the company replacing an approved
  document is **not** notified (the company did it). `CompanyVerificationBadge` is extended to report which companies were granted or lost the badge.
- **Delivery:** after the decision is saved the use case dispatches an event; a Company listener on the `default` queue (3 tries, backoff 10/60/300 s, like the audit listeners) finds the
  company admins and writes the notifications through a new shared port `InAppNotifier` provided by Notification. A failure to notify never undoes a decision and can be retried. The queue is
  `sync` today, so it runs inside the request.
- **Notification data (approved):** `company.document_approved` `{document_type_id, document_type_slug}`; `company.document_rejected` the same plus `reason` (the code) and `note` (the admin's
  note or null); `company.verified` and `company.verification_withdrawn` have empty data. No file name, company name or admin name. One notification per company admin.
- **Requests and audit (approved):** `POST /api/v1/admin/companies/{id}/documents/{typeId}/approve {submitted_at}` (204) and `POST .../reject {submitted_at, reason, note?}` (204). `reason` is one of
  the six codes (422 `invalid-rejection-reason` otherwise); `note` is at most 500 characters and required when `reason` is `other` (422 per field); `submitted_at` is the ISO 8601 value of the admin
  list. Errors: unknown company or document 404, not pending 409 `document-not-pending`, replaced meanwhile 409 `document-changed`. Audit: `company.document_approved` (metadata `document_type_id`),
  `company.document_rejected` (metadata `document_type_id`, `reason`, `note`) and `company.document_viewed` (metadata `document_type_id`) for every opening of a file by a platform admin
  (`GET /api/v1/admin/companies/{id}/documents/{typeId}/file`, a download like the company's own); actor the admin, subject the company. Refused requests leave no entry.
- **Done (2026-10-05, branch `phase-1-company`, not committed):** the four admin endpoints, the audit, the notifications and the badge reporting. 24 new tests (whole suite 696 passed, Pint and PHPStan
  clean). Implementation notes: an invalid reason or note is an ordinary per-field 422 (`reason`, `note`), not a separate `invalid-rejection-reason` code; `submitted_at` accepts any ISO 8601 timestamp with
  a zone (`+00:00`, `+07:00` or `Z`) and is compared as a moment, to the second; review and upload of one company lock the company row; the shared port `InAppNotifier` (provided by Notification,
  `DatabaseInAppNotifier`) is the first writer of in-app notifications (empty data is stored and shown as `{}`); `CompanyVerificationBadge` now returns `BadgeChanges` (granted and withdrawn company ids,
  selected with a row lock) and the type endpoints dispatch `CompanyVerificationGranted` / `CompanyVerificationWithdrawn` after commit; the listener `NotifyCompanyAdminsOfVerification` (queue `default`)
  notifies the company admins only, never recruiters. The document types and the review need no migration and no new permission, so nothing has to run on the real database. Not done: email for these
  events (SMTP is not configured), a way to undo a decision without a new upload.

## Still open (asked one at a time)

Endpoint list; company logo and banner upload (needs the file-upload design); how the first sign-in of an invited account
works; per-company setting overrides endpoints; audit entries for membership changes (decided in `decisions.md` section 4 that they are audited); the
account-state middleware.
