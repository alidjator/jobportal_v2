# Phase 8: default tests per job stage, automatic advance and comparing assessment results (designed 2026-10-14, not coded)

Gaps found against Mekari Talenta's stage automation ("Assign assessment test", auto-move by score threshold; help center article 34576595122073),
chosen by the user from the advice of 2026-10-14. Not taken: auto-reject by score (the decision of `phase-8-rejections.md`), "unpublish" (the
`archived` status covers it), sending a test outside an assessment stage, a skill score for the candidate and per-question analytics (after
go-live). Shuffling already exists (`draw_count` per section equal to the number of questions); the front end labels it "Acak soal". Extends
`phase-6-assessments.md` (Assessment, parts 4 to 6) and `phase-1-pipeline.md` (moves). Coded in a coding session.

## Decisions

- 2026-10-14: a job may keep a **default test per assessment stage**; a move into that stage without a test sends the default (chosen by the user).
- 2026-10-14: a default of a **skill test** may **advance** the application automatically when its final score reaches a threshold; never for a
  psychological battery, never a rejection (chosen by the user).
- 2026-10-14: a **comparison of assessment results** across the candidates of one job (chosen by the user).

## 1. Default test per job stage

- Table `assessment_job_stage_tests` (Assessment): `job_id` (CASCADE, like `recruitment_job_stage_kits`), `stage_id` (an `assessment` stage of the
  job's company, CASCADE), `test_id` (RESTRICT), `due_days` (1 to the company's `max_due_days`, default 3), `advance_min_percentage` (null, or 1 to
  100), `advance_to_stage_id` (null, or a stage; both or neither), `updated_by`, timestamps; unique (`job_id`, `stage_id`); assessors in
  `assessment_job_stage_test_assessors` (at most 5, the invitation rule).
- **Endpoints** (`assessments.send` and a member who may see the job): `GET company/jobs/{id}/assessments` (the defaults per stage),
  `PUT company/jobs/{id}/assessments/{stageId} {test_id, due_days, assessor_ids?, advance?: {min_percentage, to_stage_id}}`,
  `DELETE company/jobs/{id}/assessments/{stageId}`. The test is checked by the invitation rules (`invalid-assessment-test`); a stage that is not an
  assessment stage is 422 `stage-not-assessment`. Clone copies the defaults.
- **Sending:** a move into the stage (single or bulk) without `assessment` in the request, and with no active invitation to keep, sends the
  default: start now, due `due_days` from now (at least `min_due_hours` and the test's whole duration), the assessors of the default, the member who
  moved as sender, the company's `assessment` message default as note (`phase-8-interview-calendar.md` section 6). Without a default it stays 422
  `assessment-required`. An `assessment` object in the request always wins.
- **A default that no longer works** (test archived, battery no longer granted, provider inactive): checked again at sending; a single move is 409
  `assessment-default-invalid`, a bulk move skips the application with that code. The job page shows the broken default.
- Front end: "Tes default" per assessment stage in the job's pipeline settings (C5), next to the default interview kit.

## 2. Automatic advance (skill tests only)

- **Set on the default** (`advance`): allowed only when the test is a company skill test or a library test (422 `advance-not-allowed` for a
  battery). `to_stage_id` is an active stage of the company of type `assessment` (another one) or `interview` (422 `invalid-advance-stage`
  otherwise); `hired`, `offer` and the final stages are never targets.
- **When:** the invitation's result becomes final (`scored`, so after the assessors scored every long-text answer) with a percentage of at least
  `min_percentage`, the invitation was sent in a stage that has a default with `advance`, and the application **is still in that stage**. The
  threshold of the default at that moment applies.
- **Not when:** the integrity tier is `major` (a person looks first), the application moved, has a pending rejection
  (`phase-8-rejections.md`) or is final, or the invitation was replaced or cancelled. Below the threshold nothing happens; the team decides.
- **The move:** through the normal move use case with the application row locked, as actor `system`; the history entry carries the note
  "Otomatis: skor {p}% ≥ {min}%". Into an `interview` stage it moves with `schedule_later` (`phase-8-interview-calendar.md` section 4), so the
  team schedules next; into an `assessment` stage that stage's default test is sent (chains are allowed, each needs its own score).
- **Told:** the job seeker gets the usual notices of the move; the job's assignees and the test's sender get the in-app notification
  `application.auto_advanced` (`application_id`, `job_id`, `from_stage_id`, `to_stage_id`, `percentage`; no name).
- Runs after the commit that made the result final (queued listener on the result event, retried; idempotent through the stage check).

## 3. Comparing assessment results

- `GET company/jobs/{id}/assessment-results` for a member who may see the job's applications; the same conventions as the interview comparison
  of `phase-8-interview-results.md` (active applications by default, `filter[stage_type]`, offset paging, at most 100 rows).
- **Which invitation per application:** the latest **submitted** one, optionally of one stage (`filter[stage_id]`) or one test
  (`filter[test_id]`; default the test sent most often among the rows). Applications without one are left out.
- **Skill test row:** `application {id, name, stage}`, `invitation {id, submitted_at, test {id, name}}`, `status` (`scored`, `pending_scoring`),
  `percentage`, `band`, `integrity_tier`, `sections [{title, percentage}]` (columns are the test's sections), plus `match_score`, the latest
  interview score and the average rating for context.
- **Battery row:** before signing `status: awaiting_psychologist_review` and no scales; after signing the scale bands (`[{scale, band}]`, the
  columns) and the report number. The team's access to signed scales is that of `GET .../result` today.
- **Sort:** `percentage` (default, highest first), `submitted_at`, `match_score`; for batteries `submitted_at` only.
- Front end: "Bandingkan hasil tes" on the job page (C5), next to the interview comparison.

## Changes in short

| Piece | Change |
|---|---|
| Migration | `assessment_job_stage_tests`, `assessment_job_stage_test_assessors` |
| API | `GET company/jobs/{id}/assessments`, `PUT`/`DELETE company/jobs/{id}/assessments/{stageId}`; default sending on the move and bulk move; `GET company/jobs/{id}/assessment-results` |
| Events | the advance listener on the final result |
| Notifications | `application.auto_advanced` (in-app, team) |
| Errors | 422 `stage-not-assessment`, `advance-not-allowed`, `invalid-advance-stage`; 409 `assessment-default-invalid` (skip code in bulk) |
| Front end | default test per stage with the advance rule (C5); result comparison (C5); "Acak soal" label for `draw_count` in the test editor |
