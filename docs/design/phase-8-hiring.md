# Phase 8: hire details, filled jobs and the hires export (designed 2026-10-14; sections 1, 2 and 3 coded 2026-10-17)

Gaps found against Mekari Talenta's "Move to hired" (help center article 36986013467033), adapted to a job portal: Talenta's new joiner form,
payroll registration and manpower planning are HRIS features and stay **out of scope** (collecting NIK and bank accounts would also add a large
personal-data risk). Extends `phase-1-pipeline.md` (moves, notifications), `phase-2-offers.md`, `phase-2-export.md` and `phase-8-offer-letters.md`.
Coded in a coding session.

## Decisions

- 2026-10-14: a hire carries a **join date** and an optional **note for the candidate**; the join date can be **changed later**, and the candidate is
  told (decided by the user).
- 2026-10-14: when a job's hires reach its `vacancies`, the team is **offered** to close the job and reject the remaining applicants; jobs without
  `vacancies` get no prompt but the same "mark as filled" action (decided by the user).
- 2026-10-14: in that dialog the **recruiter chooses** whom to reject: early stages are preselected, later stages are not, a pending offer is never
  rejected (decided by the user).
- 2026-10-14: a **new export of hires across jobs** for the company's HRIS (decided by the user).
- 2026-10-17: the audit `recruitment.hire_updated` holds the old and new join date and `note_changed`, **never the note text** (it may name the candidate and the audit cannot be erased); decided by the user (section 1).
- 2026-10-17: coded in parts, **section 1 first**, then 2 and 3 (decided by the user). The export's offer columns use only what `recruitment_offers`
  has today (position, salary, start date, accepted at); the terms columns (employment type, contract, probation, work mode, location, salary basis,
  allowances, benefits) are added when #12 codes the offer terms (decided by the user; section 3).
- 2026-10-17: the single move always answers 200 `{meta: {job_filled}}` (was 204); `fill` with `close: true` on a job that is not live is 409 `job-not-active` and changes nothing; `fill` answers `{data, meta: {job_status}}` with the skip codes `offer-pending`, `application-final`, `application-not-found` (decided by the user; "Coded, section 2").

## 1. Hire details

- New columns on `recruitment_applications`: `join_date` (date) and `hire_note` (at most 1,000 characters, plain text, for the candidate; the link
  rule of `phase-8-interview-calendar.md` section 6 applies). Set when the application enters `hired`, cleared when it leaves it (a correction
  back to another stage).
- **Recruiter moves to hired** (single or bulk): the move request takes `hire: {join_date, note?}`; `join_date` is required (422
  `join-date-required`), from 90 days ago to one year ahead (422 `invalid-join-date`). The dialog prefills the date from the application's offer
  (pending or last accepted) and the note from the company's default text. A bulk move uses one date and note for all.
- **Offer accepted by the candidate:** no dialog; `join_date` is the offer's start date and the note is the company's default "hired" text, if any.
- **Default text:** `company/message-defaults` gains `hired` (at most 1,000 characters), next to `interview`, `assessment` and `offer`.
- **Change later:** `PATCH company/applications/{id}/hire {join_date?, note?}` while the application is `hired` (409 `application-not-hired`), by
  whoever may move it (`applications.manage`). Same date window. Nothing changed: nothing sent. Otherwise the candidate gets the new notification
  `application.hire_updated` (in-app always, email `recruitment.hire_updated` by `application_update`), and the change is audited
  (`recruitment.hire_updated`, old and new values).
- **Shown:** the hired email (`recruitment.application_hired` gains the variables `join_date` and `note`, with the company logo and the anti-scam line
  of section 6 of the calendar design), the candidate's application detail (`hire: {join_date, note}`), the team's application detail and the
  pipeline card.

## 2. Filled jobs

- **Filled** is derived, not stored: the job has `vacancies` and its applications in `hired` number at least `vacancies`. The job list and detail
  gain `hires_count` and `filled`.
- **Prompt:** when a hire makes a job filled (crossing the line, not every later hire), the job's assignees get the in-app notification
  `job.filled` (job title, hires and vacancies) leading to the dialog; a move answered by the API also carries `meta.job_filled: true` so the
  front end opens the dialog at once. No prompt for a job without `vacancies`.
- **Dialog "Tandai terpenuhi"** (also available any time from the job page, with or without `vacancies`): the front end lists the active
  applications by stage with the pipeline list; **applied and screening are preselected**, assessment, interview and offer are not; an application
  with a **pending offer** is shown locked. The recruiter may first add some to a talent pool (the existing talent pool endpoint).
- `POST company/jobs/{id}/fill {close: bool, reject_application_ids: [], note?}`:
  - `close` closes the job through the existing close use case (`jobs.publish`).
  - `reject_application_ids` are rejected with the reason **`position_filled`** and the optional note (team-only, as every rejection note), by the
    rules and notifications of the bulk reject (`applications.manage`), at most the bulk limit per request (the dialog sends batches). An
    application with a pending offer, already final, or not of this job is skipped with its code.
  - Answers 200 with the job status and `data: [{application_id, status: rejected|skipped, code?}]`. Audited (`job.filled`, counts only).
