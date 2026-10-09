# Campaigns and source attribution (design, 2026-10-10; decided, not coded)

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
- **Registration link:** built from a new setting `CAMPAIGN_REGISTRATION_URL` (a template with `{code}`, like `COMPANY_INVITATION_URL`); the campaign responses carry the full link and the QR encodes it.
- **Hooks:** the Identity registration and Google sign-in ask a shared interface (`CampaignCodes::activeCode(string $code): ?string`, provided by Campaign) when a new account is created. Changes to a campaign go to the audit log like the other admin and company actions.
- **Legacy import:** the 11 production campaigns come with their own importer later (a slug that does not fit the code pattern is normalised and the change recorded in the id map); it is a separate step and runs on real data only with approval.
