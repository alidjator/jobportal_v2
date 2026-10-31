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
| S6 | Live or classroom sessions: schedule, seats, attendance | Both | Interview calendar, Janus |
| S7 | Assignments: an uploaded file scored by an instructor | Both | Assessment assessors |
| S8 | Group dashboard across tenants | Internal | Organization group permissions |
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
| `CourseVersion` | Modules and lessons of one version | `draft`, `published`, `retired`; a published version is locked; one published version per course; publishing a new one retires the previous |
| `Enrollment` | One learner on one version, with progress per lesson | Locked to the version at enrolment; completed when every required lesson is completed |
| `Certificate` | A numbered certificate | Copies the learner name, course title and date; never changes with the course; can be revoked with a reason |

### Tables (prefix `learning_`)

| # | Table | Main columns | Notes |
|---|---|---|---|
| 1 | `learning_categories` | `name` json (id/en), `slug`, `sort_order`, `is_active` | Master-data pattern of phase 0; managed by the platform admin |
| 2 | `learning_courses` | `company_id` (null = platform), `audience` (`public`, `internal`), `category_id`, `slug`, `status` (`active`, `archived`), `created_by`, timestamps | Keys to `company_companies`, `identity_users` |
| 3 | `learning_course_versions` | `course_id`, `number`, `title`, `summary`, `description`, `cover_path`, `language` (`id`, `en`), `estimated_minutes`, `status`, `published_at`, `published_by` | Partial unique: one `published` per course |
| 4 | `learning_modules` | `version_id`, `position`, `title` | |
| 5 | `learning_lessons` | `module_id`, `position`, `title`, `type` (`video_upload`, `video_embed`, `pdf`, `text`, `link`, `quiz`), `body`, `file_path`, `embed_url`, `duration_seconds`, `is_required`, `completion_rule` (`opened`, `watched_percent`, `quiz_passed`), `completion_threshold`, `test_id`, `pass_mark`, `max_attempts` | `test_id` is an Assessment test; embeds from YouTube and Vimeo only |
| 6 | `learning_course_skills` | `course_id`, `skill_id` | Key to `reference_skills` (S4) |
| 7 | `learning_enrollments` | `course_id`, `version_id`, `user_id`, `source` (`self`, `assigned`), `assigned_by`, `due_at`, `status` (`active`, `completed`, `withdrawn`, `expired`), `progress_percent`, `enrolled_at`, `completed_at` | One active enrolment per learner per course; `employee_id` added in the internal step (K3) |
| 8 | `learning_lesson_progress` | `enrollment_id`, `lesson_id`, `status` (`not_started`, `in_progress`, `completed`), `position_seconds`, `watched_percent`, `completed_at` | Unique `enrollment_id` + `lesson_id` |
| 9 | `learning_quiz_attempts` | `enrollment_id`, `lesson_id`, `assessment_invitation_id`, `score`, `passed`, `submitted_at` | Filled from Assessment events; limited by `max_attempts` |
| 10 | `learning_certificates` | `enrollment_id` (unique), `number`, `verification_code`, `learner_name`, `course_title`, `issued_at`, `pdf_path`, `revoked_at`, `revoke_reason` | Public verification page by `verification_code` |
| 11 | `learning_certificate_sequences` | `year`, `last_number` | Numbering per year, like the Assessment reports |
| 12 | `learning_course_units` | `course_id`, `unit_id` | Internal step: restricts an internal course to units |
| 13 | `learning_assignments` | `course_id`, target (`employee`, `unit`, `tenant`), `target_id`, `due_in_days`, `created_by` | Internal step |

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

## 7. Assessment changes

Agreed by the user on 2026-10-31 (K4). Checked in the code: about 40 files of `src/Assessment` use `application_id`; `Invitation` requires
`applicationId`, `companyId`, `jobId` and `applicantUserId`; migration `000120` keeps one active invitation per `application_id`.

| Topic | Today | Change |
|---|---|---|
| Source of an invitation | `application_id` required | `source` (`recruitment`, `learning`) and `source_id`; `application_id`, `job_id`, `company_id` nullable; a check constraint per source |
| Active invitation | One per `application_id` | One per `source` + `source_id` |
| Recruitment code | Reads every invitation | Queries and access policies filter `source = recruitment` |
| Retakes | One attempt per invitation | Unchanged: each quiz retake is a new invitation |
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
| `learning.courses.manage` | Platform admin (`PlatformPermissions`) | Categories and platform courses | |
| `learning.moderate` | Platform admin | Archiving a company's public course, revoking a certificate | |
| `group.learning.view` | Group member | Group dashboard across tenants (S8) | Internal step; pattern of `group.workforce.view` |
| none | Any account with a verified email (P3) | Enrolling, learning, quizzes, certificates | |

### Endpoints

