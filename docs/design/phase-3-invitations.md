# Phase 3, step 3: job invitations (R10, designed 2026-10-09)

A company member invites a candidate they found (see `phase-3-candidate-search.md`) to apply to one of the company's live jobs. Spec in `decisions.md` (R10): the invitation is tied to a job; status sent, viewed, applied, declined;
**default 50 invitations per day per company** (admin global setting, per-company override); **one invitation per candidate per job**; a candidate who already applied cannot be invited.

## Confirmed by the user (2026-10-09)

- **Note:** an invitation may carry an optional personal note (plain text, up to 1,000 characters). The candidate sees it in the invitation and in the email; without one the standard text is used.
- **Decline:** the candidate may give an optional reason (up to 500 characters) that the inviting member can read.
- **Expiry:** no validity period of its own. An invitation that is still `sent` or `viewed` reads as `expired` while its job is not live (closed, expired, taken down or not published) and works again if the job goes live again.

## Details to approve (proposed, not confirmed yet)

- **Context:** new bounded context `JobInvitation` (`src/JobInvitation`, tables `jobinvitation_*`), talking to the others through Shared contracts and events like `JobAlert`: Job (`JobDirectory`: the member sees the job, the job is live), JobSeeker (a new contract
  `CandidateDirectory`: is the candidate findable by slug, their name), Recruitment (a job-level `hasApplied` and the event `ApplicationSubmitted`), Notification and Platform (in-app, email template, settings).
- **Table `jobinvitation_invitations`:** company, job, candidate (plain columns, no foreign keys to other contexts), `invited_by_user_id`, `note`, `status` (`sent`, `viewed`, `applied`, `declined`), `decline_reason`, `viewed_at`, `responded_at`, `application_id`, timestamps;
  unique (job, candidate); indexes for the two lists. `expired` is never stored: it is derived from the job.
- **Who and what:** a member who may see the job (a company admin: every job of the company; a recruiter: the jobs assigned to them; anyone else 404) of an active company invites a candidate who is findable (opted in, active account, complete profile) to a **live** job.
  Errors: 404 job or candidate; 422 `job-not-live`; 422 `already-applied` (the candidate has an application on that job, whatever its status); 409 `already-invited`; 429 `invitation-limit`.
- **Daily cap:** setting `invitations.max_per_day` (default 50, range 1 to 1,000, overridable per company), counted per company and Jakarta day, taken atomically in the same way as the daily chat quota; an invitation that fails does not use a slot.
- **Endpoints (company):** `POST company/jobs/{id}/invitations` `{candidate (slug), note?}` (201), `GET company/jobs/{id}/invitations` (offset paging, `filter[status]`, newest first; the candidate's name and slug, status, note, `viewed_at`, decline reason, the inviting member).
  **Endpoints (job seeker):** `GET me/invitations` (paging, `filter[status]`, including `expired`), `GET me/invitations/{id}` (the first opening turns `sent` into `viewed`), `POST me/invitations/{id}/decline` `{reason?}` (409 `invitation-closed` when applied, declined or expired).
  The candidate sees the company, the job title, the note and the status, never the member's name. The candidate applies with the normal `POST jobs/{id}/applications`.
- **Applied:** a listener on `ApplicationSubmitted` marks the invitation of that candidate and job `applied` (also after a decline: the later fact wins) and stores the application id. A withdrawn application stays `applied`. The application itself carries no invitation flag.
- **Notifications:** in-app `invitation.received` to the candidate and an email (template `invitation.received`, preference `invitation`, email on by default, sent only to a verified address, queue `default`); in-app `invitation.declined` to the member who invited, with the reason when given. Nothing is sent when the candidate applies (the application already notifies the job's assignees).
- **Contact data:** an invitation does not open the candidate's contact data; they appear only through an application, as before.
- **Erasure:** anonymising the candidate deletes their invitations; the member who invited stays on the rows of others.
- **Tests:** access by role and assignment, each error, the cap and its override, the note and reason limits, the status flow (viewed once, applied by an application, declined, expired and back), the lists and filters, the notifications and the email, erasure.

## Coded (2026-10-09, checked with 1600 tests, committed)

Context `src/JobInvitation` (Domain, Application, Infrastructure, Interface), provider `JobInvitationServiceProvider` (in `bootstrap/providers.php`), migration `000091` (`jobinvitation_invitations`), setting `invitations.max_per_day`, template `invitation.received`, preference `invitation`.
Tests: `tests/Feature/JobInvitation/InvitationTest.php`; `SettingsTest`, `EmailTemplatesTest` and `NotificationPreferencesTest` list the new setting, template and preference. The details above were coded as proposed; what was decided while coding:

- **`expired` is a flag, not a status value.** Every row carries `status` (the stored one: `sent`, `viewed`, `applied`, `declined`) and `expired` (true while the invitation is still open and its job is not live). The list filter works on the stored status; there is no `filter[status]=expired`, because liveness belongs to Job and cannot be put in the SQL of this context without copying its rule.
- **New Shared contracts:** `CandidateDirectory` (provided by JobSeeker: `findable(slug)` and `refs(userIds)`), `JobDirectory::liveJobIds` (Job) and `ApplicantRelations::hasAppliedToJob` (Recruitment).
- **The daily cap is read before the row is written**, like the chat quota, so two requests at the very same moment may overshoot it by one. The `already-invited` check comes first, so a refused invitation never uses a slot.
- **Declining needs a live job** (otherwise 409 `invitation-closed`, as for an answered invitation); opening an expired invitation still works and marks it `viewed`.
- **Member names on the company side** come from `CompanyMemberAccounts::summaries`; the candidate never gets the member (`invited_by` is absent from their responses).
