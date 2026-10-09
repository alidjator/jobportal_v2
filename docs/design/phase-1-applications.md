# Phase 1: Applying to a job (step 7, design confirmed 2026-10-06, not coded yet)

Status: design confirmed by the user on 2026-10-06 (every answer is recorded below); coding has not started.
Inputs that already exist: `decisions.md` sections 1 and 2 (one active application per job seeker and job, withdrawal, re-applying only after `withdrawn`, stage history, rejection reasons, screening answers in a
separate table, must-have is a mark only) and `phase-1-jobseeker.md` (profile completeness to apply, each application keeps its own copy of the resume file).

## Confirmed decisions

- **Bounded context (2026-10-06):** applications belong to the existing context **`Recruitment`** (table prefix `recruitment_`), next to stages and screening questions; step 8 (pipeline) continues there.
- **Verified email (2026-10-06):** applying **requires a verified email**; otherwise 409 `email-not-verified`. This needs the account-state middleware of `architecture.md` 2.8, written with this step.
- **Content of an application (2026-10-06):** one resume (a PDF of the profile; the generated CV of J7 is added when that feature exists), the screening answers, and an **optional plain-text cover letter** (up to 2,000 characters). No other attachments.
- **Applicant profile (2026-10-06):** the recruiter sees a **snapshot frozen at the moment of applying** (contact data, experience, education, skills, languages, links), not the live profile. The snapshot is erased when the account is anonymised; the exact fields are still to be decided.
- **Snapshot fields (2026-10-06):** name, email, phone and WhatsApp, location, headline, summary, experience, education, skills, languages, links, expected salary and availability, plus the **age at the time of applying** (not the birth date). **Gender, religion, marital status and nationality are not copied.**
- **Avatar (2026-10-06):** **not shown** to recruiters and not copied; clients use initials.
- **Anonymisation (2026-10-06):** the application rows, stage history and statistics stay; the profile snapshot, the cover letter, the application's resume copy and the **free-text screening answers** are erased; yes/no, choice and number answers and the must-have result stay; the applicant shows as `Deleted user`. Recruitment erases its own data by listening to `UserAnonymized`.
- **Uniqueness (2026-10-06):** a nullable column `active_key` (`<user_id>-<job_id>`) with a **unique index**, set while the application is **not withdrawn** (so a rejected or hired application still blocks re-applying, as decided) and set to NULL on withdrawal; the daily limit is counted under the profile-row lock used by the resume limit.
- **New-application notification (2026-10-06):** the job's **assigned team members** get an **in-app** notification `application.received`; no email, and the applicant gets no notification (the response confirms). A job always has at least one assignee (Jobs rule), so no fallback is needed.
- **Applicant endpoints (2026-10-06), all behind `auth:user` and the terms gate, job seekers only:** `POST /api/v1/jobs/{id}/applications` (201; body `resume_id`, optional `cover_letter`, `answers[]`), `GET /api/v1/me/applications` (cursor-paginated, newest first, `filter[status]` by stage type), `GET /api/v1/me/applications/{id}` (job, stage type, stage history by type, answers, the name of the resume copy, download of that copy) and `POST /api/v1/me/applications/{id}/withdraw {note?}` (204). No eligibility pre-check endpoint and no per-user data on the cached public job page: clients use the profile `completeness` and the public screening questions to guide the user. The recruiter side belongs to step 8.
- **Tables (approved 2026-10-06):** (1) `recruitment_applications`: `id`, `user_id`, `job_id` (plain indexed columns, no foreign key), `company_id` (FK RESTRICT), `stage_id` (FK RESTRICT), `stage_type` (CHECK, the seven types), `active_key` (unique, nullable), `cover_letter`, `resume_name`, `resume_path`, `resume_size_bytes`, `profile_snapshot` (JSON), `meets_must_have` (null when the job has no must-have question), `withdrawn_note`, `applied_at`, timestamps; indexes (`job_id`, `stage_type`) and (`user_id`, `applied_at`). (2) `recruitment_application_answers`: `application_id` (FK cascade), `question_id` (FK RESTRICT), `answer_text`, `answer_number`, `answer_bool`; unique (`application_id`, `question_id`). (3) `recruitment_application_answer_options`: `answer_id` (FK cascade), `option_id` (FK RESTRICT) for single and multiple choice. (4) `recruitment_application_stage_history`: `application_id` (FK cascade), `from_stage_id` (null), `to_stage_id`, `from_type` (null), `to_type`, `actor_kind` (`applicant`, `recruiter`, `system`), `actor_id` (null), `rejection_reason` (null), `note` (null), `created_at`.
- **Snapshot storage (2026-10-06):** **one JSON column** `profile_snapshot` on the application (versioned shape; master values stored as id plus the names at that time), read whole and never queried by part; cleared on anonymisation. Screening answers stay in their own table.
- **Status shown to the applicant (2026-10-06):** only the **system stage type**; a company's own stage names are never shown to the job seeker (clients translate the type).
- **Withdrawal (2026-10-06):** allowed from every active stage type (`applied`, `screening`, `interview`, `offer`), **not** from `hired`, `rejected` or `withdrawn`; optional text note, no fixed reasons.
- **Closed or expired job (2026-10-06):** active applications are **left unchanged** and recruiters can keep processing them; the job only stops accepting new applications.
- **Daily limit (2026-10-06):** a global admin setting `recruitment.max_applications_per_day` (default 20, range 1 to 100), counted per calendar day in Jakarta; over the limit is 429 `application-limit-reached`. No per-company override.

