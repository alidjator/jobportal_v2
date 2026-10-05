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

Conventions as in `phase-0-schema.md`: `bigint unsigned` auto-increment `id`, enumerations as `varchar` with a check constraint, no foreign keys across
bounded contexts (references to Identity and ReferenceData are plain indexed columns), foreign keys only inside this context.

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
`suspension_reason` on `company_companies`) was run **only against the `test_` tables**; since `.env` is now `production`, the command needs `APP_ENV=testing php artisan test:database:migrate`
(its guard refuses to run in production). Implementation notes: suspending and restoring lock the company row, so two admins at the same moment cannot both succeed; the admin detail leaves out
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

## Still open (asked one at a time)

Endpoint list; company logo and banner upload (needs the file-upload design); how the first sign-in of an invited account
works; per-company setting overrides endpoints; audit entries for membership changes (decided in `decisions.md` section 4 that they are audited); the
account-state middleware.
