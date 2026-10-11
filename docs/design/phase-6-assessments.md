# Phase 6: Assessment engine (skill tests and psychological tests) (designed 2026-10-11)

## Decisions

One line per decision; the detail sections below win if they disagree.

- 2026-10-11: we build our own engine for skill tests (companies, system library) and psychological batteries (partner psychologists sign) (Decided).
- 2026-10-11: an eighth stage type `assessment`, a default stage after `screening` (Part 1 decisions).
- 2026-10-11: psychologists are a third `UserType` with required TOTP, own endpoints under `/provider`, created by an admin invitation (Part 2).
- 2026-10-11: one set of authoring use cases serves company admins, psychologists and the library admin; batteries are granted per company (Part 3).
- 2026-10-11: a test is sent by moving an application to an `assessment` stage (test required, one active invitation per application, replaced by a new test, cancelled on a move out, a rejection or a withdrawal); a published test that was sent is locked (Part 4).
- 2026-10-11: results live in one application only, proctoring is browser signals shown as tiers, retention follows the application anonymisation (Decided).

Source: `docs/analysis/benchmark-teleinterview-assessment.md` (section 3) and `decisions.md` ("assessments reopen R8"). Billing, credits and packages are out of scope (`decisions.md`).

## Decided (2026-10-11, user)

- We build our own test engine, like Mekari Talenta.
- The engine runs two kinds of test:
  - **Skill tests**, built by companies.
  - **Psychological tests** (intelligence, personality, reasoning).
- Psychological test content, scoring norms and reports are owned and signed by a **licensed partner psychologist or institution**, as required by UU 23/2022 Pasal 26 and 33.
- The candidate consents before a psychological test (Pasal 27) and has the right to an explanation of the result (Pasal 44).
- The platform never interprets psychological results on its own.

- **Stage type (2026-10-11, user):** an eighth system stage type **`assessment`**, as in Talenta and Glints. Every company gets a default `assessment` stage, backfilled for existing companies. Companies may add extra stages of this type, and funnel reports and the job seeker view recognise it. This replaces "`assessment` (skill test) is not included now" in `decisions.md` section 1. Proposed, not yet confirmed: the default stage sits after `screening` and before `interview`, and `recruitment.max_stages` rises from 10 to 11, so companies keep three extra stages.

- **Partner providers (2026-10-11, user):**
  - The platform admin registers an **assessment provider** (the partner institution) and its **psychologist accounts**. Each psychologist record holds the name, the STR and SILP numbers and their validity dates.
  - Psychologists sign in themselves. They build batteries, norm tables and report templates, and they sign candidate reports.
  - A psychologist sees only the candidates who took their provider's batteries.
  - Still to be fixed: the account mechanism, either a new role in Identity or a separate actor type.

- **Access to psychological batteries (2026-10-11, user):** only companies that the platform admin enables, as a per-company setting. Skill tests are open to every company.
- **Skill test sources (2026-10-11, user):**
  - Tests built by companies.
  - A **system library** of ready-made skill tests that the platform admin builds and maintains, as Kalibrr and TestGorilla do. This needs question banks.
- **Where results live (2026-10-11, user):** in **one application only**, as in Talenta. Only the company that sent the test sees the result, and nothing appears on the job seeker profile.
- **Special formats (2026-10-11, user):** both come now:
  - **Kraepelin-type speed columns:** timed column additions, with results per column.
  - **Drawing tests** (BAUM, HTP, DAP and similar): the candidate draws on a canvas or uploads a photo of a drawing.
  - A psychologist of the provider scores both. They need file storage for the drawings.

- **Proctoring (2026-10-11, user):** browser signals only: tab switch, full-screen exit, paste and IP change. They are shown as tiers and never reject a candidate automatically. No webcam.
- **Retention (2026-10-11, user):** answers, drawings and reports follow the application. They are erased by the existing application anonymisation.
- **System library visibility (2026-10-11, user):** companies see a test's description, the measured capabilities and one sample question per type. The question banks stay hidden, and questions are drawn at random.
- **Notifications (2026-10-11, user):** the same pattern as interviews.
  - The candidate gets email and in-app for the invitation, a reminder 24 hours before the due time, and the expiry.
  - The team gets in-app only, when a test is submitted and when a psychologist's report is signed.

