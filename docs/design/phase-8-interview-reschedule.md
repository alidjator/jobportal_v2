# Phase 8: rescheduling a confirmed interview, refusing a proposed time and no-shows (designed 2026-10-19, coded 2026-10-19)

Gap FG2 of `docs/analysis/actor-gap-analysis.md`, seen from both sides of the interview: the job seeker can only **decline** a confirmed interview,
which ends it, and the team cannot **refuse** a proposed time or record a **no-show**. Changes the rule of `phase-2-interviews.md` ("after the
limit the job seeker can only confirm or decline"; proposing was allowed only while `pending`). Extends `phase-8-interviewers.md` (panel, RSVP)
and `phase-8-interview-calendar.md` (`.ics`). Coded in a coding session.

**Read from the code (2026-10-19):** `Interview::propose` and `confirm` require `pending` without an open proposal (`assertAwaitingJobSeeker`);
`decline` works on `pending` and `confirmed`; `acceptProposal` already accepts any active interview and moves the time **in place** (same row,
`calendar_sequence + 1`, `reminded_at` cleared); there is no action to refuse a proposal and no `no_show` status. On `ProposalAccepted`,
`NotifyOfInterviewChange::calendarMethod` sends **no `.ics`** to the job seeker, and `EmailPanelOfInterview` sends **nothing** to the panel,
whose RSVPs stay as answered for the old time (see "Finding" below).

## Decisions

- 2026-10-19: a job seeker may **ask to move a confirmed interview**; the interview **stays confirmed at the old time** until the team accepts or
  refuses; with no answer the old time stands (chosen by the user).
- 2026-10-19: the request uses the **same quota of 2** proposals per interview (`proposals_used`) as before the confirmation (chosen by the user).
- 2026-10-19: requests close a **cut-off before the start**, a platform setting with a per-company override, **24 hours** by default (chosen by
  the user).
- 2026-10-19: the team gets a new action **refuse the proposed time**, with an **optional message** to the job seeker, for proposals made while
  `pending` and while `confirmed` (chosen by the user).
- 2026-10-19: a new status **`no_show`**, set by the team after the start of a confirmed interview; the job seeker is told **in-app and by email**;
  the application's stage does not change (chosen by the user).
- 2026-10-19: the fix of the "Finding" is part of this item (chosen by the user).
- 2026-10-19: the schema of "Changes in short" and these rules are approved (decided by the user): the proposed time also respects the cut-off;
  an unanswered proposal lapses at the old start; the refusal message is sent, not stored; the main interviewer may also mark a no-show; a
  mistaken `no_show` becomes `completed` through `complete` without a notice.
- 2026-10-19: the job seeker also gets a reminder **2 hours** before a confirmed interview, in-app and by email (FG3, chosen by the user).
- 2026-10-19 (coding): `recruitment_interviews.status` had **no CHECK** in the real database; migration `000176` adds a new one with all six values
  (chosen by the user). Interpretations made while coding: see "Coded".

## 1. Asking to move a confirmed interview

- **Endpoint:** the existing `POST me/applications/{id}/interview/propose {starts_at, reason?}`. Allowed when the interview is `pending` or
  `confirmed` and has no open proposal (409 `proposal-pending`), within the quota (409 `proposal-limit`).
- **While `confirmed`:** `reason` is required (1 to 500 characters, plain text; 422 `reason-required`), and the request must come at least the
  cut-off before the **current** start (409 `reschedule-too-late`, with `cutoff_hours` in the problem). The proposed start must be in the future
  and at least the cut-off ahead as well (422 `invalid-interview`), so the panel has the same notice for the new time. Recording consent and the
  phone number given at confirmation stay; the request does not ask them again.
- **While `pending`:** as today; `reason` is optional.
- **State:** `status` stays as it is, `proposed_starts_at` and `proposal_reason` are set, `proposals_used + 1`. The calendar event, the
  reminders and the panel RSVPs do not change until the team answers.
- **After the cut-off:** the job seeker may still decline (as today) or write in the chat; the interview page says so.

## 2. The team's answer

| Answer | Endpoint | Effect |
|---|---|---|
| Accept | existing `POST company/applications/{id}/interview/accept-proposal` | `confirmed` at the proposed time (in place). See "Finding" for the `.ics` and the panel. |
| Refuse | **new** `POST company/applications/{id}/interview/refuse-proposal {message?}` | proposal cleared; `status` unchanged (`pending` stays `pending`, `confirmed` keeps the old time); the quota stays used |
| Another time | existing `PUT company/applications/{id}/interview` | a new interview row as today (reschedule) |

- **Refuse:** `interviews.manage` on the application, as accept; 409 `no-proposal` without one; `message` at most 1,000 characters, plain text,
  the link rule of `phase-8-interview-calendar.md` section 6 (422 `message-link-not-allowed`). The message is sent, not stored.
- **Shown to the team:** the interview carries `proposal: {starts_at, reason, made_at}`; the pipeline badge and the calendar event show
  "Kandidat minta pindah jadwal"; the task list gains `reschedule_requests` (open proposals on interviews the caller scheduled or leads).
- **No answer before the old start:** the proposal lapses at the old start (cleared by the reminder command; nothing sent); a confirmed
  interview then simply takes place at the old time.

## 3. No-show

- **Endpoint:** `POST company/applications/{id}/interview/no-show {note?}` (`interviews.manage`, or the main interviewer): only a `confirmed`
  interview, only after `starts_at` (409 `interview-not-confirmed`, `interview-not-started`). Sets `status = no_show` (not active: the
  application may get a new interview); `note` is team-only, at most 1,000 characters.
- **Correction:** `complete` also accepts `no_show` (a mistaken mark becomes `completed`); nothing is sent to the job seeker for the correction.
- **Job seeker:** in-app `interview.no_show` and email `recruitment.interview_no_show` (preference `application_update`): "Anda tercatat tidak
  hadir pada interview {job} {time}. Hubungi perusahaan melalui chat bila ada kendala." No `.ics`.
- **Shown:** the application's interview list and the calendar (event kept, status `no_show`); the scorecard follow-up of
  `phase-8-interviewers.md` skips a `no_show` interview; analytics count it in the interview funnel.
- **Online interviews:** the attendance log of `phase-7-tele-interview.md` is shown next to the action as evidence; nothing is marked
  automatically.

## 4. Notifications

| Event | Job seeker | Team |
|---|---|---|
| Proposal on a confirmed interview | — | in-app `interview.proposed` (as today) with the reason; email by `team_interview_changes` to the scheduler and the main interviewer |
| Proposal refused | in-app `interview.proposal_refused`, email `recruitment.interview_proposal_refused` with the message (`application_update`) | — |
| Proposal accepted | as today, plus the `.ics` (see "Finding") | panel as for a reschedule (see "Finding") |
| No-show | in-app `interview.no_show`, email `recruitment.interview_no_show` | — |

## 5. Setting

`recruitment.interview_reschedule_cutoff_hours` (integer 0 to 168, default 24; 0 means until the start), admin setting overridable per company
like `recruitment.offer_labor_rules`; listed in `SettingsTest`.

## 6. Reminder 2 hours before (FG3)

- `SendStartingSoonReminders` gains a second pass for the job seeker: confirmed interviews starting within **120 minutes**, once per interview,
  claimed through a new column `recruitment_interviews.candidate_soon_reminded_at` (like `starting_reminded_at` for the panel). A time change
  (reschedule, accepted proposal) clears it, as `reminded_at` today. An interview confirmed less than 2 hours ahead is reminded on the next run
  while it has not started.
- In-app `interview.starting_soon` to the job seeker and email `recruitment.interview_starting_soon` (preference `application_update`): job,
  company, time in WIB, format, the address of an onsite interview or the link to the platform's room page (never the room credentials), and the
  company's contact line "Hubungi lewat chat bila ada kendala". No `.ics`.
- Not sent for a `no_show`, cancelled, declined or final application, as the panel pass.

## Finding (2026-10-19, fix included in this item)

Accepting a proposal moves the time in place, but the job seeker's email has **no updated `.ics`** and the panel gets **no new invitation**;
their RSVPs still answer the old time and their calendars keep it. This is a defect of the current code, not of this design, and FG2 makes it
more frequent. Proposed fix: treat `ProposalAccepted` as a time change: `.ics` `METHOD:REQUEST` (new sequence) to the job seeker, the panel
invitation of `phase-8-interviewers.md` section 2 to every panel member, and every panel RSVP back to `pending`.

## Changes in short (schema approved 2026-10-19)

| Piece | Change |
|---|---|
| Migration | `recruitment_interviews.proposal_reason` (varchar 500, null), `proposal_made_at` (timestamp, null); status CHECK gains `no_show`; `no_show_note` (varchar 1000, null); `candidate_soon_reminded_at` (timestamp, null; approved 2026-10-19) |
| Notify | `ProposalAccepted` sends `.ics` `REQUEST` to the job seeker, the panel invitation to every panel member, and resets every panel RSVP to `pending` |
| Domain | `propose` allowed on `confirmed` with reason and cut-off; `refuseProposal`; `markNoShow`; `complete` from `no_show`; `InterviewStatus::NoShow` (not active) |
| API | `reason` on propose; `POST .../interview/refuse-proposal`; `POST .../interview/no-show`; `proposal` on the interview resources |
| Setting | `recruitment.interview_reschedule_cutoff_hours` |
| Notifications | `interview.proposal_refused`, `interview.no_show`, the job seeker's `interview.starting_soon` (in-app and email); team email for proposals |
| Tasks | `reschedule_requests` |
| Errors | 409 `reschedule-too-late`, `interview-not-started`; 422 `reason-required` (reused) |
| Front end | "Minta jadwal ulang" on a confirmed interview with the cut-off; proposal banner and Terima / Tolak / Jadwalkan lain for the team; "Tidak hadir" after the start |

## Coded (2026-10-19)

- **Migration** `2026_10_19_000176_add_reschedule_and_no_show_to_interviews`: the four columns of "Changes in short" and the new
  `recruitment_interviews_status_check` (`pending`, `confirmed`, `declined`, `cancelled`, `completed`, `no_show`).
- **Setting** `recruitment.interview_reschedule_cutoff_hours` (overridable per company); no `.env` value.
- Interpretations made while coding:
  - The cut-off rule for the proposed start (at least the cut-off ahead) applies only to a request on a **confirmed** interview, as section 1
    places it; a proposal on a pending interview keeps the old rule (in the future).
  - The accepted proposal gets its own job seeker email `recruitment.interview_proposal_accepted` (with the `.ics` `REQUEST`), since the
    rescheduled email asks to confirm again; the panel gets the invitation and the in-app `interview.panel_invited` as for a reschedule.
  - The proposal's reason is in the team's in-app `interview.proposed` data (`reason`, `proposed_starts_at`); the team email words a request
    on a confirmed interview as "asked to move the confirmed interview" and carries no reason.
  - Lapsing unanswered proposals runs in `recruitment:send-starting-soon-reminders` (every five minutes), with the job seeker's 2-hour pass.
  - The 2-hour reminder is an `InterviewChanged` (`starting_soon`) claimed under the row lock like the day-before reminder, not a separate
    claim query; its email shows the meeting link of an online interview, the address of an onsite one, and "in the platform" for video.
  - `no-show` is open to the main interviewer through `panelOpens` of the lookup; anyone else needs `interviews.manage`.
  - `complete` on an application without an active interview takes the newest interview when it is a `no_show`.
  - The task `reschedule_requests` (order 29) opens `company/interviews?filter[proposal]=open`, a new filter of that list. The pipeline row
    carries `interview_proposal_open`; the calendar event carries `details.proposed_starts_at` and the actions `accept_proposal`,
    `refuse_proposal` and `no_show`, and shows `no_show` interviews; the scorecard action is limited to confirmed and completed interviews.
  - The past list of `company/interviews` shows `no_show` interviews too. Erasing an applicant also clears `proposal_reason` and `no_show_note`
    of the interviews that are kept.
- **Not done here:** analytics has no interview funnel yet (section 3 counts `no_show` there when it exists); the job seeker's interview
  resource does not say until when a move can be asked (the front end shows the cut-off from the 409 `reschedule-too-late`).
