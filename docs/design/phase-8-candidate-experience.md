# Phase 8: candidate experience: no silent applications, a rejection message, a to-do list and test preparation (designed 2026-10-15, not coded)

Gaps found from the candidate's point of view (a job seeker with applications in progress) and against Mekari Talenta (help center articles
34773101103385: an internal rejection reason plus an optional "Notify candidate via email" note; 61789018343449: email templates per scenario;
54018163360153: a pre-assessment page with a checklist and technical requirements), chosen by the user from the advice of 2026-10-15. Talenta
candidates have no account, so the to-do list goes further than Talenta. Not taken now: accommodation requests (needs a legal question on health
data), a candidate experience survey (the company reviews have a "hiring process" aspect), interviewer names for the candidate, offer negotiation.
Extends `phase-1-applications.md`, `phase-1-pipeline.md`, `phase-2-bulk-actions.md`, `phase-8-rejections.md` (pending rejections),
`phase-8-interview-calendar.md` (default texts), `phase-6-assessments.md` and the dashboard. Coded in a coding session, after the rejections.

**Read from the code (2026-10-15):** the applicant already sees the rejection reason code on `GET me/applications/{id}` (decision 2026-10-09), never
the free note; the stage notices cover `interview`, `offer`, `hired` and `rejected` only; active applications on a closed job stay as they are;
sample questions (`is_sample`) are part of the drawn bank (`StartAttempt::draw`); the job seeker dashboard has no card for tests or offers waiting.

## Decisions

- 2026-10-15: when a job closes, expires or is taken down, applicants still in `applied` or `screening` get an **in-app notice** and every active
  application shows **"Lowongan sudah ditutup"**; no email (chosen by the user).
- 2026-10-15: the team may **reject the remaining applicants** of a closed job, choosing the stages, any active stage allowed (chosen by the user).
- 2026-10-15: a move from `applied` into `screening` gives an **in-app notice** "Lamaran sedang ditinjau", no email.
- 2026-10-15: a rejection may carry a **message for the candidate**, separate from the team note, stored on the **stage history entry**; this
  reverses "no message to the applicant" of 2026-10-09 (decided by the user).
- 2026-10-15: a **to-do list** for the candidate, as a dashboard card and `GET me/tasks` (chosen by the user).
- 2026-10-15: **practice questions for skill tests** from the sample questions, which are **no longer drawn** into attempts; a device check before
  every test (decided by the user).
- 2026-10-19: an **agenda** of the candidate's coming dates, `GET me/agenda` and a dashboard card, next to the to-do list (FG1 of
  `docs/analysis/actor-gap-analysis.md`, chosen by the user).

## 1. No silent applications

- **Label:** `GET me/applications` and `GET me/applications/{id}` gain `job_closed` (true while the job is `closed`, `expired` or `taken_down`,
  read through the job port; false again after a reopen). The front end shows "Lowongan sudah ditutup. Perusahaan mungkin masih memproses
  lamaran ini." on active applications.
- **Notice:** Recruitment listens to the job closing, expiring and being taken down and writes in-app `application.job_closed` to each applicant
  whose application of that job is in `applied` or `screening` (queued, batches of 500). Again on each new closing after a reopen. No email.
- **Under review:** a recruiter move from `applied` into a `screening` stage writes in-app `application.under_review` (not between two screening
  stages, not a move back). No email.
- **Reject the remaining:** `POST company/jobs/{id}/applications/reject-remaining {stage_ids (1 or more active stages of the company), reason,
  note?, candidate_note?}` (`applications.manage`, job in scope; the job must be `closed` or `expired`, else 409 `job-still-open`). Answers 202
  `{count}` and runs on the queue through `ProcessApplication::reject` per application (same rules, history, undo window of
  `phase-8-rejections.md`, notices and emails); no bulk cap. Ends with in-app `applications.reject_remaining_done {rejected, skipped}` to the
  caller. Front end: the close dialog offers "Tolak pelamar yang masih aktif" with the stage ticks and counts; a closed job shows the same action.

## 2. Rejection message for the candidate

- **Column:** `recruitment_application_stage_history.candidate_note` (nullable, at most 1,000 characters, plain text) on the entry into `rejected`;
  `recruitment_pending_rejections` gains the same column so an undo drops it. The team `note` stays team-only.
- **Input:** `candidate_note?` on the single reject, `bulk-reject` and `reject-remaining`; the link rule of `phase-8-interview-calendar.md` section 6
  applies (422 `message-link-not-allowed`). The dialog warns that the candidate reads it.
- **Default text:** `company/message-defaults` gains `rejection` (at most 1,000 characters), prefilled in the reject dialogs; only the text actually
  sent is stored.
- **Shown:** the rejection email carries the message under the platform text (still no reason code in the email); `GET me/applications/{id}` gains
  `rejection_message` next to `rejection_reason`; the team sees it in the history. Notifications carry no text, as today.
