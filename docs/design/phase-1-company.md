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

## Slice 1: onboarding (decided 2026-10-05, not yet coded)

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

## Still open (asked one at a time)

Endpoint list; company logo and banner upload (needs the file-upload design); how the first sign-in of an invited account
works; per-company setting overrides endpoints; audit entries for membership changes (decided in `decisions.md` section 4 that they are audited); the
account-state middleware.