- **Psychologist accounts (2026-10-11, user):**
  - A third `UserType`, **`psychologist`**, with its own endpoints under `/api/v1/provider/...`.
  - The platform admin creates the account through an email invitation, like the first company admin.
  - **TOTP is required**, as for admins.

## Proposed model (to be confirmed point by point)

### Context

- **Context:** a new context `Assessment` (`src/Assessment`, table prefix `assessment_`).
- **Applications:** Recruitment keeps owning applications and stages. The two contexts talk through ports in `src/Shared`, as Recruitment does with Job.

### Building blocks

- **Test:** a named set of sections with instructions, owned by a company (skill test) or by a partner provider (psychological test).
- **Section:** a timed part with its own question type rules and navigation rules (skip, go back, jump), following Talenta.
- **Question types:**
  - Objective, scored automatically: single choice, multiple response, true/false, number, short text with an exact answer.
  - Long text, scored by hand by an assessor.
  - For psychological tests: Likert scale and forced choice.
- **Battery:** a psychological product of a provider, for example a leadership profile. It is made of sections, a norm table and a report template, and is signed by a psychologist.

### Delivery and scoring

- **Invitation:** the test is sent when an application moves into a test stage, one by one or in bulk within one job, with a start time and a due time. Statuses: `invited`, `started`, `submitted`, `expired`, `cancelled`.
- **Attempt:**
  - One attempt per invitation.
  - The timer runs on the server and keeps running when the window closes.
  - The attempt is submitted automatically when time runs out and locked after submission.
  - A candidate who drops out may resume before time runs out.
- **Scoring:**
  - Skill tests: automatic where possible, then by the assessor, shown as a percentage and a band.
  - Psychological tests: raw scores converted through the provider's norm table. Then a psychologist of the provider reviews and signs the report, which gets a report number. Before signing, the company sees only "awaiting psychologist review".
- **Integrity:**
  - Logged signals: tab switch, full-screen exit, paste, and IP changes.
  - The signals are shown as tiers (no, some or major issues).
  - They never reject a candidate automatically.
- **Question security:** the questions of a psychological battery are never shown to companies. Each attempt draws questions at random from a bank where the battery allows it.
- **Candidate side:** a consent and honesty screen comes before the test. After a psychological test is signed, the candidate sees an explanation of the result.

- **Part 1 decisions (2026-10-11, user):** (a) the funnel skips an unused `assessment` stage: while no application entered it in the range, `screening` is measured against `interview`; (b) the backfilled default stage is named `Assessment (default)` when an extra stage of the company already uses `Assessment`, and the company's own stage is not touched.

- **Part 2 decisions (2026-10-11, user):** (a) the psychologist's TOTP lives in **separate user tables** (`identity_user_two_factor`, `identity_user_recovery_codes`, `identity_user_two_factor_challenges`); the admin tables and flow are not touched; (b) the invitation (token, expiry, acceptance) lives in the **Assessment context** (`assessment_psychologist_invitations`); accepting it creates the `User` through a port in `src/Shared`, as Company does with `CompanyMemberAccounts`; (c) psychologists sign in at dedicated endpoints, `POST /api/v1/provider/auth/sign-in` then `/two-factor` (two steps, 12-hour token, as for admins); the public sign-in answers a psychologist with the same generic "incorrect email or password" error.

- **Part 3 decisions (2026-10-11, user):** (a) only a **company admin** creates and edits a company's skill tests; (b) the system library has its own admin permission, **`assessment_library.manage`**; (c) the content is edited through **per-section and per-question endpoints** (options travel with their question); (d) an admin **grants batteries to each company** (`assessment_company_batteries`); a company uses a battery only when the setting `assessment.psychological_enabled` is on for it **and** the battery is granted.

- **Part 4 decisions (2026-10-11, user):** (a) a rejection, a withdrawal by the applicant and a move out of an assessment stage cancel the active invitation when it is **invited or started**; a submitted one stays; (b) a move to an assessment stage **needs a test** (`assessment-required`) unless the application already has an active one; (c) a new test **replaces** the active invitation (the old one is cancelled) unless the candidate already started it (`invitation-in-progress`); (d) assessors are members who work on the job, **at most 5**.

## Part 2 as coded (2026-10-11)

- **Confirmed by the user (2026-10-11):** (a) the `/provider/*` routes are exempt from the job-seeker terms gate; (b) only an admin closes a psychologist account, never the psychologist; (c) public sign-in, Google sign-in and account restore stay refused for psychologists.

