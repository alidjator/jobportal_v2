# Phase 7: Interview kits and scorecards (designed and approved 2026-10-12)

Context: `Recruitment`. Adds prepared questions and per-interviewer scoring to the interview schedule of `phase-2-interviews.md` and the tele-interview forms of `phase-7-tele-interview.md`.
Until now an interview had no questions: the team scores an application only with the notes and star ratings of `phase-2-notes.md` (R7a).

## Decisions

- 2026-10-12: kits are **company templates**. A company keeps a library of kits; the scheduler picks one when scheduling, and its content is **copied into the interview** (a snapshot), so later edits to the kit never change past interviews. A job may keep a default kit per interview stage (Answers, 1).
- 2026-10-12: each panel member fills a scorecard with a **score per question** (Answers, 2).
- 2026-10-12: scoring is **independent**: a panel member sees the other scorecards only after sending their own (Answers, 3).
- 2026-10-12: kits and scorecards apply to **every interview format** (online, onsite, video, voice) (Answers, 4).
- 2026-10-12: a score of 1 to 5 per question, a weight of 1 to 5 per question in the kit; a scorecard's result is the weighted average of the answered questions (a skipped question does not count); an interview's result is the average of the sent scorecards (Answers, 5).
- 2026-10-12: a **final recommendation is required** on every scorecard: `strong_no`, `no`, `yes`, `strong_yes`, with an optional short note (Answers, 6).
- 2026-10-12: the **panel applies to every format**: the main interviewer plus at most 3 extra members (at most 4 company participants), each filling one scorecard. This widens form 1's participant table, which was for video only (Answers, 7).
- 2026-10-12: a scorecard can be filled from the interview's start; an in-app reminder goes 24 hours after the end when it is not sent; a sent scorecard stays editable until the application reaches a final stage (Answers, 8).
- 2026-10-12 (finding, decided): editing after sending would let a member align with colleagues they can now see (anchoring). Edits stay allowed, but each one **keeps the previous version** and is marked when other scorecards were visible; the team sees the history (Answers, 9).
- 2026-10-12: company admins and recruiters manage kits; limits as in Answers, 10.
- 2026-10-12: the scheduler may edit an interview's copied questions until the first scorecard draft exists (Answers, 11).
- 2026-10-12: a kit is **optional**; without one the scorecard holds only the recommendation and the note. A job's default kit for the target stage is preselected (Answers, 12).
- 2026-10-12: the proposed details below are **approved**, and kits and scorecards are coded **before** tele-interview form 1; part 1 brings the panel table that form 1 reuses (Answers, 14).
- 2026-10-12: results show in the application detail and on the applicants list card, apart from the star ratings; the job seeker never sees kits or scorecards (Answers, 13).

## Answers

1. **Where kits live:** company templates with a snapshot per interview and an optional default per job and interview stage.
2. **Scorecard shape:** a score per question (chosen over scores per competency).
3. **Visibility:** locked until the panel member sends their own scorecard. Members who may see the application but are not on the panel see the sent scorecards.
4. **Formats:** all. On video and voice the kit shows on the interviewer's screen during the call; on online and onsite it is filled after the interview.
5. **Scale:** 1 to 5 per question, weights 1 to 5, weighted average; skipped questions are left out.
6. **Recommendation:** required, four levels, plus an optional note.
7. **Who scores:** the panel, for every format.
8. **Deadline:** fill from `starts_at`; reminder 24 hours after `ends_at`; editable until a final stage.
9. **Edits after sending (finding):** allowed with a version history and a mark when other scorecards were visible.
10. **Kit management:** company admins and recruiters create, edit and delete kits; deleting a kit leaves past interviews unchanged. A kit has a name (100 characters, unique per company ignoring case), 1 to 20 questions, question text up to 500 characters, interviewer guidance per question up to 1,000 characters (never shown to the job seeker), and a weight of 1 to 5. At most 100 kits per company (admin setting).
11. **Editing the copy:** allowed for the scheduler until the first draft scorecard exists, then locked so every member scores the same questions.
12. **Without a kit:** allowed; the job's default kit for the stage is preselected and may be changed or cleared.
13. **Display:** application detail and applicants list card, separate from star ratings; nothing for the job seeker.
14. **Approval:** details approved; coded before form 1.

## Details (approved 2026-10-12)

**Schema** (all in `Recruitment`; keys to `identity_users` and `job_jobs` are `RESTRICT` as in `cross-context-foreign-keys.md`):

