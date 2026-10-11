# Design decisions (confirmed by the user)

All items below were explicitly confirmed by the user unless marked **(proposal)** or **(interpretation)**.
Dates: 2026-10-03. The project is a **REST API only** (no UI); other parties build web/mobile clients, so **the API is the contract**.
Out of scope in this phase: plans, billing, payments, invoices, pay-per-job, and featured/highlight promotion (even if non-paid).

Gap codes (J = job seeker, R = recruiter) refer to `docs/analysis/gap-analysis.md`.

## 1. Application stages

- **Model: hybrid.** Each company stage maps to one **system stage type**; companies freely name and order their stages,
  and the system still understands the meaning (notifications, funnel reports, job-seeker view).
- **Eight stage types:** `applied`, `screening`, `assessment`, `interview`, `offer`, `hired`, `rejected`, `withdrawn`.
  `assessment` (skill and psychological tests) was added 2026-10-11 (user) with the assessment engine (`phase-6-assessments.md`); coded in part 1.
- **Company stages:** every company automatically gets eight default stages, one per type (`assessment` sits after `screening`). A company may rename and reorder them
  and add extra stages of the types `screening`, `assessment`, `interview` and `offer` only (see `phase-1-stages.md`). **Default stages cannot be deleted.** Extra stages can be deleted; their
  applications move to the default stage of the same type.
- **Terminal stages:** `rejected` and `withdrawn` are final; a recruiter cannot move an application back to an active stage.
- **Rejection reasons are mandatory** from a fixed list: `qualifications_mismatch`, `experience_insufficient`,
  `salary_expectation_mismatch`, `location_mismatch`, `failed_screening`, `position_filled`, `other` (with a text note).
- **Withdrawal:** a job seeker may withdraw an application; an optional text note, no fixed reasons.
- **Stage history:** every move is recorded (from stage, to stage, time, actor: recruiter/job seeker/system, reason for rejected).
- **Notifications to the job seeker only** when an application moves to `interview`, `offer`, `hired` or `rejected`
  (`rejected` immediately, not delayed). Channels: in-app always; email controlled by a **new dedicated preference**
  (e.g. `application_update_alert`, default on). Legacy `users` alert flags are not reused.
- **Re-applying (J1):** one active application per job seeker per job; re-applying to the same job is allowed **only after `withdrawn`**
  (not after `rejected`). Old applications stay in history.

## 2. Screening questions

- **Answer types (5):** text, yes/no, single choice, multiple choice, number.
- **Must-have:** mark-only. A recruiter flags a question as must-have with an ideal answer; failing applications still arrive,
  flagged as "does not meet must-have". **No automatic rejection.**
- **Answers** are stored in a **separate table**, one row per answer (not the legacy JSON column).
- **Limit per job:** default 5, set by an **admin** (global setting + per-company override). Recruiters cannot change it.
- **Library:** per-company question library reused across jobs; the feature can be switched on/off per company; no system templates.
- **Integrity:** a question that already has answers cannot be edited or deleted, only **archived**. No text snapshot on answers
  (a typo can only be fixed by creating a new question).

## 3. Scope of gaps (all in scope for the project)

