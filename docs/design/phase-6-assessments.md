# Phase 6: Assessment engine (skill tests and psychological tests) (designed 2026-10-11)

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

1. **Stage type `assessment`** (Recruitment):
   - The enum and the CHECK constraint.
   - The default stage after `screening`.
   - A backfill through `recruitment:provision-default-stages`.
   - `recruitment.max_stages` default 11.
2. **Psychologist accounts and providers:**
   - `UserType::Psychologist` with TOTP required, created by an admin invitation.
   - The `Assessment` context with providers and psychologists, and the admin endpoints.
3. **Test authoring:**
   - Company skill tests.
   - The system library.
   - Provider batteries with sections, questions, options, norms and report templates.
   - The limit settings and `assessment.psychological_enabled`.
4. **Invitations:**
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
