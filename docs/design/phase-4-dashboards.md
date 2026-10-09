# Phase 4, step 1: dashboards (designed 2026-10-09, coded 2026-10-09, checked)

One summary endpoint per role. The numbers are computed on the server so a client needs one call; the details stay on the endpoints that already exist.
New bounded context `Dashboard` (`src/Dashboard`, read side only: no domain model, no tables of its own except the job view tables below). It reads other contexts through their `Shared` read interfaces, never their tables.

## Confirmed by the user (2026-10-09)

- **Roles:** job seeker, company, admin.
- **Shape:** `GET /me/dashboard` (job seeker), `GET /company/dashboard` (company member), `GET /admin/dashboard` (admin). One JSON document per role, a card per section.
- **Trend:** the cards carry the current numbers plus a 30-day daily series where listed below.
- **Computation:** straight aggregate queries with a short cache (1 to 5 minutes, per job seeker, per company or per admin permission set). No summary table.
- **Admin permission:** new permission `dashboard.view`. A card that shows data of another module (job approval queue, company verification queue) appears only when the admin also holds that module's permission.

## Cards

**Job seeker**

- Applications per status (active ones by stage) and the 5 most recent with their status.
- Invitations not answered yet and the nearest interviews.
- Unread conversations and unread notifications.
- Profile completeness (percentage and the sections still empty) and the number of companies that viewed the profile in the last 30 days (from the existing profile view log).
- Recommended jobs stay on their own endpoint and are not repeated.

**Company**

- Jobs per status: live, draft, pending approval, rejected, closed, expired.
- New applications (not reviewed yet) and applications per pipeline stage.
- Nearest interviews, offers waiting for an answer, conversations with unread messages.
- Job performance, per job: views, applications, applications per view, and the 30-day daily series of views and applications.

**Admin** (`dashboard.view`)

- Platform totals: job seekers, companies, live jobs, applications; 30-day daily series of sign-ups, new jobs and applications.
- Work queues: jobs waiting for approval and companies waiting for verification (each needs the module permission).
- Operations: failed queue jobs, failed emails and notification deliveries of the last 24 hours.

## Job views (new data)

A company needs views to judge a job, and the platform records none today. Decision: **one row per counted view**.

- **Viewer:** the account id when logged in; for an anonymous visitor a hash of IP and user agent with a **daily salt** (no raw IP is stored, and the hash cannot be followed across days).
- **Counted once** per viewer per job per day. Members of the owning company and recognised bots are not counted.
- **Retention:** raw rows are kept **90 days**. A scheduled job first folds them into a daily counter per job (`job_id`, `day`, `views`), then deletes the raw rows older than 90 days. The long-term trend survives without any viewer trace.
- **Write path:** the client reports a view with `POST /jobs/{job}/view` (204, limited to 120 per client address per minute) when a person looks at the page; the public job page itself only reads (it is cached for 60 seconds, so counting there would miss views and a GET would write). The view is recorded through the `bulk` queue. Decided 2026-10-09, user.
- **Legal:** the view log keeps a trace of who looked at which job, so it goes into `docs/legal-review.md` (records and retention, privacy policy text).

## Details (decided 2026-10-09, user)

- **Profile completeness:** eight sections of 12.5 percent each: headline and summary, contact (phone), location, work experience, education, skills, languages, uploaded resume. Religion, gender, age, marital status, nationality and photo are **never** counted, so the system does not push anyone to fill them. The dashboard lists the sections still empty.
- **Operations card (admin):** only what exists today: failed queue jobs of the last 24 hours per queue and the number of jobs waiting. The outcome of an email or a notification is not recorded, so a failed email shows up only as a failed job; no delivery log is added.
- **Cache:** 2 minutes for the job seeker and the company summaries, 5 minutes for the admin summary.
- **Company roles:** every member sees the company dashboard, scoped by the access to jobs that already applies to applications: a company admin sees all jobs of the company, a recruiter only the jobs assigned to them (in every card, the job performance card included). The business intelligence analytics are for the company admin only.

## Job view tables (design, to be finalised in the migration)

The tables carry the prefix of the context that owns them (job views belong to the `Job` context, which records them; the `Dashboard` context only reads them).

- `job_views`: `id`, `job_id` (indexed, no foreign key across contexts), `viewer_key` (string: `u<user_id>` for an account, a daily-salted hash for a visitor), `day` (date, Jakarta), `created_at`. Unique key on (`job_id`, `viewer_key`, `day`), so a repeated view in a day is a no-op insert.
- `job_view_daily`: `job_id`, `day`, `views`; primary key (`job_id`, `day`). Filled by the scheduled fold before the raw rows older than 90 days are deleted.
- Erasure: a deleted account's `u<user_id>` rows are removed with the rest of its data.

## Open points to settle while coding

- The scheduled fold runs daily; the exact hour is chosen with the other nightly jobs.

## What was coded (2026-10-09)

- **Cards are owned by the context that owns the data.** `Shared/Application/Dashboard` holds the contract (`DashboardCardProvider`, `DashboardCards`, `DashboardScope`, `DashboardWindow`); each context registers its read-only cards from its service provider (`Infrastructure/Dashboard/*Card.php`). The `Dashboard` context only composes them per audience, applies the admin permission of a card, and caches the result (`GetJobSeekerDashboard`, `GetCompanyDashboard`, `GetAdminDashboard`; `DashboardComposer`).
- **Endpoints:** `GET /me/dashboard`, `GET /company/dashboard`, `GET /admin/dashboard` (permission `dashboard.view`, which needs `identity:sync-permissions`). The response is `{data: {generated_at, cards: {<key>: {...}}}}`.
- **Job views:** `TrackJobView` runs on `POST /jobs/{job}/view` (404 when the job is not live); the write goes through the `bulk` queue (`WriteJobView`); `job:fold-views` runs nightly at 03:00 Jakarta; `UserAnonymized` removes the `u<id>` rows. Bots are recognised by the user agent (`TrackJobView::BOT_PATTERN`).
- **Job performance card (decided 2026-10-09, user):** views, applications and applications per view **per job**, and the 30-day daily series of views and applications for **all jobs in scope together**. The series of one job is the separate endpoint `GET /company/jobs/{id}/performance` (coded 2026-10-10): the same 30-day window and counting, with `views`, `applications`, `applications_per_view` (null without a view) and the daily series of that job alone; the job access rules apply (a recruiter outside the assignment, or another company, gets 404; `GetJobPerformance`, tests in `DashboardTest`).
- **Operations card:** only failed (last 24 hours) and waiting queue jobs, per queue.
- **Tests:** `tests/Feature/Dashboard/DashboardTest.php`, `tests/Feature/Job/JobViewsTest.php`, `tests/Unit/Dashboard/*`.