| Gap | Decision |
|---|---|
| J1 | Duplicate prevention + withdraw (see section 1) |
| J5 | Multi-criteria job alerts: several alerts per job seeker; criteria = role, category, title keyword; location (province, city); level, experience, education; job type and minimum salary. **Default 5 alerts** (admin global setting). Frequency: immediate, daily, weekly; channels in-app/email chosen per alert |
| J6 | Merged into J5: an alert is a saved search; the same results can be opened from the alert |
| J6b | Saved jobs (the legacy favourites): a job seeker saves and removes live jobs, at most 200; a job that stops being live stays in the list as unavailable. See `phase-4-saved-jobs-and-talent-pool.md` |
| J7 | CV generator: PDF built from the structured profile; several built-in templates in code; admin can enable/disable templates and set the default; at apply time the job seeker picks an uploaded resume **or** a CV generated at that moment from the latest profile, and the generated PDF is attached to the application |
| J8 | Job recommendations: simple weighted rules (no machine learning) on skill, experience, location, expected salary, role. Shown on the job seeker dashboard, as a match percentage on job lists/details (logged-in job seeker), and by periodic email |
| J9 | Chat: attachments (documents and images), **default 5 MB** per file, types PDF/JPG/PNG, admin-configurable, content-based type checks. Only the **recruiter's** online status is visible to job seekers. A recruiter can start a conversation any time; a job seeker only with a company they have applied to |
| J10a | Salary insight from own jobs only; shown only if at least **N = 5** jobs with visible salary exist for the grouping (admin-configurable) |
| J10b | Company reviews by job seekers: admin-moderated; only job seekers who applied to that company may write; the company gets one public reply per review (also moderated); **the author's name is always shown** (the user's choice; risk reported: may suppress honesty and expose authors to conflict) |
| R1 | Company accounts are **created by an admin**; the first company admin is created by the admin. Company admins invite colleagues by email (accepted by the invitee) as **company admin** or **recruiter**; one company has many users; **per-job assignment** (only assigned colleagues receive/manage that job's applications). Company admin = everything a recruiter can do + invite/remove members, edit company profile, manage stages, question library and assignments. Recruiter = create/manage jobs and manage applications of jobs assigned to them |
| R1b | Company document verification is in scope and visible in the menu; admin approves or rejects with a reason; verified companies get a public badge |
| R5 | Bulk actions: bulk stage move (one reason for all when rejecting; notifications still per application) and bulk chat message (**max 50 recipients** per send, admin-configurable) |
| R7a | Internal notes (visible to team members working on the job, never to the job seeker) and 1-5 star ratings per member; the average is shown on the applicant card |
| R7b | Interview scheduling: time, format (online/onsite), location or link, interviewer; the job seeker confirms or proposes another time. **A schedule is mandatory when moving to `interview`** (one by one). **Bulk move to `interview`:** the recruiter enters one base schedule and the system creates sequential slots per applicant |
| R7c | Structured offers: position, salary, start date, note; the job seeker accepts or declines in the system. **Accept moves the application to `hired`; decline moves it to `withdrawn`** (optional note) |
| R10 | Job invitation tied to a job; status tracked (sent, viewed, applied, declined); **default 50 invitations per day per company** (admin global setting, per-company override); **one invitation per candidate per job**; candidates who already applied cannot be invited |
| R11 | Talent pool: a company saves findable candidates (shared by its members, at most 1,000) into free categories of its own (at most 30; only a company admin renames or deletes one); a candidate who is no longer findable shows as unavailable. See `phase-4-saved-jobs-and-talent-pool.md` |
| R12 | Candidate recommendations using the same matching engine as J8 (reversed); match percentage and an invite button; only candidates whose profile is visible |
| R13 | Applicant CSV export per job by company admins and assigned recruiters; every export is audit-logged |

## 4. Phase 0 foundation decisions

- **Accounts:** public users (job seeker, company member) in **one table** with a type column; **admins in their own table** with
  token authentication. Company membership lives in the Company context and references Identity accounts.
- **Google login:** the API verifies the Google **ID token directly** (no Firebase). Social identities are stored in a separate table.
  Facebook is not carried over **(interpretation:** the user only mentioned Google login).
  **Details (2026-10-03, user):** verified with `google/auth`; the Google account is identified by its subject; an existing account with the same email is
  linked only when Google reports the email as verified, and an account whose email was never verified loses its password and tokens when linked (owner sets a new
  password through the reset flow); new Google accounts are verified job seekers without a password; company members are created by admins and can only link.
- **Admin RBAC:** Spatie permission package wrapped behind an interface (Infrastructure), custom `identity_` table names.
- **Framework tables** keep default Laravel names (no context prefix).
- **Settings:** typed key-value with a catalog in code; the database stores only changed values; per-company overrides in a separate table.
  Decided settings: screening limit (5, per company), job alerts per job seeker (5), chat attachment size (5 MB, PDF/JPG/PNG),
  salary-insight minimum (5), bulk-message recipients (50), daily invitations (50, per company), active CV templates.
  Legacy flags to decide in Phase 1: `job_auto_approved`, `edited_job_auto_approved`, `email_verification`, `job_deadline_expiration_limit`.
- **Notification channels:** in-app, email (queued, honoring preferences), mobile push (FCM), real time (WebSocket, e.g. Laravel Reverb).
  Extra infrastructure: queue workers, WebSocket server, FCM credentials.
- **Email templates:** defaults in code; admins can override subject and body through the API (allowed variables, preview).
- **Audit log:** sensitive actions + login (success and failure): data exports, admin actions (settings, moderation, verification, roles),
  company membership changes, account deletion; with who, when, what, object, IP address. Not an audit of all data changes.
  **Retention 12 months** (scheduled purge); readable only through the admin API by admins with a dedicated permission (e.g. `audit_logs.view`).
- **Master data:** multilingual (`id`, `en`) stored as one JSON name column per master table. Translatable fields everywhere use JSON columns managed with
  `spatie/laravel-translatable`, used only in Infrastructure Eloquent models (no `*_translations` tables). Slug generated from the `en` name; name search
  queries the JSON directly. Location names are untranslated proper names.
- **Primary keys:** every project table has `id` `bigint unsigned` auto-increment; exceptions are the three RBAC pivots (composite key of the Spatie
  package) and framework tables (default structure).
- **Contact data** (phone, WhatsApp, address) is not stored on `identity_users`; it belongs to the job seeker and company profiles (Phase 1).
- **Account deletion:** anonymisation with a 30-day grace period (details in section 6).
- **Tags and benefits:** tag popularity is computed automatically with an admin override; benefits are global, and companies may propose new ones
  that an admin approves or rejects.
- **In-app notifications** are retained 6 months (read or unread), purged by a scheduled job.
- **Location:** Indonesia only, administrative hierarchy with BPS codes (province, city, subdistrict, village, postal code); no countries list.
  **Work mode** on jobs: `onsite`, `hybrid`, `remote`; location required for onsite/hybrid, optional for remote.
- **Currency:** Indonesian Rupiah only (no currency column, no conversion).
- **Masters carried over:** job category, **job role (merged roles and professions)**, job type (employment relationship), job level,
  work time (working pattern), experience level, education level, salary period, **skill** (kind `hard`/`soft`, used by jobs and candidates;
  replaces free-text hard/soft skill fields), tag (free keywords on jobs), benefit, industry, organization type, team size, religion,
  nationality, language. Not carried: currencies, timezones, search_countries, geographics, countries.
- **Religion and nationality:** collected, optional, visible to the profile owner and the companies they applied to; **may be used for filters,
  matching and alerts** (the user's choice). Risk reported: religion-based selection; recommended review against data-protection and
  labor law before release (legal details not verified).
- **Legal documents and FAQ:** versioned terms/privacy, `id`+`en` JSON, body and FAQ answers written in Markdown (clients render and sanitise);
  user consent is recorded (version, time, user; the IP address is cleared on anonymisation); migrated accounts accept the active version at first
  login (flow not detailed).
- **Public profile URLs:** a unique, changeable slug (not an opaque id), on the job seeker profile and company tables (Phase 1).
- **Phase 0 column design:** reviewed table by table and **approved by the user on 2026-10-03** for all five contexts (see `phase-0-schema.md`).
  Nothing exists in the database yet; migrations are written next with per-table confirmation.

## 5. Delivery decisions

- **Result: API only.** Admin functions (master data, moderation, settings, verification) are admin API endpoints with token auth.
- **Production data migration: yes, all core data** (accounts, profiles, resumes, jobs, applications, master data) from `karir_pkp` into the new schema;
  old password hashes kept if compatible. **Safety rule:** write migration scripts from the schema and synthetic data;
  **running them against real data (reading production table contents, which include personal data) requires explicit user approval at that time**;
  read from production only, write to `jobportal_v2`.
- **Legacy features carried over:** campaigns and source attribution; Moodle training integration (token must not stay in the query string);
  newsletter (subscribe + admin bulk send); Google login; legal pages + FAQ via the API. **Not carried over:** blog and comments, testimonials,
  other static pages, SEO settings.
- **Phases (approved):**
  - **0 Foundation:** identity (job seeker, company member, token-based admin, Google login), admin settings, notification infrastructure,
    safe file storage, audit log, master data and location, legal pages and FAQ. The source-attribution column is prepared here.
  - **1 Core recruiting loop:** companies and teams (R1), jobs and lifecycle, job search, job seeker profile and resumes, applying (J1),
    hybrid stages and history, screening questions, pipeline, stage notifications, **data migration** (scripts and tests).
  - **2 Collaboration:** notes and ratings, interview scheduling, offers, bulk actions, chat with attachments, CSV export (document verification moved to Phase 1 and is done).
  - **3 Discovery and matching:** job alerts (J5/J6), candidate search, matching engine (J8, R12), job invitation (R10), CV generator (J7).
  - **4 Content and trust:** salary insight, company reviews, campaigns, Moodle training, newsletter.
- **Order inside Phase 1 (confirmed 2026-10-05, company side first):** 1) Company and team (done), 1b) company document verification (done; it was moved here from Phase 2 and is complete),
  2) jobs, their lifecycle and per-job assignment, 3) public job search, 4) hybrid stages (seven default stages per company), 5) screening questions, 6) job seeker profile and resumes,
  7) applying (J1), 8) pipeline with stage history and stage notifications, 9) data migration (scripts and tests only). Each step is designed one question at a time before it is coded.
