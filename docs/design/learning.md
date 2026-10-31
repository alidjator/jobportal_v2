# ONESIS Learning: a learning management system for internal and public learners (designed 2026-10-31, not coded)

A learning management system (LMS) for two audiences: **public learners** (job seekers on ONESIS Career) and **internal learners** (employees of
internal tenants, in Workspace). It changes no code and no schema. It builds on `phase-4-training.md` (the Moodle catalog proxy), `phase-6-assessments.md`
(the assessment engine), `erp-organization.md` (groups, tenants, units), `erp-employee-handoff.md` (#13, the Employee context) and `self-hosted.md`
section 8 (module entitlements). Paid courses are designed here but not coded ([Paid courses](#4-paid-courses)); every other part of plans and billing
stays out of scope.

## Decisions

- 2026-10-31 (user, D1): **we build our own `Learning` bounded context** (`src/Learning`), not a Moodle integration. The `Training` context keeps showing
  the Moodle catalog until its courses are moved ([Moodle](#5-moodle)).
- 2026-10-31 (user, D2): **a new licensed module, ONESIS Learning**, controlled by the module entitlements; public screens in Career, internal screens
  in Workspace, platform administration in Console.
- 2026-10-31 (user, D3): **internal learners are employees of the Employee context**, not company members; the internal side waits for E1 to E4 and #13.
  The public side does not wait for Employee.
- 2026-10-31 (user, D4): **public courses are authored by the platform admin (Console) and by companies (Workspace)**; partner institutions later (N6).
- 2026-10-31 (user, D5): **quizzes and final exams reuse the Assessment engine** (questions, drawing, section timers, server scoring, integrity
  signals, long-text scoring); `Learning` talks to Assessment through a port and events, never through its use cases.
- 2026-10-31 (user, D6): **lesson video is uploaded (MP4, own storage) or embedded (YouTube, Vimeo)**; adaptive streaming (HLS) comes later (N7).
- 2026-10-31 (user, D7): **paid courses are designed but not coded**; an exception to the billing scope that holds for this module only.
- 2026-10-31 (user, D10): **paid courses: the platform sells, one price, lifetime access, no refunds, Midtrans, a new `Commerce` context**
  ([Paid courses](#4-paid-courses)).
- 2026-10-31 (user, D8): **coding order**: the public side after E1 to E4 (module entitlements), the internal side after #13.
- 2026-10-31 (user): the feature priorities of [section 3](#3-features) are accepted as proposed.
- 2026-10-31 (user, K1): **owner and audience**: `company_id` nullable (null = the platform) and `audience` `public` or `internal`; a platform course
  is always public; a company's public course shows on its own portal and on the marketplace ([Domain model](#6-domain-model)).
- 2026-10-31 (user, K2): **an aggregate `CourseVersion`**: a published version is locked, a change is a new draft copied from the latest version,
  learners stay on the version they enrolled in, the slug does not change.
- 2026-10-31 (user, K3): **every learner is keyed by `user_id`**; a nullable `employee_id` is added in the internal step (every employee has a
  `user_id`, `erp-employee-handoff.md` section 2).
- 2026-10-31 (user, K4): **neutral source columns on `assessment_invitations`**: `source` (`recruitment`, `learning`) and `source_id`;
  `application_id`, `job_id` and `company_id` become nullable ([Assessment changes](#7-assessment-changes)).
- 2026-10-31 (user, R1, R2): uploads: MP4 video up to 500 MB, PDF up to 20 MB, cover JPG, PNG or WebP up to 2 MB.
- 2026-10-31 (user, P1): **a company sees a public learner's name, email and progress only with the learner's consent**, asked at enrolment and
  withdrawable; without it the company sees counts only. Legal question 30 (`legal-review.md`, priority D) ([Permissions](#8-permissions-and-endpoints)).
- 2026-10-31 (user, P2): **a course author may create quiz tests** with `learning.courses.author`, without `assessments.author`.
- 2026-10-31 (user, P2a): **quiz tests are kept apart by a `purpose` column** (`recruitment`, `learning`) on `assessment_tests`; the
  recruitment library and the default stage tests list `recruitment` only, and `learning.courses.author` changes `learning` tests only.
- 2026-10-31 (user, P3): **any account with a verified email may enrol in a public course** (job seekers, company members, psychologists).
- 2026-10-31 (user, P4): **a company's public course goes live when published**; the platform admin moderates afterwards, and the setting
  `learning.company_public_courses_enabled` switches company public courses off.
- 2026-10-31 (user, P5): **video is uploaded in chunks** through the API; the nginx and PHP body limits stay small.
- 2026-10-31 (user, S1 to S4): **files stay on the server's private disk** (the volume is enlarged first), chunks of 8 MB, paths
  `learning/<kind>/<hex>`, the cover served through an API route ([Files](#files-r1-r2-s1-to-s4)).
- 2026-10-31 (user, E1 to E7): **enrolment rules**: modes `self`, `assigned`, `purchased`; `expired` only for recurring certification, a passed
  due date is a computed `overdue`; re-enrolment, assigned courses, the internal catalog, archived courses, and an optional window and seat limit on
  the course ([Enrolment](#enrolment)).
- 2026-10-31 (user, G1 to G8): **grading and ranking**: `pass_mark` in percent, the last attempt counts and a passed quiz cannot be retaken,
  essays wait in `awaiting_scoring`, a weighted final score, score and band on the certificate (switchable per course), integrity only flagged,
  catalog sort keys, and a rank column in company reports ([Grading and ranking](#grading-and-ranking)).
- 2026-10-31 (user, L1 to L9): **learning methods and evaluation**: pre-test and post-test, an optional sequential order, answer feedback for
  learning tests, practice quizzes still limited, a course feedback survey, live sessions through Janus (from the start), an external link or on
  site, tasks with a rubric, and video answers behind a switch ([Learning methods and evaluation](#learning-methods-and-evaluation)).
- 2026-10-31 (user, C1 to C9): **course management**: cohorts, a team per course, review before publishing through Approval, duplicating,
  announcements, invitations, linked translations, actions on learners, and deleting a never-published draft ([Course management](#course-management)).
- 2026-10-31 (user, J1 to J10): **scheduling**: scheduled publishing, module release (drip), a time zone per session and cohort, conflict checks,
  calendar files, session series with an attendance threshold, certificate validity, automatic cohort status and an agenda ([Scheduling](#scheduling)).
- 2026-10-31 (user, A1 to A7): **audit trail**: a catalog of `learning.*` actions through the shared `AuditTrail`, no audit of learning activity,
  score corrections with a reason, a consent history, durable training records apart from the 12-month log, an erasure step, a log per course and
  exports ([Audit trail](#audit-trail)).
- 2026-10-31 (user, R1 to R8): **reports and dashboards**: cards on the existing role dashboards, company analytics across courses, a compliance
  matrix, question and drop-off analysis, a group dashboard, Console reports, all computed by direct queries with a short cache ([Reports and dashboards](#reports-and-dashboards)).
- 2026-11-01 (user, U1 to U7): **user management**: three built-in Learning presets, outside trainers as members, listeners for removed and
  suspended members, certificate reissue with the current name, support access to content only, admin permissions in the existing role editor,
  and learner groups with outside coordinators who see consenting members' progress ([User management](#user-management)).

## 1. What exists today (read 2026-10-31)

| Asset | State | Use for Learning |
|---|---|---|
| `Training` | Read-only proxy of the PKP Moodle (16 courses); no tables, no enrolment, no single sign-on, no certificates | Stays until the courses move |
| `Assessment` | Question bank, question types, drawing, timed sections, server scoring, integrity tiers, assessors for long text, numbered PDF reports | Quizzes, final exams, certificate rendering |
| `Organization` | Group, legal entity, tenant, unit structure, group permissions across tenants | Assignment per unit, group-level reports |
| Employee (#13, not coded) | Hand-off of a hire, HR role | The internal learner; onboarding courses |
| Module entitlements (`self-hosted.md` section 8) | Workforce, Employee and the add-ons are licensed | ONESIS Learning is one more licensed module |
| Career modes and installation type | Marketplace or company portal, `single` or `multi`, draft and publish | Public catalog per portal |
| `JobSeeker`, `Matching`, CV generator | Profile, skills, rule-based recommendations | Certificates on the profile and CV, skills earned |
| Notification, WhatsApp, Newsletter, Campaign | Email, in-app, WhatsApp, bulk sends | Reminders, due dates, course promotion |
| Tele-interview (Janus), interview calendar | WebRTC video, scheduling | Live classes |

## 2. Audiences

| | Public (Career) | Internal (Workspace) |
|---|---|---|
| Learner | Job seeker (`identity_users`) | Employee (`employee_id`) |
| Enrolment | Self-enrolment | Assignment with a due date, and self-enrolment in internal courses |
| Authors | Platform admin, companies | Companies (HR or a learning role) |
| Can be coded | After E1 to E4 | After #13 |
| Waits for Employee | Nothing | M4 (assignment), M10 (compliance reports), S2, S3, S8, S11 |

## 3. Features

M = must-have, S = should-have, N = nice-to-have. "Reuses" names the existing context or pattern.

### Must-have

| Code | Feature | For | Reuses |
|---|---|---|---|
| M1 | Course catalog: course, module, lesson, category, search; visibility public, internal tenant or chosen units | Both | ReferenceData (tags, skills), sticky search bar |
| M2 | Lesson content: video, PDF, rich text, links | Both | CKEditor, private disk |
| M3 | Draft and publish, course versions (active learners stay on their version) | Both | The portal draft-and-publish pattern |
| M4 | Enrolment: self-enrolment (public), mandatory assignment with a due date (internal) | Both | Organization units, Notification |
| M5 | Progress per lesson, resume where left, completion rule per lesson (watched share, read, quiz passed) | Both | |
| M6 | Quizzes and final exam with a pass mark | Both | Assessment engine |
| M7 | Numbered PDF certificate and a public verification page | Both | Report rendering and numbering |
| M8 | Roles and permissions: learning admin, course author, learner | Both | Permission catalog and presets |
| M9 | Notifications: assigned, due soon, completed, certificate issued | Both | Notification, email, WhatsApp, queue `bulk` |
| M10 | Progress reports per course and per learner, export | Internal | Export (phase 2), Dashboard |
| M11 | Licensed module with its switch in Console "Pengaturan" | Both | Module entitlements, settings |
| M12 | Indonesian and English | Both | API and front-end i18n |

### Should-have

| Code | Feature | For | Reuses |
|---|---|---|---|
| S1 | Learning paths: ordered courses and prerequisites | Both | |
| S2 | Onboarding: mandatory courses assigned when HR starts onboarding | Internal | Employee hand-off #13 |
| S3 | Recurring certification: expiry, recertification, reminders | Internal | Scheduler, Notification |
| S4 | Certificates and earned skills on the profile, the CV and in matching | Public | JobSeeker, CV generator, Matching J8 |
| S5 | Courses suggested or required by a job; courses for missing skills | Public | Job, screening, Matching |
| S6 | Live or classroom sessions: schedule, seats, attendance (designed, L7) | Both | Interview calendar, Janus |
| S7 | Tasks: an uploaded file scored by an instructor with a rubric (designed, L8) | Both | Learning itself (Assessment has no file answer for skill tests) |
| S8 | Group dashboard across tenants (designed, R7) | Internal | Organization group permissions |
| S9 | Course ratings and reviews | Public | Review |
| S10 | Moving the Moodle courses, or showing both catalogs side by side | Both | Training |
| S11 | Requests to attend external training through approvals | Internal | Approval |

### Nice-to-have

| Code | Feature | For | Reuses |
|---|---|---|---|
| N1 | SCORM, xAPI or LTI import | Both | |
| N2 | Gamification: points, badges, leaderboard | Both | |
| N3 | Discussion or questions per course | Both | Messaging |
| N4 | Personal course recommendations (rules, no machine learning) | Public | Matching |
| N5 | Course promotion through newsletters and campaigns | Public | Newsletter, Campaign |
| N6 | Partner institutions author and sell courses | Public | Provider institutions |
| N7 | Adaptive video (HLS) and watermark | Both | |
| N8 | AI help: quiz generator, lesson summary | Both | |
| N9 | Open Badges and sharing to LinkedIn | Public | Certificates (M7) |
| N10 | Training needs analysis from assessment results | Internal | Assessment, Employee |

## 4. Paid courses

Designed, not coded (D7); decided by the user on 2026-10-31 (D10, B1 to B8). Nothing here is built until the user lifts the billing scope and legal
question 31 is answered. Internal courses are always free for employees.

| Code | Topic | Decision |
|---|---|---|
| B1 | Seller | **The platform only**; companies selling their courses comes later (the order keeps a `seller`, so it can be added without redesign) |
| B2 | Price | **One price per course**, paid once; bundles and subscriptions are nice-to-have |
| B3 | Access | **For life**, including later versions, and kept when the course is archived |
| B4 | Refunds | **None**: every sale is final (open to change by legal question 31 (b)) |
| B5 | Payment gateway | A port `PaymentGateway` (`createCharge`, `verifyNotification`, `status`) with **Midtrans (Snap)** as the first adapter; webhooks verified by signature and idempotent per order; no gateway configured means paid courses cannot be bought (self-hosted operators set their own) |
| B6 | Vouchers | Percentage or fixed codes with a quota and validity (nice-to-have) |
| B7 | Context | **A new `Commerce` context** (prefix `commerce_`): products, prices, orders, payments, vouchers; `Commerce` keeps `product_type` = `course` and `product_id`; `Learning` enrols the buyer on the event `OrderPaid` |
| B8 | Tax and legal | Legal question 31 (priority B): VAT on digital services, receipts or tax invoices, refunds under consumer law, what lifetime access binds the operator to |

Notes:

- Midtrans has no built-in split of payments; when companies sell (B1), a manual payout or a second adapter (for example Xendit) behind the same port
  is needed.
- `Commerce` is a Core context switched on only where paid features exist; its layers join `tests/Arch/LayersTest.php` when coded.

## 5. Moodle

`Training` stays as it is (`GET training/courses`, `GET me/training/courses`) until the 16 Moodle courses are rebuilt or imported in `Learning` (S10);
then the Career training page reads `Learning` and `Training` is retired. How and when is decided with S10.

## 6. Domain model

Agreed by the user on 2026-10-31 (K1 to K4). Links to other contexts follow `cross-context-foreign-keys.md`: foreign keys only to `identity_users`,
`company_companies` and `reference_*` (and to `employee_employees` once it exists, if added to that list).

### Aggregates

| Aggregate | Holds | Rules |
|---|---|---|
| `Course` | Owner, audience, category, slug, skills earned | Never deleted once it has learners; archived instead |
| `CourseVersion` | Modules and lessons of one version | `draft`, `submitted` (C3), `scheduled` (J1), `published`, `retired`; a published version is locked; one published version per course; publishing a new one retires the previous |
| `Enrollment` | One learner on one version, with progress per lesson | Locked to the version at enrolment; completed when every required lesson is completed |
| `Certificate` | A numbered certificate | Copies the learner name, course title and date; never changes with the course; can be revoked with a reason |

### Tables (prefix `learning_`)

| # | Table | Main columns | Notes |
|---|---|---|---|
| 1 | `learning_categories` | `name` json (id/en), `slug`, `sort_order`, `is_active` | Master-data pattern of phase 0; managed by the platform admin |
| 2 | `learning_courses` | `company_id` (null = platform), `audience` (`public`, `internal`), `category_id`, `slug`, `status` (`active`, `archived`), `enrollment_opens_at`, `enrollment_closes_at`, `seat_limit` (all three nullable, E7), `translation_of` (nullable, C7), `certificate_valid_months` (nullable = never expires, J9), `created_by`, timestamps | Keys to `company_companies`, `identity_users` |
| 3 | `learning_course_versions` | `course_id`, `number`, `title`, `summary`, `description`, `cover_path`, `language` (`id`, `en`), `estimated_minutes`, `certificate_shows_score` (default true, G5), `sequential` (default false, L2), `status` (`draft`, `submitted`, `scheduled`, `published`, `retired`), `submitted_at`, `publish_at` (nullable, J1), `published_at`, `published_by` | Partial unique: one `published` per course |
| 4 | `learning_modules` | `version_id`, `position`, `title`, `release_after_days`, `release_at` (both nullable, at most one; J2) | |
| 5 | `learning_lessons` | `module_id`, `position`, `title`, `type` (`video_upload`, `video_embed`, `pdf`, `text`, `link`, `quiz`, `live_session`, `task`), `body`, `file_path`, `embed_url`, `duration_seconds`, `is_required`, `completion_rule` (`opened`, `watched_percent`, `quiz_passed`, `attended`, `task_passed`), `completion_threshold`, `attendance_threshold` (percent, default 100, J6), `test_id`, `quiz_role` (`graded`, `pre_test`, `post_test`; L1), `paired_lesson_id` (L1), `feedback_mode` (`never`, `after_pass`, `always`; default `after_pass`; L3), `pass_mark` (percent 0 to 100, G1), `weight` (default 1, G4), `max_attempts` | `test_id` is an Assessment test; embeds from YouTube and Vimeo only |
| 6 | `learning_course_skills` | `course_id`, `skill_id` | Key to `reference_skills` (S4) |
| 7 | `learning_enrollments` | `course_id`, `version_id`, `user_id`, `cohort_id` (nullable, C1), `source` (`self`, `assigned`, `purchased`, `invited`), `assigned_by`, `order_id` (nullable, no foreign key; E1), `invitation_id` (nullable, C6), `due_at`, `status` (`active`, `completed`, `withdrawn`, `expired`), `progress_percent`, `final_score` (percent, nullable, G4), `completed_manually_by`, `completion_reason` (C8), `enrolled_at`, `completed_at` | One active enrolment per learner per course; `employee_id` added in the internal step (K3) |
| 8 | `learning_lesson_progress` | `enrollment_id`, `lesson_id`, `status` (`not_started`, `in_progress`, `completed`), `position_seconds`, `watched_percent`, `completed_at` | Unique `enrollment_id` + `lesson_id` |
| 9 | `learning_quiz_attempts` | `enrollment_id`, `lesson_id`, `assessment_invitation_id`, `status` (`awaiting_scoring`, `scored`; G3), `score`, `passed` (both null while awaiting), `integrity_tier`, `voided_at`, `voided_by` (G6), `submitted_at` | Filled from Assessment events; limited by `max_attempts` |
| 10 | `learning_certificates` | `enrollment_id` (unique), `number`, `verification_code`, `learner_name`, `course_title`, `final_score`, `band` (copies, null when hidden or no quiz; G5), `issued_at`, `expires_at` (nullable, J9), `reissued_at`, `reissue_count` (U4), `pdf_path`, `revoked_at`, `revoke_reason` | Public verification page by `verification_code` |
| 11 | `learning_certificate_sequences` | `year`, `last_number` | Numbering per year, like the Assessment reports |
| 12 | `learning_course_units` | `course_id`, `unit_id` | Internal step: restricts an internal course to units |
| 13 | `learning_assignments` | `course_id`, target (`employee`, `unit`, `tenant`), `target_id`, `due_in_days`, `created_by` | Internal step |
| 14 | `learning_course_feedback` | `enrollment_id` (unique), `course_id`, `version_id`, `rating` (1 to 5), `comment`, `hidden_at`, `hidden_by`, timestamps | L5 |
| 15 | `learning_live_sessions` | `lesson_id`, `cohort_id` (nullable, C1), `starts_at`, `ends_at`, `mode` (`online_janus`, `online_link`, `onsite`), `url`, `location`, `seat_limit`, `host_user_id`, `check_in_code`, `recorded`, `series_id` (nullable, J6), `timezone` (J3) | L7 |
| 16 | `learning_session_registrations` | `session_id`, `enrollment_id`, `status` (`registered`, `attended`, `absent`, `cancelled`), `checked_in_at`, `recording_consent_at` | Unique `session_id` + `enrollment_id`; L7 |
| 17 | `learning_task_criteria` | `lesson_id`, `position`, `title`, `max_points` | The rubric; L8 |
| 18 | `learning_task_submissions` | `enrollment_id`, `lesson_id`, `file_path`, `text`, `status` (`submitted`, `returned`, `passed`, `failed`), `score`, `feedback`, `graded_by`, `submitted_at`, `graded_at` | L8 |
| 19 | `learning_task_scores` | `submission_id`, `criterion_id`, `points`, `comment` | L8 |
| 20 | `learning_cohorts` | `course_id`, `name`, `starts_at`, `ends_at`, `seat_limit`, `enrollment_closes_at`, `status` (`planned`, `running`, `finished`, `cancelled`), `timezone` (J3) | C1 |
| 21 | `learning_course_members` | `course_id`, `user_id`, `role` (`owner`, `instructor`, `grader`), `cohort_id` (nullable) | C2 |
| 22 | `learning_announcements` | `course_id`, `cohort_id` (nullable), `title`, `body`, `sent_by`, `sent_at` | C5 |
| 23 | `learning_invite_links` | `course_id`, `cohort_id` (nullable), `token`, `expires_at`, `max_uses`, `uses`, `created_by` | C6 |
| 24 | `learning_invitations` | `course_id`, `cohort_id` (nullable), `email`, `user_id` (nullable), `token`, `status` (`sent`, `accepted`, `declined`, `expired`), `invited_by`, `expires_at` | C6 |
| 25 | `learning_session_series` | `lesson_id`, `cohort_id` (nullable), `recurrence` (`daily`, `weekly`), `weekdays`, `occurrences`, `seat_limit`, `timezone` | J6 |
| 26 | `learning_sharing_consents` | `enrollment_id`, `granted`, `consent_text_version`, `occurred_at`, `ip_address` | History of the consent of P1; kept while the enrolment exists (A4) |
| 27 | `learning_learner_groups` | `company_id` (null = platform), `name`, `code`, `join_mode` (`invite`, `code`, `email_domain`), `email_domain`, `status` | U7 |
| 28 | `learning_learner_group_members` | `group_id`, `user_id`, `role` (`member`, `coordinator`), `consented_at`, `joined_at` | Unique `group_id` + `user_id`; U7 |
| 29 | `learning_course_groups` | `course_id`, `group_id` | A course opened to a group; U7 |

### Files (R1, R2, S1 to S4)

| Kind | Limit | Path on the private disk | Served |
|---|---|---|---|
| Lesson video | MP4, 500 MB | `learning/videos/<hex>.mp4` | A signed route streamed by `FileStreamResponse` (byte ranges), valid `learning.video_url_minutes` |
| Lesson PDF | 20 MB | `learning/documents/<hex>.pdf` | A signed route |
| Course cover | JPG, PNG or WebP, 2 MB | `learning/covers/<hex>.<ext>` | An API route with cache headers, like company logos (S4; replaces "public disk") |
| Certificate | | `learning/certificates/<hex>.pdf` | `GET me/learning/certificates/{id}/pdf` |
| Upload chunks | 8 MB each | `learning/uploads/<upload_id>/<n>.part` | Never; joined on `complete`, then deleted |

- **Storage (S1, user, 2026-10-31):** the application's private disk on the server (`FileStorage`, `FileStreams`), no separate disk or object
  storage. Checked 2026-10-31: the dev server's `/` is 96% full (5.5 GB free), so the operator enlarges the volume before video uploads are used
  (`docs/deployment-checklist.md` section 1).
- **Chunks (S2):** 8 MB each, below nginx `client_max_body_size 12m`, so at most 63 chunks for 500 MB. An upload session lives 24 hours; the
  scheduler deletes abandoned chunks. On `complete` the server joins the chunks, checks the SHA-256 sent by the client and the type with `finfo`.
- **Directories (S3):** `<context>/<kind>/<random hex>.<ext>`, as the other contexts do.
- **Video length:** ffmpeg and ffprobe are not installed on the host, so `duration_seconds` comes from the browser's metadata of the file.

### Enrolment

Agreed by the user on 2026-10-31 (E1 to E7).

| Mode | `source` | Who | How | Due date | Leaving |
|---|---|---|---|---|---|
| Self, public course | `self` | Any account with a verified email (P3) | `POST learning/courses/{id}/enroll`, with `share_with_company` for a company course (P1) | None | `withdraw` |
| Self, internal course (E5) | `self` | Employees of the owning tenant, in the course's units when `learning_course_units` has rows | `GET company/learning/catalog`, then the same `enroll` | None | `withdraw` |
| Assigned (internal step) | `assigned` | Employees, by person, unit or tenant | `learning_assignments`, or onboarding (S2) | `due_at` from `due_in_days` | Only HR cancels the assignment; the learner's `withdraw` answers 409 (E4) |
| Invited (C6) | `invited` | Anyone invited by email, CSV or a private link | `GET learning/invitations/{token}`, then `claim` after signing in or up | Cohort's end, if any | `withdraw` |
| Purchased (designed only, D10) | `purchased` | Any account with a verified email | `Learning` enrols the buyer on `OrderPaid`; `order_id` is kept (E1) | None | `withdraw`; enrolling again needs no new purchase (B3) |

Rules:

- **Status (E2):** `active`, `completed`, `withdrawn`, `expired`. `expired` is used only by recurring certification (S3): a `completed` enrolment
  turns `expired` when its certificate's validity ends, and a new assignment follows. A passed `due_at` changes no status: the API returns
  `overdue: true`, the learner may still finish, and reports tell on time from late. A `purchased` enrolment never expires.
- **Enrolling again (E3):** after `withdrawn`, a new enrolment on the latest published version; after `completed`, the learner may replay the course
  without a new certificate, except for recurring certification.
- **Archived course (E6):** active learners may finish; nobody new may enrol.
- **Window and seats (E7, nice-to-have):** `enrollment_opens_at`, `enrollment_closes_at` and `seat_limit` on the course, so a new version does not
  reset them; seats count `active` and `completed` enrolments across versions, not `withdrawn`; assigned enrolments ignore both. Seats of live
  sessions (S6) are designed with S6.

### Course management

Agreed by the user on 2026-10-31 (C1 to C9).

| Code | Topic | Rule |
|---|---|---|
| C1 | Cohorts | A course may run in cohorts (`learning_cohorts`): an enrolment and a live session may belong to one; content opens at `starts_at`, the default due date is `ends_at`, seats are counted per cohort, reports filter by cohort, and an assignment may name a cohort. A course without cohorts stays self-paced |
| C2 | Team per course | `learning_course_members`: `owner` (edits the course and its team; the creator becomes owner), `instructor` (cohorts, sessions, announcements, actions on learners, scoring), `grader` (scores tasks and essays of that course), optionally for one cohort. The catalog permissions still apply but reach only courses of one's team; `learning.courses.view_all` reaches every course of the company. Members only; platform courses use `learning.courses.manage` |
| C3 | Review before publishing | Approval subject `learning_course_version`: when the company has a flow for it, publishing submits the version (`submitted`, locked; withdrawing returns it to `draft`; a rejection returns it with the reason); without a flow it publishes at once. Platform courses publish at once |
| C4 | Duplicate | Copies the latest version into a new draft course; quiz tests are copied as new `learning` tests |
| C5 | Announcements | Per course or cohort, in-app and email, on the `bulk` queue; by the owner or an instructor |
| C6 | Invitations | Private links with expiry and use limit; invitations by email to an existing account (in-app and email) or to an email without an account (a token link as in #31, behind the existing `invitations.email_enabled`); CSV import of at most 500 rows on the `bulk` queue; enrolments from them have `source = invited` |
| C7 | Two languages | One language per version; a translation is a separate course linked by `translation_of`; the catalog shows the course in the request's language and links the other |
| C8 | Actions on learners | Extend the due date, reset progress, mark completed by hand (with a reason, kept in `completion_reason`), remove the learner; each is written to the audit log; by the owner or an instructor |
| C9 | Delete | A course that was never published and has no learners may be deleted; otherwise it is archived |

### Scheduling

Agreed by the user on 2026-10-31 (J1 to J10). Times are stored in UTC.

| Code | Topic | Rule |
|---|---|---|
| J1 | Scheduled publishing | `publish_at` on a version: a ready version (approved first when C3 applies) becomes `scheduled`, locked, and the scheduler (every minute) publishes it at that time; cancelling returns it to `draft` (a new submission when C3 applies); the owner is told |
| J2 | Module release | `release_after_days` (from the cohort's `starts_at`, else the enrolment date) or `release_at` per module; a module opens when its time has come and, with `sequential`, the earlier required lessons are completed; pre-tests are never held back; the outline shows `locked` with `releases_at` and a held lesson answers 409; learners are told in-app; assigning warns when the due date comes before the last release |
| J3 | Time zone | `timezone` on live sessions, series and cohorts: `Asia/Jakarta` (default), `Asia/Makassar` or `Asia/Jayapura`; emails and the web show the time in that zone with its label (WIB, WITA, WIT); `.ics` stays UTC; no time zone per account |
| J4 | Conflicts | The host is warned when a session overlaps their other sessions or interviews; a learner may not register for two overlapping sessions (409) |
| J5 | Calendar files | A `.ics` (UTC, with the place or the link) in the email of a session or cohort registration, as for interviews |
| J6 | Series | `learning_session_series` creates daily or weekly meetings at once; a learner registers for the series once; a change applies to one meeting or to every later one; `attended` needs at least `attendance_threshold` percent of the meetings; single sessions stay possible |
| J7 | Changed or cancelled session | Registered learners are told with a new `.ics`; when a session is cancelled or its new time lacks seats, the registration is cancelled and the learner picks another |
| J8 | Cohort status | The scheduler moves `planned` to `running` at `starts_at` and `running` to `finished` at `ends_at` |
| J9 | Certificate validity | `certificate_valid_months` on the course is copied as `expires_at` at issue; reminders 30 and 7 days before; on expiry the enrolment turns `expired` (E2), an internal assigned course is assigned again with a new due date, a public learner may enrol again for a new certificate; the verification page shows the validity; an expired certificate stays on the profile with a label and stops counting as a verified skill in matching; a later change affects new certificates only |
| J10 | Agenda | `GET me/learning/agenda` (sessions, due dates, cohorts, module releases) and `GET company/learning/agenda` (sessions one hosts); `from` and `to`, at most 62 days |

### Audit trail

Agreed by the user on 2026-10-31 (A1 to A7). `Learning` writes through the shared port `AuditTrail` (`platform_audit_logs`, append-only, kept 12
months, no sensitive values in `metadata`); entries carry the company, so they also show in the company and group activity logs.

| Code | Topic | Rule |
|---|---|---|
| A1 | Audited actions | `learning.<subject>_<verb>`: course and version lifecycle (created, submitted, scheduled, published, archived, deleted, duplicated), team and cohort changes, assignments, the actions of C8, scores and score corrections, voided attempts (G6), certificates issued and revoked, moderation, invitations and imports, consent changes (P1) |
| A2 | Learning activity | Not audited (opening a lesson, watching a video): the record is `learning_lesson_progress`, the quiz attempts and the integrity events |
| A3 | Score corrections | The old and the new score and the grader in `metadata`; a reason is required |
| A4 | Consent history | `learning_sharing_consents` (granted or withdrawn, the version of the consent text, time, IP), kept while the enrolment exists; readable by the learner and the platform admin |
| A5 | Retention | The audit log stays 12 months; the durable training record is Learning's own tables (enrolments, scores, attendance, tasks, certificates), kept while the employee record exists (internal; its retention after leaving follows Employee, #13) or while the account exists (public). A new step of the account erasure chain (pattern of `EraseAssessmentDataWhenUserAnonymized`) deletes progress, task submissions and their files, consents, feedback and session registrations, and anonymises enrolments and certificates; the verification page then says the certificate is not available; quiz attempts follow the Assessment erasure for `source = learning` |
| A6 | Log per course | `GET company/learning/courses/{id}/activity`: the entries about the course, its versions and cohorts; owner and `learning.courses.view_all` |
| A7 | Exports | A compliance CSV per period (enrolments, due dates, completion, scores, certificates, attendance) and an export of the company's `learning.*` audit entries, with the same fields the company activity log shows (legal question 10 decides IP and browser) |

### Reports and dashboards

Agreed by the user on 2026-10-31 (R1 to R8). Follows `phase-4-dashboards.md`: the `Dashboard` context reads Learning through a read interface in
`Shared`, never its tables; days are counted in Asia/Jakarta.

| Code | Topic | Rule |
|---|---|---|
| R1 | Dashboard cards | Learning cards on the existing endpoints. `me/dashboard`: active courses, nearest due dates, next session, certificates (with those expiring). `company/dashboard`: active learners, completion rate, overdue learners, tasks waiting for a score; shown only to holders of `learning.reports.view`. `admin/dashboard`: public courses, new enrolments, moderation reports; shown only with `learning.courses.manage` or `learning.moderate` |
| R2 | Company analytics | Per course: enrolments, completion rate, mean final score, mean learning time, rating, mean pre/post gain (L1); filters `from`, `to`, category, unit, cohort; a 30-day daily series |
| R3 | Compliance matrix (internal step) | Employees × required courses with the state not started, in progress, overdue, completed, expired; filter by unit; compliance percentage per unit; exported with A7 |
| R4 | Question analysis | For `learning` tests: share of correct answers per question and the wrong options chosen most; for the owner and instructors; cached 1 hour |
| R5 | Drop-off | Per lesson, the share of learners whose last completed lesson it is and who have not returned for 14 days; in the course report |
| R6 | Computation | Direct aggregate queries with a cache of 1 to 5 minutes, as the existing dashboards; indexes on `course_id`, `status`, `enrolled_at`, `completed_at`; a daily summary table (pattern of `job_view_daily`) only if the volume asks for it, without changing the API |
| R7 | Group dashboard (S8, internal step) | The numbers of R2 and R3 per tenant of the group, with `group.learning.view` |
| R8 | Console reports | Platform courses and the public courses of every company: enrolments, completion rate, rating, moderation reports |

Public learners without the consent of P1 are counted in every number but never listed.

### User management

Decided by the user on 2026-11-01 (U1 to U7). Accounts, statuses and sign-in stay with Identity; members get access only through presets
(`phase-8-company-permissions.md`).

| Code | Topic | Rule |
|---|---|---|
| U1 | Built-in presets | "Admin pembelajaran" (every `learning.*` permission), "Pengajar" (`learning.courses.author`, `learning.tasks.grade`), "Penilai" (`learning.tasks.grade`); Company admin holds them all; Recruiter and Interviewer get none; the Learning presets are offered only when the company's package includes ONESIS Learning; a new group `learning` in `CompanyPermission::group()` |
| U2 | Outside trainers | Invited as company members with the "Pengajar" preset, under the tenant's rules (TOTP for internal tenants) |
| U3 | Removed or suspended member | `CompanyMemberRemoved`: the course team rows go; a course left without an owner passes to the company admins, who are told to name one. `CompanyMemberSuspended`: the rows stay unusable until `CompanyMemberReactivated`; the admins are told. Either way, future sessions they host are flagged as needing a host (not cancelled), their pending scoring returns to the course's other graders, invitations they sent stay valid, past scores and audit entries keep their name, their own public enrolments are untouched (internal ones follow Employee, since removal is not leaving). Audited `learning.course_member_removed`, `learning.course_owner_reassigned`, `learning.session_host_missing` |
| U4 | Name on certificates | Copied at issue; `POST me/learning/certificates/{id}/reissue` takes the account's current name, keeps the number and the verification code, renders a new PDF and sets `reissued_at`; at most 3 times; not for a revoked or expired certificate; the verification page shows the current name and the reissue date; audited `learning.certificate_reissued` with the old and new name |
| U5 | Platform support access | As in `phase-8-company-support-access.md`: course content and settings may be read; learners' progress, scores and submissions may not |
| U6 | Platform admins | `learning.courses.manage` and `learning.moderate` join the existing admin role editor; no new built-in role |
| U7 | Learner groups | Owned by a company or the platform; joined by invitation (C6 pattern), a group code, or automatically by a verified email domain; joining asks the member's consent that the group's owner and coordinators see their progress, scores and certificates (recorded as A4); a hidden public course may be opened to a group and a whole group enrolled at once (`source = invited`); reports filter by group; managed by holders of `learning.courses.author`. A **coordinator** may be any account outside the company (for example a lecturer), named by the group's manager and accepting an invitation; they read a report of consenting members in Career. Legal question 30 (d) |

### Grading and ranking

Agreed by the user on 2026-10-31 (G1 to G8). Scoring itself is the Assessment engine's (points, percentage, band `low`, `fair`, `good`,
`excellent` from the admin's skill bands; assessors score long text).

| Code | Topic | Rule |
|---|---|---|
| G1 | Pass mark | `pass_mark` is a percentage 0 to 100, compared with the attempt's percentage |
| G2 | Which attempt counts | The **last** attempt |
| G2a | Retake after passing | Not allowed: once passed, the quiz is closed, so the last attempt is the passing one; `max_attempts` no longer applies |
| G3 | Essays waiting for an assessor | The attempt is `awaiting_scoring`; `score` and `passed` stay null, the lesson is not completed, no retake while waiting; the Assessment scoring event (raised again after the assessor) updates the attempt and the lesson, and the learner is told |
| G4 | Final score | Σ(score × `weight`) / Σ `weight` over the required quiz lessons, at completion once every attempt is `scored`; null for a course without quizzes |
| G5 | Certificate | Shows the final score and its band (the Assessment skill bands, translated) when `certificate_shows_score` is on and a score exists; both are copied at issue, so a later change of the bands does not alter it; the public verification page shows them too |
| G6 | Integrity tier `major` | Never fails an attempt by itself; flagged in reports; an author may void the attempt (`voided_at`, `voided_by`), which then counts as not taken |
| G7 | Catalog sort | `sort` accepts `newest`, `popular` (enrolments of the last 90 days), `rating` (after S9) and `title`, each with `-` for descending |
| G8 | Company report | `sort` by `final_score` or `completed_at`, plus a computed `rank` per course |

**Rank (G8, interpretation):** computed when the report is read, never stored; among `completed` enrolments with a final score, ordered by
`final_score` descending and then `completed_at` ascending (the earlier finisher ranks higher on a tie), standard competition ranking (1, 2, 2, 4).
Public learners without the consent of P1 are ranked but not listed, so the visible ranks may have gaps; the report already shows their count.
A public leaderboard stays N2.

### Learning methods and evaluation

Agreed by the user on 2026-10-31 (L1 to L9).

| Method | Lesson type | Completion rule |
|---|---|---|
| Self-paced content | `video_upload`, `video_embed`, `pdf`, `text`, `link` | `opened`, `watched_percent` |
| Quiz, final exam, pre-test, post-test | `quiz` | `quiz_passed` (a pre-test is completed once taken) |
| Live session (S6) | `live_session` | `attended` |
| Task (S7) | `task` | `task_passed` |

| Code | Topic | Rule |
|---|---|---|
| L1 | Pre-test and post-test | `quiz_role` `pre_test`: one attempt, no pass or fail, not in the final score, no answer feedback; a `post_test` is an ordinary graded quiz linked by `paired_lesson_id`; reports show both scores and the gain per learner, and the mean gain per course |
| L2 | Order | `sequential` on the version: a lesson opens once every earlier required lesson is completed; a pre-test is always taken first, even when off; an attempt `awaiting_scoring` does not lock the next lesson; the outline marks `locked: true` and a locked lesson answers 409 |
| L3 | Answer feedback | For `learning` tests only: after an attempt is `scored`, the learner sees their answers, the right answers, the points and the question's `explanation`, as `feedback_mode` allows (`never`, `after_pass`, `always`) |
| L4 | Practice quizzes | A quiz with `is_required = false` keeps `max_attempts`, is not in the final score and does not decide completion of the course |
| L5 | Course feedback | Rating 1 to 5 and a comment after completion, optional and editable, per version; anonymous to authors and HR for internal courses; the public rating and `sort=rating` (S9, G7) read it; `learning.moderate` hides comments |
| L6 | Supervisor evaluation | Nice-to-have, designed with the internal step |
| L7 | Live sessions | Several dated sessions per lesson, the learner registers for one; `seat_limit` per session (full answers 409); modes `online_janus` (one to many: hosts publish video, learners speak when allowed, at most `learning.live_max_participants`, attendance from the Janus join and leave events through the tele-interview webhook), `online_link` and `onsite` (the host marks attendance or learners check in with the code); reminders a day and an hour before |
| L7b | Recording | Optional per session, with each learner's consent and a retention period like interviews; behind `learning.live_recording_enabled`, off until legal question 23 is answered |
| L8 | Tasks | A file (PDF, DOCX, XLSX, PPTX, ZIP, JPG, PNG; 20 MB) and/or text; scored per rubric criterion by holders of `learning.tasks.grade` (platform courses: `learning.courses.manage`); the score is the percentage of points; passed at `pass_mark`, resubmitted up to `max_attempts` with the rules of G2 and G2a; in the final score when required |
| L9 | Video answers | Allowed in `learning` tests behind `learning.video_answers_enabled`, off by default until legal question 23 is answered |

**Naming (interpretation):** the user agreed "assignment" lessons (L8); they are called `task` in the schema and the API, because
`learning_assignments` and `source = assigned` already mean a course assigned to an employee.

## 7. Assessment changes

Agreed by the user on 2026-10-31 (K4). Checked in the code: about 40 files of `src/Assessment` use `application_id`; `Invitation` requires
`applicationId`, `companyId`, `jobId` and `applicantUserId`; migration `000120` keeps one active invitation per `application_id`.

| Topic | Today | Change |
|---|---|---|
| Source of an invitation | `application_id` required | `source` (`recruitment`, `learning`) and `source_id`; `application_id`, `job_id`, `company_id` nullable; a check constraint per source |
| Active invitation | One per `application_id` | One per `source` + `source_id` |
| Recruitment code | Reads every invitation | Queries and access policies filter `source = recruitment` |
| Retakes | One attempt per invitation | Unchanged: each quiz retake is a new invitation |
| Explanations (L3) | | `explanation` (nullable) on `assessment_questions`, used by `learning` tests only; a review of a scored attempt for the learner when the lesson's `feedback_mode` allows |
| Video answers (L9) | Skill tests in a video section | Refused in `learning` tests unless `learning.video_answers_enabled` is on |
| Test owner | `company`, `provider`, `library` | Unchanged: a company course's quiz is owned by the company, a platform course's by the library; skill tests only |
| Test purpose (P2a) | | `purpose` (`recruitment`, `learning`) on `assessment_tests`, default `recruitment`; recruitment lists and default stage tests filter `recruitment`; an invitation's `source` must match the test's `purpose` |
| Retention | Follows the application anonymisation | For `learning`: follows the erasure of the learner's account |
| Events | | `Learning` listens to the submit and scoring events; it never calls Assessment use cases |

## 8. Permissions and endpoints

Agreed by the user on 2026-10-31 (P1 to P5). Routes follow the Workforce pattern (`company/...` for Workspace, `organization/...` for the group,
`admin/...` for Console, `me/...` for the signed-in account).

### Permissions

| Permission | Holder | Allows | Notes |
|---|---|---|---|
| `learning.courses.author` | Company member (`CompanyPermission`, new group `learning`) | Courses, draft versions, modules, lessons, uploads, and quiz tests for its courses (P2) | |
| `learning.courses.publish` | Company member | Publishing a version, archiving a course | Split from author, like `jobs.create` and `jobs.publish` |
| `learning.reports.view` | Company member | Progress reports and export | Public learners: identity only with consent (P1) |
| `learning.assign` | Company member | Assigning courses to employees, units or tenants | Internal step |
| `learning.tasks.grade` | Company member | Scoring task submissions (L8) | |
| `learning.courses.view_all` | Company member | Every course of the company, not only those of one's team (C2) | Pattern of `jobs.view_all` |
| `learning.courses.manage` | Platform admin (`PlatformPermissions`) | Categories and platform courses | |
| `learning.moderate` | Platform admin | Archiving a company's public course, revoking a certificate | |
| `group.learning.view` | Group member | Group dashboard across tenants (S8) | Internal step; pattern of `group.workforce.view` |
| none | Any account with a verified email (P3) | Enrolling, learning, quizzes, certificates | |

### Endpoints

| Area | Route | Purpose | Permission |
|---|---|---|---|
| Public | `GET learning/categories` | Categories | |
| Public | `GET learning/courses` | Catalog; `filter[q]`, `filter[category]`, `filter[company]`, `filter[skill]`, `sort` (`newest`, `popular`, `rating`, `title`; G7) | |
| Public | `GET learning/courses/{slug}` | Detail and outline of the published version (lesson titles only) | |
| Public | `GET learning/certificates/{code}` | Certificate verification | |
| Public | `GET learning/invitations/{token}`, `POST learning/invitations/{token}/decline` | An invitation or private link, without signing in (C6) | |
| Learner | `POST me/learning/invitations/{token}/claim` | Accept an invitation or private link (C6) | verified email |
| Public | `GET learning/courses/{id}/cover` | Course cover with cache headers (S4) | |
| Learner | `GET learning/files/{token}` | Signed route that streams a lesson video or PDF (S1) | signature |
| Learner | `POST learning/courses/{id}/enroll` | Self-enrolment; `share_with_company` for a company course (P1) | verified email |
| Learner | `GET me/learning/enrollments`, `GET me/learning/enrollments/{id}` | My courses, outline, progress | |
| Learner | `POST me/learning/enrollments/{id}/withdraw` | Leave a course | |
| Learner | `PUT me/learning/enrollments/{id}/sharing`, `GET me/learning/enrollments/{id}/sharing/history` | Give or withdraw the consent of P1; its history (A4) | |
| Learner | `GET me/learning/enrollments/{id}/lessons/{lesson}` | Lesson content; video through a signed URL | |
| Learner | `POST me/learning/enrollments/{id}/lessons/{lesson}/progress` | Video position and watched share | |
| Learner | `POST me/learning/enrollments/{id}/lessons/{lesson}/complete` | Completion for the rule `opened` | |
| Learner | `GET me/learning/enrollments/{id}/lessons/{lesson}/quiz-attempts/{attempt}/review` | Answer feedback (L3) | |
| Learner | `POST me/learning/enrollments/{id}/lessons/{lesson}/sessions/{session}/register`, `POST .../sessions/{session}/cancel`, `POST .../sessions/{session}/check-in` | Live sessions (L7) | |
| Learner | `POST me/learning/enrollments/{id}/lessons/{lesson}/task-submissions` | Submit a task (L8) | |
| Learner | `PUT me/learning/enrollments/{id}/feedback` | Course feedback (L5) | |
| Learner | `POST me/learning/enrollments/{id}/lessons/{lesson}/quiz-attempts` | Creates an Assessment invitation (`source = learning`), then the existing test flow | |
| Learner | `GET me/learning/certificates`, `GET me/learning/certificates/{id}/pdf` | My certificates | |
| Learner | `GET me/learning/agenda` | Agenda (J10) | |
| Learner | `POST me/learning/certificates/{id}/reissue` | Reissue with the current name (U4) | |
| Learner | `POST me/learning/groups/join` (`code`), `POST me/learning/groups/{id}/leave`, `GET me/learning/groups` | Learner groups (U7) | verified email |
| Learner | `GET me/learning/groups/{id}/report` | Report of consenting members for a coordinator (U7) | coordinator of the group |
| Learner | `POST me/learning/enrollments/{id}/lessons/{lesson}/series/{series}/register` | Register for a series (J6) | |
| Workspace | `GET company/learning/catalog` | Internal courses an employee may enrol in (E5, internal step) | employee of the tenant |
| Workspace | `GET/POST company/learning/courses`, `GET/PUT company/learning/courses/{id}` | Courses | author |
| Workspace | `POST company/learning/courses/{id}/archive` | Archive | publish |
| Workspace | `DELETE company/learning/courses/{id}` | Delete a never-published course without learners (C9) | author, owner |
| Workspace | `POST company/learning/courses/{id}/duplicate` | Duplicate (C4) | author |
| Workspace | `GET/PUT company/learning/courses/{id}/members` | Team (C2) | owner |
| Workspace | `GET/POST company/learning/courses/{id}/cohorts`, `PUT company/learning/cohorts/{id}`, `POST company/learning/cohorts/{id}/cancel` | Cohorts (C1) | owner, instructor |
| Workspace | `GET/POST company/learning/courses/{id}/announcements` | Announcements (C5) | owner, instructor |
| Workspace | `GET/POST company/learning/courses/{id}/invite-links`, `DELETE company/learning/invite-links/{id}`, `GET/POST company/learning/courses/{id}/invitations`, `POST company/learning/courses/{id}/invitations/import` | Invitations (C6) | owner, instructor |
| Workspace | `POST company/learning/enrollments/{id}/extend`, `.../reset`, `.../complete`, `.../remove` | Actions on learners (C8) | owner, instructor |
| Workspace | `GET/POST company/learning/courses/{id}/versions`, `GET/PUT company/learning/versions/{id}` | Versions; `POST` copies the latest into a draft | author |
| Workspace | `POST company/learning/versions/{id}/modules`, `PUT/DELETE company/learning/modules/{id}`, `POST company/learning/versions/{id}/modules/reorder` | Modules | author |
| Workspace | `POST company/learning/modules/{id}/lessons`, `PUT/DELETE company/learning/lessons/{id}`, `POST company/learning/modules/{id}/lessons/reorder` | Lessons | author |
| Workspace | `POST company/learning/uploads`, `PUT company/learning/uploads/{id}/chunks/{n}`, `POST company/learning/uploads/{id}/complete` | Chunked upload of a video or PDF, then attached to a lesson (P5) | author |
| Workspace | `POST/DELETE company/learning/courses/{id}/cover` | Course cover | author |
| Workspace | `POST company/learning/versions/{id}/publish`, `POST company/learning/versions/{id}/withdraw-submission` | Publish a version, or submit it when an approval flow exists (C3) | publish, owner |
| Workspace | `GET company/learning/courses/{id}/enrollments`, `GET company/learning/courses/{id}/enrollments/export` | Reports and export; `sort` by `final_score` or `completed_at`, with `rank` (G8) | reports.view |
| Workspace | `GET/POST company/learning/assignments` | Assignments (internal step) | assign |
| Workspace | `GET company/learning/agenda` | Sessions one hosts (J10) | host |
| Workspace | `POST company/learning/lessons/{id}/series`, `PUT company/learning/series/{id}` (`scope`: one or later meetings) | Session series (J6) | author, owner, instructor |
| Workspace | `PUT company/learning/versions/{id}/schedule`, `DELETE company/learning/versions/{id}/schedule` | Scheduled publishing (J1) | publish, owner |
| Workspace | `GET/POST company/learning/lessons/{id}/sessions`, `PUT/DELETE company/learning/sessions/{id}`, `PUT company/learning/sessions/{id}/attendance` | Live sessions (L7) | author; attendance also the host |
| Workspace | `PUT company/learning/lessons/{id}/criteria` | Task rubric (L8) | author |
| Workspace | `GET company/learning/task-submissions`, `PUT company/learning/task-submissions/{id}/score` | Scoring tasks (L8) | tasks.grade |
| Workspace | `GET/POST company/learning/groups`, `PUT company/learning/groups/{id}`, `GET/POST company/learning/groups/{id}/members`, `PUT company/learning/groups/{id}/courses`, `POST company/learning/groups/{id}/enroll` | Learner groups (U7) | author |
| Workspace | `GET company/learning/analytics` | Company analytics (R2) | reports.view |
| Workspace | `GET company/learning/courses/{id}/question-analysis`, `GET company/learning/courses/{id}/drop-off` | Question analysis and drop-off (R4, R5) | owner, instructor, view_all |
| Workspace | `GET company/learning/compliance` | Compliance matrix (R3, internal step) | reports.view |
| Workspace | `GET company/learning/courses/{id}/activity` | Activity log of a course (A6) | owner, view_all |
| Workspace | `GET company/learning/compliance/export`, `GET company/learning/activity/export` (`from`, `to`, at most 12 months) | Compliance CSV and audit export (A7) | reports.view with view_all |
| Workspace | `GET company/learning/courses/{id}/feedback` | Course feedback, anonymous for internal courses (L5) | reports.view |
| Group | `GET organization/learning/analytics` | Group dashboard (R7, internal step) | `group.learning.view` |
| Console | `GET/POST/PUT/DELETE admin/learning/categories` | Categories | courses.manage |
| Console | The course, version, module, lesson and upload routes of Workspace under `admin/learning/...`, with `company_id` null | Platform courses | courses.manage |
| Console | `GET admin/learning/courses` with `filter[company]` | Every course | courses.manage or moderate |
| Console | `GET admin/learning/analytics` | Console reports (R8) | courses.manage or moderate |
| Console | `POST admin/learning/courses/{id}/archive` | Moderation of a company course (P4) | moderate |
| Console | `POST admin/learning/certificates/{id}/revoke` | Revoke with a reason | moderate |
| Console | `POST admin/learning/feedback/{id}/hide` | Hide a feedback comment (L5) | moderate |

The consent of P1 adds `share_with_company` and `shared_at` to `learning_enrollments` (null for a platform course).

### Settings (Console "Pengaturan")

| Key | Default | Purpose |
|---|---|---|
| `learning.company_public_courses_enabled` | `true` | Companies may publish public courses (P4) |
| `learning.video_url_minutes` | `60` | Lifetime of a signed video URL |
| `learning.certificate_number_format` | `CERT/{year}/{number}` | Certificate number |
| `learning.due_soon_days` | `3` | Reminder before a due date (internal step) |
| `learning.live_janus_enabled` | `false` | Live sessions through Janus (L7d); on after Janus is installed and the server capacity is checked |
| `learning.live_max_participants` | `50` | Learners per Janus session (L7c) |
| `learning.live_recording_enabled` | `false` | Recording of Janus sessions (L7b; legal question 23) |
| `learning.video_answers_enabled` | `false` | Video answers in learning tests (L9; legal question 23) |

### Notifications

| Event | Recipient | Channels |
|---|---|---|
| Enrolled | Learner | In-app |
| Course completed, certificate issued | Learner | Email, in-app |
| Certificate revoked | Learner | Email, in-app |
| Course archived by moderation | Members with `learning.courses.publish` | Email, in-app |
| Version approved or rejected (C3) | The owner of the course | Email, in-app |
| Invited to a course (C6) | The invitee | Email, in-app when the account exists |
| Announcement (C5) | Learners of the course or cohort | Email, in-app |
| Cohort starts, cohort cancelled (C1) | Learners of the cohort | Email, in-app |
| Version published by the schedule (J1) | The owner of the course | In-app |
| Module released (J2) | Learner | In-app |
| Session changed or cancelled (J7) | Registered learners | Email with `.ics`, in-app |
| Certificate expires in 30 and 7 days, expired (J9) | Learner; HR for assigned courses | Email, in-app |
| Assigned, due soon, overdue | Employee; HR for overdue | Email, in-app, WhatsApp (internal step) |
| Quiz scored by an assessor (G3) | Learner | In-app |
| Live session registered, a day before, an hour before (L7) | Learner | Email, in-app |
| Task submitted (L8) | Members with `learning.tasks.grade` | In-app |
| Task scored or returned (L8) | Learner | Email, in-app |

## 9. Open (next design steps)

- None. Coding waits for E1 to E4 (public side) and #13 (internal side); paid courses also wait for legal question 31 and the billing scope.