- **Account:** `UserType::Psychologist` (CHECK on `identity_users.type`), `ActorKind::Psychologist`. The account uses the `user` guard and the `/provider` endpoints; the consumer terms gate does not apply to the provider routes. The public sign-in, Google sign-in and account restore answer a psychologist like a wrong password (`google-sign-in-unavailable` for Google), so no token is ever issued without the second factor. A psychologist cannot delete the account (`psychologist-account-managed`): an admin suspends it.
- **TOTP:** `identity_user_two_factor` (encrypted secret, `confirmed_at`, `last_step`), `identity_user_recovery_codes`, `identity_user_two_factor_challenges`. Flow as for admins: `POST /provider/auth/login` answers a 5-minute challenge (with the enrolment secret and QR code on the first sign-in), `POST /provider/auth/two-factor` answers the 12-hour token and, at enrolment, 10 recovery codes. The nightly purge also clears expired psychologist challenges.
- **Tables:** `assessment_providers` (unique `lower(name)`), `assessment_psychologists`, `assessment_psychologist_invitations` (holds the name and licence data entered by the admin; the psychologist row is created at acceptance).
- **Admin endpoints** (`assessment_providers.manage`, under `/admin/assessment`): providers (list with `filter[q]` and `filter[status]`, create, show, update, suspend, restore); `providers/{id}/psychologists`; `providers/{id}/invitations` (list pending, create, delete); `psychologists/{id}` (renew licences), `suspend`, `restore`, `reset-two-factor`.
- **Public and provider endpoints:** `GET /provider/invitations/{token}`, `POST /provider/invitations/{token}/accept` (password only; answers `next: sign_in`, no token), `GET /provider/me`.
- **Signing rule:** `canSignReports` is true when the psychologist is active, the provider is active and both STR and SILP are valid through today. Part 6 enforces it when a report is signed.
- **Config:** `PSYCHOLOGIST_INVITATION_URL` (template with `{token}`), email template `assessment.psychologist_invitation`, seven `assessment.*` audit actions.

## Part 3 as coded (2026-10-11)

- **Tables (migration 000119):** `assessment_tests` (owner: `company_id`, `provider_id` or neither for the library; CHECKs on kind, status, owner and exactly one creator), `assessment_sections`, `assessment_questions`, `assessment_question_options`, `assessment_norms`, `assessment_report_templates`, `assessment_company_batteries`. Content tables cascade from the test; positions are plain numbers kept contiguous by the repositories.
- **Authors:** one set of use cases and routes serves three authors, resolved from the account by `AuthoringAccess`: a company admin (skill tests of the company), a psychologist (batteries of the provider, shared by all its psychologists) or an admin (library). Routes: `/company/assessment/...`, `/provider/...`, `/admin/assessment/library/...` (permission `assessment_library.manage`), each with `tests` (CRUD, `publish`, `archive`, `restore`, `clone`), `tests/{id}/sections` (+ `PUT .../sections/order`), `sections/{id}`, `sections/{id}/questions` (+ `PUT .../questions/order`), `questions/{id}`. The account decides the author, so a company admin who calls a `/provider` route still writes the company's tests.
- **Status rules:** draft, published, archived. Edits are allowed in any state but archived (a published test stays editable until part 4 locks it once it has invitations). Only a draft is deleted. Restoring an archived test that was published brings it back published. Publishing checks completeness (sections, questions, `draw_count` not above the question count; for a battery a norm for every scale and a report template) and lists every problem in `errors.test` (`test-incomplete`).
- **Question rules:** skill tests use `single_choice` (one correct), `multiple_response` (at least one), `true_false` (two options, one correct), `number` (one accepted numeric answer), `short_text` (up to ten accepted answers), `long_text`. Batteries add `likert` (scored options, scale on the question), `forced_choice` (scored options with their own scale) and `drawing` (drawing sections only). Objective and Likert questions of a battery need a `scale_key` (`^[a-z][a-z0-9_]{0,59}$`). The section format is fixed after creation; a `speed_columns` section lasts `columns` × `seconds_per_column` and has no questions. At most one sample question per type per test.
- **Norms and report template (batteries only):** `PUT /provider/tests/{id}/norms` replaces the whole table (ranges of one scale may not overlap, the scale must be used by a question); the template accepts `{{candidate_name}}`, `{{test_title}}`, `{{report_number}}`, `{{signed_on}}`, `{{psychologist_name}}` and `{{scale:KEY:raw|standard|band}}` with a known scale; part 6 renders them.
- **Catalogue:** `GET /company/assessment/library` (published library tests), `.../batteries` (granted batteries of active providers; `psychological-disabled` while the company setting is off), `.../catalog/{id}` (description, capabilities, size and the samples, without correct answers or scores). Every company member reads it.
- **Granting:** an admin with `assessment_providers.manage` lists published batteries (`GET /admin/assessment/batteries`) and grants or revokes them per company (`/admin/assessment/companies/{id}/batteries`).
- **Settings (all overridable per company except the drawing size):** `assessment.psychological_enabled` (off), `max_company_tests` (50), `max_sections_per_test` (10), `max_questions_per_section` (200), `max_section_minutes` (180), `min_due_hours` (1), `max_due_days` (30), `max_drawing_megabytes` (5); the last three are used from part 4 and 5. Limits of a provider or the library use the global values.

