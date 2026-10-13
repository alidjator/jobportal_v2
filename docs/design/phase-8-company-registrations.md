# Phase 8: company registration requests

New API work asked by the front end (`docs/design/frontend.md`, section 8.1, page P13 `/untuk-perusahaan`). Designed on 2026-10-13, coded after the
legacy import rehearsal together with the front end. Code is not started.

## Decisions

- 2026-10-13: a public form lets a company ask for an account; requests wait in an admin review queue, and an approved one leads to the existing flow
  (the admin creates the company, the first company admin is invited) (decided in `frontend.md` 8.1). Company accounts are still created by an admin
  (`decisions.md` R1): the form only asks.
- 2026-10-13: the contact email is confirmed by a link (double opt-in) before a request enters the queue ([detail](#retention-and-notification)).
- 2026-10-13: no captcha: honeypot, IP limiter and double opt-in ([detail](#retention-and-notification)).
- 2026-10-13: unconfirmed requests are deleted after 7 days; decided ones are anonymised 90 days after the decision ([detail](#retention-and-notification)).
- 2026-10-13: admins learn of a new request from a dashboard queue card and an optional email to an address in the settings
  ([detail](#retention-and-notification)).
- 2026-10-13: the rest of the design agreed as proposed, adding admin page D22 ([detail](#design)).

## Starting points (read from the code, 2026-10-13)

- `CreateCompany` needs `name`, `industry_id`, `organization_type_id` and `admin_email`; it sends the 7-day invitation, which refuses an email that
  already has an account (409 `email-already-registered`, `phase-1-company.md`).
- The newsletter's double opt-in (confirmation link by email) and IP limiters are the existing anti-spam pattern; the API has no captcha.
- The admin dashboard already shows queues as cards (`CompanyVerificationQueueCard`, `JobApprovalQueueCard`).
- Every public IP limiter depends on the open client-IP point (`frontend.md` 5.2): behind the Nuxt proxy without a trusted proxy, the limit of this form
  would be shared by every visitor.

## Design

Agreed by the user on 2026-10-13.

**Naming:** "registration request" (`CompanyRegistration`), not "application", which already means a job application. Lives in the Company context.

**Form fields** (`POST /api/v1/company-registrations`, public):

| Field | Rule |
|---|---|
| `company_name` | required, 2 to 255 |
| `industry_id`, `organization_type_id` | required, active masters (what `CreateCompany` needs) |
| `team_size_id` | optional master |
| `province_id`, `city_id` | required, city inside the province |
| `website` | optional URL |
| `description` | optional, up to 2000 characters, plain text |
| `contact_name` | required, 2 to 255 |
| `contact_position` | required, up to 100 |
| `contact_email` | required email; becomes the first company admin's invitation address |
| `contact_phone` | required, the profile phone rule (optional `+`, 8 to 15 digits) |
| `privacy_accepted` | must be true; the active privacy document version is stored with the request |
| `website_url_confirm` | honeypot: must be empty, a filled one is answered as a success and dropped |

No files: verification documents are uploaded later by the company admin (C21).

**Lifecycle:** `unconfirmed` → `pending` → `approved` or `rejected`.

1. **Submit:** 202 with the same body whatever happens (an email that already has an account, a duplicate), so the form cannot be used to find out
   whether an email is registered. The request is stored `unconfirmed` and a confirmation link (token stored as a hash) is emailed.
2. **Confirm** (`POST company-registrations/confirm {token}`): the request becomes `pending` and the contact gets a "received" email. A second
   pending or unconfirmed request for the same email replaces nothing: the new one is dropped and the contact is told the first is still being read.
3. **Admin review** (`companies.manage`): `GET admin/company-registrations` (`filter[status, q]`), `GET .../{id}` with warnings (the email already has
   an account; a company with a similar name exists), `PATCH .../{id}` to correct the fields before approving, `POST .../{id}/approve`,
   `POST .../{id}/reject {reason, message_to_user?}` (same pattern as the user suspension in `phase-8-user-management.md`).
4. **Approve:** runs `CreateCompany` with the request's data (name, masters, contact email), copies website, location and team size into the new profile,
   and links the request to the company; the standard invitation email is the approval notice. If the email already has an account, approval is
   refused with 409 `email-already-registered` and the admin rejects or asks the contact for another address.
5. **Reject:** email to the contact with `message_to_user` when given; the internal reason stays admin-only.
6. **Audit:** `company_registration.approved` and `company_registration.rejected` with the admin.

**New page in the admin panel:** D22 "Company registrations" (queue, detail, correct, approve, reject), plus a dashboard queue card.

**Emails** (templates in `id` and `en`, editable in D13, in the language of the request): confirm, received, rejected.

## Retention and notification

Decided by the user on 2026-10-13:

- **Double opt-in:** as in the lifecycle above; the confirmation link is valid 24 hours.
- **No captcha:** the honeypot, an IP limiter (a few requests per hour per address, settled when coding) and the confirmation link; no third-party
  service. The IP limiter only works per visitor once the client-IP point of `frontend.md` 5.2 is settled.
- **Retention:** a scheduled job deletes `unconfirmed` requests 7 days after submission, and anonymises `approved` and `rejected` ones 90 days after the
  decision (contact name, position, email, phone and description cleared; company name, masters, status, dates and the linked company kept for
  statistics).
- **Admin notification:** a queue card on the admin dashboard (`pending` count, oldest first) and an email to the address in a new setting
  `company.registration_notify_email` (empty by default, so nothing is sent until it is filled; `SettingsTest` lists it).
