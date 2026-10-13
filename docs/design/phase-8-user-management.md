# Phase 8: admin management of public user accounts

New API work asked by the front end (`docs/design/frontend.md`, section 9.3, page D21). Designed on 2026-10-13, coded after the legacy import
rehearsal together with the front end. Code is not started.

## Decisions

- 2026-10-13: admin endpoints to list, search, view, suspend and reactivate job seekers and company members, audited, behind a new permission
  (decided in `frontend.md` 9.3). Admin accounts stay console-only.
- 2026-10-13: the user gets an email on suspension and on reactivation; the admin may add a message for the user, the internal reason stays admin-only
  ([detail](#email-to-the-user)).
- 2026-10-13: the account detail adds a profile summary (counts and location, no content) ([detail](#design)).
- 2026-10-13: the rest of the design agreed as proposed ([detail](#design)).

## Current state (read from the code, 2026-10-13)

- `identity_users` holds job seekers and company members (`type`), psychologists are users too (`UserType::Psychologist`) but are managed by
  `assessment_providers.manage` (D19, suspend, restore, reset two-factor) and stay out of this feature.
- `AccountStatus` has `active`, `suspended`, `deactivated`; sign-in already refuses the last two (`account-suspended`, `account-deactivated`,
  `architecture.md` 2.8). Nothing in the API can set `suspended` today.
- Company suspension (`SuspendCompany`) is the pattern to follow: a reason of 5 to 1000 characters stored with the time, seen by admins only, cleared on
  restore, audited through the context's own listener; no email is sent.

## Design

Agreed by the user on 2026-10-13.

- **Permission:** `users.manage` (group `identity`), registered in the catalog; `identity:sync-permissions` gives it to `super_admin`.
- **`GET admin/users`** (paginated): `filter[q]` (part of the name or the email; a trigram index on `identity_users.name` and `email` is added),
  `filter[type]` (`job_seeker`, `company_member`), `filter[status]`, `filter[verified]`, `filter[pending_deletion]`; `sort` `-created_at` (default),
  `name`, `-last_login_at`. Item: `id`, `type`, `name`, `email`, `status`, `email_verified`, `created_at`, `last_login_at`, and for a company member the
  company name and slug (through a port that answers for the whole page in one query). Psychologists are never listed.
- **`GET admin/users/{id}`:** the fields above, `locale`, sign-in methods (password, Google), `registration_campaign_code`, deletion
  requested and due dates, `anonymized_at`, the suspension time, reason and admin, and the company role. **Profile summary (chosen by the user):**
  for a job seeker the city and province, whether the profile is complete and discoverable, the number of resumes and of applications and the date of
  the last one; for a company member the company, the role, the number of jobs created and of open jobs assigned. Each count comes from its context
  through a port; no profile text, resume file, application content or message is returned.
- **`POST admin/users/{id}/suspend`** `{reason, message_to_user?}` (`reason` 5 to 1000 characters, admin-only; `message_to_user` optional, up to
  1000 characters, sent in the email): only an active account; every token of the account is revoked in the same
  transaction; otherwise 409 `account-not-active`. An account waiting for deletion or anonymised cannot be suspended.
- **`POST admin/users/{id}/reactivate`** `{message_to_user?}`: only a suspended account (409 `account-not-suspended` otherwise); the reason and time are cleared.
- **Storage:** `identity_users` gains `suspended_at`, `suspension_reason`, `suspension_message` and `suspended_by_admin_id` (new migration), and the status check stays as it is.
- **Audit:** new actions `account.suspended` (with the reason) and `account.reactivated`, written by Identity's listener with the admin, IP and user
  agent.
- **Company members:** suspending the last company admin of a company is allowed; the company keeps running without an admin until a platform admin
  invites a new one (D4, `POST admin/companies/{id}/invitations`). The detail shows when the account is the company's only admin.

## Email to the user

**Decided by the user on 2026-10-13** (answer "alasan (opsional)", read as: an email is always sent and the admin chooses whether to tell the user
why). Two email templates (`account_suspended`, `account_reactivated`, in `id` and `en`, editable in D13 like the other templates) in the account's
`locale`. The suspension email says the account is suspended and how to contact the platform; when the admin filled `message_to_user`, it is
included. The internal `reason` is never sent. Sent through the queue after the transaction; the message is stored with the suspension and audited.
