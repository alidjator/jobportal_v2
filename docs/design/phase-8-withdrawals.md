# Phase 8: withdrawals marked by the team, withdrawal reasons and team notices (designed 2026-10-14, not coded)

Gaps found against Mekari Talenta's "Mark as withdrawn" (help center articles 34633532438169 and 34773101103385). Talenta lets only the recruiter
mark a withdrawal; our job seekers keep their own withdraw button. Not taken now: Talenta's archive and "move to another job listing" (reopening a
final application). Extends `phase-1-applications.md` (withdrawal), `phase-1-pipeline.md` (final stages, notifications), `phase-2-offers.md` (decline)
and `phase-8-interview-calendar.md` (`.ics`). Coded in a coding session.

## Decisions

- 2026-10-14: a team member may **mark an application withdrawn** for a candidate who backed out outside the platform; the job seeker is **told**
  (in-app and email) and the status stays final (decided by the user).
- 2026-10-14: a withdrawal carries a **reason from a fixed list**: required when the team marks it, optional for the job seeker (who keeps the free
  note) (decided by the user).
- 2026-10-14: when an application is withdrawn, the job's **assignees** get an in-app notice, and the **interviewers** of an interview it cancels get an
  in-app notice and an **email with a calendar cancel** (decided by the user). These are the first emails to the team.

## 1. Withdrawal reasons

- `WithdrawalReason` (Recruitment domain, like `RejectionReason`): `accepted_other_offer`, `salary`, `location`, `role_mismatch` (the job is not what
  the candidate expected), `process_too_long`, `personal`, `unreachable` (team only: the candidate stopped answering), `other`.
- **Team:** reason required (422 `invalid-withdrawal`, `errors.reason`); `other` needs a note.
- **Job seeker:** reason optional, any value except `unreachable` (422 `invalid-withdrawal`); `other` needs no note. The withdraw request and the
  offer decline (`POST me/applications/{id}/offer/decline`) both take `reason?` next to `note?`. The front end shows the list as choices above the note.
- **Stored:** new column `recruitment_application_stage_history.withdrawal_reason` (nullable, CHECK on the values), set on the history entry into
  `withdrawn`, and `recruitment_applications.withdrawal_reason` for the list and the reports. The application's `withdrawn_note` stays the job
  seeker's own note; the team's note goes into the history entry's `note`, team-only like a rejection note.
- **Shown:** the team's application detail (`withdrawal: {reason, by: applicant|recruiter, actor?, note?, at}`) and history; the job seeker sees the
  reason label on their application detail (their own, or the team's), never the team's note.
- **Erasure:** anonymisation clears both notes as today; the reason code stays (it is not personal text).
- **Reports:** the BI funnel (`phase-4-business-intelligence.md`) splits withdrawn applications by reason and by who marked them (`by_reason`
  table in the withdrawal part of the pipeline report; counts only). Imported legacy withdrawals have no reason (`null`, shown as "Tidak diketahui").

## 2. Marked by the team

- `POST company/applications/{id}/withdraw {reason, note?}` (204), needs `applications.manage` and access to the application. Same stages as the
  job seeker: every active stage except `hired` (409 `application-not-withdrawable`); a hire that falls through is first moved back (the correction
  move of `phase-8-hiring.md`) and then marked. A pending rejection blocks it (409 `rejection-pending`, `phase-8-rejections.md`). Single only, no bulk.
- **Effects:** exactly those of the job seeker's withdrawal: the default `withdrawn` stage, `active_key` freed (the candidate may apply again), the
  active interview cancelled, the pending offer withdrawn, the active test cancelled; the history entry has actor `recruiter` and the member.
- **Job seeker told:** new in-app notification `application.withdrawn_by_company` (always) and email `recruitment.application_withdrawn_by_company`
  (by `application_update`), with the job, the company, the reason label and the line "Jika ini tidak benar, hubungi {company}" (with the company
  contact shown on the job). **One email per move:** the separate "interview cancelled", "offer withdrawn" and "test cancelled" emails and notices
  are not sent; the withdrawal email carries the `interview.ics` cancel when an interview was active (section 3 of the calendar design).
- **Audit:** `recruitment.application_withdrawn_by_company` (application, reason; no note).
- Front end: "Tandai mengundurkan diri" in the application menu (C6, C7) with the reason list and the note.

## 3. Team notices

- **Assignees:** on every withdrawal (by the job seeker, by an offer decline, or marked by the team) the job's notification recipients
  (`JobDirectory::notificationRecipients`) get the in-app notification `application.withdrawn` (`application_id`, `job_id`, `reason`, `by`; ids and
  codes only, no name, as `application.received`). The member who marked it is left out; for an offer decline, the offer's sender already gets
  `offer.declined` and is left out too.
- **Interviewers:** when the withdrawal cancels an interview, its main interviewer and panel members get the in-app notification
  `interview.cancelled_for_team` (`interview_id`, `application_id`, `starts_at`) and the email `recruitment.interview_cancelled_team`: job title,
  candidate name, the time (Jakarta), "Lamaran ditarik" and a link to the application, with `interview.ics` as `METHOD:CANCEL` (the interview's
  `calendar_uid`, `calendar_sequence` raised) so an event they added from `GET company/interviews/{id}/calendar.ics` disappears. The member who
  marked the withdrawal is left out.
- Team emails have no preference yet: the email always goes (a member preference can be added later with the team notification settings).
- Both run as queued listeners after commit (event `ApplicationWithdrawn` with `by`, `reason` and the cancelled interview id), retried like
  `NotifyAssigneesOfApplication`; a failure never undoes the withdrawal.

## Changes in short

| Piece | Change |
|---|---|
| Migration | `recruitment_application_stage_history.withdrawal_reason`, `recruitment_applications.withdrawal_reason` (both nullable, CHECK) |
| Domain | `WithdrawalReason`; event `ApplicationWithdrawn` |
| API | `reason` on `POST me/applications/{id}/withdraw` and the offer decline; `POST company/applications/{id}/withdraw`; `withdrawal` on the team's and the job seeker's application detail |
| Notifications | `application.withdrawn_by_company` (in-app, email `recruitment.application_withdrawn_by_company`); `application.withdrawn` (in-app, team); `interview.cancelled_for_team` (in-app, email `recruitment.interview_cancelled_team` with `.ics` cancel) |
| Reports | withdrawals by reason and by who marked them |
| Audit | `recruitment.application_withdrawn_by_company` |
| Errors | 422 `invalid-withdrawal` gains `errors.reason`; 409 `application-not-withdrawable`, `rejection-pending` for the team action |
| Front end | reason choices in the job seeker's withdraw and decline dialogs; "Tandai mengundurkan diri" with reason and note (C6, C7); reason and actor on the team's application detail |
