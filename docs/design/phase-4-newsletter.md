# Newsletter (designed 2026-10-10)

A public email list that people join with an email address only (no account), and an admin who sends issues to it. The legacy module (`Modules/Newsletter`) has table `emails` (`id`, `email`, timestamps), a public `POST /subscribe`, a confirmation link, and for admins a list, a delete and a bulk send.

## Legacy problems that are not carried over

- The confirmation token lives in the **session**, so the link only works in the browser that subscribed. The new token is stored (hashed) with an expiry.
- **No way to unsubscribe.** Every mail now carries an unsubscribe link and the `List-Unsubscribe` headers.
- Deleting a subscriber had no permission check; the new routes sit behind `auth:admin` and `can:newsletter.manage`.
- The bulk send had no history, no guard against sending twice and took raw HTML from the admin. The new send is a stored issue with counts, sent once, written in Markdown.
- `unique:emails,email` told a visitor whether an address was already on the list. The new subscribe answers the same way for every address.

## Decided (2026-10-10, user)

1. **Sending:** a stored **issue** (subject, Markdown body) with a history. The admin can send a test to their own email, then send it to every confirmed subscriber through the `bulk` queue. An issue is sent once; a sent issue cannot be edited, deleted or sent again. The issue keeps its status (`draft`, `sending`, `sent`), the number of recipients, delivered and failed.
2. **Body format:** **Markdown**, rendered into the standard email layout like every other template. No raw HTML.
3. **Recipients:** every **confirmed** subscriber. One list, no segments.

## Proposed (no decision needed unless you disagree)

- **Context:** a new `Newsletter` bounded context (`src/Newsletter`), tables prefixed `newsletter_`.
- **Table `newsletter_subscribers`:** `id`, `email` (varchar 254, stored lower case, unique), `status` (`pending`, `confirmed`, `unsubscribed`), `locale` (`id` or `en`, from the request language), `confirm_token_hash` (sha-256, null once used), `confirm_expires_at` (3 days), `confirmed_at`, `unsubscribed_at`, timestamps. An `unsubscribed` row is kept on purpose: it is the suppression list, so an import or a careless re-add never writes to someone who left.
- **Table `newsletter_issues`:** `id`, `subject` (150), `body` (Markdown, up to 20,000 characters), `status`, `created_by_admin_id`, `sent_by_admin_id`, `started_at`, `finished_at`, `recipients`, `delivered`, `failed`, timestamps. Keys to `identity_admins` (RESTRICT).
- **Table `newsletter_deliveries`:** `issue_id`, `subscriber_id`, `status` (`queued`, `sent`, `failed`), `sent_at`, unique (`issue_id`, `subscriber_id`). The unique key makes a retried or repeated dispatch send each person at most once. The row of a deleted subscriber is deleted with it (cascade); counts on the issue stay.
- **Subscribing (double opt-in):** `POST newsletter/subscribers` `{email}` always answers 202 with no body detail. A new address becomes `pending` and gets the `newsletter.confirm` email (a template in the email catalog, editable by admins like the others); a `pending` one gets the email again at most once every 10 minutes with a fresh token; a `confirmed` one gets nothing; an `unsubscribed` one becomes `pending` again and must confirm again. Limits: 5 per minute per client address and 3 per address per hour.
- **Confirming:** `POST newsletter/confirm` `{token}`. The confirmation link is built from the template `NEWSLETTER_CONFIRM_URL` (`https://<client site>/newsletter/confirm?token={token}`), the client page posts the token. A wrong, used or expired token is 422 `newsletter-token-invalid`.
- **Unsubscribing:** the token is stateless, `{subscriber id}.{HMAC-SHA256 of the id with the application key}`, so it never expires and needs no column. `POST newsletter/unsubscribe` `{token}` for the client page (link from the template `NEWSLETTER_UNSUBSCRIBE_URL`), and `POST newsletter/unsubscribe/{token}` for the one-click header (`List-Unsubscribe` and `List-Unsubscribe-Post`, RFC 8058). Both are idempotent and answer 204; a bad token is 422 `newsletter-token-invalid`.
- **Admin (`newsletter.manage`, needs `identity:sync-permissions`):**
  - `GET admin/newsletter/subscribers` (filters `q`, `status`; the response meta carries the counts per status), `DELETE admin/newsletter/subscribers/{id}` (erasure on request; audited).
  - `GET|POST admin/newsletter/issues`, `GET|PUT|DELETE admin/newsletter/issues/{id}` (PUT and DELETE only while `draft`), `POST admin/newsletter/issues/{id}/test` (sent to the admin's own email, not counted), `POST admin/newsletter/issues/{id}/send` (202; 409 `newsletter-issue-not-draft`; 409 `newsletter-no-recipients`).
- **Sending flow:** `send` marks the issue `sending`, then a queued job walks the confirmed subscribers in chunks, inserts the delivery rows and queues one `bulk` job per delivery; each job sends the mail (the issue rendered in the subscriber's language layout, the unsubscribe link and headers added), marks the delivery `sent`, or `failed` after 3 tries (growing delay, like the alert digest). When no delivery is `queued` the issue becomes `sent` with its counts.
- **Cleanup:** a daily command `newsletter:purge-pending` deletes `pending` rows older than their confirmation expiry plus 7 days.
- **Audit:** `newsletter.issue.sent` and `newsletter.subscriber.deleted`, written by a Platform listener like the other contexts.
- **Rendering:** the issue is rendered with the platform's Markdown-to-email renderer. If it only renders registered templates, a small port in `Shared` for an ad hoc Markdown body is added when coding (decided there, no behaviour change for templates).
- **Settings (env):** `NEWSLETTER_CONFIRM_URL` and `NEWSLETTER_UNSUBSCRIBE_URL`, templates with `{token}` (like `COMPANY_INVITATION_URL`). With the confirm URL empty, subscribing answers 503 `newsletter-unavailable`.
- **Legacy import:** the rows of `emails` become `confirmed` subscribers (the legacy row only existed after the link was clicked), `confirmed_at` = the legacy `created_at`, locale `id`; the address is lower-cased, an invalid or duplicate one is skipped and reported. A separate importer (`newsletter-subscribers`), run on real data only with approval.
- **Tests:** subscribe flow for each status, same answer for every address, token expiry and reuse, limits, stateless unsubscribe (both routes, tampered token), suppression kept, issue lifecycle (draft edit and delete, test mail, send once, no recipients, double send refused), deliveries unique, retries, counts, permission (`newsletter.manage`), audit entries, purge command, importer.

## Not in this phase

- Segments, scheduled sends, open and click tracking, templates for issues, importing subscribers from a file, subscribing a signed-in account's own email from the profile.
