# Phase 8: hire details, filled jobs and the hires export (designed 2026-10-14, not coded)

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
- The candidates rejected this way get the usual rejection notice; the reason stays hidden from them.

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
