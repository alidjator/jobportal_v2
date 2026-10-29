# Phase 8: small account and public items (designed 2026-10-20)

Closes MG-A01, MG-A03 (FG7), MG-A11, MG-A12, MG-A13, MG-A14 and MG-C01 of `docs/analysis/multi-persona-gap-analysis.md`. Builds on `frontend.md` (11.4 sitemap, 11.7 address picker, J2, J5, J15, M2), `phase-8-activation.md` (the support settings), `phase-8-company-admin.md` section 3 (the retention) and `phase-8-provider-institutions.md` (section 8, in-house psychologists). Design only: nothing is coded.

## Decisions

- 2026-10-20 **MG-A01:** a light public feed of live jobs and of **active companies that have a live job**: id, slug, `updated_at` ([section 1](#1-mg-a01-the-sitemap-feed); user).
- 2026-10-20 **MG-A03 (FG7):** for go-live there is **no help queue**: a public `GET support-contact` gives the support email and WhatsApp number of the two settings; the in-house help request is postponed ([section 2](#2-mg-a03-help-channel); user).
- 2026-10-20 **MG-A11:** `PATCH me` changes the account language (`locale`) ([section 3](#3-mg-a11-account-language)).
- 2026-10-20 **MG-A12:** nothing to build in the API; the address picker must open a partial chain and a save that does not send the address never validates it ([section 4](#4-mg-a12-partial-addresses)).
- 2026-10-20 **MG-A13:** a list of active sessions and a sign-out per device; **no notice** of a new device (the device name comes from the client) ([section 5](#5-mg-a13-active-sessions); user).
- 2026-10-20 **MG-A14:** the job seeker is told only **after** the company erased the application data (`company_erased_at`); no promise of a future date, because the period waits for question 15 of the legal review ([section 6](#6-mg-a14-retention-notice); user).
- 2026-10-20 **MG-C01:** a psychologist changes the password while signed in at `PUT provider/me/password`, and the `/mitra` pages get forgot and reset ([section 7](#7-mg-c01-psychologist-password)).

## 1. MG-A01: the sitemap feed

- `GET sitemap/jobs?after_id=&per_page=` (public, `per_page` up to 5,000, default 1,000) returns `{data: [{id, slug, updated_at}], next_after_id}` for **live** jobs (the public rules: active company, not on hold, not taken down), ordered by id; `slug` is the public slug with the id (`PublicJobResource`). `updated_at` is the job's own last change (a publish, an edit, a reopen), so a closed job drops out.
- `GET sitemap/companies?after_id=&per_page=` the same for **active companies that have at least one live job**, `updated_at` = the later of the company's own change and the latest change of its live jobs.
- Cache: `Cache-Control: public, max-age=300` and an `ETag` (the `PublicCacheHeaders` middleware); the public limiter applies. No personal data and nothing from the frozen P12.
- **Tests:** only live jobs and the companies with one; a job taken down or closed leaves; pagination by `after_id` is complete and has no repeat; `updated_at` moves on an edit; no auth; headers.

## 2. MG-A03: help channel

- `GET support-contact` (public, cached 5 minutes) returns `{email, whatsapp}` from `platform.support_email` and `platform.support_whatsapp`; either is `null` while empty. P13, the footer and the account card show them; the activation emails already carry the same values.
- Not built (postponed with FG7): a help-request form, a queue for admins, an answer and a link to the account. It can come later without changing this endpoint.
- **Tests:** the values follow the settings; empty values are `null`; no auth.

## 3. MG-A11: account language

- `PATCH me` with `{locale: "id"|"en"}` (any signed-in account of `auth:user`, outside the terms gate like `GET me`; 422 for another value) updates `identity_users.locale` and answers with the current account (`CurrentUserResource`). The emails and notifications of the account already read this column, so they follow at once.
- The front end sends it when a signed-in user switches the site language (the cookie stays the interface language for visitors). The admin console has its own language and is not part of this.
- **Tests:** the value changes and the next email uses it; an invalid value is 422; the response is the account; a psychologist and a company member use it too.

## 4. MG-A12: partial addresses

- No API change. `UpdateMyProfileRequest` already treats every address field as `sometimes`, so a save that carries none of them never validates the address. The page (`frontend.md` 11.7) opens with the chain as stored (province, city, subdistrict, village, postal code, each may be empty) and sends only the fields the user changed.
- **Test to add:** `PATCH me/profile` with only `headline` succeeds for an imported profile whose lower levels are empty, and a deliberate change of the city with no subdistrict is accepted.

## 5. MG-A13: active sessions

- `GET me/sessions` returns the account's tokens: `{id, device_name, created_at, last_used_at, expires_at, current}`, newest use first; `current` marks the token of the request. `DELETE me/sessions/{id}` revokes one token of the **caller's own account** (204; another account's id is 404); revoking the current one signs the device out. `last_used_at` is the value Sanctum already keeps (written at most every few minutes, `TokenLastUsedTest`).
- No notice of a new device, no fingerprint: the device name is sent by the client and cannot tell devices apart. The admin console's own list is not built now.
- **Tests:** the list shows only the caller's tokens with `current`; a revoked token stops working; another account's id is 404; the current token can be revoked; a psychologist's tokens are listed at `me/sessions` as well.

## 6. MG-A14: retention notice

- `GET me/applications/{id}` and the list row gain `company_erased_at` (null until the company's retention erased the application, `recruitment_applications.company_erased_at` of `phase-8-company-admin.md` section 3). J5 then says "Perusahaan telah menghapus data lamaran ini sesuai kebijakan retensinya"; nothing is said before.
- **Depends on #26** (the column and the nightly erasure): until it is coded the field is absent. No new notification (the retention design already says none).
- **Tests:** null before the erasure, the timestamp after, both on the detail and the list; the history (job, company, stage, dates) stays.

## 7. MG-C01: psychologist password

- `PUT provider/me/password` is the use case of `PUT me/password` (`ChangePassword`) under the provider routes, outside the terms gate like the other provider routes; the wrong current password counts in the sign-in throttle of the account, and the other tokens are revoked.
- The `/mitra` application (M2) gets a forgot page and a reset page that call the shared `POST auth/password/forgot` and `POST auth/password/reset` (the code is sent by email, as for a job seeker; `RequestPasswordReset` already works for a psychologist). After a reset the psychologist signs in at `provider/auth/login` and passes the second factor as always.
- **Tests:** change with the right and the wrong current password; the others are signed out; forgot, reset, then provider sign-in works and asks for the second factor; a suspended psychologist cannot reset.

## Open points

None.