## Part 4 as coded (2026-10-11)

- **Tables (migration 000120):** `assessment_invitations` (RESTRICT foreign keys to `recruitment_applications`, `company_companies`, `job_jobs`, `identity_users` (candidate and sender) and `assessment_tests`; status CHECK; `due_at > starts_at`; a partial unique index allows one invited or started invitation per application) and `assessment_invitation_assessors`. The foreign key to `recruitment_applications` follows the precedent of `jobinvitation_invitations.application_id` (`cross-context-foreign-keys.md`).
- **Sending:** the move request (single and bulk) takes `assessment: {test_id, starts_at?, due_at, assessor_ids?}`. A move to an assessment stage sends it, or keeps the active invitation when none is in the request; a test for another stage is refused (`invalid-assessment-input`). `PUT /company/assessment/applications/{id}/invitation` sends or replaces a test while the application sits in an assessment stage (201 new, 200 replaced; `not-in-assessment-stage`, `job-taken-down`, `invitation-in-progress`), which also re-sends after a cancel or an expiry. A bulk move checks the shared test once before it starts and reports `invitation-in-progress` for an application that cannot take it.
- **Rules (`InvitationChecks`, errors name `test_id`, `starts_at`, `due_at`, `assessor_ids`):** the test is published and is a test of the company, a library test, or a battery that is granted, whose provider is active, when the setting `assessment.psychological_enabled` is on (`invalid-assessment-test`). The start defaults to now and may be at most 5 minutes in the past. The due time is at least `min_due_hours` and at most `max_due_days` from now (per company) and leaves the whole test duration after the start (`invalid-assessment-schedule`). Assessors (`invalid-assessor`): at most 5, members who may see the job; a skill test with a long-text question needs at least one; a battery takes none (its psychologist scores it).
- **Team endpoints (`company/assessment`):** `GET applications/{id}/invitations`, `GET invitations/{id}`, `POST invitations/{id}/cancel` (`invitation-not-active`); visible to whoever may see the application. Cancelling keeps the application in its stage.
- **Candidate endpoints:** `GET /me/assessment/invitations` (`filter[status]`) and `GET /me/assessment/invitations/{id}` with the instructions and the title, format and length of each section, never a question. Consent and start are part 5.
- **Expiry and reminders:** `assessment:expire-invitations` closes the invited ones past their due time; `assessment:send-invitation-reminders` reminds the invited ones due within 24 hours, once. Both run hourly. A started test is not touched: its attempt has its own deadline (part 5).
- **Notifications:** `assessment.invited`, `.reminder`, `.expired`, `.cancelled` in-app to the candidate (always) and email by the preference `application_update` (templates `assessment.test_invited`, `test_reminder`, `test_expired`, `test_cancelled`). The team gets none in this part (submissions and signed reports come in parts 5 and 6). A replaced invitation sends one `invited`, no `cancelled`.
- **Lock:** `AuthoringAccess::editableTest` refuses every content change of a test that has an invitation (`test-locked`, 409): update, sections, questions, norms and report template. Archive, restore and clone stay open.
- **Ports:** `Shared\Application\Assessment\AssessmentInvitations` (Assessment provides; Recruitment sends, checks and cancels through it) and `Shared\Application\Recruitment\TeamApplications` (Recruitment provides; Assessment finds an application for a member through it).

