# Phase 8: comparing interview results, scale anchors, duplicating kits and interview scores in the export (designed 2026-10-14, not coded)

Mekari Talenta documents no interview kits or scorecards (searched 2026-10-14; it has assessors on an interview and comments on a stage move), so
these come from our own gap list, chosen by the user from the advice of 2026-10-14. Not taken now: email reminders (with the team notification
preferences, after go-live), starter kits from the platform, a question bank, interviewer calibration in BI, comments in the move dialog (the front
end may call the notes endpoint) and competency groups (the decision of 2026-10-12 stays). Extends `phase-7-interview-kits.md` and
`phase-2-export.md`. Coded in a coding session.

## Decisions

- 2026-10-14: a **comparison of interview results** across the candidates of one job (chosen by the user).
- 2026-10-14: optional **scale anchors** per question for the scores 1, 3 and 5 (chosen by the user).
- 2026-10-14: **duplicating a kit** instead of a question bank (chosen by the user).
- 2026-10-14: **interview scores in the applicant export**; this lifts "interviews" from the export's "not in the export" list (chosen by the user).

## 1. Comparison across candidates

- `GET company/jobs/{id}/interview-results` for a member who may see the job's applications (`applications.view` or `applications.manage`).
- **Which interview per application:** the latest interview with at least one sent scorecard, optionally limited to one interview stage
  (`filter[stage_id]`, 422 `stage-not-interview`). Applications without one are left out. Active applications by default; `filter[stage_type]`
  opens the final ones (to look back at a filled job).
- **Columns:** `filter[kit_id]` picks the kit whose questions become the columns (default: the kit used most often among the rows). An
  interview's question maps to a column when its copy came from that kit and its text is the same after trimming and ignoring case; an edited or
  extra question is not a column (its score still counts in the interview's total). Each cell: the average score of the sent scorecards for that
  question and the number of scores.
- **Per row:** `application {id, name, stage}`, `interview {id, starts_at, kit {id, name}}`, `score` (the interview result), `recommendations
  {strong_no, no, yes, strong_yes}`, `scorecards_sent`, `panel_size`, `cells [{question, average, count}]`, and for context `match_score`,
  the latest assessment result as the applicant list shows it, and the average star rating.
- **Lock rule:** a row whose interview has the caller on the panel without a sent scorecard shows the application but `null` for every result field
  and `locked: true`, the rule of the applicant list.
- **Sort:** `score` (default, highest first), `yes` (yes plus strong_yes), `match_score`; offset paging, at most 100 rows a page.
- Anonymised applicants show as `Deleted user` with their scores, as ratings do.
- Front end: "Bandingkan hasil interview" on the job page (C5): a table with the questions as columns, the anchor texts as tooltips on the headers.

## 2. Scale anchors

- New nullable columns on `recruitment_interview_kit_questions` and on the copy `recruitment_interview_questions`: `anchor_1`, `anchor_3`,
  `anchor_5` (plain text, at most 200 characters each; 422 `invalid-kit` on the kit, the copy's existing error on the copy).
- Copied with the questions when scheduling, carried over by a new time and editable on the copy under the existing rule (until the first draft
  scorecard).
- Shown in `GET company/interviews/{id}/questions` and the kit endpoints; the scorecard form shows them under the 1 to 5 buttons (scores 2 and 4 sit
  between). Never shown to the job seeker.

## 3. Duplicating a kit

- `POST company/interview-kits/{id}/duplicate` (`interview_kits.manage`), 201 with the new kit: same questions, guidance, weights and anchors;
  name "{name} (salinan)", then "(salinan 2)" and so on until it is unique, cut to 100 characters. Counts toward `recruitment.max_interview_kits`
  (409 `kit-limit-reached`). Job defaults are not copied.

## 4. Interview scores in the export

- `GET company/jobs/{id}/applications/export` gains four columns after the rating columns: **interview score** (the latest interview with a sent
  scorecard, two decimals), **interview yes** (yes plus strong_yes), **interview no** (no plus strong_no), **interview kit** (its name).
- The lock rule applies to the exporting member: for an interview where they are on the panel without a sent scorecard, the four cells are empty.
- Scorecard notes, per-member scores and per-question scores stay out of the export.
- `phase-2-export.md` "Not in the export" keeps notes, ratings by person, offers, resume files and rejection reasons; interviews move to this summary.

## Changes in short

| Piece | Change |
|---|---|
| Migration | `anchor_1`, `anchor_3`, `anchor_5` on `recruitment_interview_kit_questions` and `recruitment_interview_questions` |
| API | `GET company/jobs/{id}/interview-results`; anchors on kits and the copy; `POST company/interview-kits/{id}/duplicate`; four export columns |
| Errors | 422 `stage-not-interview` on the comparison filter; existing kit errors for anchors |
| Front end | comparison table (C5); anchors in the kit editor and the scorecard form; "Duplikat" in the kit library |
