# Phase 8: recruitment calendar and interview note (designed 2026-10-14, not coded)

Two gaps found against Mekari Talenta's interview flow (help center articles 34781370644761 and 42469173015193): Talenta has a recruitment calendar of
every interview and lets the recruiter add a note for the candidate to the invitation. Extends `phase-2-interviews.md`, `phase-2-offers.md` and
`phase-6-assessments.md`. Coded in a coding session.

## Decisions

- 2026-10-14: the calendar shows **interviews and deadlines**: interviews, assessment due times, offer answer deadlines and the start dates of
  accepted offers, each type filterable (decided by the user).
- 2026-10-14: by default a member sees **every event they may see** (the jobs in their scope), with a "mine only" filter and a job filter (decided by
  the user).
- 2026-10-14: interview emails to the job seeker carry an **`.ics` file**, and the team can download one per interview (decided by the user).
- 2026-10-14: the interview gets a **note for the candidate**; changing only the note **does not reschedule**: the confirmation stays and the job
  seeker is told the details changed (decided by the user).
- 2026-10-14: an application may move to an interview stage **without a schedule** when the request says so explicitly ("schedule later"), one by
  one or in bulk; such applications are marked "not scheduled" (decided by the user, [section 4](#4-move-to-interview-without-a-schedule)). The
  team toggle "send the invitation email" of Talenta is **not built**: the job seeker's own email preference decides (decided by the user).

## 1. Recruitment calendar

### Endpoint

`GET company/calendar?from=YYYY-MM-DD&to=YYYY-MM-DD` (Jakarta days, `to` included, at most **42 days**, enough for a month view with the spill-over
weeks; 422 `invalid-calendar-range` otherwise).

- `filter[type]`: a comma list of `interview`, `assessment_due`, `offer_deadline`, `start_date` (all by default).
- `filter[job_id]`: one job. `filter[mine]=1`: only events that involve the caller (main interviewer or panel member of an interview, assessor of an
  assessment, sender of a test or offer).
- Not paginated: at most 1,000 events, the earliest first; beyond that `meta.truncated: true` and the client narrows the range or the filters.

### What appears

| Type | Source | Shown when | Time |
|---|---|---|---|
| `interview` | `recruitment_interviews` | `pending`, `confirmed`, `completed` (declined and cancelled ones are not shown) | `starts_at` to `ends_at` |
| `assessment_due` | `assessment_invitations` | `invited`, `started` | `due_at` (a point in time) |
| `offer_deadline` | `recruitment_offers` | `pending` | `expires_at` (a point in time) |
| `start_date` | `recruitment_offers` | `accepted` | the start date (all day) |

### Access

The member's job scope as for applications: the jobs they are assigned to, or every job with `jobs.view_all`; the member needs `applications.view`
(`phase-8-company-permissions.md`; before that change, any member with the current application access). Anonymised applications do not appear.

### Event

`id` (`interview:12`, `offer:7`...), `type`, `title` (candidate name and job title), `starts_at`, `ends_at` (null for a point in time), `all_day`,
`status`, `application_id`, `job_id` and job title, candidate name, and for an interview `format`, `location` or whether a meeting link exists (the
link itself stays in the interview detail), the main interviewer and the panel. `involves_me` marks the caller's own events. `actions`: what the
caller may do now, by the rules of the existing use cases: `view_application`, `edit` (reschedule, `PUT .../interview`), `edit_note`, `cancel`,
`scorecard` (panel members, from the interview's start), `download_ics`. The calendar only reads; every action goes to its existing endpoint.

### Where the code lives

- A registry in `Shared/Application/Calendar` (`CalendarSource`, `CalendarEvent`, `CalendarScope`), filled by Recruitment (interviews, offers) and
  Assessment (due times) from their service providers, like the dashboard cards and the analytics reports. The `Dashboard` context serves the
  endpoint (range check, scope, merging and sorting).
- Each source reads with the scope's job ids and the range, on indexed columns (`starts_at`, `due_at`, `expires_at`, start date); the coding session
  adds the missing indexes.

### Front end

New page **C26 Calendar** (`/rekruter/kalender`), in the company menu next to the jobs, from the Able Pro calendar page: month, week, day and list
views, the type and job filters and "Hanya saya". Clicking an event opens a panel with the details and the row actions. Colours per type.

## 2. Note for the candidate

- New column `recruitment_interviews.note` (text, at most 1,000 characters, plain text, nullable). It is written for the job seeker; internal remarks
  stay in the application notes (`phase-2-notes.md`).
- **Set:** `note` in `PUT company/applications/{id}/interview`, in the move request (`interview.note`) and in the bulk move (one note for every
  slot). A new time (`PUT`) keeps the earlier note when `note` is left out; `note: null` clears it.
- **Change only the note:** `PATCH company/applications/{id}/interview {note}` while the interview is `pending` or `confirmed` (409
  `interview-not-active` otherwise), by whoever may schedule it. The status stays, nothing is cancelled, the proposal count is untouched. Nothing
  changed: nothing sent.
- **Shown:** `GET me/applications/{id}/interview`, the scheduled, rescheduled and reminder emails, the `.ics` description, the team's interview
  detail and the calendar event.
- **Told:** a note change sends the new notification `interview.updated` to the job seeker, in-app always and by email (template
  `interview.updated`, preference `application_update`), with a new `.ics`. The team is not told. Added to the notification catalog of
  `phase-8-api-translations.md`.

## 3. Calendar file (`.ics`)

- **To the job seeker:** the emails "interview scheduled", "rescheduled", "details updated" and "cancelled" carry `interview.ics` (`text/calendar`,
  iCalendar 2.0). The decline by the job seeker and the rejection also send a cancel when an interview was active.
- **One event per interview chain:** new columns `recruitment_interviews.calendar_uid` (a UUID, created with the first schedule of an application
  and copied when a new time replaces the interview) and `calendar_sequence` (raised on every change). The job seeker's calendar app therefore moves
  or cancels the same event instead of adding a second one.
- **Content:** `METHOD:REQUEST` (new, changed) or `METHOD:CANCEL`; `ORGANIZER` the platform's no-reply address with the company name as `CN`; the job
  seeker as `ATTENDEE` with `RSVP=FALSE` (answers happen on the platform, not by calendar reply); `SUMMARY` "Interview: {job title} - {company}";
  `DTSTART`/`DTEND` in UTC (no time zone block needed); `LOCATION` the address, or "Online" / "Video di platform" / "Telepon"; `DESCRIPTION` the
  format, the note and the link to the job seeker's interview page; `URL` that page. **Never** a join token or the raw meeting link of another
  participant; an external meeting link set by the recruiter is included, as it is in the email today.
- **Team:** `GET company/interviews/{id}/calendar.ics` for whoever may see the application (same content, `METHOD:PUBLISH`, attendee list left
  out). The team gets no emails, as today.
- Built by a small writer in Recruitment's infrastructure (no new library unless the coding session finds the escaping and line folding
  worth one).

## 4. Move to interview without a schedule

Talenta's "Move only". Cases: the time was agreed outside the platform (phone, walk-in), a large bulk move whose slots come later, a second
interview stage whose time is not known yet.

- **Explicit:** the move request (single and bulk) takes `interview: {schedule_later: true}` instead of a schedule. Without a schedule and without
  this flag the move is still refused (422 `schedule-required`), so a forgotten schedule never passes silently. `schedule_later` with schedule
  fields is 422 `invalid-interview`.
- **No interview row** is created and nothing is sent to the job seeker except the usual stage change. The schedule comes later through
  `PUT company/applications/{id}/interview` (exists), which sends the invitation as today.
- **"Not scheduled"** is a derived state, not a stored one: an application in an interview stage with no `pending` or `confirmed` interview. It
  already happens today after a cancel (the application stays in the stage), so the same mark covers both.
- **Pipeline:** each application in the list (`GET company/jobs/{id}/applications`) carries `interview_state` (`not_scheduled`, `pending`,
  `confirmed`, `completed`, or null outside an interview stage) and the list takes `filter[interview_state]=not_scheduled`. The front end shows a
  "Belum dijadwalkan" badge with a "Jadwalkan" action on the table and the kanban card.
- **Job seeker:** the interview card of the application says "Jadwal interview akan dikabarkan" while the application is in an interview stage
  without an active interview (`GET me/applications/{id}/interview` answers `data: null` as today; the front end reads the stage type).
- Not on the calendar (no time) and no reminder; the filter is the work list. No schema change.

## Changes in short

| Piece | Change |
|---|---|
| Migration | `recruitment_interviews.note`, `calendar_uid`, `calendar_sequence`; indexes for the calendar reads |
| API | `schedule_later` in the move and bulk move; `interview_state` and its filter in the pipeline list; `GET company/calendar`; `PATCH company/applications/{id}/interview`; `note` in schedule, move and bulk move; `GET company/interviews/{id}/calendar.ics`; `note` in the interview resources |
| Notification | `interview.updated` (in-app and email) |
| Email | `.ics` on the interview emails; template `interview.updated` |
| Errors | 422 `invalid-calendar-range` |
| Front end | C26 Calendar; the note field in the schedule dialog and the job seeker's interview card |
