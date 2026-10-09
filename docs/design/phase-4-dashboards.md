# Phase 4, step 1: dashboards (designed 2026-10-09)

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
- **Write path:** the public job detail request must not wait for the write; the view is recorded through the `bulk` queue.
- **Legal:** the view log keeps a trace of who looked at which job, so it goes into `docs/legal-review.md` (records and retention, privacy policy text).

## Open points to settle while coding (verify before relying on them)

- Profile completeness is not computed anywhere yet: define the sections and weights in the job seeker context.
- Failed email and notification deliveries: check whether the delivery outcome is recorded today; if not, the operations card shows only failed queue jobs.
- Exact cache lifetime and the cache key per role.
