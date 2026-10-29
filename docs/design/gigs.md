# Gigs: shift gigs and project gigs (designed 2026-10-29, not coded)

Benchmark and reasons: `docs/analysis/benchmark-gigs.md`. Everything below is a **proposal** until the user answers the questions of
[section 10](#10-open-questions); answers go into `## Decisions` and the detail section in the same edit.

## Decisions

- 2026-10-29: **on hold: no gig coding, API or front end, until the user discusses it again.**
- 2026-10-29: **both kinds of gig, shift gigs first, project gigs after them**; both flows are designed (decided by the user; [section 1](#1-what-a-gig-is)).
- 2026-10-29: **project gigs = projects posted by a tenant (proposals, award, milestones, deliverables) plus a service catalogue** where freelancers list
  service packages that companies order (Fastwork, LinkedIn service page); the catalogue **lifts the marketplace freeze for this part only**; payment
  stays outside the system, no escrow, fee or invoice (decided by the user; [section 11](#11-project-gigs)).
- 2026-10-29: **service catalogue**: in `company` mode only the members of that installation's companies see and order packages (in Workspace, not on
  the public Career site); a package is **reviewed by the platform admin** before it is visible; companies **rate a provider 1 to 5** after accepting
  the work, and the average shows on the provider profile (decided by the user; [section 11.2](#112-service-catalogue-decided-2026-10-29)).
- 2026-10-29: **the tenant company employs the shift worker directly as a daily worker (PP 35/2021); ONESIS is only the tool**, no outsourcing licence,
  no payouts (decided by the user; [section 6](#6-daily-worker-limit-pp-352021)).
- 2026-10-29: **a new posting defaults to booking mode `approval`**; the company may switch a posting to `instant` (decided by the user; [section 4](#4-booking-lifecycle)).
- 2026-10-29: **every tenant of an installation may use gigs**, not only the internal tenants (decided by the user; [section 8](#8-where-gigs-run)).
- 2026-10-29: **shift gigs (sections 1 to 3 of [section 9](#9-order-of-work-proposed)) are coded before go-live** as `phase-8-index.md` #45; the parts that wait for
  `legal-review.md` questions 26 to 28 (location at clock-in, reliability suspension, identity documents) are built **switched off** until answered
  (decided by the user, who resolved the conflict with the priority B rule and the limited coding of `decisions.md` section 5).
- 2026-10-29: **the schema of [section 12.1](#121-tables-context-gig-prefix-gig_-the-migration-is-run-by-the-user) is approved** (decided by the user), with the review statuses of the next line.
- 2026-10-29: **a gig posting follows the installation's job review policy**: when jobs need review, a published posting goes to `pending_review` and the
  platform admin approves or rejects it with a reason (decided by the user; [section 12.3](#123-endpoints-api-v1)).
- 2026-10-29: **the Recruiter preset gets both `gigs.manage` and `gigs.timesheets`** (decided by the user; [section 12.4](#124-permissions-settings-scheduler-notifications)).
- 2026-10-29: **project gigs and the service catalogue are #46, also before go-live, after #45** (decided by the user); API plan in [section 13](#13-api-plan-for-46-project-gigs-and-the-service-catalogue-schema-approved-2026-10-29-not-coded).
- 2026-10-29: **#46: the schema of 13.1 is approved; a job seeker may also start a chat with a company where they have a proposal or an engagement, seen on the
  company side by members with `gigs.order`; a provider must be at least 18; projects are public on Career like jobs; the Recruiter preset gets
  `gigs.order`** (decided by the user; [section 13.6](#136-questions-answered-2026-10-29)).
- 2026-10-29: **screens of [section 14](#14-screens-decided-2026-10-29-not-coded)**: paths follow the project's concept (Indonesian public and Workspace paths, English `/me`), maps with
  Leaflet and OpenStreetMap tiles (risk reported, tile URL configurable), three API additions (`GET gig-options`, bulk timesheet approval, the list of
  reliability events), front-end step 8 before go-live (decided by the user).

## 1. What a gig is

- A **gig** is paid work of one or a few **shifts** at one company location: a date, a start and end time, a headcount, a role and a pay rate.
  A job seeker **books** a shift instead of applying; there is no stage pipeline, no interview and no offer.
- **Shift gigs** (Indeed Flex, Instawork) come first; **project gigs** (LinkedIn Services, Sribulancer) follow (decided 2026-10-29). Sections 2 to 9
  describe shift gigs; project gigs are [section 11](#11-project-gigs-open).
- A normal job of type `freelance` or `daily` stays as it is; a gig is not a job type but its own flow.
- **Out of scope:** paying workers, payouts, early pay, invoices, fees. The system records approved hours and the agreed rate and exports them; payment
  happens in the company's payroll.

## 2. Bounded context

- New context **`Gig`** (`src/Gig`, tables `gig_*`), not an extension of Job or Recruitment: its lifecycle (many short bookings, attendance,
  timesheets, reliability) shares nothing with the application pipeline. It reads companies, locations, job seekers and the organisation structure
  through ports in `src/Shared`, as every context does.
- Foreign keys follow `cross-context-foreign-keys.md`: `company_id`, `location_id`, `user_id` with `RESTRICT`.

## 3. Model

| Table | Holds |
|---|---|
| `gig_postings` | company, location, title, role (`reference_*` role), description (Markdown), requirements, what to bring and wear, on-site contact, pay rate and basis (`hour`, `shift`), **booking mode** (`instant` or `approval`), optional organisation unit and cost centre, status `draft`, `open`, `closed`, `cancelled` |
| `gig_shifts` | posting, `starts_at`, `ends_at`, unpaid break minutes, headcount, status `open`, `full`, `started`, `finished`, `cancelled` |
| `gig_bookings` | shift, worker (`identity_users` id of the job seeker), status (section 4), confirmation and cancellation times and reasons, who cancelled |
| `gig_attendances` | booking, clock-in and clock-out time, latitude and longitude at each, inside the geofence or not, approved worked minutes, approved by, dispute note |
| `gig_ratings` | booking, stars 1 to 5 from the company, optional note (never shown to the worker in the first cut) |
| `gig_preferred_workers` | company, worker, added by: the company's own list (Indeed Flex preferred pool) |
| `gig_reliability_events` | worker, company, kind (`late_cancel`, `no_show`), booking, points, expiry |

- A posting with several shifts covers a multi-day gig; a worker books each shift, or all of them at once when the posting says so (Instawork: a
  multi-day booking is cancelled as a whole).
- The geofence radius is a company setting, default **200 m**, at most 500 m (Indeed Flex); the location needs latitude and longitude
  (`company_locations.latitude`, `longitude` exist and are nullable: a location without them cannot carry a gig).

## 4. Booking lifecycle

```
instant:   booked ─► confirmed ─► checked_in ─► completed
approval:  requested ─► booked (company approves) ─► ...
any open state ─► cancelled_by_worker | cancelled_by_company
booked/confirmed, no clock-in by start + grace ─► no_show
```

- **Booking:** a new posting is in mode `approval` by default (decided 2026-10-29). The worker must meet the posting's requirements (profile complete, minimum age, required documents or certificates, not suspended,
  no overlapping booking, the daily-worker limit of section 6). Preferred workers see a shift a set time before everyone else (default 2 hours).
- **Confirmation:** the worker confirms between 24 and 16 hours before the start (Instawork); a reminder goes by notification and WhatsApp
  (`phase-8-whatsapp.md`). An unconfirmed booking at 16 hours is released and the slot reopens.
- **Cancellation:** free within 15 minutes of booking or more than 24 hours before the start; later is a `late_cancel` event. The company may cancel
  with a reason at any time; the worker is notified at once.
- **Clock-in and clock-out** in the Career app, from 15 minutes before the start, inside the geofence; outside it the clock-in is still taken but
  flagged for the company to review. A missing clock-out closes 8 hours after the shift end and is flagged.
- **Timesheet:** the company approves or corrects the worked minutes of each finished booking within a set period (default 3 days); after it, the
  recorded times are approved automatically. The worker sees the approved time and may dispute once with a note.

## 5. Reliability and ratings

- Points per event: `late_cancel` 1, `no_show` 3, kept 30 days; at 6 points the worker cannot book for 7 days (all values are platform settings;
  shape from Instawork). Booked shifts during a suspension stay booked. The company can waive an event (weather, emergency).
- The company rates a finished booking 1 to 5 stars; the average per company is shown only to that company. A 5-star worker can be added to the
  preferred list in one click.
- Proposed: workers do not rate companies in the first cut (company reviews are frozen with the marketplace).

## 6. Daily-worker limit (PP 35/2021)

- Per worker and **legal entity**, the system counts the calendar days with a completed or booked shift in a month. Booking a shift that would make
  the 21st day is refused (`gig-daily-limit-reached`); the company sees a warning from day 18.
- Three months in a row at 21 days cannot happen through the system; days worked outside it are not known. The rule is a safety net, not legal advice:
  `legal-review.md` gets a question on it.

## 7. Screens

| App | Who | Pages |
|---|---|---|
| Career | job seeker | gig list and map by date, area and role; gig detail with shifts; my shifts (upcoming, past); clock-in and clock-out; my timesheets |
| Workspace | company admin, recruiter with the new permission `gigs.manage` | postings and shifts (calendar), roster per shift, approvals of requests, attendance review, timesheets, ratings, preferred workers, CSV export of approved hours per period and cost centre |
| Console | platform admin | settings (radius, points, windows), moderation of postings when the review policy asks for it |

- Every element follows the permission parity rule (`frontend` `CLAUDE.md` rule 20): a member without `gigs.manage` sees no gig tab, card or button.

## 8. Where gigs run

- **Every tenant** of an installation may use gigs, internal and external (decided 2026-10-29), on the same Career host, as a "Gigs" section next to
  jobs; a switch per installation (`gigs.enabled`, off by default). In `company` mode the section shows the gigs of that company or group only.
- A gig worker is an ordinary job seeker account; no new account type.

## 9. Order of work (proposed)

1. Postings, shifts, booking (instant and approval), cancellation, daily-worker limit, notifications.
2. Attendance with the geofence, timesheets, CSV export.
3. Reliability points, ratings, preferred workers, early access.
4. Later: QR clock-in at sites with weak GPS, face check at clock-in (needs the legal review), hand-off of repeat gig workers to Employee.

## 10. Questions

1. ~~Kind of gig~~: both, shift first (answered 2026-10-29).
2. ~~Who employs the worker~~: the tenant company, as a daily worker (answered 2026-10-29).
3. ~~Default booking mode~~: `approval` (answered 2026-10-29).
4. ~~Who may use gigs first~~: every tenant (answered 2026-10-29).
5. Sent to `legal-review.md` as questions 26 (location at clock-in), 27 (daily-worker limit, reliability suspension) and 28 (identity checks,
   photo comparison, freelancer ratings), priority B: the parts they touch are not coded until answered (added 2026-10-29).

## 11. Project gigs

Project gigs meet two standing rules: the marketplace features are frozen (`erp-direction.md` section 1) and payments, escrow and fees are out of
scope (`CLAUDE.md` rule 7). Decided 2026-10-29 (user): projects posted by a tenant **and** a service catalogue; the catalogue lifts the freeze for this
part only; billing stays out.

### 11.1 Projects posted by a tenant

- A project gig is **posted by a tenant company on its own portal** (the client is the company, never another job seeker), so it works in `company`
  mode and is not a marketplace feature: brief, deliverables, deadline, budget (shown only), required skills.
- Job seekers send a **proposal** (price, time, note, attachments); the company shortlists, chats (Messaging) and **awards** one or more proposals
  (LinkedIn Services, Sribulancer).
- After the award: milestones with a due date, the freelancer submits a deliverable, the company accepts or asks for a revision; acceptance is recorded,
  **payment happens outside the system** (as LinkedIn Services); no escrow, no fee, no invoice.

### 11.2 Service catalogue (decided 2026-10-29, details open)

- A job seeker who offers services gets a **provider profile** (headline, skills, portfolio) and lists **service packages**: title, description,
  deliverables, delivery time, price shown only, up to three tiers (Fastwork).
- A company member with the new permission `gigs.order` sends a **request** on a package (brief, deadline); the provider sends an **offer**, the company
  accepts it, and from there it runs as an awarded project of 11.1 (milestones, deliverables, acceptance).
- **Visibility (decided 2026-10-29):** in `company` mode the catalogue is seen and ordered only by the members of that installation's companies, in
  Workspace; the public Career site does not show it. The provider manages the profile and packages from Career.
- **Moderation (decided 2026-10-29):** a new package, and a change to a visible one, goes to the platform admin's review queue in Console before it is
  visible (statuses `draft`, `pending_review`, `active`, `rejected` with a reason, `paused`, `taken_down`), as job review.
- **Ratings (decided 2026-10-29):** after accepting the work of an order or an awarded project, the company rates the provider 1 to 5 with an optional
  note; one rating per order; the average and the count show on the provider profile to the members who can see the catalogue.

## 12. API plan for #45, shift gigs (schema approved 2026-10-29, not coded)

The schema was confirmed by the user on 2026-10-29; the posting review (statuses `pending_review`, `rejected`) was added the same day.

### 12.1 Tables (context `Gig`, prefix `gig_`; the migration is run by the user)

Foreign keys follow `cross-context-foreign-keys.md`: `RESTRICT` to `company_companies`, `company_locations`, `identity_users`, `reference_job_roles`;
none to `organization_*` (as `workforce_requisitions.cost_centre_id`); keys inside the context are ordinary foreign keys.

| Table | Columns |
|---|---|
| `gig_postings` | `id`, `company_id`, `location_id`, `job_role_id` (nullable), `title` (150), `description` (text, Markdown), `requirements` (text, nullable), `what_to_bring` (text, nullable), `site_contact_name` (100, nullable), `site_contact_phone` (32, nullable), `pay_amount` (bigint, rupiah), `pay_basis` (`hour`, `shift`), `min_age` (smallint, nullable), `required_documents` (json list of kinds, empty by default), `booking_mode` (`approval` default, `instant`), `book_all_shifts` (bool, default false), `organization_unit_id`, `cost_centre_id` (nullable, no FK), `status` (`draft`, `pending_review`, `open`, `rejected`, `closed`, `cancelled`), `status_reason` (255, nullable), `created_by_user_id`, `published_at`, `reviewed_at`, `reviewed_by_admin_id` (nullable), `closed_at`, timestamps; index (`company_id`, `status`) |
| `gig_shifts` | `id`, `posting_id`, `starts_at`, `ends_at` (timestamptz), `break_minutes` (smallint, default 0), `headcount` (smallint), `booked_count` (smallint, kept by the use cases), `status` (`open`, `full`, `started`, `finished`, `cancelled`), `cancel_reason` (255, nullable), timestamps; index (`status`, `starts_at`) |
| `gig_bookings` | `id`, `shift_id`, `user_id` (the worker), `status` (`requested`, `booked`, `confirmed`, `checked_in`, `completed`, `no_show`, `declined`, `released`, `cancelled_by_worker`, `cancelled_by_company`), `requested_at`, `booked_at`, `confirmed_at`, `cancelled_at`, `cancel_reason` (255, nullable), `late_cancel` (bool), timestamps; unique (`shift_id`, `user_id`); index (`user_id`, `status`) |
| `gig_attendances` | `booking_id` (unique), `clock_in_at`, `clock_out_at`, `clock_in_latitude`, `clock_in_longitude`, `clock_out_latitude`, `clock_out_longitude` (decimal 9,6, nullable), `clock_in_inside_fence` (bool, nullable), `auto_clock_out` (bool), `flagged` (bool), `worked_minutes`, `approved_minutes` (int, nullable), `approved_by_user_id` (nullable), `approved_at`, `auto_approved` (bool), `dispute_note` (500, nullable), `disputed_at`, timestamps |
| `gig_ratings` | `id`, `booking_id` (unique), `company_id`, `user_id` (worker), `stars` (smallint 1-5), `note` (500, nullable), `rated_by_user_id`, timestamps |
| `gig_preferred_workers` | `id`, `company_id`, `user_id`, `added_by_user_id`, `created_at`; unique (`company_id`, `user_id`) |
| `gig_reliability_events` | `id`, `user_id`, `company_id`, `booking_id`, `kind` (`late_cancel`, `no_show`), `points` (smallint), `expires_at`, `waived_at`, `waived_by_user_id`, `waive_reason` (255), `created_at`; index (`user_id`, `expires_at`) |
| `gig_worker_documents` | `id`, `user_id`, `kind` (`id_card`, `food_handler`, `health_certificate`, `other`), `file_path` (private disk), `number` (encrypted, nullable), `expires_at` (nullable), timestamps; switched off with `gig.identity_documents_enabled` (question 28) |

### 12.2 Rules in the use cases

- **Book:** posting `open`, shift `open` and in the future, profile complete, age at least `min_age`, required documents present (only when the
  documents switch is on), no overlapping booking of the worker, not suspended (only when the suspension switch is on), preferred-only window
  respected, and the daily-worker limit. `instant` takes a slot at once (`booked`); `approval` creates `requested` without taking a slot, the company's
  approval takes it and fails with 409 `gig-shift-full` when none is left. A posting with `book_all_shifts` books every future shift in one request, all or none.
- **Daily-worker limit:** distinct calendar days (Asia/Jakarta, the day of `starts_at`) of `booked`, `confirmed`, `checked_in` and `completed`
  bookings of the worker in the month, per legal entity of the company through a port (per company for a tenant without a legal entity). Refused at 21 with
  409 `gig-daily-limit-reached`; the roster shows the count from 18.
- **Clock-in** from 15 minutes before the start until the end; **clock-out** after the clock-in. With `gig.clock_in_location_enabled` off (default,
  question 26) only the times are recorded; with it on, the coordinates are required and checked against the location's circle.
- **Timesheet:** `worked_minutes` = clock-out minus clock-in minus `break_minutes`, never negative; the company approves or corrects it; the scheduler
  approves it after `gig.timesheet_auto_approve_days`. One dispute per booking, within 7 days of the approval.
- **Cancellation by the company** of a posting or shift cancels its open bookings with the reason and notifies each worker; bookings are never deleted.
- Erasure of a job seeker account (`ApplicantDataEraser` pattern): bookings and attendances stay for the company's payroll with the worker's name
  replaced; coordinates, documents and rating notes are deleted.

### 12.3 Endpoints (`/api/v1`)

| Who | Endpoints |
|---|---|
| Public | `GET gigs` (offset; `filter[q]`, `filter[province]`, `filter[city]`, `filter[job_role]`, `filter[date_from]`, `filter[date_to]`; `sort` `starts_at`, `-published_at`), `GET gigs/{id}` with its future shifts; in `company` mode only the gigs of that company or group |
| Job seeker | `POST gig-shifts/{id}/book`, `GET me/gig-bookings` (`filter[status]`, `filter[when]=upcoming\|past`), `GET me/gig-bookings/{id}`, `POST me/gig-bookings/{id}/confirm`, `/cancel`, `/clock-in`, `/clock-out`, `/dispute`; `GET`, `POST`, `DELETE me/gig-documents` (switched off) |
| Company | `GET`, `POST company/gig-postings`, `GET`, `PATCH company/gig-postings/{id}`, `POST company/gig-postings/{id}/publish`, `/close`, `/cancel`; `POST company/gig-postings/{id}/shifts`, `PATCH`, `POST company/gig-shifts/{id}/cancel`; `GET company/gig-shifts` (calendar: `filter[from]`, `filter[to]`, `filter[location]`); `GET company/gig-shifts/{id}/bookings` (roster); `POST company/gig-bookings/{id}/approve`, `/decline`, `/cancel`, `/no-show`; `GET company/gig-timesheets` (`filter[state]=pending\|approved\|disputed`, period), `PATCH company/gig-bookings/{id}/timesheet`, `GET company/gig-timesheets/export` (CSV, throttled, audited); `PUT company/gig-bookings/{id}/rating`; `GET`, `POST`, `DELETE company/gig-preferred-workers`; `POST company/gig-reliability-events/{id}/waive` |

| Added for the screens (2026-10-29) | `GET gig-options` (public, cached: the five switches, the confirmation and cancellation windows, the radius), `POST company/gig-timesheets/approve` (`booking_ids`, at most 100, all or none; `gigs.timesheets`), `GET company/gig-reliability-events` (offset; `filter[user]`, `filter[active]`; `gigs.manage`) |
| Platform admin | `GET admin/gig-postings` (`filter[status]`), `POST admin/gig-postings/{id}/approve`, `/reject` (reason), with the job review permission |

Every company row carries its allowed `actions` (permission parity), as the other company lists. Publishing goes to `pending_review` when the
installation's job review policy asks for it (the same setting as jobs), else straight to `open`; an edit of an `open` posting by the review policy's
core fields sends it back to review, as jobs.

### 12.4 Permissions, settings, scheduler, notifications

- **Company permissions** (new group `gigs`): `gigs.manage` (postings, shifts, roster, approvals, cancellations, ratings, preferred workers) and
  `gigs.timesheets` (approve and correct hours, export). The company admin holds both; the Recruiter preset gets both (decided 2026-10-29); the Interviewer preset neither.
- **Settings** (`SettingsCatalog`, company overridable where marked): `gig.enabled` (bool, false), `gig.geofence_radius_m` (200, 50-500, overridable),
  `gig.preferred_window_minutes` (120, 0-1440, overridable), `gig.confirm_window_hours` (24 to 16: two integers), `gig.free_cancel_minutes` (15),
  `gig.late_cancel_hours` (24), `gig.no_show_grace_minutes` (30), `gig.auto_clock_out_hours` (8), `gig.timesheet_auto_approve_days` (3, overridable),
  `gig.points_late_cancel` (1), `gig.points_no_show` (3), `gig.points_valid_days` (30), `gig.suspension_points` (6), `gig.suspension_days` (7),
  `gig.daily_worker_day_limit` (20), and the three legal switches `gig.clock_in_location_enabled`, `gig.reliability_suspension_enabled`,
  `gig.identity_documents_enabled` (all false). Every new key goes into `tests/Feature/Platform/SettingsTest.php`.
- **Scheduler** (every 5 minutes): release unconfirmed bookings at the end of the confirmation window, mark `no_show` after the grace, start and
  finish shifts, close forgotten clock-outs, auto-approve timesheets; daily: expire reliability points.
- **Notifications** (in-app and email; WhatsApp through #33 when it is coded): to the company: new request, worker cancelled, dispute; to the
  worker: approved, declined, cancelled by the company, confirm now, released, shift in 2 hours, timesheet approved.

### 12.5 Order of coding

1. Postings, shifts, public list and detail, booking, approval, cancellation, daily-worker limit, roster, notifications, permissions and settings.
2. Attendance, timesheets, disputes, export, scheduler jobs.
3. Ratings, preferred workers and their window, reliability events and the (switched-off) suspension, worker documents (switched off).

## 13. API plan for #46, project gigs and the service catalogue (schema approved 2026-10-29, not coded)

Same context `Gig`. One **engagement** is the agreed work between a company and a provider, whether it starts from an awarded proposal (11.1) or from a
package request (11.2); milestones, deliveries and the rating hang on it. No money moves: prices and amounts are shown and exported only.

### 13.1 Tables (proposed)

| Table | Columns |
|---|---|
| `gig_provider_profiles` | `user_id` (unique, the job seeker), `headline` (150), `about` (text), `skill_ids` (json, `reference_skills`), `status` (`active`, `hidden`), `rating_average` (decimal 3,2, nullable), `rating_count` (int), timestamps |
| `gig_portfolio_items` | `id`, `user_id`, `title` (150), `description` (text, nullable), `url` (255, nullable), `sort_order`, timestamps; at most 20 per provider |
| `gig_service_packages` | `id`, `user_id`, `job_role_id` (nullable), `title` (150), `description` (text, Markdown), `status` (`draft`, `pending_review`, `active`, `rejected`, `paused`, `taken_down`), `status_reason` (255), `reviewed_at`, `reviewed_by_admin_id`, timestamps; at most 10 per provider |
| `gig_package_tiers` | `id`, `package_id`, `tier` (`basic`, `standard`, `premium`), `name` (60), `deliverables` (text), `delivery_days` (smallint), `revisions` (smallint), `price` (bigint, rupiah); unique (`package_id`, `tier`); `basic` required |
| `gig_projects` | `id`, `company_id`, `job_role_id` (nullable), `title` (150), `brief` (text, Markdown), `deliverables` (text), `skill_ids` (json), `budget_min`, `budget_max` (bigint, nullable), `proposal_deadline` (date), `due_date` (date, nullable), `max_awards` (smallint, default 1), `organization_unit_id`, `cost_centre_id` (nullable, no FK), `status` (`draft`, `pending_review`, `open`, `rejected`, `closed`, `cancelled`), `status_reason`, `created_by_user_id`, `published_at`, `reviewed_at`, `reviewed_by_admin_id`, `closed_at`, timestamps |
| `gig_proposals` | `id`, `project_id`, `user_id`, `price` (bigint), `delivery_days` (smallint), `cover_note` (text), `status` (`submitted`, `shortlisted`, `awarded`, `declined`, `withdrawn`), `decided_at`, timestamps; unique (`project_id`, `user_id`) |
| `gig_engagements` | `id`, `company_id`, `user_id` (provider), `source` (`project`, `package`), `project_id`, `proposal_id`, `package_tier_id` (nullable), `title` (150), `brief` (text), `agreed_price` (bigint, nullable until the offer), `due_date` (date, nullable), `revisions_allowed` (smallint), `status` (`requested`, `offered`, `active`, `completed`, `declined`, `expired`, `cancelled`), `cancelled_by` (`company`, `provider`), `cancel_reason` (255), `requested_by_user_id`, `completed_at`, timestamps |
| `gig_milestones` | `id`, `engagement_id`, `title` (150), `due_date` (date, nullable), `amount` (bigint, nullable, shown only), `sort_order`, `status` (`pending`, `submitted`, `revision_requested`, `accepted`), `revisions_used` (smallint), `accepted_at`, `accepted_by_user_id` (nullable: null when accepted automatically), timestamps |
| `gig_deliveries` | `id`, `milestone_id`, `note` (text), `submitted_at`, `decision` (`accepted`, `revision_requested`, nullable), `decision_note` (text), `decided_by_user_id`, `decided_at` |
| `gig_files` | `id`, `owner_type` (`proposal`, `delivery`, `portfolio_item`), `owner_id` (polymorphic, no FK), `path` (private disk), `name`, `mime`, `size`, `uploaded_by_user_id`, `created_at`; content-checked like chat attachments, at most 5 per owner, 10 MB each |
| `gig_provider_ratings` | `id`, `engagement_id` (unique), `company_id`, `user_id` (provider), `stars` (1-5), `note` (500, nullable), `rated_by_user_id`, timestamps |

### 13.2 Flows

- **Package request:** a member with `gigs.order` requests a tier with a brief and a wish date → `requested`; the provider answers with an **offer**
  (price, due date, milestones, by default one for the whole tier) → `offered`, or declines; the company accepts the offer → `active`. A request
  unanswered after `gig.request_expire_days` (5) becomes `expired`; an offer unanswered after the same period too.
- **Project:** published like a gig posting (job review policy); job seekers with a provider profile send one proposal each until the deadline; the
  company shortlists, declines, and awards up to `max_awards`; an award creates an `active` engagement with the proposal's price and the milestones the
  company sets (default one). When `max_awards` is reached or the deadline passes, the project closes and the open proposals are declined.
- **Milestones:** the provider submits a delivery (note, files); the company accepts it or asks for a revision while `revisions_used` <
  `revisions_allowed` (a tier's `revisions`, or `gig.project_revisions` (2) for projects); with no answer after `gig.delivery_auto_accept_days` (7) it is
  accepted automatically. All milestones accepted → `completed`.
- **Cancellation:** the company or the provider may cancel an engagement that is not `completed`, with a reason; accepted milestones stay accepted.
- **Rating:** after `completed`, one rating by the company (`gigs.order`); the profile's average and count are updated in the same transaction.
- **Conversation:** questions go through the existing chat (one thread per company and job seeker, `phase-2-chat.md`). A job seeker may also start a
  thread with a company where they have a proposal or an engagement, read through a new port in `src/Shared` (`GigRelations`); on the company side such a
  thread is seen by members with `gigs.order`, besides the members who see it today (decided 2026-10-29).
- **Provider age:** a provider profile needs an age of at least 18 (decided 2026-10-29); `PUT me/gig-provider-profile` answers 422 `gig-provider-too-young` otherwise.
- **Export:** `GET company/gig-engagements/export` lists accepted milestones and amounts per period and cost centre for payment outside the system.
- **Erasure** of the provider's account: profile, packages, portfolio and files are deleted; engagements, milestones and ratings stay with the name replaced.

### 13.3 Endpoints (`/api/v1`)

| Who | Endpoints |
|---|---|
| Public | `GET gig-projects` (offset; `filter[q]`, `filter[job_role]`, `filter[skill]`), `GET gig-projects/{id}` |
| Job seeker (provider) | `GET`, `PUT me/gig-provider-profile`; `GET`, `POST`, `PATCH`, `DELETE me/gig-portfolio-items`; `GET`, `POST`, `PATCH`, `DELETE me/gig-packages`, `POST me/gig-packages/{id}/submit`, `/pause`, `/resume`; `POST gig-projects/{id}/proposals`, `GET me/gig-proposals`, `POST me/gig-proposals/{id}/withdraw`; `GET me/gig-engagements`, `GET me/gig-engagements/{id}`, `POST me/gig-engagements/{id}/offer`, `/decline`, `/cancel`; `POST me/gig-milestones/{id}/deliveries`; `POST me/gig-files` (upload, then referenced by id) |
| Company | `GET company/gig-providers` (`filter[q]`, `filter[skill]`, `filter[job_role]`, `filter[min_rating]`), `GET company/gig-providers/{userId}`, `GET company/gig-packages/{id}`, `POST company/gig-package-tiers/{id}/request`; `GET`, `POST company/gig-projects`, `GET`, `PATCH company/gig-projects/{id}`, `POST company/gig-projects/{id}/publish`, `/close`, `/cancel`, `GET company/gig-projects/{id}/proposals`, `POST company/gig-proposals/{id}/shortlist`, `/decline`, `/award`; `GET company/gig-engagements`, `GET company/gig-engagements/{id}`, `POST company/gig-engagements/{id}/accept-offer`, `/cancel`, `PUT company/gig-engagements/{id}/milestones` (before the first delivery), `POST company/gig-deliveries/{id}/accept`, `/request-revision`, `PUT company/gig-engagements/{id}/rating`, `GET company/gig-engagements/export` |
| Platform admin | `GET admin/gig-packages` (`filter[status]`), `POST admin/gig-packages/{id}/approve`, `/reject`, `/take-down`; `GET admin/gig-projects`, `POST admin/gig-projects/{id}/approve`, `/reject` |

### 13.4 Permissions, settings, notifications

- Company permission `gigs.order` (group `gigs`): browse the catalogue, request packages, post projects, award, accept deliveries, rate, export.
  The company admin and the Recruiter preset hold it (decided 2026-10-29).
- Settings: `gig.catalogue_enabled` (bool, false), `gig.request_expire_days` (5), `gig.project_revisions` (2), `gig.delivery_auto_accept_days` (7),
  `gig.max_packages_per_provider` (10). The package review uses the existing job review permission of the platform admin.
- Notifications: provider: new request, offer accepted, proposal shortlisted, awarded or declined, revision requested, delivery accepted, rated,
  package approved or rejected; company: offer received, delivery submitted, provider cancelled, request expired.

### 13.5 Order of coding

1. Provider profile, portfolio, packages and their review, catalogue for companies.
2. Projects, proposals, award.
3. Engagements from both sources, milestones, deliveries, files, cancellation, scheduler (expiry, auto-accept).
4. Ratings, export, notifications.

### 13.6 Questions (answered 2026-10-29)

1. Schema of 13.1: approved.
2. Chat: also with a company where the job seeker has a proposal or an engagement; seen by members with `gigs.order`.
3. Minimum age of a provider: 18.
4. Projects: public on Career like jobs; a proposal needs a signed-in job seeker with a provider profile.
5. Recruiter preset: gets `gigs.order`.

## 14. Screens (decided 2026-10-29, not coded)

Page maps in the format and numbering of `frontend.md` sections 7 to 9 (next free numbers: P16, J19, C33, D29). `frontend.md` points here instead
of repeating the rows. **#45** and **#46** mark the API item each element needs. Every element shows only with the access its API allows (frontend
`CLAUDE.md` rule 20); a page that needs a switched-off setting is hidden, not shown empty (14.5).

### 14.1 Career, public (server-rendered, index)

| # | Page | Path | API | Notes |
|---|------|------|-----|-------|
| P16 | Gig search | `/kerja-harian` (`/en/gigs`) | **#45:** `GET gigs` with `filter[q, province, city, job_role, date_from, date_to]` | Cards grouped by date: time, pay with basis, location, slots left, "Butuh persetujuan" badge. Header menu "Kerja harian" next to "Lowongan" when `gig.enabled`. |
| P17 | Gig detail | `/kerja-harian/{id}` (`/en/gigs/{id}`) | **#45:** `GET gigs/{id}` | Description, requirements, what to bring, location (address, a Leaflet map with OpenStreetMap tiles, 14.5; distance when the browser shares its location), shift table with "Pesan" per shift (or "Pesan semua shift"); signed out: "Masuk untuk memesan" (A1 with return). `JobPosting` JSON-LD with `employmentType` `TEMPORARY`. |
| P18 | Project search | `/proyek` (`/en/projects`) | **#46:** `GET gig-projects` with `filter[q, job_role, skill]` | Cards: budget range, proposal deadline, skills. Header menu "Proyek freelance" when `gig.catalogue_enabled`. |
| P19 | Project detail | `/proyek/{id}` (`/en/projects/{id}`) | **#46:** `GET gig-projects/{id}` | Brief, deliverables, budget, deadlines; "Kirim proposal" leads to J24 (signed in; without a provider profile, to J21 first). |

### 14.2 Career, job seeker area (`/me/**`, client only)

| # | Page | Path | API |
|---|------|------|-----|
| J19 | My shifts and detail | `/me/shifts`, `/me/shifts/{bookingId}` | **#45:** `GET me/gig-bookings` (tabs Mendatang, Menunggu persetujuan, Selesai, Dibatalkan), `GET me/gig-bookings/{id}`; `confirm` (banner in the window), `cancel` (dialog that says when it counts as a late cancellation), `clock-in`, `clock-out` (one large button; with `gig.clock_in_location_enabled` the browser location is asked first and a refusal explains why; the map shows the worker and the circle of the site), timesheet card with `dispute`. Booking itself is on P17 (`POST gig-shifts/{id}/book`), with the refusal reasons shown in words (limit reached, overlap, documents, suspension). |
| J20 | Gig documents | `/me/gig-documents` | **#45, switched off:** `GET`, `POST`, `DELETE me/gig-documents`; hidden while `gig.identity_documents_enabled` is off |
| J21 | Provider profile | `/me/provider` | **#46:** `GET`, `PUT me/gig-provider-profile`, portfolio items (CRUD, files), rating summary; tabs Profil, Portofolio, Paket |
| J22 | Package editor | `/me/provider/packages/{id}` | **#46:** `me/gig-packages` CRUD, `submit`, `pause`, `resume`; three tier columns in the layout of the template `price-v1`; the review status and reason on top |
| J23 | My proposals | `/me/proposals` | **#46:** `GET me/gig-proposals`, `withdraw` |
| J24 | Send a proposal | `/proyek/{id}/proposal` | **#46:** `POST gig-projects/{id}/proposals` with `POST me/gig-files`; `focus` layout like J4 |
| J25 | Engagements and detail | `/me/engagements`, `/me/engagements/{id}` | **#46:** `GET me/gig-engagements[/{id}]`; on `requested` the offer form (`offer`, `decline`); milestones as a timeline with "Kirim hasil" (`POST me/gig-milestones/{id}/deliveries`), revision notes, `cancel`; the company's rating when given |

The job seeker sidebar gets a "Kerja harian dan proyek" group: Shift saya (J19), Dokumen (J20, when on), Profil penyedia (J21), Proposal (J23), Kerja proyek (J25);
items follow the two switches. The dashboard J1 gets a "Shift berikutnya" card (from `GET me/gig-bookings?filter[when]=upcoming`).

### 14.3 Workspace (menu group "Kerja harian dan proyek", after *Talent*)

| # | Page | Path | API | Access |
|---|------|------|-----|--------|
| C33 | Shift calendar | `/kerja-harian/jadwal` | **#45:** `GET company/gig-shifts` (month, week, day, list; location filter), the template `calendar.html` as C26 | `gigs.manage` |
| C34 | Shift postings and editor | `/kerja-harian`, `/kerja-harian/baru`, `/kerja-harian/{id}` | **#45:** `company/gig-postings` (list with status tabs, create, edit, `publish`, `close`, `cancel`), shifts (`POST company/gig-postings/{id}/shifts`, `PATCH`, `cancel`) with a "repeat" helper that creates several shifts in one form (one request per shift) | `gigs.manage` |
| C35 | Shift roster | `/kerja-harian/shift/{id}` | **#45:** `GET company/gig-shifts/{id}/bookings`; `approve`, `decline`, `cancel`, `no-show`; the day count per worker from 18 (warning) and 21 (blocked); clock-in flags; stars (`PUT .../rating`); add or remove a preferred worker | `gigs.manage` |
| C36 | Timesheets | `/kerja-harian/timesheet` | **#45:** `GET company/gig-timesheets` (tabs Menunggu, Disetujui, Disanggah; period), `PATCH company/gig-bookings/{id}/timesheet`, `POST company/gig-timesheets/approve` (bulk, up to 100), `GET company/gig-timesheets/export` | `gigs.timesheets` |
| C37 | Workers | `/kerja-harian/pekerja` | **#45:** `company/gig-preferred-workers` (tab Favorit), `GET company/gig-reliability-events` with `waive` (tab Keandalan) | `gigs.manage` |
| C38 | Service catalogue | `/katalog-jasa`, `/katalog-jasa/penyedia/{userId}`, `/katalog-jasa/paket/{id}` | **#46:** `GET company/gig-providers[/{userId}]`, `GET company/gig-packages/{id}`, `POST company/gig-package-tiers/{id}/request` (dialog: brief, wish date). Grid from the template `ecom_product.html`, package page from `ecom_product-details.html` with the tiers of `price-v1` | `gigs.order` |
| C39 | Projects and proposals | `/proyek`, `/proyek/baru`, `/proyek/{id}` | **#46:** `company/gig-projects` (list, editor, `publish`, `close`, `cancel`), `GET .../proposals` as a comparison table; `shortlist`, `decline`, `award` (dialog with the milestones) | `gigs.order` |
| C40 | Engagements | `/kerja-proyek`, `/kerja-proyek/{id}` | **#46:** `GET company/gig-engagements[/{id}]`, `accept-offer`, `cancel`, `PUT .../milestones`, `accept` or `request-revision` of a delivery, `PUT .../rating`, `GET company/gig-engagements/export` | `gigs.order` |

Sidebar group *Kerja harian dan proyek*: Jadwal shift (C33), Posting shift (C34), Timesheet (C36, count waiting), Pekerja (C37), Katalog jasa (C38), Proyek (C39),
Kerja proyek (C40, count of deliveries waiting). C35 is reached from C33 or C34. The group shows only when `gig.enabled` or `gig.catalogue_enabled`
is on and the member holds one of the three permissions.

### 14.4 Console

| # | Page | Path | API | Access |
|---|------|------|-----|--------|
| D29 | Gig moderation | `/kerja-harian-dan-proyek` | `GET admin/gig-postings` (**#45**), `GET admin/gig-projects`, `GET admin/gig-packages` (**#46**) as three tabs with `approve`, `reject` (reason), `take-down` | the job review permission (`jobs.manage`) |

The `gig.*` settings appear on the existing settings page by their catalog group; no new page.

### 14.5 What the front end reads

- The switches `gig.enabled`, `gig.catalogue_enabled`, `gig.clock_in_location_enabled`, `gig.identity_documents_enabled` and
  `gig.reliability_suspension_enabled` decide which menus and pages exist; they come from `GET gig-options` (12.3).
- **Maps (decided 2026-10-29):** Leaflet (npm, exact version) with OpenStreetMap tiles on P16 (map view next to the list), P17 and J19 (clock-in), using
  `company_locations.latitude` and `longitude`; a location without them shows the address only. Risk reported: the public OSM tile servers allow only
  light use, need the attribution and no heavy prefetch, and each tile request sends the viewer's IP address to the OSM Foundation; the tile URL is a
  front-end setting (`NUXT_PUBLIC_MAP_TILE_URL`) so an installation can switch to its own tile server or a paid provider before traffic grows, and the
  privacy policy names the tile provider (with `legal-review.md` question 25).
- Notification targets (`frontend.md` 12.4) get one row per gig notification of 12.4 and 13.4 when the items are coded.

### 14.6 Questions (answered 2026-10-29)

1. Paths: "follow the existing concept of the project" (the user's answer), read as: Career public paths in Indonesian with translated `/en`
   segments (`/kerja-harian`, `/proyek`; `/en/gigs`, `/en/projects`), the job seeker area in English (`/me/shifts`, `/me/provider`, as `/me/applications`),
   Workspace in Indonesian without a prefix (`/kerja-harian/...`, `/proyek`, `/katalog-jasa`, `/kerja-proyek`, as `/lowongan/baru`), menu labels in
   Indonesian ("Kerja harian", "Proyek freelance").
2. Maps: Leaflet with OpenStreetMap tiles (14.5, with the reported risk).
3. The three API additions: agreed, in 12.3.
4. Coding order: step 8 of `frontend.md` section 13, right after the API of #45 and #46, before go-live.
