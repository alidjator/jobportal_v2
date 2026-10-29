# Phase 2, step 6: chat with attachments and bulk messages (J9 and the chat half of R5, designed 2026-10-08)

New bounded context `Messaging` (`src/Messaging`, tables `messaging_*`). It asks the other contexts through `Shared\Application` interfaces (company membership, which applications a seeker has, which members may see them),
as the other contexts do. Spec in `decisions.md`: J9 (attachments PDF/JPG/PNG, default 5 MB, admin-configurable, content-based type checks; only the **recruiter's** online status is visible to job seekers;
a recruiter can start a conversation any time, a job seeker only with a company they applied to) and R5 (bulk chat message, max 50 recipients, admin-configurable, queue `bulk`).

## Confirmed by the user (2026-10-08)

- **A conversation is a company + job seeker pair** (one thread per pair, whatever the number of applications). A recruiter can also start it with a candidate who has not applied.
- **Real time with Reverb now.** The package, broadcasting config, channel authorization and broadcast events are coded in this step; tests use the log/null broadcaster. The Reverb process (supervisor) and the nginx
  WebSocket proxy are **deployment tasks for the user** (root-owned); until then the REST endpoints work and clients can poll.
- **Attachments: up to 5 per message** (PDF, JPG, PNG; 5 MB each by default, setting; types checked by content).
- **Email:** an in-app notification at once; an email only when the message is still unread **15 minutes** later, and at most one email per thread until the receiver reads it; it follows a notification preference.
- **Recruiter online status** comes from a Reverb presence channel, nothing is stored; it shows offline until Reverb is deployed.
- **Company side visibility:** everyone who may see at least one of the seeker's applications (company admins and recruiters assigned to a job the seeker applied to). A thread started with a candidate who has not applied is visible to the starter
  and the company admins. Replies show the sender's name.
- **Delete, no edit:** a sender can delete their own message within 15 minutes (soft: shown as "message deleted", its files removed); messages cannot be edited.
- **A job seeker may start a thread with a company where they have any application, whatever its status** (a rejected seeker can still ask for feedback). The company can close a thread: the seeker then cannot send; the company can reopen it.
- **Bulk message:** recipients are the `application_ids` of **one job**, at most 50 (setting), as the bulk move of step 4; each recipient gets the message in their own company thread (created when missing); the answer reports `sent` or `skipped` per item.
  Attachments are allowed with the same rules and are **stored once**: the messages share the stored files, so 50 recipients do not mean 50 copies.
- **Anti-spam:** a daily message cap per company (setting, default 500, counted across single and bulk sends; over it is 429 `daily-limit`), plus a throttle of 60 sends per member per minute.

## Details (approved by the user, 2026-10-08)

- **Tables:** `messaging_conversations` (company_id, job_seeker_user_id, started_by_user_id, closed_at, closed_by_user_id, last_message_at; unique company + seeker), `messaging_messages` (conversation_id, sender_side `company|seeker`,
  sender_user_id, body up to 5,000 characters or null, deleted_at, created_at; at least a body or a file), `messaging_attachments` (name, path, mime, size, owner_user_id) joined to messages by `messaging_message_attachments`
  (a stored file is deleted when its last message link goes), `messaging_conversation_reads` (conversation_id, user_id, last_read_message_id). Migrations from `000083`.
- **Settings (admin, with `SettingsTest` updated):** `messaging.max_attachment_bytes` (5 MB), `messaging.max_bulk_recipients` (50), `messaging.max_daily_company_messages` (500). The 5-files-per-message limit is a constant.
- **Endpoints** (cursor pagination, newest first): company side `GET company/conversations`, `GET company/conversations/{id}/messages`, `POST company/conversations/{id}/messages` (multipart: `body`, `files[]`),
  `POST company/conversations` (start with a seeker: `user_id`, first message), `POST company/conversations/{id}/read`, `POST company/conversations/{id}/close|reopen`, `DELETE company/messages/{id}`,
  `POST company/jobs/{id}/applications/bulk-message`; seeker side `GET me/conversations`, `GET me/conversations/{id}/messages`, `POST me/conversations/{id}/messages`, `POST me/conversations` (company id, first message),
  `POST me/conversations/{id}/read`, `DELETE me/messages/{id}`; downloads `GET .../messages/{id}/attachments/{attachmentId}` (access checked, private storage); `GET .../unread-count`.