| Area | Route | Purpose | Permission |
|---|---|---|---|
| Public | `GET learning/categories` | Categories | |
| Public | `GET learning/courses` | Catalog; `filter[q]`, `filter[category]`, `filter[company]`, `filter[skill]`, `sort` | |
| Public | `GET learning/courses/{slug}` | Detail and outline of the published version (lesson titles only) | |
| Public | `GET learning/certificates/{code}` | Certificate verification | |
| Public | `GET learning/courses/{id}/cover` | Course cover with cache headers (S4) | |
| Learner | `GET learning/files/{token}` | Signed route that streams a lesson video or PDF (S1) | signature |
| Learner | `POST learning/courses/{id}/enroll` | Self-enrolment; `share_with_company` for a company course (P1) | verified email |
| Learner | `GET me/learning/enrollments`, `GET me/learning/enrollments/{id}` | My courses, outline, progress | |
| Learner | `POST me/learning/enrollments/{id}/withdraw` | Leave a course | |
| Learner | `PUT me/learning/enrollments/{id}/sharing` | Give or withdraw the consent of P1 | |
| Learner | `GET me/learning/enrollments/{id}/lessons/{lesson}` | Lesson content; video through a signed URL | |
| Learner | `POST me/learning/enrollments/{id}/lessons/{lesson}/progress` | Video position and watched share | |
| Learner | `POST me/learning/enrollments/{id}/lessons/{lesson}/complete` | Completion for the rule `opened` | |
| Learner | `POST me/learning/enrollments/{id}/lessons/{lesson}/quiz-attempts` | Creates an Assessment invitation (`source = learning`), then the existing test flow | |
| Learner | `GET me/learning/certificates`, `GET me/learning/certificates/{id}/pdf` | My certificates | |
| Workspace | `GET/POST company/learning/courses`, `GET/PUT company/learning/courses/{id}` | Courses | author |
| Workspace | `POST company/learning/courses/{id}/archive` | Archive | publish |
| Workspace | `GET/POST company/learning/courses/{id}/versions`, `GET/PUT company/learning/versions/{id}` | Versions; `POST` copies the latest into a draft | author |
| Workspace | `POST company/learning/versions/{id}/modules`, `PUT/DELETE company/learning/modules/{id}`, `POST company/learning/versions/{id}/modules/reorder` | Modules | author |
| Workspace | `POST company/learning/modules/{id}/lessons`, `PUT/DELETE company/learning/lessons/{id}`, `POST company/learning/modules/{id}/lessons/reorder` | Lessons | author |
| Workspace | `POST company/learning/uploads`, `PUT company/learning/uploads/{id}/chunks/{n}`, `POST company/learning/uploads/{id}/complete` | Chunked upload of a video or PDF, then attached to a lesson (P5) | author |
| Workspace | `POST/DELETE company/learning/courses/{id}/cover` | Course cover | author |
| Workspace | `POST company/learning/versions/{id}/publish` | Publish a version | publish |
| Workspace | `GET company/learning/courses/{id}/enrollments`, `GET company/learning/courses/{id}/enrollments/export` | Reports and export | reports.view |
| Workspace | `GET/POST company/learning/assignments` | Assignments (internal step) | assign |
| Group | `GET organization/learning/reports` | Group dashboard (internal step) | `group.learning.view` |
| Console | `GET/POST/PUT/DELETE admin/learning/categories` | Categories | courses.manage |
| Console | The course, version, module, lesson and upload routes of Workspace under `admin/learning/...`, with `company_id` null | Platform courses | courses.manage |
| Console | `GET admin/learning/courses` with `filter[company]` | Every course | courses.manage or moderate |
| Console | `POST admin/learning/courses/{id}/archive` | Moderation of a company course (P4) | moderate |
| Console | `POST admin/learning/certificates/{id}/revoke` | Revoke with a reason | moderate |

The consent of P1 adds `share_with_company` and `shared_at` to `learning_enrollments` (null for a platform course).

### Settings (Console "Pengaturan")

| Key | Default | Purpose |
|---|---|---|
| `learning.company_public_courses_enabled` | `true` | Companies may publish public courses (P4) |
| `learning.video_url_minutes` | `60` | Lifetime of a signed video URL |
| `learning.certificate_number_format` | `CERT/{year}/{number}` | Certificate number |
| `learning.due_soon_days` | `3` | Reminder before a due date (internal step) |

### Notifications

| Event | Recipient | Channels |
|---|---|---|
| Enrolled | Learner | In-app |
| Course completed, certificate issued | Learner | Email, in-app |
| Certificate revoked | Learner | Email, in-app |
| Course archived by moderation | Members with `learning.courses.publish` | Email, in-app |
| Assigned, due soon, overdue | Employee; HR for overdue | Email, in-app, WhatsApp (internal step) |

## 9. Open (next design steps)

- None. Coding waits for E1 to E4 (public side) and #13 (internal side); paid courses also wait for legal question 31 and the billing scope.
