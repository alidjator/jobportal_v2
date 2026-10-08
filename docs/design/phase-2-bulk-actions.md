# Phase 2, step 4: bulk actions (R5, design final 2026-10-08, not coded)

Context: `Recruitment`. Spec in `decisions.md` (R5): bulk stage move (one reason for all when rejecting; notifications still per application) and bulk chat message (max 50 recipients,
admin-configurable). **The bulk chat message belongs to step 6 (chat), because it needs the chat itself; this step covers the stage move and the rejection only.**
Related: `phase-1-pipeline.md` (move and reject rules), `phase-2-interviews.md` (`InterviewSlotPlanner`), `phase-2-offers.md` (offer rules).

## Confirmed by the user (2026-10-08)

- **One job per request.** Every id must belong to the same job, which the member may see (same access as the pipeline).
- **Partial success with a per-item report.** Each application is processed on its own (own transaction, row lock first, as `ProcessApplication`); one failure never stops or rolls back the others.
  The response lists, for every id, `moved` or the error code that skipped it. Notifications and emails stay per application, after each commit.
- **Selection:** an explicit `application_ids` list, at most **100** per request, run synchronously. The cap is an admin setting (default 100), like the chat cap; `SettingsTest` must list the new key.
- **Bulk move to an offer stage:** one shared offer for all selected applicants (position defaulting to the job title, salary, start date, note, deadline), validated once with the single-offer rules.
  Terms that differ per person are sent one by one.

## Details (approved by the user, 2026-10-08)

- **Endpoints (all `auth:user`, members of an active company):** `POST company/jobs/{id}/applications/bulk-move {application_ids, stage_id, interview?, offer?}` and
  `POST company/jobs/{id}/applications/bulk-reject {application_ids, reason, note?}`. Both answer 200 with `data: [{application_id, status: moved|skipped, code?}]`
  (`code` is the error code of the single action: `application-final`, `already-in-stage`, `job-taken-down`, `invalid-offer`, and so on). A request that is wrong as a whole is 422 and changes nothing:
  empty or over-cap `application_ids`, duplicate ids, an id that is not an application of this job (404 for the job the member may not see), an invalid `stage_id` (`invalid-stage`),
  an invalid shared input (`invalid-schedule`, `invalid-interview`, `invalid-interviewer`, `invalid-offer`, `invalid-rejection`). The shared input is validated **before** any application is touched.
- **Reuse, not a second code path:** a per-application `ProcessApplication` call (the same `move` and `reject` rules, errors, history and events). `BulkProcessApplications` only validates the request,
  plans, loops, catches the per-item `StateConflict`/`BusinessRuleViolation` and collects the report.
- **Bulk move to `interview`:** `interview` carries the base schedule: `starts_at`, `duration_minutes`, `gap_minutes` (0 to 240), `format`, `location` or `meeting_url`, `interviewer_id` and the daily window
  (`day_start`, `day_end`, default `09:00` and `17:00`, zone Asia/Jakarta, Monday to Friday). `InterviewSlotPlanner::plan` is called once for the number of ids and the slots are handed out in the **order of
  `application_ids`**. Slots are planned only for the applications that pass the cheap checks first (not final, not already in the stage), so a skipped
  application does not leave a hole. Each application then gets its own `InterviewInput` and goes through the normal move, which replaces an active interview as the single move does.
- **Bulk move to an offer stage:** the shared `offer` goes through `OfferPlacer` per application (an active offer is replaced, as in the single move); the job seeker gets one offer email each.
- **Bulk move to other stages:** no extra input; an `interview` or `offer` object for any other stage is 422 (`invalid-schedule`, `invalid-offer-input`), as in the single move.
- **Bulk reject:** one reason and note for all, same rules as `reject` (`other` needs a note, up to 1,000 characters); each application gets its own history entry, interview cancellation and offer withdrawal.
- **Atomicity of the plan:** the slots are planned before the loop, so a skipped application after planning (a race with a withdrawal) leaves one unused slot; this is accepted.
- **No new table, no new migration.** No audit entry beyond each application's own stage history. Order of results equals the order of `application_ids`.

## Confirmed later the same day (2026-10-08)

- **Busy times are skipped:** the planner receives the interviewer's active (pending or confirmed) interviews and moves a slot past any overlap, still inside the daily window on working days.
  This needs a small extension of `InterviewSlotPlanner` (a list of busy ranges) and an `InterviewRepository` query; the single schedule keeps no double-booking check.
- **Slot order is the order of `application_ids`** (the client sorts before sending).

## Decisions on the draft (2026-10-08)

- Endpoint shapes as written above (two endpoints under the job, 200 with a per-item report, not 207).
- A slot freed by a failure after planning is not re-planned (accepted).
- The bulk chat message moves to step 6 (chat); the 50-recipient cap is created there, separate from the 100 cap of this step.
- No new permission (same access as the pipeline), so `identity:sync-permissions` is not needed. Nothing is open.

## Coding plan (not started)

1. Setting for the cap (default 100, update `SettingsTest`); `InterviewSlotPlanner` busy ranges and the `InterviewRepository` query for the interviewer's active interviews, with unit tests.
2. `BulkProcessApplications` (validation, planning, per-item loop, report), the two endpoints, requests and resources, feature tests.
3. Final checks (tests, Pint, PHPStan), this document and `HANDOFF.md`.