- The candidates rejected this way get the usual rejection notice (no reason in it); the reason code shows on their application detail, as for
  every rejection since 2026-10-09; the note stays team-only.

## 3. Hires export

- `GET company/hires/export?from=YYYY-MM-DD&to=YYYY-MM-DD` (Jakarta days, at most 366, 422 `invalid-export-range`), optional `filter[job_id]`:
  every application **now in `hired`** whose last move into `hired` falls in the range, across the jobs the member may see; needs
  `applications.export`.
- **Columns:** application id, hired at, join date, name, email, phone, city, job id and title, position, employment type, contract months,
  probation months, work mode, work location, salary basis, base salary, allowances (`name: amount` joined with `; `), allowances total, benefits
  (joined with `; `), offer accepted at. Offer columns are empty for a hire without an offer or with an offer from before the terms existed.
- Everything else follows `phase-2-export.md`: streamed CSV with a byte order mark, formula-injection guard, the row cap
  `recruitment.max_export_rows`, the 10-per-hour throttle, anonymised rows kept with empty personal columns, audit entry **`export.hires`**
  (`{from, to, job_id, row_count}`) written before the file is sent, file name `hires-{from}-{to}.csv`.
- Front end: an "Export karyawan baru" button with the date range on the company dashboard or the jobs list (C3).

## Changes in short

| Piece | Change |
|---|---|
| Migration | `recruitment_applications.join_date`, `hire_note` |
| API | `hire` in the move and bulk move; `PATCH company/applications/{id}/hire`; `hires_count` and `filled` on jobs; `meta.job_filled` on moves; `POST company/jobs/{id}/fill`; `GET company/hires/export`; `hired` in `company/message-defaults` |
| Notifications | `application.hire_updated` (in-app and email `recruitment.hire_updated`); `job.filled` (in-app, team); `recruitment.application_hired` gains `join_date` and `note` |
| Audit | `recruitment.hire_updated`, `job.filled`, `export.hires` |
| Errors | 422 `join-date-required`, `invalid-join-date`, `invalid-export-range`; 409 `application-not-hired` |
| Front end | hire dialog (C6, C7); "Tandai terpenuhi" dialog (C5, C6); hires export button; join date and note on the job seeker's application |

## Coded, section 1 (2026-10-17)

Migrations `000163` (`recruitment_applications.join_date`, `hire_note`) and `000164` (`company_message_defaults.hired`); both new.

- **Move and bulk move:** `hire: {join_date, note?}` (`HireInput`, `HireFields`); `ProcessApplication::hireFor` checks it (`HireDetails::make`: window of 90 days back to one year ahead in Jakarta days, note at most 1,000 characters, the link rule `message-link-not-allowed`). A move to another stage with `hire` is 422 `invalid-hire-input` (same pattern as `offer` and `interview`). The bulk move checks the shared details once before moving anyone. A move out of `hired` clears both columns; a move between two hired stages stores the new details.
- **Offer accepted:** `join_date` is the offer's start date (never refused for the window), the note is the company's `hired` default (`CompanyMessageTexts::hired`).
- **`PATCH company/applications/{id}/hire`** (`UpdateApplicationHire`, `applications.manage`): only the parts given change; a date that is not changed is not checked against the window again (a note can still be corrected on an old hire); no change sends and audits nothing; `job-taken-down` also applies, as for moves; a hire from before the details existed needs a `join_date` first. Event `ApplicationHireUpdated`: notification `application.hire_updated` (data: ids, job title, company name, `join_date`) and email `recruitment.hire_updated` by `application_update`.
- **Hired email** (`recruitment.application_hired`) now goes through the framed applicant mail (company logo, anti-scam line) and has the variables `join_date` and `note`, each an optional block with its label (empty without a value), as in the receipt email. The event `ApplicationStageChanged` carries the details.
- **Shown:** `hire: {join_date, note}|null` on the candidate's application detail (`/me/applications/{id}`), the team's detail and the pipeline card; also in the candidate's data export.
- **Message defaults:** `hired` (at most 1,000 characters) next to the other scenarios.
- **Audit (confirmed by the user, 2026-10-17):** the entry `recruitment.hire_updated` holds the old and new join date and `note_changed`, not the note text; the design's "old and new values" is read that way. Also: `hire_note` is cleared by the applicant erasure (the date stays); the extra errors `invalid-hire-note` and `invalid-hire-input`.

## Coded, section 2 (2026-10-17)

No migration. Answers of the user (2026-10-17): the single move answers **200 with `{meta: {job_filled}}` always** (it was 204; the existing tests were adjusted); `fill` with `close: true` on a job that is not live is **409 `job-not-active` and changes nothing**; the answer of `fill` is `{data: [...], meta: {job_status}}` and the skip codes are `offer-pending`, `application-final` and `application-not-found` (not of this job, or not visible to the member).

