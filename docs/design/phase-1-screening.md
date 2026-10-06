# Phase 1: Screening questions (step 5, DONE 2026-10-06)

Status: design confirmed by the user on 2026-10-06 (every answer is recorded below). **Coding in three parts (confirmed):** part 1 = the four migrations (run on the `test_` tables first; the real tables
only with the user's approval), the two settings and the domain (types, choices, ideal answers); part 2 = the seven library endpoints; part 3 = the ports to Job, the questions of a job (GET and PUT) and the public
endpoint.

**Part 1 coded (2026-10-06, not committed):** migrations `000063` to `000066` (run on the `test_` tables only; the real tables are **not** created yet), the settings `recruitment.screening_enabled` and
`recruitment.max_screening_questions`, and the domain `QuestionType`, `QuestionRules` (text and choice limits) and `IdealAnswer` (validation by type and `isMetBy`). 33 new tests (suite 938 passed, Pint and
PHPStan clean). Lesson: MariaDB foreign key names are unique in the whole database and limited to 64 characters, so the two foreign keys of `recruitment_job_question_ideal_options` have explicit names that
start with the table prefix.
Decided earlier (`decisions.md` section 2): five answer types (text, yes/no, single choice, multiple choice, number); must-have is a mark only (no automatic rejection; failing applications arrive flagged
"does not meet must-have"); answers in a separate table, one row per answer; limit per job default 5 set by an admin (global setting with a per-company override); per-company library reused across jobs, the
feature can be switched on or off per company, no system templates; a question that already has answers cannot be edited or deleted, only archived; no text snapshot on answers.

## Confirmed decisions

- **Bounded context (2026-10-06):** the library, the attachment to jobs and (step 7) the answers live in **Recruitment**, with the stages and applications. Job does not know about screening.
- **Where the flags live (2026-10-06):** the library holds only the question text, the type and the choices. **Required, must-have and the ideal answer are set per job** when a question is attached, so the same
  question can be must-have in one job and optional in another.
- **Ideal answer per type (2026-10-06):** a must-have question is checked automatically when the application arrives. `yes/no`: one ideal answer. `single choice`: one or more accepted options (the answer
  must be one of them). `multiple choice`: every ideal option must be among the chosen ones (extra choices are fine). `number`: a minimum and/or a maximum (at least one). `text` **cannot be must-have**
  (a machine cannot judge it).
- **Required and must-have (2026-10-06):** marking a question must-have **forces it to be required**, so a must-have question always has an answer to check.
- **Content limits (2026-10-06):** question text 5 to 300 characters; a choice question has 2 to 10 options of 1 to 100 characters each, unique ignoring letter case; a text answer is at most 1,000 characters;
  a number answer is a whole non-negative number of at most 9 digits; questions are plain text in one language (written by the company, not translated).
- **Feature switch (2026-10-06):** the admin setting `recruitment.screening_enabled` (boolean, default on, overridable per company, managed by admins with `settings.manage`). While it is off for a company, its
  library and its job attachments cannot be changed and its jobs neither show nor ask questions; nothing is deleted, and switching it on again restores everything.
- **Who may do what (2026-10-06):** company admins create, change and archive the library questions (R1). Company admins and recruiters read the library and attach, detach and arrange questions on the jobs
  they may manage (a company admin on every job of the company, a recruiter on the jobs assigned to them).
- **When a job's questions may change (2026-10-06):** while the job's status is editable (not `pending_review`, not `taken_down`) and the job has **no applications**; once it has applications the list is locked
  (409 `job-questions-locked`), like the title and category of the job. To change them the company clones the job. Applications arrive in step 7, so until then Recruitment asks a port that reports none.
- **Changing library questions (2026-10-06):** the type never changes. The text and the choices can be changed, and the question deleted, only while it is **not attached to any job that has applications and has
  no answers**; otherwise it can only be archived (409 `question-in-use`). An archived question cannot be attached to jobs any more; its existing attachments keep working, and it can be restored.
- **Limit per job (2026-10-06):** the admin setting `recruitment.max_screening_questions` (integer 1 to 20, default 5, overridable per company, managed with `settings.manage`) counts every question attached to the
  job. Lowering it below the current count leaves the attached questions alone and refuses only new ones (409 `screening-limit-reached`).
- **Tables (approved 2026-10-06):** (1) `recruitment_screening_questions`: `company_id` (FK `company_companies`, RESTRICT), `type` (CHECK `text`, `yes_no`, `single_choice`, `multiple_choice`, `number`),
  `text` varchar(300), `archived_at` null, `created_by_user_id` (no FK), timestamps. (2) `recruitment_screening_question_options`: `question_id` (FK cascade), `label` varchar(100), `position`; unique
  (`question_id`, `label`), case-insensitive. (3) `recruitment_job_questions`: `job_id` (plain index, **no foreign key** to `job_jobs`: it is another context), `question_id` (FK RESTRICT), `position`,
  `is_required`, `is_must_have`, `ideal_yes` tinyint null, `ideal_min` and `ideal_max` bigint unsigned null; unique (`job_id`, `question_id`). (4) `recruitment_job_question_ideal_options`: `job_question_id`
  (FK cascade), `option_id` (FK RESTRICT); unique pair. The answers table comes with applications (step 7).
- **Endpoints (approved 2026-10-06):** library under `/api/v1/company/screening-questions`: `GET` (every company member; `filter[archived]=true|false`, `filter[q]`, offset-paginated) and `GET {id}`; for company
  admins only `POST {text, type, options[]}`, `PATCH {id} {text?, options[]?}`, `DELETE {id}`, `POST {id}/archive` and `POST {id}/restore`. On a job: `GET /company/jobs/{id}/screening-questions` (members who may
  see the job) and `PUT /company/jobs/{id}/screening-questions {questions: [{question_id, required, must_have, ideal}]}`, which replaces the whole ordered list (an empty list removes all); `ideal` is
  `{yes: bool}`, `{option_ids: [...]}` or `{min?, max?}` by type.
- **Public side (2026-10-06):** `GET /api/v1/jobs/{id}/screening-questions` (no sign-in; `{id}` and `{id}-{slug}` as for the job page). Same 404 as the job page for a job that is not live or whose company is
  suspended or hidden; `Cache-Control: public, max-age=60` and an `ETag`. It shows `id`, `text`, `type`, `options [{id, label}]`, `required` and the order, and **never** the must-have flag or the ideal answer.
  With the feature switched off for the company the list is empty. The job page itself does not change. Recruitment reaches the job through new ports in `src/Shared` provided by Job (to be fixed when coding).
- **Audit (2026-10-06):** changes to the library and to the questions of a job are **not audited**, like the stages. Admin changes of the two settings are audited by the settings mechanism.
- **Deleting and editing library questions, as coded in part 2 (2026-10-06, confirmed with the user):** a question can be **deleted only while it is attached to no job at all** (409 `question-in-use`; the
  company detaches it first), which matches the foreign key; otherwise it is archived. Choices are sent as `[{id?, label}]` on `PATCH`: an entry with an `id` renames that choice (the ideal answers that point
  to it stay valid), an entry without one is a new choice, a choice that is not sent is deleted, and the order of the list is the order of the choices; deleting a choice that a job uses as an ideal answer
  is 409 `question-in-use`. Creating sends `options: [{label}]`. While `recruitment.screening_enabled` is off for the company, every change is refused with 403 `screening-disabled` (reading stays possible).

**Part 2 coded (2026-10-06, not committed):** the seven library endpoints (`GET|POST /company/screening-questions`, `GET|PATCH|DELETE .../{id}`, `POST .../{id}/archive` and `/restore`), 17 tests in
`ScreeningQuestionTest` (suite 955 passed, Pint and PHPStan clean). Implementation notes: the use cases share `RecruitmentAccess` (renamed from `StageAccess`) and `ScreeningSwitch`; error codes
`invalid-screening-question` (422, `errors` per field), `question-in-use` (409) and `screening-disabled` (403); a question or a choice of another company is 404; the list shows the **active questions by
default** (`filter[archived]=true` for the archived ones), newest first; **an archived question can still be edited** (nothing in the design forbids it); archiving twice changes nothing. The "applications or
answers depend on it" rule goes through the port `ScreeningQuestionUsage`, bound to `NoApplicationsYet` until step 7.

**Part 3 coded (2026-10-06, not committed), which completes step 5:** `GET|PUT /company/jobs/{id}/screening-questions` and the public `GET /jobs/{job}/screening-questions`; 21 tests in
`JobScreeningQuestionTest` (suite 976 passed, Pint and PHPStan clean). Implementation notes: Recruitment reaches the job through the new shared port `JobDirectory` (`visibleToMember`, `publicCompanyId`, `lock`;
provided by Job, which moved its "is the job live" rule into `JobVisibility`, shared with the public page); a job with applications is asked through `JobApplicationCheck` (bound to `NoApplicationsYet`
until step 7). Errors: `invalid-job-questions` (422, every problem at once under `errors["questions.<n>.<field>"]`), `job-not-editable` (409, same code as the Job context), `job-questions-locked` (409),
`screening-limit-reached` (409: refused only when the list is longer than the limit **and** adds a question that is not attached yet), `screening-disabled` (403). My choices while coding: an `ideal` sent
for a question that is not must-have is refused (422); a must-have question is stored as required whatever was sent; an archived question can stay in a job's list but cannot be added back once removed;
the public list uses a separate DTO (`PublicScreeningQuestion`) so the must-have flag and the ideal answer cannot leak.
