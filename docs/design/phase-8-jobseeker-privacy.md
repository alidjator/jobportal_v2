# Phase 8: personal data export, profile reminders, blocked companies and activity status (designed 2026-10-14; sections 1 and 2 coded 2026-10-15, sections 3 and 4 coded 2026-10-31)

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
- 2026-10-15: the first reminder counts from a new `identity_users.first_login_at` (set once at the first sign-in; empty for imported accounts until their first one) (chosen by the user; [coded](#coded-sections-1-and-2-2026-10-15)).

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

## Coded: sections 1 and 2 (2026-10-15)

Migration `000139` (`identity_users.first_login_at`; `jobseeker_profiles.reminders_sent` 0 to 3 and `last_reminded_at`; `jobseeker_data_exports`). Not yet checked by the
user. Interpretations, where the design left room:

- **Export machinery** (JobSeeker): `RequestDataExport` locks the profile row, so two requests cannot both pass the checks; the build runs in a queued
  listener of the event `DataExportRequested` (queue `default`, 3 tries, 300 s). The archive is built in memory and a temporary file, so it is bounded by the
  size of the person's own files (a few resumes and attachments). A build still `building` after **60 minutes** is dead: it does not block a new request and the
  hourly cleanup (`job-seeker:clean-up-data-exports`) closes it as `failed`. A `failed` request does not count against the one-a-day limit. The cleanup runs
  **hourly**, not daily, so an archive is deleted soon after its 24 hours; a ready archive past its 24 hours is already refused (404) and shows `expired`.
- **Parts of `data.json`** (key: exporter, context): `account` (Identity), `profile` (JobSeeker), `applications` (Recruitment), `assessments` (Assessment),
  `job_invitations`, `saved_jobs`, `job_alerts`, `conversations` (Messaging), `reviews`, `notification_preferences`, `talent_pool`. Files go to
  `files/<key>/`. Each context tags its own `PersonalDataExporter` (a Shared port with a registry, like the erasure steps).
- **Not in the archive, for the legal review (question 14):** the company's replies to reviews; the in-app notifications; **interview recordings and voice
  call logs** (they hold the candidate's voice or image, the design does not list them); the raw test answers and scores (they carry the test provider's answer
  keys); the company-side category of a talent pool entry. **Training enrolments:** Training holds no data of its own (Moodle is the source), so there is no part.
  **Blocked companies** and the activity switches join the archive when sections 3 and 4 are coded. The newsletter and campaign subscriptions are by email address, not by account, and are not exported.
- **Talent pool:** until the talent pool consent of `phase-8-talent-pool.md` exists, `talent_pool` lists the companies that keep the person, and since when.
- **Ready notice:** in-app type `account.data_export_ready` and the email template of the same key (always sent, to the account's address, whether or not verified).
  The audit entry `privacy.data_exported` carries the export id and the size.
- **Erasure** deletes the archives and the request rows.
- **Reminders:** the Identity port `FirstSignIns` gives the accounts that signed in before today (Jakarta) and are active, verified job seekers; JobSeeker keeps
  the state. Days are Jakarta days: the first reminder goes at the first run on a day after the first sign-in, the next ones when the last one was sent on a day
  at least 7 days ago. An account that never saved a profile gets its profile row at the first reminder (the state lives there). A switched-off
  `profile_reminder` preference sends nothing and counts nothing, so turning it on again resumes. **Recommendations** are not named: they need a complete
  profile, which a reminded person does not have; only the live jobs that match their (unpaused) alerts are, as one sentence of the email, counted up to 100 per alert.
- **Link and opt-out:** the email links to the client profile page set in `JOB_SEEKER_PROFILE_URL` (empty: no link). There is no signed one-click opt-out
  link yet (no signed routes exist in the API); the preference in the notification settings is the opt-out, and the email says so.

## Coded: section 4 (2026-10-31; API)

Migration `000340` (`identity_users.last_active_on` date; `jobseeker_profiles.open_to_offers` boolean, default false). Not yet checked by the user. Interpretations, where the design left room:

- **Marking:** the middleware `RecordUserActivity` is in the `api` group after the terms gate and acts on routes with `auth:user`. It runs **before** the route, so a job seeker's own answer already shows today's bucket. `UserRepository::recordActiveOn` is one conditional `UPDATE` (job seeker accounts only, only when the stored day is empty or older than today in Jakarta), so a busy day writes once. The terms gate runs first: an account that must accept new terms is refused before it is marked.
- **Buckets** (`ActiveWithin`): a day counts as inside a bucket when it is at most that many days ago, so 7 days ago is `7d`, 8 is `30d`, 30 is `30d`, 31 is `90d`, 90 is `90d`, 91 is `older`. Null (never seen: an imported account that has not signed in) shows no bucket.
- **Shown:** `active_within` and `open_to_offers` on the candidate list row (search and recommended candidates), on `GET company/candidates/{slug}` and, as the owner's own view, on `GET me/profile` (`active_within`, `open_to_offers`). The date is never in an answer.
- **Search:** `filter[open_to_offers]=1` keeps those open to offers (`0` or absent: no filter); `sort=-last_active` puts the most recently active first with never-seen accounts last, ties by profile update; `sort=-updated_at` is the default.
- **Export and erasure:** the archive's `account` part gains `last_active_on` and the `profile` part gains `visibility.open_to_offers`; erasure clears `last_active_on`.
- **Front end:** the "Terbuka untuk tawaran" switch and the activity note on the profile (Career), badges and the filter and sort in the candidate search (Workspace).

## Coded: section 3 (2026-10-31; API)

Migration `000350` (`jobseeker_blocked_companies`: user RESTRICT, company CASCADE as designed, unique pair). Not yet checked by the user. Decisions of 2026-10-31 and interpretations:

- **Port:** `CandidateDirectory::findable(companyId, slug)` and `summaries(userIds, language, companyId)` take the asking company; `findableIds(userIds, ?companyId)` ignores blocks without one. `CandidateReader` takes the company in `search`, `findBySlug`, `findableRows` and `pool` (a `NOT EXISTS` on the block table). So the search, the recommended candidates, the profile, its picture and resumes, invitation by slug, a new chat by slug and saving a candidate by slug all see the person as missing (404).
- **Talent pool:** an entry stays. `EntryViews` asks for the summaries of the company, so the entry shows `available: false` and no candidate; an entry that rests on application consent stays available. The cleanups (`EntryBasis::withoutBasis`) ignore blocks, because a block hides and never ends a right; the invitation flow (`AvailablePoolCandidates`) honours them, so a blocked person cannot be invited from the pool either (422 `entry-unavailable`).
- **Applicants (decided 2026-10-31):** an application still opens the applicant profile of the company's own screens, but the link to the public candidate profile is gone (the person is not findable). Saving an applicant to the pool without the pool consent is refused (`talent-pool-consent-missing`) while the person blocks the company; with consent it works.
- **Existing data (decided 2026-10-31):** invitations and conversations that exist when a company is blocked stay visible and can be answered; only new ones are refused.
- **Endpoints:** `GET me/blocked-companies` (the most recent first, a suspended or hidden company stays listed so the block can be lifted), `PUT` and `DELETE me/blocked-companies/{companyId}` (204, both idempotent). `PUT` needs a public company (404 for a missing, suspended or hidden one) and a free place (422 `blocked-companies-limit`, 20 at most; blocking a company again at the limit changes nothing).
- **Export and erasure:** the `profile` part gains `blocked_companies` (company, time); erasure deletes the rows.
- **Front end:** the list in account settings with a company picker (the public company search), and a note on what blocking does.
- **Marketplace only (decided 2026-10-31):** blocking companies exists on the marketplace only, as the verification of companies does (`career-modes.md`): `CompanyBlockingFeature` (Platform, true for the `marketplace` mode or a portal without an identity). Elsewhere `GET`, `PUT` and `DELETE me/blocked-companies` answer 404 `company-blocking-unavailable`, `CandidateReader` ignores the rows that exist, and the Career front end hides the card and the button. The last active bucket, "open to offers" and the candidate search are not limited by the mode.