## Open questions

1. ~~Stage type~~: decided, see above.
2. ~~Partner provider in the system~~: decided, see above. Original question:
   - Who are the providers and psychologists as users? This needs a new account type or role.
   - Should their STR and SILP numbers be stored?
   - Who registers them: the platform admin?
3. ~~Decided.~~ every company, or only companies an admin enables?
4. ~~Decided.~~
   - Should there be only company-built tests, as in Talenta?
   - Or also a system library built by the platform admin, as in Kalibrr and TestGorilla?
5. ~~Decided.~~ Should results belong to one application only, as in Talenta? Or should they be reusable on the job seeker profile with the candidate choosing visibility, as in Kalibrr?
6. ~~Decided.~~ (TestGorilla): include them, which needs consent and storage, or log only browser signals?
7. ~~Decided.~~
   - How long are answers and reports kept?
   - Should deletion follow the application anonymisation that already exists?
8. ~~Decided.~~ such as Kraepelin (speed columns) and drawing tests: include them now, or start with question-based formats only?
9. **Limits:** tests per company, sections and questions per test, and minimum and maximum durations, as admin settings like the screening limits.
10. ~~Decided.~~
    - The candidate gets the invitation, a reminder before the due time and an expiry notice.
    - The team gets submissions and signed reports, in-app only as for interviews.

## Schema, endpoints and limits (approved by the user 2026-10-11)

The tables use the prefix `assessment_`. Links to other contexts are plain indexes, with no foreign key, following `cross-context-foreign-keys.md`.

### Providers

1. `assessment_providers` (partner institutions):
   - `name`, `legal_name`
   - `status`: `active` or `suspended`
   - timestamps
2. `assessment_psychologists`:
   - `user_id`: unique; refers to `identity_users`
   - `provider_id`: foreign key
   - `str_number` and `str_valid_until`
   - `silp_number` and `silp_valid_until`
   - `status`
   - timestamps

   A psychologist whose STR or SILP has expired cannot sign reports.

### Tests

3. `assessment_tests`:
   - `kind`: `skill` or `psychological`
   - Owner: `company_id`, or `provider_id`, or neither for the system library
   - `title`, `description`, `instructions`
   - `measured_capabilities`: JSON, at most 10
   - `status`: `draft`, `published` or `archived`
   - `created_by`
   - timestamps

   A published test that has invitations is locked: it can only be archived or cloned, like screening questions.
4. `assessment_sections`:
   - `test_id`, `position`, `title`, `instructions`
   - `format`: `questions`, `speed_columns` or `drawing`
   - `duration_seconds`
   - Navigation: `allow_skip`, `allow_back`, `allow_jump`
   - `draw_count`: null means every question; otherwise the number drawn at random from the bank
   - `settings`: JSON, for example the number of Kraepelin columns, digits per column and seconds per column
5. `assessment_questions`:
   - `section_id`, `position`
   - `type`: `single_choice`, `multiple_response`, `true_false`, `number`, `short_text`, `long_text`, `likert`, `forced_choice` or `drawing`
   - `text`, `points`
   - `scale_key`: the psychological dimension
   - `is_sample`: a sample question shown to companies
6. `assessment_question_options`:
   - `question_id`, `label`, `position`
   - `is_correct`
   - `score_value` and `scale_key`, for Likert and forced choice
7. `assessment_norms` (psychological):
   - `test_id`, `scale_key`
   - `raw_min`, `raw_max`
   - `standard_score`, `band`
8. `assessment_report_templates`: `test_id`, `body`, with placeholders per scale.

### Delivery

9. `assessment_invitations`:
   - `application_id`, `company_id`, `test_id`
   - `starts_at`, `due_at`
   - `status`: `invited`, `started`, `submitted`, `expired` or `cancelled`
   - `invited_by`
   - timestamps
10. `assessment_invitation_assessors`: the company members who score long-text answers.
11. `assessment_attempts`:
    - `invitation_id`: unique, which gives one attempt
    - `consented_at`, `started_at`
    - `ends_at`: the server deadline
    - `submitted_at`, `auto_submitted`