## Coding plan (confirmed 2026-10-06, five parts)

1. The four migrations (on the `test_` tables first; the real tables only with the user's approval), the application domain and the account-state middleware (verified email).
2. Applying: use case, answer validation, `meets_must_have` through `IdealAnswer::isMetBy`, resume copy, profile snapshot, daily limit and its setting, the `application.received` notification, and the real
   implementations of the stubs `JobApplicationCheck` and `ScreeningQuestionUsage`.
3. List, detail, download of the resume copy, and withdrawal.
4. The Jobs lock (title, category, role, job type and level cannot change once a job has applications), moving applications in `DeleteStage`, and the `application_update` notification preference.
5. Anonymisation (a Recruitment listener on `UserAnonymized`) and the final tests; this document is updated after every part.

## Part 1 coded (2026-10-06, not committed)

Migrations `000074` to `000077` (run on the `test_` tables only; the real tables are **not** created, as agreed they wait until after part 2), the domain (`Application`, `ApplicationResume`, `RejectionReason`,
`StageMoveActor`, `StageType::allowsWithdrawal`) and the verified-email gate. Suite 1083 passed, Pint and PHPStan clean. Notes: in `recruitment_application_stage_history`, `from_stage_id` and `to_stage_id` are
**plain columns without a foreign key** (a deviation from "FK" for stages in the table list above, chosen so an extra stage can still be deleted; the history keeps the stage types); the answers table links to
`recruitment_screening_questions` and the answer-options table to `recruitment_screening_question_options` with `RESTRICT`, so answered questions and options cannot be deleted. The gate is the middleware alias
`verified.email` (`RequireVerifiedEmail`, use case `EnsureEmailVerified`): opt-in per route after `auth:user`, 409 `email-not-verified`; it is not attached to any route yet (part 2 adds it to the apply route).

## Part 2 coded (2026-10-06, not committed)

`POST /api/v1/jobs/{job}/applications` (`auth:user`, `verified.email`; `{job}` is the id with the optional slug as on the job page). Body: `resume_id`, optional `cover_letter`, `answers: [{question_id, value}]`, where
`value` follows the question type (text: string; yes/no: boolean; number: whole number 0 to 999,999,999; single choice: the option id; multiple choice: a list of option ids). Answer: 201 with `id`, `job_id`, `status`
(the stage type) and `applied_at`; the must-have result is never returned to the applicant. Errors: 403 `job-seeker-only`, 404 (the job is not live), 409 `already-applied` and `email-not-verified`, 422
`profile-incomplete` (with `missing`), `invalid-application` (`errors.resume_id`, `errors.cover_letter`) and `invalid-answers` (`errors` keyed `answers.<question id>`, all reported together), 429
`application-limit-reached` (with `Retry-After` until the next Jakarta midnight). Rules: the answers are only asked and checked while screening is switched on for the company; the text answer limit is the
existing 1,000 characters; the daily count includes withdrawn applications; an application takes the company's default `applied` stage and writes the first stage-history entry (applicant, from nothing).
Implementation: new shared ports `ApplicantProfileSource` (JobSeeker: completeness, the chosen resume file, the snapshot, and the lock on the applicant) and `ApplicantContacts` (Identity: name and email);
`JobDirectory::assigneeUserIds`; the resume copy is stored under `recruitment/applications/<random>.pdf` inside the transaction and deleted again when anything fails; `ApplicationSubmitted` is dispatched
after commit and the queued listener `NotifyAssigneesOfApplication` (queue `default`) writes `application.received` (ids only) for the job's assignees; `DatabaseApplicationUsage` replaces the stub
`NoApplicationsYet` for `JobApplicationCheck` and `ScreeningQuestionUsage` (questions with answers, or attached to a job that has applications, are locked); new global setting
`recruitment.max_applications_per_day` (default 20, range 1 to 100, not overridable per company). **Refinement of the snapshot (confirmed by the user, 2026-10-06):** a master value is stored as its id plus its name in
both languages (`{id, name: {id, en}}`), so a recruiter reads it in either language. 21 feature tests and 6 unit tests; suite 1112 passed, Pint and PHPStan clean. The real tables for the four application
migrations are still **not** created.

## Part 3 coded (2026-10-06, not committed)

`GET /api/v1/me/applications` (cursor-paginated, newest first by time applied then id, `filter[status]` = a stage type, any other filter is 422, an invalid cursor is 422 `invalid-cursor`), `GET /me/applications/{id}`,
`GET /me/applications/{id}/resume` and `POST /me/applications/{id}/withdraw {note?}` (204); all `auth:user` (so the terms gate applies), job seekers only (403 `job-seeker-only`); another person's application is 404.
List item: `id`, `job {id, title}`, `company {id, name}`, `status` (the system stage type), `applied_at`; a job or company that no longer exists leaves its title or name null and the application stays. Detail adds
`cover_letter`, `resume {name}` (null once the copy was erased), `withdrawn_note`, `history [{status, at, by}]` (oldest first; `by` is `applicant`, `recruiter` or `system`) and `answers [{question {id, text, type},
text, number, bool, options [{id, label}]}]`; it never shows the must-have result, the profile snapshot, recruiter notes or rejection reasons. **Decided 2026-10-06 (user): the applicant does not see the rejection reason for now (only the status `rejected`); to be decided again in step 8 when rejecting exists.** **Decided 2026-10-09 (user): the applicant sees the reason code of a rejection (`rejection_reason` on `GET /me/applications/{id}`, null unless the status is `rejected`), never the free note;** notifications and emails still carry no reason. Earlier wording: whether the applicant sees the rejection
reason; until then it is hidden. The resume download answers `application/pdf` with `attachment`, `no-store`, `nosniff` and `Content-Security-Policy: default-src 'none'`, named as the applicant named it. Withdrawal:
the application locks its row, moves to the company's default `withdrawn` stage, frees `active_key` (so the job can be applied to again, as a new application; a rejected or hired one still blocks), keeps
the note in `withdrawn_note` and adds a history entry (from the stage it was in, by the applicant); from `hired`, `rejected` or `withdrawn` it is 409 `application-not-withdrawable`; the note is at most 1,000
characters (422 `invalid-withdrawal`); the resume copy stays. New: `JobDirectory::titles`, `ApplicantGuard`, test trait `BuildsApplicationFixtures`. 14 feature tests; suite 1126 passed, Pint and PHPStan clean.

## Part 4 coded (2026-10-06, not committed)

**The Jobs lock:** once a job has applications (withdrawn ones count, they are history), `PATCH /company/jobs/{id}` refuses a change to `title`, `category_id`, `role_id`, `job_type_id` or `job_level_id` with 409
`job-locked-after-applications` and `fields` naming what was refused; a value sent unchanged is not a change, and every other field stays editable. Job asks the new shared port `JobApplications`
(`hasApplications`, provided by Recruitment through `DatabaseApplicationUsage`; `JobApplicationCheck` now extends it). The check runs right after the changes are applied, before the live-job completeness rules.
**Deleting an extra stage:** `DeleteStage` moves every application of the stage to the default stage of the same type in the same transaction (`ApplicationRepository::moveStage`), with one history entry per
application (from the deleted stage to the default one, same type, actor `system`); the applicant's history leaves out an entry that repeats the previous stage type, so the applicant never sees a move between two
stages of one type. **Preference:** the kind `application_update` is registered in the notification catalog by the Recruitment provider with the **email channel only**, on by default (decided earlier: in-app is
always written; the email is what the user tunes); push joins when push can be sent (my choice for the push channel, to confirm). The email itself is sent with the stage notifications in step 8. The old test sample
of the preference tests was renamed `probe_update`. 7 feature tests; suite 1133 passed, Pint and PHPStan clean.

## Part 5 coded (2026-10-07, not committed), which completes step 7

**Anonymisation:** the queued listener `EraseApplicantDataWhenUserAnonymized` (queue `default`, 3 tries, idempotent) runs `EraseApplicantData`: it deletes the resume copies first, then in one transaction clears, for
every application of the user, the profile snapshot, the cover letter, the resume name, path and size, and the free-text answers; the application rows, their stage and history, and the yes/no, number and choice
answers stay. **Addition to the decision (confirmed by the user, 2026-10-07):** the withdrawal note is also cleared, because the applicant wrote it. A failing file deletion stops before any row is cleared, so a retry finishes
the job. The applicant's own list and detail keep working afterwards (without cover letter, resume and text answers); the "Deleted user" label belongs to the recruiter side (step 8). 4 feature tests; suite 1137
passed, Pint and PHPStan clean. The four application migrations were run against the real tables on 2026-10-07 with the user's approval (77 migrations recorded; the new tables are empty and identical to the test database).