- **Access:** the same company membership rules as the pipeline (403 for a non-member or a suspended company, 404 for a thread the member may not see); an unverified email cannot send (the account-state rule in `architecture.md`).
- **Unread:** the read pointer is per user, so each company member has their own unread count on a shared thread.
- **Who is notified on the company side:** the member who last wrote in the thread, else the one who started it, else the members assigned to the seeker's latest application; the seeker side is the seeker. In-app is one notification per thread, updated while unread.
- **Reverb:** private channel `conversations.{id}` (authorized as the access rule above) carrying `MessageSent`, `MessageDeleted` and `MessagesRead`; a presence channel `company-presence.{companyId}` that **only company members can join**
  (the seeker is not a member, so the seeker never appears to the recruiter). A seeker's view of "recruiter online" is computed by the server from the presence channel and returned as `company_online` on the conversation
  (false when Reverb is unreachable). The attachments are never broadcast, only their metadata.
- **Erasure:** anonymising the seeker deletes the files of their messages and the bodies of their messages (rows stay for the company's count, shown as "Deleted user"); a removed company member keeps their messages with the name shown from the
  company record.
- **Scheduled commands:** `messaging:send-unread-emails` every minute (the 15-minute rule), `messaging:purge-...` not needed. Emails go on the `default` queue; bulk sends run in the request for up to 50 recipients (as step 4), the emails and
  notifications on the `bulk` queue.
- **Verification note:** the server-side presence lookup (Reverb's HTTP API) is the one piece that needs a live server to verify; it is coded behind an interface with a fake for the tests.

## Coded (2026-10-08, checked and committed)

- `laravel/reverb` installed (`composer require -W`: this lowered guzzle 8.2.0 to 7.15.5, both allowed by Laravel; no project code uses Guzzle directly). `config/broadcasting.php` and `config/reverb.php` added; allowed origins come from `REVERB_ALLOWED_ORIGINS`
  (empty refuses every browser) and clients cannot send events. `.env.example` lists the `REVERB_*` names (no values). `BROADCAST_CONNECTION` stays `log` until the user deploys Reverb.
- Migrations `000083` to `000086` (conversations, messages, attachments with the message link table, read states). Ran on the test and the real database.
- Context `Messaging`. Use cases are side-agnostic where the rules are the same (`ListConversations`, `SendMessage`, `DeleteMessage`...), decided by the caller's kind; routes exist under `company/` and `me/`.
- Choices made while coding: a closed conversation refuses writing from **both** sides until reopened; the unread reminder email goes to whoever was told (job seeker, or the company-side member) and the read row holds `unread_since` and `emailed_at`;
  a message sent also moves the sender's own read pointer; the daily cap counts company messages per Jakarta day (setting is overridable per company, as the bulk limit); bulk items skipped with a code: `application-not-found`, `duplicate-recipient`,
  `daily-limit`, `recipient-unavailable`, `conversation-closed`; a company member starting a thread with an existing thread they may not see gets 409 `conversation-exists`; erasure of a job seeker clears only seeker-side texts and files.
- Shared contract changes: `ApplicantRelations` (new, Recruitment), `JobDirectory::assignedJobIds`, `ApplicantContacts::isActiveJobSeeker`, `InAppNotifier::refresh` (updates the unread notification of the same conversation).
- Tests: `ChatTest`, `ChatNotificationsTest`, `BulkMessageTest` (37 tests; full suite 1534).
- Deployment tasks for the user: run Reverb under a process manager, proxy the WebSocket path in nginx, raise `client_max_body_size` and PHP `upload_max_filesize`/`post_max_size` to at least 5 files of 5 MB, run migrations `000083` to `000086` on the real
  database, then `identity:sync-permissions` is not needed (no permission added).

Gigs (2026-10-29, `gigs.md` 13.2, not coded): a job seeker may also start a thread with a company where they have a gig proposal or engagement;
members with `gigs.order` see such a thread.

## Starting a conversation from the front end (decided 2026-10-26)

`POST company/conversations` names the job seeker by exactly one of `user_id`, `candidate` (the slug of a candidate the search finds) or `application_id` (an application the member may see; another company's is a 404), so the front end never needs an account id (C7 and C12 "Kirim pesan"). Same pattern as `POST company/talent-pool`. Tests: `ChatTest`.