12. `assessment_attempt_questions`: the questions drawn for this attempt, in order.
13. `assessment_answers`:
    - `attempt_id`, `question_id`
    - `option_ids`, `number_value`, `text_value`
    - `score`, `scored_by`, `answered_at`
14. `assessment_speed_column_results`: `attempt_id`, `section_id`, `column_no`, `attempted`, `correct`.
15. `assessment_drawings`:
    - `attempt_id`, `question_id`
    - `path`: on the configured disk
    - `source`: `canvas` or `upload`
16. `assessment_integrity_events`:
    - `attempt_id`
    - `type`: `tab_switch`, `fullscreen_exit`, `paste` or `ip_change`
    - `occurred_at`
17. `assessment_results`:
    - `attempt_id`
    - `section_scores`: JSON
    - `total`, `band`
    - `integrity_tier`: `none`, `some` or `major`
18. `assessment_reports` (psychological):
    - `attempt_id`, `psychologist_id`
    - `report_number`: unique per provider
    - `body`
    - `status`: `pending_review` or `signed`
    - `signed_at`

### Endpoints

- **Admin:**
  - Providers and psychologists: CRUD and invitations.
  - The system skill library.
  - The company setting `assessment.psychological_enabled`, default off.
- **Provider** (`/provider`):
  - Batteries, sections, questions, norms and report templates.
  - The review queue, report draft and sign.
- **Company** (`/company`):
  - Its own skill tests (CRUD, clone, archive).
  - The catalogue of system tests and of the batteries it may use, showing samples only.
  - Inviting: moving an application to an `assessment` stage takes an `assessment` object (`test_id`, `starts_at`, `due_at`, `assessor_ids`), and bulk moves share it.
  - Cancelling an invitation.
  - Results, integrity log and signed report (PDF download), visible to whoever may see the application.
  - Scoring long-text answers.
- **Job seeker** (`/me`):
  - Invitations.
  - Consent and start.
  - Fetch the current section, save answers, upload a drawing.
  - Report integrity events.
  - Submit.
  - Read the explanation of a signed psychological report.

### Limits

These are admin settings, overridable per company.

| Setting | Default | Range |
|---|---|---|
| Company skill tests | 50 | 1 to 500 |
| Sections per test | 10 | 1 to 20 |
| Questions per section | 200 | 1 to 1,000 (batteries reach 570 questions) |
| Section duration | 1 to 180 minutes | |
| Gap from invitation to due time | At least 1 hour | Up to 30 days |
| Drawing upload | JPG or PNG, 5 MB | |

## Coding parts (approved by the user 2026-10-11; coding starts in a new session with part 1)

1. **Stage type `assessment`** (Recruitment) (**coded 2026-10-11**, not yet checked; migration 000116 is new; funnel: the stage before an unused `assessment` stage is measured against `interview`):
   - The enum and the CHECK constraint.
   - The default stage after `screening`.
   - A backfill through `recruitment:provision-default-stages`.
   - `recruitment.max_stages` default 11.
2. **Psychologist accounts and providers** (**coded 2026-10-11**, not yet checked; migrations 000117 and 000118 are new; see "Part 2 as coded" below):
   - `UserType::Psychologist` with TOTP required, created by an admin invitation.
   - The `Assessment` context with providers and psychologists, and the admin endpoints.
3. **Test authoring** (**coded 2026-10-11**, targeted tests green; migration 000119 is new; see "Part 3 as coded"):
   - Company skill tests.
   - The system library.
   - Provider batteries with sections, questions, options, norms and report templates.
   - The limit settings and `assessment.psychological_enabled`.
4. **Invitations** (**coded 2026-10-11**, migration 000120 is new; see "Part 4 as coded"):
   - Moving to an `assessment` stage, one by one and in bulk.
   - Cancelling.
   - Expiry and the 24-hour reminder.
   - Notifications.
5. **Candidate attempt:**
   - Consent and start, with the server timer.
   - Drawing questions at random.
   - Saving answers.
   - Kraepelin columns and drawings.
   - Integrity events.
   - Submit and automatic submit.
   - Automatic scoring.
6. **Results and reports:**
   - Company results and the integrity tier.
   - Assessor scoring of long text.
   - The provider review queue and signing, with the report number and the STR and SILP check.
   - The PDF report.
   - The candidate's explanation.
   - Erasure through application anonymisation.

As in earlier phases, migrations run on the test database first. The real tables need the user's approval.
