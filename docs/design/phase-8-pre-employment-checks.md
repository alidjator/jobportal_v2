# Phase 8: pre-employment checks: medical check-up, background and reference checks (designed 2026-10-19, not coded)

Gap FG12 of `docs/analysis/actor-gap-analysis.md`: Indonesian corporate and state-owned employers (the PKP group among them) run a medical
check-up (MCU), a background check, reference checks and often ask for a police record (SKCK) between the user interview and the join date. Today
a team can only name a custom stage and write notes. Extends Recruitment (`phase-1-pipeline.md`, `phase-2-offers.md`,
`phase-8-offer-letters.md`), the agenda of `phase-8-candidate-experience.md` section 5 and the company permissions. Coded in a coding session,
after `docs/legal-review.md` question 21.

## Decisions

- 2026-10-19: checks are a **checklist per application**, from check types the company defines; the **MCU result is a category only** (fit, fit
  with notes, unfit), never a medical document; the candidate sees the schedule and instructions, not the detailed result; an offer may be marked
  **conditional on the checks** (chosen by the user).
- 2026-10-19: the schema of "Changes in short" and the rules of sections 1 to 5 are approved (decided by the user): `checks.manage` (Recruiter
  preset, not Hiring Manager), consent required for medical, background and police record, outcomes for `checks.manage` only with audited reads,
  files for non-medical checks only, medical outcomes and files deleted 90 days after the application ends, the rejection reason
  `failed_pre_employment_check` without automatic rejection, checks from the interview, offer and hired stages, bulk requests.

## 1. Check types (company)

- `GET/POST company/check-types`, `PATCH company/check-types/{id}`, `POST .../{id}/deactivate|reactivate` (`checks.manage`): `name` (1 to 100),
  `kind` (`medical`, `background`, `reference`, `police_record`, `education`, `other`), `instructions` (for the candidate, at most 2,000, link rule
  of `phase-8-interview-calendar.md` section 6), `requires_consent` (true by default; forced true for `medical`, `background` and
  `police_record`). The front end offers presets (MCU, Background check, Reference check, SKCK, Verifikasi ijazah).

## 2. Checks on an application

- **Request:** `POST company/applications/{id}/checks {check_type_id, scheduled_at?, place?, vendor?, note_to_candidate?}` (`checks.manage`,
  application visible to the member) while the application is in an `interview`, `offer` or `hired` stage (409 `check-not-allowed-stage`); one
  open check per type and application (409 `check-already-open`). Bulk: `POST company/jobs/{id}/applications/bulk-checks` with the same body for
  up to the bulk limit.
- **States:** `requested` → (`consented`) → `scheduled` → `completed`; `waived` and `cancelled` from any open state. `PATCH
  company/applications/{id}/checks/{checkId}` changes the schedule, place, vendor or note (the candidate is told of a new time).
- **Result:** `POST .../checks/{checkId}/result {outcome: pass|pass_with_notes|fail, note?}`; `note` team-only, at most 1,000. For `medical` the
  outcome reads **fit / fit with notes / unfit** and nothing else is accepted. For the other kinds an optional PDF (at most 10 MB, content
  checked) may be attached, team-only. Recording a result completes the check.
- **Who sees what:** status, schedule and vendor: everyone who may see the application. **Outcome, note and file: `checks.manage` only** (a hiring
  manager or interviewer without it sees "Selesai"). Every outcome read of a `medical` or `police_record` check is audited.
- **No automatic move:** an `unfit` or `fail` never rejects or withdraws anything; the team decides (the rejection reason list gains
  `failed_pre_employment_check`).

## 3. The candidate's side

- **Consent:** a check with `requires_consent` waits for `POST me/applications/{id}/checks/{checkId}/consent {agree: true}` or `.../refuse
  {reason?}`; the consent text names the check, the purpose (this job only), who sees the outcome and the retention. A refusal is shown to the team;
  nothing moves automatically.
- `GET me/applications/{id}` gains `checks: [{id, name, kind, status, scheduled_at, place, vendor, instructions, note_to_candidate,
  consent_required, consented_at}]`; **no outcome, no team note, no file**. After `completed` the candidate sees "Selesai".
- To-do (`me/tasks`): `check_consent` while consent is pending. Agenda (`me/agenda`): `check` at its scheduled time.
- Notifications: `check.requested`, `check.scheduled`, `check.rescheduled`, `check.cancelled` (in-app; email by `application_update`).

## 4. Conditional offer

- The offer gains `conditional_on_checks` (bool) and `condition_check_type_ids`; the letter adds "Penawaran ini bergantung pada hasil {names}."
  Accepting does not wait for the checks. A failed check leaves the offer as it is; the team withdraws it or not.
- The hires export gains `checks_completed` (yes or no), never the outcomes.

## 5. Retention and erasure

- Outcomes, notes and files follow the applicant retention of `phase-8-company-admin.md` section 3 and the account erasure; the `medical` outcome
  and every check file are deleted **90 days after the application becomes final** when no shorter rule applies (an internal tenant hands a hire's
  outcome to Employee with #13 only if question 21 allows it).

## Changes in short

| Piece | Change |
|---|---|
| Migration | `recruitment_check_types` (`company_id`, `name`, `kind`, `instructions`, `requires_consent`, `is_active`, timestamps); `recruitment_application_checks` (`application_id`, `check_type_id`, `status`, `scheduled_at`, `place`, `vendor`, `note_to_candidate`, `consented_at`, `consent_refused_at`, `refusal_reason`, `outcome`, `result_note`, `result_file_path`, `result_recorded_by`, `result_recorded_at`, `requested_by`, timestamps; unique open (`application_id`, `check_type_id`)); `recruitment_offers.conditional_on_checks`, `condition_check_type_ids` (json) |
| Permissions | `checks.manage` (catalog 30); presets: Recruiter gains it, Hiring Manager does not |
| API | check types; checks on an application (request, bulk, change, result, cancel, waive); consent and refusal for the candidate; `checks` on my application |
| Ports | agenda and to-do sources gain `check` and `check_consent` |
| Notifications | `check.requested`, `scheduled`, `rescheduled`, `cancelled`, and the team's `check.consent_refused` |
| Audit | `check.outcome_recorded`, `check.outcome_viewed` (medical and police record), no note text |
| Rejection reasons | `failed_pre_employment_check` |
| Legal | `docs/legal-review.md` question 21 |
| Front end | check types in the company settings; a "Cek pra-kerja" panel on the application; consent card and schedule for the candidate; condition box in the offer form |
