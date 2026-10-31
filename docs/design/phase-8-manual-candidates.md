# Phase 8: candidates from outside the platform (designed 2026-10-19, API coded 2026-10-30 behind a switch)

Gap FG9 of `docs/analysis/actor-gap-analysis.md`: HR teams, the internal tenants above all, still receive CVs by email, at walk-in interviews,
through referrals and at job fairs without a sign-up. Today a team can only work with people who hold an account and apply themselves (or who
are findable and invited, `phase-3-invitations.md`). Extends the JobInvitation context and `phase-1-applications.md` (sources). Coded in a
coding session, after `docs/legal-review.md` question 20.

## Decisions

- 2026-10-19: the team **invites a person by email** to a job and **may attach the CV** it received; the CV is **team-only** and **deleted after
  30 days** when the person does not claim the invitation; the person signs up or signs in, agrees, and then applies; the CV becomes theirs
  (chosen by the user).
- 2026-10-19: the schema of "Changes in short" and the rules of sections 1 to 4 are approved (decided by the user): `applications.manage` on a
  live job, the daily invitation cap, the same answer whether or not the email has an account, a claim from an account with another email,
  audited CV reads, at most 3 resends (one a day), nothing in the pipeline or analytics before the claim.

## 1. Inviting

- **Endpoint:** `POST company/jobs/{id}/email-invitations` (multipart): `name` (1 to 150), `email`, `channel` (`email_cv`, `walk_in`,
  `referral`, `job_fair`, `other`), `note?` (to the person, at most 1,000, link rule of `phase-8-interview-calendar.md` section 6), `cv?` (PDF,
  checked by content, at most 5 MB). Needs `applications.manage` on a **live** job (422 `job-not-live`).
- **Limits:** one open invitation per job and email (409 `already-invited`); the daily cap `invitations.max_per_day` counts these too; an address
  on the platform's suppression list is refused (422 `email-suppressed`).
- **No account lookup shown:** the answer is the same whether or not the email belongs to an account, so the team cannot test addresses. When the
  email belongs to a person who already applied to that job, the person gets the invitation anyway and the claim ends in `already-applied`.
- **Email** `invitation.email_received` (essential for the invitation itself, not marketing; still honours suppression): company, job, the note,
  "Perusahaan menerima CV Anda melalui {channel}", the link with a token (stored hashed, valid 30 days), and a "Bukan saya / tolak" link.

## 2. The team's view before the claim

- `GET company/jobs/{id}/email-invitations` (who may see the job): name, email, channel, note, status (`sent`, `opened`, `claimed`, `declined`,
  `expired`, `cancelled`), invited by, dates, `has_cv`.
- `GET company/email-invitations/{id}/cv` (`applications.view` on the job; audited `invitation.cv_viewed`): the attached CV, until the claim or
  the deletion. No other data of the person exists before the claim.
- `POST company/email-invitations/{id}/resend` (once a day, at most 3 times) and `POST company/email-invitations/{id}/cancel` (deletes the CV at
  once).
- Shown in the pipeline header as "Menunggu kandidat (N)"; never in the pipeline columns, the counts or the analytics until applied.

## 3. The person's claim

- `GET email-invitations/{token}` (public): company, job title, note, channel, whether the link is still valid. `POST
  email-invitations/{token}/decline` deletes the CV at once and ends the invitation (`declined`); the team sees only the status.
- **Claim** (signed in, verified email): `POST me/email-invitations/{token}/claim {agree: true}` links the invitation to the account (the
  account email may differ from the invited one: the token proves receipt) and answers the job and the CV. The front end then opens the normal
  apply form with **"Gunakan CV yang dikirim ke perusahaan"** offered: choosing it copies the file into the person's resumes; not choosing it
  deletes the team's copy. The application comes from `POST jobs/{id}/applications` with the new source **`manual`** and the channel kept on the
  invitation (`claimed`, `application_id`).
- An unverified or new account follows the usual sign-up and verification first; the token survives that round trip in the front end.

## 4. Deletion and erasure

