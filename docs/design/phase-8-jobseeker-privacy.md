# Phase 8: personal data export, profile reminders, blocked companies and activity status (designed 2026-10-14, not coded)

Gaps found from the job seeker's point of view (Mekari Talenta has no job seeker accounts, so it offers no benchmark here), chosen by the user from
the advice of 2026-10-14. Sections 1 and 2 are **before go-live**: the export answers UU 27/2022 (PDP) Articles 7 and 13, and the reminder helps the
imported accounts. Not taken now: an embeddable job widget for company websites, a profile preview (front end only, from `me/profile` and the
visibility rules) and archiving applications. Extends `phase-1-jobseeker.md` (JobSeeker), `phase-3-candidate-search.md`, `phase-3-invitations.md`
and `phase-3-matching.md`. Coded in a coding session.

## Decisions

- 2026-10-14: a job seeker can **download a copy of their personal data** (chosen by the user).
- 2026-10-14: **reminders to complete the profile**, by email, limited and switchable (chosen by the user).
- 2026-10-14: a job seeker may **block companies** from finding them (chosen by the user).
- 2026-10-14: candidate search shows a rounded **last active** status and an **open to offers** switch (chosen by the user).

## 1. Personal data export (before go-live)

- `POST me/data-export` (job seekers; 202): queues the build (queue `default`). At most one per 24 hours (429 `data-export-limit` with
  `Retry-After`); a running one answers 409 `data-export-running`. `GET me/data-export` gives the latest: `status` (`building`, `ready`,
  `expired`, `failed`), `ready_at`, `expires_at`, and a download link while ready.
- **Archive:** one ZIP on the private disk (`exports/users/{user}/{uuid}.zip`), deleted after **24 hours** by the daily cleanup, downloaded through
  `GET me/data-export/download` (signed-in owner only, `Cache-Control: no-store`). Contents:
  - `data.json` with a `version` and one key per context, each built by that context through a new Shared port `PersonalDataExporter` (one
    registry, like the erasure steps): account (email, name, type, created and last sign-in dates, terms accepted with versions and times, consent
    for campaigns), profile with experiences, education, skills, languages, links, visibility switches and blocked companies; resumes (names);
    applications with job, company, stage history as the job seeker sees it, the rejection reason code, the withdrawal note and reason, screening
    answers, the profile snapshot, interviews (times, format, the job seeker's own answers), offers (terms, answers, acceptance evidence),
    assessment invitations and the explanations the job seeker may read; job invitations; saved jobs; job alerts; conversations with their
    messages (both sides of the job seeker's own threads); company reviews; notification preferences; the list of companies that viewed the
    profile; training enrolments; the talent pool consents.
  - `files/`: the resume files, the generated CVs still stored, the avatar, the job seeker's own chat attachments and drawings uploaded in tests.
  - `README.txt` in Indonesian explaining each part.
- **Left out (data of the company or the psychologist, not of the job seeker):** team notes and ratings, scorecards, the team's rejection and
  withdrawal notes, talent pool notes, restriction reasons, the company's psychological report text and the psychologist's review notes,
  integrity signals in detail (the tier stays). This boundary is `docs/legal-review.md` **question 14** (with question 9 on talent pool notes).
- **Audit:** `privacy.data_exported` (user, size; no content). The ready notice is in-app and email (template `account.data_export_ready`,
  always sent: it answers a request).
- An account in its deletion grace period may still export; an anonymised one cannot (there is no owner).

## 2. Reminders to complete the profile (before go-live)

- **When:** the profile is not complete for applying (`completeness.missing` not empty), the email is verified, and the account is a job seeker's.
  First reminder 1 day after the first sign-in (for imported accounts: after the first sign-in following the import), then every 7 days, **at most 3
  in all**; none once the profile is complete. A daily task (`job-seeker:send-profile-reminders`, 10:00 Jakarta) picks the due accounts.
- **Content:** what is missing in plain words ("nomor telepon", "kota", "pengalaman atau pendidikan", "resume") with a link to the profile, plus the
  number of live jobs matching their alerts or recommendations when there are any.
- **Preference:** new `profile_reminder` (email, on by default) in the notification preferences; the email has the usual one-click opt-out.
- **State:** `jobseeker_profiles.reminders_sent` (0 to 3) and `last_reminded_at`; reset never (three is the lifetime cap).
- In-app: none (the dashboard already shows `completeness`).

## 3. Blocked companies

- `GET me/blocked-companies`, `PUT me/blocked-companies/{companyId}` (204), `DELETE me/blocked-companies/{companyId}` (204). At most **20**
  (422 `blocked-companies-limit`). A company that does not exist or is hidden is 404. The front end picks a company with the public company search.
- Table `jobseeker_blocked_companies` (`user_id`, `company_id`, `created_at`; unique pair; FK to `company_companies` CASCADE, listed in
  `CrossContextForeignKeysTest`).
- **Effects, only for a blocked company:** the person is left out of its candidate search and recommendations; opening the profile by slug is 404;
  a job invitation is 404 `candidate` (as for anyone not findable); its members cannot start a chat with the person when no application exists.
  The person may still apply to that company: an application opens the applicant profile as usual (the person chose to).
- **Existing data:** blocking does not remove the person from the company's talent pool by search (the entry turns `available: false` for that
  company); an entry resting on application consent (`phase-8-talent-pool.md`) stays.
- The company is never told it was blocked. Erasure deletes the rows.

## 4. Last active and open to offers

- **Last active:** new column `identity_users.last_active_on` (date, Jakarta), set by a middleware on authenticated job seeker requests at most
  once a day (it writes only when the stored date is older than today). Imported accounts start empty.
- **Shown** in candidate search rows, the candidate profile and recommended candidates as a bucket only: `active_within: 7d | 30d | 90d | older`
  (null when unknown). Never the date. The job seeker sees the same bucket on their own profile with a note that companies see it.
- **Open to offers:** new column `jobseeker_profiles.open_to_offers` (boolean, default false), set with `PATCH me/profile`; shown as a badge in
  candidate search and recommendations and as `filter[open_to_offers]=1` in candidate search. Off says nothing (no "not looking" label).
- **Sort:** candidate search gains `sort=-last_active` next to the current order (profile updated).

## Changes in short

| Piece | Change |
|---|---|
| Migration | `privacy` export rows (`jobseeker_data_exports`: user, status, path, size, ready_at, expires_at, error); `jobseeker_profiles.reminders_sent`, `last_reminded_at`, `open_to_offers`; `jobseeker_blocked_companies`; `identity_users.last_active_on` |
| Ports | `PersonalDataExporter` (one per context, in a registry) |
| API | `POST`/`GET me/data-export`, `GET me/data-export/download`; `GET`/`PUT`/`DELETE me/blocked-companies`; `open_to_offers` on the profile; `active_within` and `open_to_offers` on candidate rows; `filter[open_to_offers]`, `sort=-last_active` |
| Scheduler | daily profile reminders; daily export cleanup |
| Notifications | `account.data_export_ready` (in-app, email); preference `profile_reminder` and template `job_seeker.profile_reminder` |
| Audit | `privacy.data_exported` |
| Errors | 429 `data-export-limit`; 409 `data-export-running`; 422 `blocked-companies-limit` |
| Legal | `docs/legal-review.md` question 14 (export boundary) |
| Front end | "Unduh data saya" in account settings; blocked companies list; open to offers switch and activity note on the profile; badges in candidate search |