- Not yet decided: order inside the other phases, estimates, and per-phase bounded-context/schema design beyond Phase 0 and the Company context.

## 6. Still open (see also `phase-0-schema.md`)

Phase 0 open questions were all answered on 2026-10-03 (details in `phase-0-schema.md`): public profile URL = unique changeable slug; account deletion =
anonymisation; tag popularity = automatic with admin override; benefits = global plus company proposals approved by admin; location names untranslated;
audit log kept 12 months and readable only by admins with a dedicated permission; migration mapping deferred to Phase 1.
Account anonymisation details (confirmed 2026-10-03): 30-day grace period (`deletion_requested_at`, account deactivated, scheduled job anonymises
afterwards); email replaced by `anonymized-{id}@anonymized.invalid`; marker is `anonymized_at` only (no `anonymized` status value). Sign-in stays
rejected during the grace period (the error explains the scheduled deletion); the user cancels through a dedicated endpoint (valid credentials plus
explicit confirmation), which reactivates the account. The anonymisation also clears the `ip_address` of consent records and deletes the user's social
accounts, device tokens and notifications.

Phase 0 details deliberately left open (slug rules, anonymisation column and file list, tag list size and refresh, benefit proposal flow, audit-log
purge, `notifications` index, verification-code lifetime, location data source and master seed values, package compatibility with Laravel 13) are listed
in `phase-0-schema.md`, section "Details still open".

