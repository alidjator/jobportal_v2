# Phase 8: closing the recruitment of a deleted account and pausing the process of a suspended company (designed 2026-10-20)

Closes MG-P04 and MG-P05 of `docs/analysis/multi-persona-gap-analysis.md` (decisions 16 and 17, and the answers of 2026-10-20 recorded below). Builds on
`phase-8-withdrawals.md` (the withdrawal effects and reasons), `phase-8-activation.md` (the `system` actor of a withdrawal, MG-A08) and `phase-2-offers.md`.
Design only: nothing is coded.

## Decisions

- 2026-10-20 **MG-P04, at the deletion request** (not at the anonymisation 30 days later): every active application of the account is withdrawn by the **system** with the new reason `account_deleted`; its interview and test are cancelled and its pending offer withdrawn, as in a withdrawal by the team; the **team is told** (job assignees in-app, panel by email with the calendar cancel); the candidate gets no notice; **restoring the account reopens nothing** (user, gap-analysis decision 16; [section 2](#2-mg-p04-the-deletion-request)).
- 2026-10-20 **MG-P04, hired:** an application in the hired stage stays hired; the job's assignees and the company admins get an in-app notice that the hired person closed their account (user; [section 2](#2-mg-p04-the-deletion-request)).
- 2026-10-20 **MG-P05, at the suspension:** each candidate with an active application at the company gets one neutral in-app notice "proses rekrutmen sementara ditunda" (no reason, no word of suspension); a second notice follows the restore; reminders pause (user, decision 17; [section 3](#3-mg-p05-the-suspension-of-a-company)).
- 2026-10-20 **Channel:** in-app for every such candidate; **email only** for a candidate with an interview scheduled, an open offer or a running test (user; [section 3](#3-mg-p05-the-suspension-of-a-company)).
- 2026-10-20 **Deadlines:** nothing expires while the company is suspended; at the restore the deadlines of pending offers and test invitations move later by **the length of the suspension** (user; [section 3](#3-mg-p05-the-suspension-of-a-company)).
- 2026-10-20 **Interviews:** an interview whose time falls in the suspension is **not cancelled** and **not counted as a no-show**; the team reschedules by hand after the restore (user; [section 3](#3-mg-p05-the-suspension-of-a-company)).

## 1. Facts this design rests on

- `RequestAccountDeletion` deactivates the account, revokes the tokens and dispatches `AccountDeletionRequested`; no listener closes the recruitment. `UserAnonymized` only erases personal data (`EraseApplicantData`).
- A withdrawal by the job seeker (`WithdrawMyApplication`) or by the team (`WithdrawApplicationByTeam`) moves the application to the company's default `withdrawn` stage, frees `active_key`, cancels the active interview and test and withdraws the pending offer; the team is told by `NotifyTeamOfWithdrawal`. `StageMoveActor` already has `system`, and the resources show `by: system` since the MG-A08 fix.
- `WithdrawalReason` has eight values, checked by constraints on `recruitment_applications.withdrawal_reason` and `recruitment_application_stage_history.withdrawal_reason`; the team cannot withdraw a hired application (`application-not-withdrawable`) and a pending rejection blocks it (`rejection-pending`).
- `SuspendCompany` stores `company_companies.suspended_at` and dispatches `CompanySuspended`; `RestoreCompany` clears it and dispatches `CompanyRestored` (no start time in the event). `CompanySummary.status` says `active` or `suspended` through the `CompanyDirectory` port.
- No reminder or deadline job looks at the company status: `SendInterviewReminders`, `SendStartingSoonReminders`, `ProcessOfferDeadlines` (reminder and expiry) and the assessment `SendInvitationReminders` (and the expiry of an invitation) run for suspended companies too.

## 2. MG-P04: the deletion request

- **Trigger:** a queued listener of `AccountDeletionRequested` in Recruitment runs the use case `CloseRecruitmentOfAccount(userId)`, retried three times; a failure never blocks the deletion. It works application by application, each in its own transaction, so one failure leaves the others done and a rerun finishes the rest (idempotent: an application that is no longer active is skipped).
- **Which applications:** every application of the user in an active stage **except `hired`**. One that cannot be withdrawn (a pending rejection) is skipped and left to complete; the listener logs the code, nothing else.
- **Effects:** exactly those of the withdrawal by the team (default `withdrawn` stage, `active_key` freed, interview and test cancelled, pending offer withdrawn); the history entry has actor `system`, `withdrawal_reason = account_deleted`, no member, no note.
- **Reason:** the new value `account_deleted`, system only (never accepted from a request: 422 `invalid-withdrawal`), label "Akun ditutup" / "Account closed". Migration: the two check constraints gain the value. The BI withdrawal table splits it as one more reason; imported withdrawals keep `null`.
- **Who is told:** the existing team notice (`NotifyTeamOfWithdrawal`: job assignees in-app) with a text for `by: system` ("kandidat menutup akunnya"), and the panel of a cancelled interview by the existing email with the cancel; the job seeker gets nothing (the account is closed; `NotifyApplicantOfWithdrawal` already skips a withdrawal that is not by the team).
- **Hired:** the application stays; a new in-app notice `application.hired_account_closed` goes to the job's assignees and the company admins (the type is added to the Recruitment catalog, preference `application_update`, always in-app). Nothing else changes: the hire record is the company's.
- **Restoring the account** (`auth/account/restore` within 30 days) reopens nothing; the person may apply again (the key is free). The anonymisation 30 days later stays as it is.
- **Shown:** the company detail shows `withdrawal: {reason: account_deleted, by: system}` and the history entry; the job seeker (after a restore) sees the reason label on J5.

## 3. MG-P05: the suspension of a company

**Pause (while `company_companies.status = suspended`):**

- `SendInterviewReminders`, `SendStartingSoonReminders`, the offer reminder of `ProcessOfferDeadlines` and the assessment invitation reminders skip an item whose company is suspended; they are sent normally after the restore (an item whose moment passed is not reminded).
- **Nothing expires:** `ProcessOfferDeadlines` does not expire a pending offer, and the assessment job does not expire an invitation, while the company is suspended. The check is one read of `CompanyDirectory` per company per run, not a join.

**Restore:**

- `CompanyRestored` gains `suspendedAt` (the start, read before the column is cleared). The Recruitment and Assessment listeners shift the deadline of every **pending offer** and every **open test invitation** of the company later by `restoredAt - suspendedAt` (whole seconds), in one statement each, and mark the offer as shifted in its history ("deadline extended by the suspension"). A shifted deadline is shown as any other new deadline; the candidate is told by the second notice.
- Interviews are not touched. An interview whose start falls between `suspendedAt` and `restoredAt` is excluded from the no-show prompt of the team and from the no-show analytics (a rule on the time window, no new column: the analytics read `company_companies` windows through the same port as above).

**Notices:**

- **Who:** each job seeker with an active application (any stage except hired, rejected and withdrawn) at the company, **one notice per candidate per company** whatever the number of applications.
- **In-app** (always): `application.process_on_hold` at the suspension and `application.process_resumed` at the restore, with the company name and no reason; the text is the one of the decision, "proses rekrutmen sementara ditunda", and "proses rekrutmen dilanjutkan" with the note that deadlines were extended.
- **Email** only for a candidate with an interview scheduled, an open offer or a running test: templates `recruitment.process_on_hold` and `recruitment.process_resumed` (editable in D13, preference `application_update`, the same for the company side as for other recruitment emails). The email of the second notice lists the new deadlines.
- The notices go out from the listeners of `CompanySuspended` and `CompanyRestored` (queued, three tries); a company suspended and restored within minutes still sends both.

## 4. Schema and API

| Change | Context | Detail |
|---|---|---|
| `withdrawal_reason` check gains `account_deleted` | Recruitment | on `recruitment_applications` and `recruitment_application_stage_history`; migration, new |
| `CompanyRestored::$suspendedAt` | Company | event field, no column |
| offer history note "deadline extended by the suspension" | Recruitment | uses the existing history; no column |

No new endpoint. The resources already carry `by` and `reason`; `WithdrawalReason` gets the label. New error codes: none. New notification types (Recruitment): `application.process_on_hold`, `application.process_resumed`, `application.hired_account_closed`; new email templates: `recruitment.process_on_hold`, `recruitment.process_resumed` (`EmailTemplatesTest` lists them).

## 5. Coding order and tests

1. **MG-P04:** `account_deleted`, the use case and listener, the team text for `by: system`, the hired notice. Tests: every active application withdrawn by the system with its interview, test and offer closed; hired untouched with the notice; a pending rejection skipped and the rest done; rerun does nothing; restore reopens nothing; the panel email carries the cancel; the candidate is not notified.
2. **MG-P05 pause and shift:** the skip in the four jobs, the no-expiry rule, the shift at the restore, the no-show exclusion. Tests: a suspended company's reminders do not go out and do after the restore; an offer past its deadline does not expire while suspended; the deadline moves by the suspension length; an interview in the window is not a no-show.
3. **MG-P05 notices:** the two in-app types, the two emails, one notice per candidate per company. Tests: three applications at one company give one notice; a candidate with a scheduled interview gets the email, another does not; a hired or rejected application is not counted; the restore sends the second notice.

Checks are the user's step after each part (`CLAUDE.md`).

## Open points

None.
