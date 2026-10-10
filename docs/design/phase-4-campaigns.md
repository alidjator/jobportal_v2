# Campaigns and source attribution (designed and coded 2026-10-10)

A campaign is a registration link with a code, a type and an active period, used to find out where new job seekers come from (a job fair, a school, a partner, social media). In the legacy system it is owned by a company, produces a link and a QR code, and writes the code to `candidates.campaign_channel` at registration. The new system keeps that and adds the results. The account column `identity_users.registration_campaign_code` and the `campaign_code` field of the registration and Google sign-in requests already exist.

## Decided (2026-10-10, user)

1. **Owner:** a company (its company admins manage it) **or the platform** (an admin with the permission manages it, for example a job fair of the platform). One code space, unique globally and case-insensitively.
2. **Results are counts only**, never a list of people: registrations, how many of those registrants applied, applications, and a 30-day daily series of registrations. A campaign of a company counts applications to **that company's jobs**; a platform campaign counts applications to any job.
3. **Code at registration:** a code that matches a campaign that is active on that day is stored (its canonical, lowercase form); any other code is ignored and the registration still succeeds. Today any code of the right pattern is stored; this replaces it. Codes of accounts imported from the legacy system are kept as they are.
4. **QR code:** a server endpoint returns an SVG of the registration link. This needs a pure PHP package (`bacon/bacon-qr-code`, SVG backend, no image extension) added to `composer.json`.
5. **Results query (option A):** one read-only reporting query inside the Campaign context joins `identity_users` and `recruitment_applications`, a documented exception (it never writes). An index on `identity_users.registration_campaign_code` is added.

## Proposed (no decision needed unless you disagree)

- **Context:** a new `Campaign` bounded context (`src/Campaign`), tables prefixed `campaign_`.
- **Table `campaign_campaigns`:** `id`, `company_id` (null = platform, key to `company_companies`, RESTRICT), `code` varchar(64) (the pattern the requests already use, `^[A-Za-z0-9_.-]+$`, stored lowercase, unique through a `lower()` index), `name` varchar(150), `type`, `starts_on` and `ends_on` (Jakarta dates, both optional, start not after end), `is_active` (default true), `created_by_user_id` or `created_by_admin_id` (one of the two, keyed, RESTRICT), timestamps.
- **Types** (English values for the seven legacy ones): `job_fair`, `education_institution`, `government_institution`, `training_institution`, `partner`, `job_marketplace`, `social_media`.
- **Code never changes** after creation, because it is printed in links and QR codes. To change it, deactivate the campaign and create a new one. A campaign with no registration can be deleted (`DELETE`, never GET); one with registrations can only be deactivated.
- **Active** means `is_active` and today (Jakarta) inside the period, with an open end when a date is missing.
- **Endpoints**
  - Company admin: `GET|POST company/campaigns`, `GET|PATCH|DELETE company/campaigns/{id}`, `GET company/campaigns/{id}/results`, `GET company/campaigns/{id}/qr`. A recruiter has no access; another company's campaign is a 404.
  - Admin with the new permission `campaigns.manage` (needs `identity:sync-permissions`): the same under `admin/campaigns`, with a `company_id` filter and the ability to create a platform campaign.
  - Public: `GET campaigns/{code}` returns the name, the type and the company's name and logo, for the registration page; 404 when the campaign is not active; limited per client address and cached like the public job page.
- **Registration link:** built from a new setting `CAMPAIGN_REGISTRATION_URL` (a template with `{code}`, like `COMPANY_INVITATION_URL`); the user chose the legacy shape, `https://<client site>/{code}/register`, so the client route `/{code}/register` calls `GET campaigns/{code}` and then registers with `campaign_code`; the campaign responses carry the full link and the QR encodes it.
- **Hooks:** the Identity registration and Google sign-in ask a shared interface (`CampaignCodes::activeCode(string $code): ?string`, provided by Campaign) when a new account is created. Changes to a campaign go to the audit log like the other admin and company actions.
- **Legacy import:** the 11 production campaigns come with their own importer later (a slug that does not fit the code pattern is normalised and the change recorded in the id map); it is a separate step and runs on real data only with approval.