- `jobinvitation:purge-email-invitations` daily: an invitation `sent`, `opened` or `expired` older than 30 days loses its CV file and its name
  and email (row kept as `expired` for the counts); `declined` and `cancelled` lose them at once.
- The person's later account erasure follows the application rules; the invitation row keeps no personal data after the claim.

## Changes in short

| Piece | Change |
|---|---|
| Migration | `jobinvitation_email_invitations`: `company_id`, `job_id`, `name` (null after purge), `email` (null after purge), `channel`, `note`, `token_hash`, `cv_path`, `cv_name`, `status`, `invited_by_user_id`, `claimed_by_user_id`, `application_id`, `opened_at`, `responded_at`, `expires_at`, `resent_count`, `last_sent_at`, timestamps; unique open (`job_id`, lower `email`) |
| Recruitment | `ApplicationSource::Manual`; analytics source report gains it |
| API | company: create, list, CV, resend, cancel; public: show, decline; job seeker: claim |
| Scheduler | `jobinvitation:purge-email-invitations` daily |
| Audit | `invitation.email_sent`, `invitation.cv_viewed`, `invitation.email_cancelled` (no name or email in the metadata) |
| Errors | 409 `already-invited`, `invitation-closed`; 422 `job-not-live`, `email-suppressed`, `invalid-file` |
| Legal | `docs/legal-review.md` question 20 |
| Front end | "Tambah kandidat" on the job page; "Menunggu kandidat" list; the claim page; "Gunakan CV yang dikirim ke perusahaan" in the apply form |

## Coded 2026-10-30 (API and front end; switched off)

Built as designed, **switched off** (the user chose this over waiting for question 20): setting `invitations.email_enabled` is `false` until legal answers. While it is off the company
side answers 403 `feature-disabled` and the person's side answers 404, so the links of a test run do nothing in production.

- **Migration** `2026_10_30_000300`: `jobinvitation_email_invitations` as in "Changes in short" (partial unique index on the open invitation per job and lower-case email; the token kept as a
  SHA-256 hash; the `applications.source` check gains `manual`).
- **Settings:** `invitations.email_enabled` (boolean, false) and `invitations.email_claim_url` (text, `https://karir.satu.solutions/undangan-lamaran/{token}`; the decline link is the same page with `?tolak=1`).
  The daily cap `invitations.max_per_day` counts these invitations too.
- **Permissions:** send, resend, cancel need `applications.manage`; the list and the CV need `applications.view`; both need a job the member sees (404 otherwise).
- **Endpoints:** `GET/POST company/jobs/{id}/email-invitations` (POST multipart), `GET company/email-invitations/{id}/cv`, `POST company/email-invitations/{id}/resend|cancel`; public
  `GET email-invitations/{token}` and `POST email-invitations/{token}/decline` (throttled); job seeker `POST me/email-invitations/{token}/claim {agree}`, `.../use-cv` (copies the CV into the
  resumes, answers `resume_id`) and `.../discard-cv`. The claim needs a job seeker with a verified email (409 `email-not-verified`).
- **Interpretations:** the CV is team-only while the invitation is open (after a claim, a refusal or a cancel the team has no CV); a claimed invitation's copy of the CV is deleted when the person
  chooses, when they apply, or 30 days after the claim. `resend` needs the 24 hours since the last send and gives a new token (the old link stops working); the sixth state `expired` is set
  by the purge. A claimed person's application takes the source `manual` whatever the client hinted.
- **Purge:** `jobinvitation:purge-email-invitations`, daily 04:00.
- **Audit:** `invitation.email_sent`, `invitation.cv_viewed`, `invitation.email_cancelled`, without name or email.
- **Email:** template `invitation.email_received` (editable like the others), sent on the `default` queue in the language of the request of the member.
- **Front end (2026-10-30):** Workspace tab "Menunggu kandidat" on the job's invitations page (`EmailInvitationsTab`, `EmailInvitationDialog`; list needs `applications.view`, adding and cancelling `applications.manage`; the tab says so while the feature is off), Career page `/undangan-lamaran/{token}` (claim, decline, choose the CV). Not tried in a browser.