- **`hires_count` and `filled`** on the job list and the job detail (also inside the admin console's detail): the port `JobHires` (Shared, provided by Recruitment) counts the applications in a hired stage per job; `FilledJob::is` derives `filled` (vacancies stated and hires at least vacancies).
- **Crossing the line:** `WatchJobFill` runs in the transaction of every move into `hired` (from a stage that is not `hired`; a move between two hired stages is not a hire), of recruiter moves, bulk moves and the offer acceptance. It locks the job row first, then the job is filled by this hire when its hires **equal** its vacancies, so two simultaneous hires give exactly one prompt. The event `JobFilled` (after commit) goes to `NotifyAssigneesOfJobFilled` (queued, in-app only): notification `job.filled` to the job's `notificationRecipients` (its assignees, or the company admins when all are suspended) with `job_id`, `job_title`, `hires`, `vacancies`.
- **Move responses:** `ProcessApplication::move` returns whether the job was filled; `POST company/applications/{id}/move` answers 200 `{meta: {job_filled}}`; `bulk-move` adds `meta.job_filled` (true when any item crossed the line). The offer acceptance answers the candidate as before.
- **`POST company/jobs/{id}/fill`** (`FillJob`, `BulkApplicationController::fill`): `close` required, `reject_application_ids` required (may be empty, at most `recruitment.max_bulk_applications`, else 422 `too-many-applications`), optional `note` (rules of a rejection, 422 `invalid-rejection`). Needs `applications.manage`, except a request that only closes the job, which needs only `jobs.publish` (checked by the close itself). The job is closed first through the port `JobCloser` (Job: `CloseJobForCaller` over `CloseJob`); then each application goes through `ProcessApplication::reject` with `position_filled`, so it keeps the rules, history and notifications of a bulk reject. `reject(..., sparePendingOffer: true)` checks the pending offer under the application's row lock (a race with a new offer is safe). Audit `job.filled` (`closed`, `rejected`, `skipped`, `company_id`; never the note) through the event `JobFillHandled`, written in the request.
- **Also:** `JobDirectory` gained `vacancies()` and `statusOf()`. The API docs cache was rebuilt and the error catalog regenerated (`offer-pending` is new).

## Coded, section 3 (2026-10-17)

No migration. **Added by #12 (2026-10-18, see `phase-8-offer-letters.md`):** nine terms columns now follow the offer columns. They were deferred first: the offer terms columns (employment type, contract months, probation months, work mode, work location, salary basis, allowances and their total, benefits) are not in the file, because `recruitment_offers` has no terms yet (decided by the user, 2026-10-17); the columns are added when #12 codes the offer terms. The offer columns of the file are the position, the salary, the start date and the accepted-at moment, from the latest accepted offer of the application; they are empty for a hire without an accepted offer.

- **`GET company/hires/export?from=&to=`** (`ExportHires`, `CompanyApplicationController::exportHires`, `ExportHiresRequest`): `from` and `to` are required `YYYY-MM-DD` Jakarta days (a missing or malformed one is the ordinary 422 validation error); `to` before `from`, or more than 366 days counting both ends, is 422 `invalid-export-range` (new code, error catalog regenerated). Optional `filter[job_id]` (any other filter is 422; a job the member may not see is 404). Needs `applications.view` and `applications.export`.
- **Which hires:** applications of the company **now** in `hired` whose last move into `hired` from another stage type (`recruitment_application_stage_history`, `to_type = hired` and `from_type` null or not `hired`) falls in `[from 00:00, to + 1 day 00:00)` Jakarta time. Oldest hire first. The jobs: every job of the company with `jobs.view_all`, else the jobs assigned to the member; on a job where the member is a bound hiring manager only the applications that carry the marker (`HiringManagerScope`, as the pipeline list).
- **Columns:** application id, hired at (ISO 8601 UTC), join date, name, email, phone, city, job id, job title, offer position, offer salary (IDR), offer start date, offer accepted at. Contact data is always open here (a hired application is past `applied`, see `ContactVisibility`). An anonymised hire keeps its row: name `Deleted account`, email, phone and city empty.
- **Reused from the applicants export:** `CsvResponse` (byte order mark, formula guard), the row cap `recruitment.max_export_rows` (422 `export-too-large` before the audit), the limiter `EXPORT_LIMITER` (10 per member per hour; it is the same limiter, so applicant exports and hires exports share the 10), the non-queued audit listener. Audit `export.hires` (`from`, `to`, `job_id`, `row_count`, `company_id`; subject the company) through the event `HiresExported`, written before the file is sent; `export.hires` is in the "export" group of the company activity log. File name `hires-{from}-{to}.csv`.
- **Port:** `HireExportReader` (Application) read by `DatabaseHireExportReader` (ids first, then chunks of 200, offers read per chunk).
- Tests: `HiresExportTest` (14).