## What was coded (2026-10-10)

- **Context `Campaign`** (`src/Campaign`), migration `000103` (table `campaign_campaigns` with keys to `company_companies`, `identity_users` and `identity_admins`, checks for the type, the period, exactly one creator and a lower-case code, and a partial index on `lower(identity_users.registration_campaign_code)`). Added to `CrossContextForeignKeysTest`.
- **Use cases** `CreateCampaign`, `UpdateCampaign` (PUT, every editable field; `code` and `company_id` are refused), `DeleteCampaign` (409 `campaign-has-registrations`), `ListCampaigns` (filters `q`, `type`, `status`, `company_id` for admins), `GetCampaign`, `GetCampaignResults`, `RenderCampaignQr`, `GetPublicCampaign`. `CampaignAccess` gives the scope: a company admin of an active company (their own campaigns, others are 404) or a platform admin.
- **Endpoints:** `company/campaigns[/{id}[/results|/qr]]` and the same under `admin/campaigns` (permission `campaigns.manage`, needs `identity:sync-permissions`), and the public `GET campaigns/{code}` (limited per address, cached like the public job page, 404 unless running).
- **Registration:** `RunningCampaignCodes` implements the shared `CampaignCodes`; `RegisterJobSeeker` and `SignInWithGoogle` store the canonical code of a running campaign and drop any other code.
- **Results:** `DatabaseCampaignStatistics`, one reporting query over accounts and applications (counts only).
- **QR:** `BaconQrCodes` draws an SVG of the link; the link comes from `CAMPAIGN_REGISTRATION_URL` (template with `{code}`; empty means no link and a 503 `campaign-link-unavailable` for the QR). The QR endpoint needs the bearer token, so the client fetches it and shows the result.
- **Audit:** `CampaignChanged` (created, updated, deleted) is recorded by `RecordCampaignAuditEntries` (`campaign.created`, `campaign.updated`, `campaign.deleted`).
- **Tests:** `tests/Feature/Campaign/CampaignTest.php`; the two registration tests that sent a campaign code now create the campaign first.
- **Not done:** the importer of the 11 legacy campaigns (a separate step that runs on real data only with approval).

## Legacy import coded (2026-10-10)

`CampaignsImporter` (entity `campaigns`, id map `campaign`, depends on `accounts` and `companies`; writer `DatabaseCampaignWriter`; migration-free). Run it with `php artisan legacy:import campaigns` (a dry run until `--write` is given). The legacy table was checked by schema only (`SHOW COLUMNS`, no data): `id`, `company_id`, `title` (50), `slug` (50, not unique), `campaign_type`, `location`, `description`, `start_date`, `end_date`, `created_by`, `created_at`, `updated_at`; every column but `id` and `campaign_type` may be null.

- **Code:** the legacy `slug` (it is the value `candidates.campaign_channel` holds, as the client route `/{slug}/register` shows), lower-cased; characters outside letters, digits, `_`, `.`, `-` become `-` (flagged `code-normalised`); the oldest row wins a duplicate (compared in lower case), later ones are skipped as `code-taken`. The registration codes of imported accounts are not touched and are matched in lower case.
- **Type:** `job_fair`, `lembaga_pendidikan`, `lembaga_pemerintahan`, `lembaga_training`, `mitra`, `job_marketplace`, `sosial_media` map to the new seven; anything else is skipped as `type-unknown`.
- **Creator:** the legacy `created_by` user, or the owner of the company when that user was not imported (same rule as the screening questions).
- **Other fields:** `title` is the name; dates are kept (a missing one leaves that end open); every imported campaign is active; `location` and `description` are not carried; timestamps are kept.
- **Skip reasons:** `company-missing`, `company-not-imported`, `creator-not-imported`, `type-unknown`, `name-missing`, `slug-missing`, `slug-unusable`, `date-invalid`, `period-invalid`, `code-taken`.
- **Tests:** `tests/Feature/LegacyImport/CampaignsImporterTest.php` (9 tests, new synthetic table `campaigns` in `tests/Support/LegacySchema.php`). Nothing was run against production data.