- `recruitment_interview_kits`: `company_id`, `name` (unique per company ignoring case), `created_by`, `updated_by`, timestamps.
- `recruitment_interview_kit_questions`: `kit_id` (deleted with the kit), `position`, `text`, `guidance` (nullable), `weight` (CHECK 1 to 5).
- `recruitment_job_stage_kits`: `job_id`, `stage_id` (an `interview` stage of the job's company), `kit_id` (deleted with the kit), unique (`job_id`, `stage_id`).
- `recruitment_interview_participants` (from form 1, now for every format): `interview_id`, `user_id`, unique pair; at most 3 rows.
- `recruitment_interviews` gains `kit_id` (nullable, set null when the kit is deleted; for information only) and `kit_name` (copied).
- `recruitment_interview_questions`: `interview_id`, `position`, `text`, `guidance`, `weight` (the copy).
- `recruitment_interview_scorecards`: `interview_id`, `member_id`, `status` (`draft`, `submitted`), `recommendation` (nullable while draft; CHECK four values), `note` (up to 2,000 characters), `score` (weighted average, 2 decimals, null without scored answers), `submitted_at`, `edited_at`, timestamps; unique (`interview_id`, `member_id`).
- `recruitment_interview_scorecard_answers`: `scorecard_id`, `question_id`, `score` (nullable = skipped, CHECK 1 to 5), `note` (up to 1,000 characters); unique pair.
- `recruitment_interview_scorecard_revisions`: `scorecard_id`, `previous` (JSON: recommendation, note, answers, score), `others_visible` (whether another scorecard was sent when the edit was made), `created_at`.

**Rules:**

- Scoring is open on a `confirmed` or `completed` interview from `starts_at`, to the main interviewer and the extra members only; a draft is saved with `PUT`, sending needs the recommendation. Anyone else who may see the application, and a panel member who has sent, sees the sent scorecards; a panel member who has not sent sees none, and the applicants list card hides the interview result from them too.
- Rescheduling (which replaces the interview row) copies the questions and the panel to the new row; scorecards stay with the row they were written on.
- Removing a member from the panel deletes their draft but keeps a sent scorecard.
- Erasure (anonymising the applicant): the answer notes, the scorecard notes and the revision copies are deleted (they may quote the applicant); scores and recommendations stay, as ratings do. A new `ErasureStep`.
- Reminder: the hourly interview task also sends one in-app reminder to each panel member without a sent scorecard 24 hours after `ends_at`. No email, no audit entries (the rows and revisions are the record).
- Setting `recruitment.max_interview_kits` (default 100).

**Endpoints** (company side; access as the application detail unless stated):

| Method and path | Purpose |
|---|---|
| `GET/POST company/interview-kits`, `GET/PUT/DELETE company/interview-kits/{id}` | Kit library; `PUT` replaces the name and the whole question list (`invalid-kit` 422, `kit-name-taken`, `kit-limit-reached` 409) |
| `GET company/jobs/{id}/interview-kits`, `PUT/DELETE company/jobs/{id}/interview-kits/{stageId}` | Default kit per interview stage of a job (`stage-not-interview` 422) |
| `PUT company/applications/{id}/interview` | Gains `kit_id` (omitted = the job's default for the target stage, `null` = none) and `participant_ids[]` for every format |
| `GET/PUT company/interviews/{id}/questions` | Read the copy (with guidance; also used on screen during a call) or replace it (`scoring-started` 409) |
| `GET/PUT company/interviews/{id}/scorecard`, `POST .../scorecard/submit` | The member's own scorecard: draft save, send (`not-on-panel` 403, `scoring-not-open` 409, `recommendation-required` 422) |
| `GET company/interviews/{id}/scorecards`, `GET .../scorecards/{scorecardId}/revisions` | Sent scorecards under the lock rule (`scorecards-locked` 403 for a panel member who has not sent) and the edit history |

The application detail gains `interviews[].result {score, scorecards_sent, panel_size, recommendations {strong_no, no, yes, strong_yes}}`; list rows gain `interview_result {score, yes, no}` for the latest interview with a sent scorecard (one grouped query).

**Coding parts** (one fresh session each, **before** form 1, since part 1 brings the panel that form 1 reuses):

1. Kits: library, job defaults, `kit_id` and the panel when scheduling, the copy and its editing, the setting.
2. Scorecards: draft, send, edits with revisions, the lock rule, results in the detail and the list, the reminder, erasure.
