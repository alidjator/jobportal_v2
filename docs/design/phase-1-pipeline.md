# Phase 1: The pipeline (step 8, design confirmed 2026-10-07, not coded yet)

Status: design confirmed by the user on 2026-10-07 (every answer is recorded below); coding has not started.
Inputs that already exist: `decisions.md` sections 1 and 2 (stage types, terminal stages, mandatory rejection reasons from a fixed list, stage history, notifications to the job seeker only for `interview`,
`offer`, `hired` and `rejected`, email through the `application_update` preference) and `phase-1-applications.md` (the application tables, the snapshot, what the applicant sees). Access rule that already
holds for jobs: a company admin sees every job of the company, a recruiter only the jobs assigned to them.

## Confirmed decisions

- **Where recruiters read applications (2026-10-07):** **per job** (`GET /company/jobs/{id}/applications`, filtered by stage and searchable). No company-wide list across jobs in this step.
- **List row and detail (2026-10-07):** the list row shows the application id, the name, the headline, the latest work experience (title and company), the city, the expected salary, the age, whether the must-have
  marks are met (null when the job has none), the stage (the company's name and the system type) and the time applied. The **detail** shows the whole snapshot (contact data, history, skills, languages, links),
  the cover letter, the screening answers, the resume download and the stage history. Email and phone appear only in the detail.
- **List paging, filters and order (2026-10-07):** **offset pagination** (with a total, the shared paging component). Filters: `filter[stage_id]`, `filter[stage_type]`, `filter[meets_must_have]` (true or false) and `filter[q]`
  (the name, searched in the snapshot); any other filter is 422. Order: `sort=applied_at` or `-applied_at`, newest first by default. The list also answers the **number of applications per stage** for the pipeline columns.
- **Moves by a recruiter (2026-10-07):** **free among the active stages** (`applied`, `screening`, `interview`, `offer`, `hired`, default or extra): forward, backward or skipping, and `hired` may be moved back
  (to fix a mistake). A recruiter may also **reject** from any active stage (reason required, below). `rejected` and `withdrawn` are final; a recruiter never moves an application to `withdrawn` (only the applicant
  withdraws) and never moves one out of a final stage. Moving to the stage the application is already in is refused.
- **Interview and offer before Phase 2 (2026-10-07):** in this step a recruiter may move an application to `interview` and `offer` **without a schedule or offer details**, and the applicant is notified as decided.
  When Phase 2 adds scheduling (R7b) and structured offers (R7c), the rule "a schedule is mandatory when moving to `interview`" is switched on; that changes the behaviour of the move endpoint at that point.
- **Rejection (2026-10-07):** the reason is mandatory from the fixed list (`qualifications_mismatch`, `experience_insufficient`, `salary_expectation_mismatch`, `location_mismatch`, `failed_screening`,
  `position_filled`, `other`). The reason `other` **requires** a text note; every other reason may carry an optional note; a note has at most 1,000 characters. The note and the reason live in the stage history and are
  seen only by the company's team (the applicant sees neither, decided 2026-10-06). A normal move carries no note (internal notes belong to Phase 2, R7a).
- **When the applicant is notified (2026-10-07):** every time an application is moved **into** one of the types `interview`, `offer`, `hired` or `rejected`, except a move between two stages of the same type;
  a move back into one of them (for example `hired` to `interview`) notifies again; `rejected` is sent immediately. In-app is always written; the email follows the preference `application_update`
  (default on, email only for now). No delay and no once-per-type limit.
- **In-app notification content (2026-10-07):** one type per outcome: `application.moved_to_interview`, `application.moved_to_offer`, `application.hired`, `application.rejected`. Data: `application_id`, `job_id`,
  `job_title` and `company_name` (frozen when sent, so the notification list needs no extra call). Never the rejection reason or note, and never the recruiter's name.
- **Email to the applicant (2026-10-07):** four templates in the shared catalog, `recruitment.application_interview`, `recruitment.application_offer`, `recruitment.application_hired` and
  `recruitment.application_rejected`, each with default texts in `id` and `en` that an admin can override per language. Variables: `name`, `job_title`, `company_name`. The rejection email is
  neutral and polite and never carries the reason or the note. No link (no client page exists yet). It is sent only when the applicant's preference `application_update` allows email, in the language of the account,
  on a queue (to be chosen when coding); nothing is sent for a deleted (anonymised) account.
- **Recruiter endpoints (approved 2026-10-07):** all `auth:user` (so the terms gate applies), members of an active company only; a company admin sees every job of the company, a recruiter only the jobs assigned to
  them; an application of another job or another company is 404. (1) `GET /api/v1/company/jobs/{id}/applications` (list with the number of applications per stage); (2) `GET /api/v1/company/applications/{id}`
  (detail: the snapshot, the cover letter, the answers, the stage history with the company's stage names, and the rejection reason and note); (3) `GET /api/v1/company/applications/{id}/resume` (the resume copy);
  (4) `POST /api/v1/company/applications/{id}/move {stage_id}` (204; to an active stage of the company, never `rejected` or `withdrawn`); (5) `POST /api/v1/company/applications/{id}/reject {reason, note?}` (204).
  The company's stages are read with the existing `GET /company/stages`.
- **Audit (2026-10-07):** the stage history is the record of moves and rejections (who, when, to which stage, the reason and note); **no extra audit log entries**, and opening a detail or downloading a resume is not recorded.
- **Job status and processing (2026-10-07):** applications can be moved and rejected whatever the status of the job, **except `taken_down`** (by an admin): they stay readable, but a move or rejection is 409
  `job-taken-down`. A suspended company is refused with 403 as in every other company feature.
- **Effect of `hired` (2026-10-07):** none on the job: `hired` changes the application's status and notifies the applicant; the recruiter closes the job; `vacancies` stays informational.
- **Applicant of an anonymised account (2026-10-07):** the list and the detail answer `applicant.name` = null and `applicant.is_deleted` = true (the client writes and translates "Deleted user"); the other snapshot
  columns are null too, and the resume cannot be downloaded (404). The application can still be moved or rejected; no notification or email is sent to a deleted account.
- **Stage names in the history (2026-10-07, consequence of the design):** the history keeps stage ids without foreign keys, so a stage that was deleted later has no name any more; the recruiter's history then shows
  the stage type only.

## Coding plan (confirmed 2026-10-07, four parts; no new migration, the tables exist)

1. The recruiter's read side: access per job (a Job port for the status and the assignment), the list with its filters, the stage counts and offset paging, the detail and the resume download, with tests.
2. Move and reject: the domain rules, the application row lock, the stage history by the recruiter, the error codes (`application-final`, `already-in-stage`, `invalid-stage`, `job-taken-down`) and tests, including
   a race with a withdrawal.
3. Notifications: the event, the four in-app types, the four email templates and their sending according to the `application_update` preference, nothing for a deleted account.
4. The final tests, this document and the handoff.

## Part 1 coded (2026-10-07, not committed)

`GET /api/v1/company/jobs/{id}/applications`, `GET /company/applications/{id}` and `GET /company/applications/{id}/resume`, all `auth:user` (so the terms gate applies), for members of an active company (403
`not-a-company-member` or `company-suspended`); a job the member may not see, an application of another company and an application of an unassigned job are all 404. **List:** row `id`, `applicant {is_deleted, name,
headline, latest_experience {title, company}, city, expected_salary {amount, period}, age}`, `meets_must_have`, `stage {id, type, name}`, `applied_at`; offset pagination with the standard `links` and `meta`
(total); a top-level `stages` array with **every stage of the company in order** and its `count` over all the job's applications (not affected by the filters); filters `stage_id`, `stage_type`,
`meets_must_have`, `q` (the name, any letter case, `%` and `_` searched literally) and `sort=applied_at|-applied_at`; anything else is 422. Email and phone are not in the list. **Detail:** `job`, `stage`,
`meets_must_have`, `applied_at`, `applicant` (the whole snapshot without `version`, so name, email, phone, location, experience, education, skills, languages and links; for an anonymised account only
`is_deleted: true` and a null name), `cover_letter`, `resume {name}` (null once erased), `withdrawn_note`, `answers` and `history` (each entry: `from` and `to` as `{stage_id, type, name}`, with a null name for a stage
that was deleted, `by`, `actor {id, name}` for a recruiter, `reason`, `note`, `at`). **Confirmed by the user (2026-10-07):** the detail also shows the applicant's withdrawal note; the history shows `by` and the actor `{id, name}` for a recruiter (names come from the company-member account port).
The resume download has the same headers as the applicant's (`PdfDownload`). Implementation: `RecruiterApplicationReader` (Application) with a database reader, `CompanyApplicationAccess`,
`ApplicationRepository::find`; the answers query is shared (`DatabaseApplicationAnswers`). 13 feature tests; suite 1150 passed, Pint and PHPStan clean.

## Part 2 coded (2026-10-07, not committed)

`POST /api/v1/company/applications/{id}/move {stage_id}` and `POST /company/applications/{id}/reject {reason, note?}`, both 204, for the members who may see the job (same access as the read side; 403
`not-a-company-member` or `company-suspended`, 404 for an application the member may not see). **Move:** to any stage of the company whose type is active (`applied`, `screening`, `interview`, `offer`, `hired`,
default or extra), forward, backward or skipping; into `rejected`, `withdrawn`, a stage of another company or an unknown stage it is 422 `invalid-stage` (`errors.stage_id`); into the stage it is already in 409
`already-in-stage`. **Reject:** into the company's default `rejected` stage from any active stage (`hired` included); the reason is required from the fixed list (422 otherwise), `other` needs a note, a note has at most
1,000 characters (422 `invalid-rejection`, `errors.note`), a blank note counts as none; the application keeps its key, so a rejected applicant cannot apply to the job again. **Both:** a rejected or withdrawn
application is final (409 `application-final`); a job taken down by an admin cannot be processed (409 `job-taken-down`) but stays readable, any other status can; an anonymised applicant's application can still be
processed. Each action locks the application row first, so it meets a withdrawal that happened meanwhile (the result is `application-final`); each writes the new stage, one history entry (actor `recruiter`, the
member's account id, the reason and the note) and, after the commit, dispatches `ApplicationStageChanged` (application, job, company, applicant, from type, to type) for the notifications of part 3. Nothing is changed when
a rule refuses. New: `JobDirectory::isTakenDown`, `ApplicationRepository::recordMove`, domain methods `Application::moveTo` and `reject`, the use case `ProcessApplication`. 6 unit tests and 15 feature tests; suite 1177
passed, Pint and PHPStan clean.

## Part 3 coded (2026-10-07, not committed)

When a recruiter moves or rejects an application, `ApplicationStageChanged` (part 2) is handled by two queued steps on the `default` queue (3 tries, backoff 10, 60, 300 seconds), separate so a retry of one never repeats
the other: the in-app notification and the email (`NotifyApplicantOfStageChange`). The applicant is told only when the application **enters** `interview`, `offer`, `hired` or `rejected` and the type changed (a move
between two stages of one type, a move to `applied` or `screening`, and the applicant's own withdrawal tell nobody; a move back into one of the four tells again). In-app types `application.moved_to_interview`,
`application.moved_to_offer`, `application.hired` and `application.rejected`, data `application_id`, `job_id`, `job_title`, `company_name`; always written. Email: the templates `recruitment.application_interview`,
`..._offer`, `..._hired` and `..._rejected` (default texts in `id` and `en`, variables `name`, `job_title`, `company_name`, none required; admin-overridable through the existing email template API), sent on the `default`
queue in the language of the applicant's account, only when the preference `application_update` allows email (default on). Neither carries the rejection reason, the note, the recruiter or a link. Nothing is sent when the
account is gone or anonymised (`ApplicantContacts::contact` is null then; the contact now also carries the locale). With `MAIL_MAILER=log` and `QUEUE_CONNECTION=sync` (the current dev settings) the emails are
written to the log inside the request. **Confirmed by the user (2026-10-07):** the email queue is `default` (not `critical`, which the verification codes use); the default texts, written by me (the user approved their content only in
principle), were reviewed and kept; an admin can change them per language or they can be edited in `RecruitmentEmailTemplates`. 9 feature tests; suite 1186 passed, Pint and PHPStan clean.

## Part 4 done (2026-10-07, not committed), which completes step 8

The final test is `PipelineJourneyTest`: one application from applying to the recruiter's list and counts, a move to the interview, a rejection with reason and note, the final-state refusals, the history, and the
erasure of the applicant, checking at every step that the applicant's side and the company's side agree and that the applicant never sees the reason or the note. Suite 1187 passed, Pint and PHPStan clean. No
migration was needed in this step. The handoff is updated; the next step is the data migration (step 9).
