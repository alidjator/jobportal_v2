# Phase 8: WhatsApp notices for time-critical events (designed 2026-10-19, not coded; coded after go-live)

Gap FG6 of `docs/analysis/actor-gap-analysis.md`: in Indonesia candidates read WhatsApp far more than email, and missed interviews and tests cost
the teams most. Today the channels are in-app, email and push (push deferred). Extends the Notification context (preference catalog,
`NotificationChannel`), JobSeeker (the `whatsapp` number of the profile), `phase-8-interview-reschedule.md` (reminders),
`phase-8-pre-employment-checks.md` and `self-hosted.md` (per-installation configuration). Coded in a coding session **after go-live**; the Meta
Business verification and the template approvals start now because they take weeks.

## Decisions

- 2026-10-19: WhatsApp goes through a **`WhatsAppGateway` port** with **two adapters**: the official **WhatsApp Business Cloud API (Meta)** and an
  **unofficial gateway** (WhatsApp Web automation behind an HTTP service); the installation chooses (decided by the user; see "Risk").
- 2026-10-19: only **time-critical utility notices** to job seekers: interviews, tests, offers and pre-employment checks; no job alerts,
  newsletters or marketing, nothing to company members (chosen by the user).
- 2026-10-19: **opt-in with a one-time code sent by WhatsApp**: the job seeker switches WhatsApp on and confirms the number with a code; off by
  default; changing the number switches it off (chosen by the user).
- 2026-10-19: **designed now, coded after go-live**; switched on per installation by `.env`, per company by the platform admin, with a global
  daily cap (chosen by the user).
- 2026-10-19: the schema of "Changes in short" and the rules of sections 1 to 6 are approved (decided by the user).

## Risk (reported to the user on 2026-10-19, kept by decision)

The unofficial adapter breaks WhatsApp's terms: the sending number can be banned without notice (every reminder stops, and the number can no
longer move to the official API), the WhatsApp Web session and the message contents pass through a service without a data-processing agreement
(UU 27/2022; `docs/legal-review.md` question 22), there are no approved templates and no reliable delivery status, and official notices cannot be
told apart from scam messages. The design therefore: keeps the adapter **off by default**, shows the risk on the admin settings page and in the
self-hosted documentation, never sends the one-time code through it unless the installation has no official adapter, and keeps the message
texts identical to the official templates.

## 1. Channel and preference

- `NotificationChannel::WhatsApp` (`whatsapp`). One preference key **`whatsapp_reminders`** for job seekers, channel `whatsapp` only, default off:
  "Kirim pengingat penting lewat WhatsApp". It can be switched on only with a verified number (422 `whatsapp-not-verified`).
- WhatsApp comes **in addition to** in-app and email; the email preference `application_update` keeps working on its own.

## 2. Number verification and opt-in

- The number is the profile's `whatsapp` (normalised to E.164, Indonesian numbers to `+62`; 422 `invalid-phone`).
- `POST me/whatsapp/verification` sends a 6-digit code (authentication template), valid 10 minutes, kept hashed in the cache; at most one per
  minute and 5 per day (429 `verification-limit`). `POST me/whatsapp/verification/confirm {code}`: at most 5 tries per code (422
  `invalid-code`); success sets `whatsapp_verified_at` and `whatsapp_opt_in_at` and switches `whatsapp_reminders` on.
- `DELETE me/whatsapp` switches it off (clears `whatsapp_opt_in_at`, keeps the verification). Changing the profile's `whatsapp` clears both.
- The privacy export carries the number, the verification and opt-in times.

## 3. What is sent