Still open: matching weights and signals; salary-insight grouping dimensions; rating scale and reporting for reviews; slot rules for bulk interviews and the number
of reschedule rounds; offer validity and revisions; export columns (the rejection reasons, accepted types and sizes of company document verification were decided on 2026-10-05, see `phase-1-company.md`);
anti-spam rules for bulk messages beyond the 50-recipient cap; CV template count; reviewer visibility rules (to be confirmed with legal review).

**Deferred to Phase 3 (recorded 2026-10-07): the "fresh graduate" category.** Today the only signal is the self-chosen `jobseeker_profiles.experience_level_id`, whose master has a "Fresh Graduated" row (0 to under 1 year), and
the profile completeness rule (one experience or one education entry) lets a fresh graduate apply. Nothing derives the category from the education and experience history, and nothing matches applicants to jobs by level.
It is decided together with the matching engine (J8, R12), candidate search and invitation. Questions to answer then: (1) is the category chosen by the job seeker or computed from the history (graduation year in
`jobseeker_educations`, length of `jobseeker_experiences`); (2) how are the two masters "Fresh Graduated" and "Less than a Year", which share the range 0 to 1 year, told apart. The data migration needs nothing from it:
the legacy experience level is carried only when it matches a master (confirmed rule of step 9).

**Deferred product decision (recorded 2026-10-07): applicants living abroad.** The location of a profile is Indonesian only (BPS chain), and applying requires province and city, so a job seeker who lives outside Indonesia
cannot complete a profile, even for a `remote` job. Decide whether that is intended; if not, either allow an empty location for such applicants or add a flat `reference_countries` master (ISO codes; the BPS chain stays
under Indonesia). Nationality (`reference_nationalities`, a profile attribute) must not be reused as a country list.

**Deferred (recorded 2026-10-11, user): FCM push and real-time in-app notifications; automatic tag popularity and company benefit proposals.** Device tokens are stored but nothing sends to them, in-app notifications are not broadcast, `reference_tags.popularity_override` is unused and there is no proposal flow for company benefits. Each needs its own design (push also needs an FCM account); revisit after the Phase 5 hardening (`phase-5-architecture-review.md` findings 7, 8 and 20; finding 20 is removing a device token on sign-out).

**Decided (recorded 2026-10-11, user): assessments reopen R8.** We build our own test engine, like Mekari Talenta: company-built skill tests plus psychological tests (intelligence, personality, reasoning). Psychological test content, scoring norms and reports are owned and signed by a **licensed partner psychologist or institution** (UU 23/2022 Pasal 26 and 33), with candidate consent (Pasal 27) and a right to an explanation of the result (Pasal 44); the platform never interprets psychological results on its own. Benchmark: `docs/analysis/benchmark-teleinterview-assessment.md`. **Tele-interview (decided 2026-10-11, user; changed the same day):** ~~(1) Zoom, Google Meet and Microsoft Teams integration~~ dropped in favour of a self-hosted Janus WebRTC server (`phase-7-tele-interview.md`); order now (1) live video inside the platform on Janus, (2) recorded one-way video (a question type of the assessment engine), (3) voice calls through Asterisk that also reach ordinary phone numbers through a **licensed SIP trunk provider**, with both numbers masked (narrowed 2026-10-12 to one-way masking: the candidate never sees the team member's number; `phase-7-tele-interview.md`). Recording of calls and video is **optional per interview and needs the candidate's explicit consent** before it starts (UU PDP), with a retention period and permanent deletion. **Interview kits and scorecards (decided 2026-10-12, user):** company kit templates copied into each interview, a panel for every format, a score of 1 to 5 per weighted question plus a required recommendation per panel member, scored independently (`phase-7-interview-kits.md`); coded before tele-interview form 1.