- **Erasure:** the account erasure and the retention of `phase-8-company-admin.md` clear it, as the team note.

## 3. To-do list for the candidate

- **Port:** a `Shared` registry `ApplicantTaskSource` (like the dashboard cards): each context returns the caller's open items `{key, subject_id,
  application_id?, job_title, company_name, due_at?}`. Recruitment: `interview_confirm` (interview `pending`, not started; due at the start) and
  `offer_answer` (offer sent, unanswered; due at its deadline). Assessment: `assessment_take` (invitation `invited` or `started`, not past due;
  due at its due time). JobInvitation: `job_invitation` (`sent` or `viewed`, job live; no due date).
- **`GET me/tasks`** (job seekers; not cached): every item, due ones first by `due_at`, then the rest newest first; at most 100.
- **Dashboard card `todo`:** the first 5 items and the total, under the dashboard cache.
- Front end: "Perlu tindakan" at the top of the dashboard with the due time ("tenggat 2 hari lagi") and a link to the item; a full page from
  `me/tasks`.

## 4. Test preparation

- **Samples not drawn:** `StartAttempt::draw` leaves out questions with `is_sample`, for every test. Publishing (and the auto-issue check) counts
  only the other questions against each section's `draw_count` (`test-incomplete`). A published test whose bank without samples is short keeps
  drawing them until the author fixes it; the authoring view flags it (`samples_drawn: true`).
- **Practice:** `GET me/assessment/invitations/{id}/practice` (invitation `invited` or `started`, skill tests only, else 404): the samples per
  section with their correct answers or accepted answers (`long_text` and `video_answer` without); nothing stored, no timer of the attempt. The
  front end checks the answers in the browser; a `video_answer` sample records locally without upload.
- **Device check:** `GET me/assessment/invitations/{id}` gains `requirements {camera, microphone}` (true when a `video` section exists). Before
  `start` the front end checks the browser (full screen, recording when needed), the connection, and the camera and microphone, and shows the
  environment checklist (quiet room, stable connection, no switching tabs). Front end only; the server keeps its rules.

## 5. Agenda (added 2026-10-19, FG1)

The to-do list holds what waits for the candidate; the agenda holds **when** things happen, answered or not.

- **Port:** a `Shared` registry `ApplicantAgendaSource`, filled like `ApplicantTaskSource`: Recruitment gives `interview` (`pending` and
  `confirmed`, `starts_at` to `ends_at`, format, place or whether a room exists, the open proposal of `phase-8-interview-reschedule.md`),
  `offer_deadline` (offer `pending`, `expires_at`) and `join_date` (application `hired`, all day); Assessment gives `assessment_window`
  (invitation `invited` or `started`, `starts_at` to `due_at`).
- **`GET me/agenda?from=YYYY-MM-DD&to=YYYY-MM-DD`** (job seekers; Jakarta days, `to` included, at most 42 days, 422 `invalid-calendar-range`;
  default today to 30 days ahead): items `{id ("interview:12"...), type, starts_at, ends_at, all_day, status, application_id, job_title,
  company_name, link}`, earliest first, at most 200 (`meta.truncated`). Not cached. Final applications (rejected, withdrawn) are left out, as are
  anonymised ones.
- **Dashboard card `agenda`:** the next 5 items from now and the count of the next 7 days, under the dashboard cache; the card is dropped from
  the cache on the same events that drop `todo`.
- Front end: "Jadwal mendatang" under "Perlu tindakan" on the dashboard; a full list page with a week and list view; each item links to the
  interview, test or offer page.

## Changes in short

| Piece | Change |
|---|---|
| Migration | `recruitment_application_stage_history.candidate_note`; `recruitment_pending_rejections.candidate_note` |
| Ports | `ApplicantTaskSource` (Shared; Recruitment, Assessment, JobInvitation); `ApplicantAgendaSource` (Shared; Recruitment, Assessment) |
| API | `job_closed` and `rejection_message` on my applications; `candidate_note` on reject, bulk reject and reject-remaining; `POST company/jobs/{id}/applications/reject-remaining`; `rejection` in `company/message-defaults`; `GET me/tasks`; `GET me/agenda`; `GET me/assessment/invitations/{id}/practice`; `requirements` on the invitation |
| Assessment | samples left out of the draw; publish counts without samples; `samples_drawn` flag |
| Dashboard | cards `todo` and `agenda` |
| Notifications | `application.job_closed`, `application.under_review`, `applications.reject_remaining_done` (in-app) |
| Errors | 409 `job-still-open` |
| Front end | closed-job label; reject-remaining in the close dialog; candidate message in the reject dialogs and the application detail; "Perlu tindakan"; "Jadwal mendatang"; practice and device check before a test |
