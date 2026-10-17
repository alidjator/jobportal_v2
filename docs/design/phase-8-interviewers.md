# Phase 8: interviewers: access through the panel, invitations and RSVP, reminders and scorecard follow-up (designed 2026-10-15; section 1 coded 2026-10-17)

Gaps found from the interviewer's point of view and against Mekari Talenta (help center articles 47603513135385, 34576595122073: an Interviewer
role limited to the jobs it is assigned to; 34781370644761 and 42469173015193: interviewers picked when scheduling, "Submit interview feedback"
from the calendar), chosen by the user from the advice of 2026-10-15. Not taken now: a briefing page, telling the interviewer the outcome,
interviewer availability and two-way calendar sync, a personal calendar feed. Extends `phase-2-interviews.md`, `phase-7-interview-kits.md` (panel,
scorecards), `phase-7-tele-interview.md` (rooms), `phase-8-company-permissions.md` (catalog, presets), `phase-8-interview-calendar.md` (`.ics`),
`phase-8-recruiter-workday.md` (team preferences, tasks) and `phase-8-hiring-managers.md` (the panel already opens an application to a hiring
manager). Coded in a coding session, after the interview calendar.

**Read from the code (2026-10-15):** the day-before reminder (`SendInterviewReminders`, `NotifyOfInterviewChange`) emails the job seeker and gives
the team an **in-app notice only**, to the main interviewer and whoever scheduled it; extra panel members get nothing. `phase-2-interviews.md` line
13 ("a reminder email goes to … the interviewer") is outdated. The scorecard reminder (`SendScorecardReminders`) is one in-app notice 24 hours after
`ends_at`. There is no list of a member's interviews (`GET company/interviews`).

## Decisions

- 2026-10-15: a new permission **`interviews.participate`**; being on an interview's panel opens **the interview material** of that application,
  without assignment to the job; the Interviewer preset becomes this permission alone (decided by the user).
- 2026-10-15: interviewers get an **invitation email with `.ics`** and answer **Hadir / Tidak bisa**; a "Tidak bisa" only tells the scheduler; RSVP
  is stored in **columns on both panel tables** (schema decided by the user).
- 2026-10-15: the day-before **email to the whole panel** and an **in-app notice 30 minutes** before the start (chosen by the user).
- 2026-10-15: scorecard follow-up at **24 hours** (in-app and email) and **72 hours** (again, plus the scheduler and the main interviewer)
  (chosen by the user).
- 2026-10-15: team interview emails follow the preference **`team_interview_changes`** (chosen by the user).

## 1. Access through the panel

- **Catalog:** `interviews.participate` (group Applicants): be chosen as main interviewer or panel member, join the video and voice rooms, fill
  one's scorecard. No other permission is required with it. The catalog has 22 permissions with `applications.review`.
- **Presets:** Interviewer = `interviews.participate` only. Recruiter and Hiring Manager gain it. **Migration:** every member and pending invitation
  holding `interviews.manage` gets it; members on the Interviewer preset today keep their current permissions (shown as custom; the company
  admin moves them to the new preset).
- **Choosing the panel:** main interviewer and extra members are active members of the company with `interviews.participate` or company admins
  (422 `invalid-interviewer`); assignment to the job is no longer required.
- **What the panel opens** (while the member is on the panel of any interview row of the application, past or present; rescheduling copies the
  panel): the application detail without notes and ratings, the resume and files, the screening answers, the assessment results (through
  `TeamApplications`), the application's interviews with their questions, kit and the scorecards under the lock rule, the rooms and the
  recordings of interviews they sat on. **Not:** notes, ratings, offers, conversations, stage moves, exports, reviews of `phase-8-hiring-managers.md`
  unless their own permissions give them through the job. A member who also sees the job by assignment keeps that wider view.
- **Rights that stay with `interviews.manage`:** scheduling, rescheduling, cancelling, changing the panel, deleting a recording. Editing the
  interview's questions stays with the scheduler, the main interviewer and a company admin (`phase-7-interview-kits.md`).
- **My interviews:** `GET company/interviews` (new; any member, own rows only unless `interviews.manage` and `filter[mine]=0`):
  `filter[when]=upcoming|past`, `filter[my_scorecard]=pending` (the task link of `phase-8-recruiter-workday.md`), `filter[rsvp]=pending`; each row
  has the time, format, job title, candidate name, role on the panel, own RSVP and scorecard state. Whichever of the two designs is coded first
  builds this list.
- The rule sits in `CompanyApplicationAccess` next to the hiring manager boundary: "visible" = job scope or hiring-manager marker or panel
  membership, with the panel's reduced view.

## 2. Invitations and RSVP

- **Columns:** `recruitment_interviews.interviewer_rsvp`, `interviewer_rsvp_note` (≤500), `interviewer_rsvp_at`; the same three on
  `recruitment_interview_participants` (`rsvp`, `rsvp_note`, `rsvp_at`). `rsvp`: `pending` (default), `accepted`, `declined`. A reschedule copies
  the panel with `pending`; a member removed and added again starts `pending`.
- **Email** `team.interview_invitation` to each panel member on scheduling, on a new time and when added to the panel (job title, candidate name,
  time in WIB, format, place or room link, the kit's name, link to the interview), with `interview.ics` `METHOD:REQUEST` (the interview's
  `calendar_uid` and `calendar_sequence`, attendee the member). Removal from the panel or cancellation sends `METHOD:CANCEL` (the cancellation by a
  withdrawal is already `phase-8-withdrawals.md`). All follow `team_interview_changes`; the in-app notice `interview.panel_invited` is always
  written.