| Event (existing notification) | Template (utility, `id` and `en`) |
|---|---|
| Interview scheduled, rescheduled, proposal accepted | `onesis_interview_scheduled` |
| Interview cancelled | `onesis_interview_cancelled` |
| Interview reminder day before; 2 hours before (#29 section 6) | `onesis_interview_reminder` |
| Test invited; test reminder | `onesis_assessment_invited`, `onesis_assessment_reminder` |
| Offer sent; offer expiring | `onesis_offer_sent`, `onesis_offer_expiring` |
| Pre-employment check scheduled or rescheduled (#32) | `onesis_check_scheduled` |
| One-time code | `onesis_verification_code` (authentication) |

- Content: company, job, time in WIB, place or "online", and a link to the platform page; **never** a meeting link, salary, outcome or note text
  (the page shows them after sign-in). The language follows the user's locale.
- Sent on the queue `default` by a listener on the existing notifications, only when: the installation has a driver, the company's switch is
  on, the user's `whatsapp_reminders` is on with a verified number, and the daily cap is not reached.
- Template names live in config; `docs/deployment-checklist.md` lists them for approval in Meta Business Manager.

## 4. Gateway, delivery and replies

- Port `WhatsAppGateway::sendTemplate(phone, template, locale, params): providerMessageId`. Adapters: `MetaCloudWhatsAppGateway` (Graph API,
  `WHATSAPP_META_*`), `HttpWhatsAppGateway` for the unofficial service (`WHATSAPP_HTTP_URL`, `WHATSAPP_HTTP_TOKEN`; the template text is
  rendered locally), `NullWhatsAppGateway` (default, `WHATSAPP_DRIVER=null`).
- Delivery webhook `POST webhooks/whatsapp/meta` (signature `X-Hub-Signature-256` checked) updates the message log; the unofficial adapter
  leaves the status at `sent`.
- **Incoming replies are not read.** The templates say "Pesan ini dikirim otomatis. Balas lewat aplikasi ONESIS Career."
- A failed send is retried 3 times; the email already went out, so nothing else is needed.

## 5. Switches and limits

- `.env` `WHATSAPP_DRIVER` (`meta`, `http`, `null`) per installation; the settings page shows the driver and, for `http`, the risk notice.
- Company setting `notifications.whatsapp_enabled` (platform admin only, default off) for the company's notices; the one-time code does not
  depend on it.
- Platform setting `notifications.whatsapp_max_per_day` (default 2,000); over it nothing more is sent that day and the operations alert of #30
  emails once.
- The operations card gains `whatsapp {sent_today, failed_today, cap}`.

## 6. Message log and erasure

- Table `notification_whatsapp_messages`: `user_id`, `company_id` (null for the code), `template`, `status` (`queued`, `sent`, `delivered`,
  `read`, `failed`), `provider_message_id`, `error` (≤500), `sent_at`, `updated_at`. No phone number, no message text. Purged after 90 days;
  the account erasure deletes the user's rows.

## Changes in short

| Piece | Change |
|---|---|
| Migration | `jobseeker_profiles.whatsapp_verified_at`, `whatsapp_opt_in_at` (timestamps, null); `notification_whatsapp_messages` |
| Notification | `NotificationChannel::WhatsApp`; preference `whatsapp_reminders`; port `WhatsAppGateway` with Meta, HTTP and null adapters; listener on the notices of section 3 |
| API | `POST me/whatsapp/verification`, `POST me/whatsapp/verification/confirm`, `DELETE me/whatsapp`; `POST webhooks/whatsapp/meta` |
| Settings | `notifications.whatsapp_enabled` (company, admin only), `notifications.whatsapp_max_per_day` (platform) |
| `.env` | `WHATSAPP_DRIVER`, `WHATSAPP_META_TOKEN`, `WHATSAPP_META_PHONE_NUMBER_ID`, `WHATSAPP_META_APP_SECRET`, `WHATSAPP_HTTP_URL`, `WHATSAPP_HTTP_TOKEN` |
| Scheduler | purge of the message log (daily) |
| Errors | 422 `whatsapp-not-verified`, `invalid-phone`, `invalid-code`; 429 `verification-limit` |
| Legal | `docs/legal-review.md` question 22 |
| Front end | WhatsApp card in the notification settings with the code dialog; operations card counts; risk notice for the `http` driver |