- **Answer:** `POST company/interviews/{id}/rsvp {answer: accepted|declined, note?}` (panel member, until the start; 409 `interview-started`),
  changeable. A `declined` tells the scheduler (in-app `interview.panel_declined` and email `team.interview_panel_declined` with the note); the panel
  stays as it is.
- **Shown:** `rsvp` per panel member on the interview, the calendar event and the pipeline's interview badge ("1 belum menjawab", "1 tidak bisa").
  Tasks: `my_rsvp` (the caller's pending answers) and, for the scheduler, `panel_declined` (upcoming interviews with a declined member).

## 3. Reminders before the interview

- **Day before:** the existing reminder also emails every panel member (`team.interview_reminder`, with the room link or place and a link to the
  interview; `team_interview_changes`) and writes the in-app notice to every panel member, not only the main interviewer and the scheduler.
- **30 minutes before:** in-app `interview.starting_soon` to every panel member who did not decline, with the room link for an online
  interview. Run by the scheduler every 5 minutes on confirmed interviews (`starting_reminded_at` on `recruitment_interviews`, once). No email.
- `phase-2-interviews.md` line 13 is corrected to point here.

## 4. Scorecard follow-up

- **24 hours** after `ends_at` (existing): the in-app notice stays and an email `team.scorecard_reminder` is added.
- **72 hours:** a second in-app notice and email to the members still missing, and an in-app notice `interview.scorecards_missing` to the
  scheduler and the main interviewer naming how many are missing. New column `scorecard_escalated_at` on `recruitment_interviews` (once).
- **Preference:** the scorecard emails get their own key **`team_scorecard_reminders`** (on by default); `team_scoring` of
  `phase-8-recruiter-workday.md` (off by default) keeps the assessor work only. The in-app notices are always written.
- Interviews whose application became final get no reminder, as today; the lookback of 7 days stays.

## Changes in short

| Piece | Change |
|---|---|
| Migration | RSVP columns on `recruitment_interviews` and `recruitment_interview_participants`; `recruitment_interviews.starting_reminded_at`, `scorecard_escalated_at`; `interviews.participate` rows for holders of `interviews.manage` |
| Catalog | `interviews.participate`; Interviewer preset = it alone; Recruiter and Hiring Manager gain it |
| API | `GET company/interviews`; `POST company/interviews/{id}/rsvp`; `rsvp` on interviews and panel members; panel choice by permission instead of job assignment; the panel's reduced application view |
| Preferences | `team_scorecard_reminders` (on) |
| Scheduler | 30-minute reminder every 5 minutes; 72-hour scorecard step in the hourly task |
| Notifications | `interview.panel_invited` (in-app, email `team.interview_invitation` with `.ics`), `interview.panel_declined` (in-app, email), `team.interview_reminder` (email), `interview.starting_soon` (in-app), `team.scorecard_reminder` (email), `interview.scorecards_missing` (in-app) |
| Tasks | `my_rsvp`, `panel_declined` |
| Errors | 409 `interview-started` |
| Front end | "Wawancara saya" list; RSVP buttons in the interview, the email link and the calendar; RSVP badges on the panel; the Interviewer preset and the "preset lama" mark on the team page |

## Coded: section 1 (2026-10-17, migration `000158`)

- **Catalog and presets:** `interviews.participate` (group Applicants, no `applications.view` needed); the catalog has 29 permissions. Interviewer = it alone; Recruiter
  and Hiring Manager gain it. The migration gives it to every member and pending invitation holding `interviews.manage`; members on the old Interviewer preset keep
  their permissions and show as custom. `CompanyTeam::interviewerUserIds` lists who may sit on a panel.
- **Choosing the panel:** `InterviewScheduler::assertMayParticipate` (422 `invalid-interviewer`): an active member holding the permission, or a company admin; the job
  assignment no longer matters. This replaces the old rule "interviewers are assigned to the job" of `phase-8-company-permissions.md` section 5.
- **Access:** `TeamApplicationLookup::find(..., panelOpens: true)` first tries the job rule; when it refuses, a member holding `interviews.participate` who sits on the
  panel of any interview of the application (`InterviewPanel::includes`) gets `TeamApplication::panelOnly`. The refusal of the job rule is kept when the panel does not
  open it (a member without a seat gets `company-permission-required` for `applications.view`, not a 404). Opened this way: the application detail (no rating, no other
  applications, not marked as viewed), the resume, the application's interviews, the questions, the scorecards (the lock rule is unchanged), the calendar file, the video and
  voice rooms (they already require a seat on that interview), recording start and stop, the lists of recordings, calls and attendance and the recording file (only for an
  interview they sat on: `TeamInterviewLookup::assertSatOn`), the list and detail of the application's test invitations and the result of a skill test through the port
  `TeamApplications` (`panelOpens`, `TeamApplicationRef::panelOnly`). Not opened: notes, ratings, stage moves, offers, conversations, exports, reviews, scheduling,
  rescheduling, cancelling, the panel, deleting a recording, and the results of psychological tests (`assessments.send` stays required). Editing the questions still needs
  the scheduler, the main interviewer or a company admin; the main interviewer now gets there without the job.
- **My interviews:** `GET company/interviews` (any member): `filter[when]=upcoming|past`, `filter[my_scorecard]=pending`, `filter[mine]=0` (needs `interviews.manage`
  and `applications.view`; the jobs the member sees and the hiring manager boundary apply), cursor-paginated. Each row: job, candidate, time, format, status, `role`
  (`main`, `panel`, null) and `scorecard` (`pending`, `sent`, `not_open`, null). `filter[rsvp]` and the own RSVP wait for section 2. Cancelled and declined interviews are not listed.
- **Open:** the recruitment calendar still needs `applications.view`, so a panel-only member does not see their interviews there (the list above is their view until
  then); the `my_scorecard` task link of `phase-8-recruiter-workday.md` should point at this list.
