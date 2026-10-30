# Phase 8: pre-employment checks: medical check-up, background and reference checks (designed 2026-10-19, part 1 of the API coded 2026-10-30 behind a switch)

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

## Coded 2026-10-30 (API part 1; the front end and part 2 follow)

Built **switched off** like #31 (the user chose that over waiting for question 21): setting `recruitment.pre_employment_checks_enabled` is `false` until legal answers. While it is off
the company side answers 403 `feature-disabled`, the candidate's consent calls answer 404 and `GET me/applications/{id}` carries `checks: []`.

- **Coded:** the permission `checks.manage` (catalog 30; the Recruiter preset gains it, Hiring Manager does not; members who already hold explicit permissions do **not** get it, a company admin ticks it), check
  types (`GET/POST company/check-types`, `PATCH .../{id}`, `POST .../deactivate|reactivate`; a type has a unique name per company and its kind never changes), the checklist of an application
  (`GET/POST company/applications/{id}/checks`, `PATCH .../{checkId}`, `POST .../{checkId}/waive|cancel`, `POST .../{checkId}/result`, `GET .../{checkId}/result/file`), the candidate's consent
  (`POST me/applications/{id}/checks/{checkId}/consent|refuse`) and `checks` on `GET me/applications/{id}`; audit `check.outcome_recorded` and `check.outcome_viewed` (medical and police record, and every
  file read), no note text.
- **Interpretations:** `recruitment_application_checks.consent_required` records whether consent was needed when the check was requested (the type may change later); the status follows what is known
  (`requested`, `consented` once the candidate agrees, `scheduled` once a time is set and the consent is in); a refusal keeps the status `requested` and shows to the team with the reason (nothing moves);
  a result waits for the consent (409 `check-consent-pending`) and completes the check; a closed check frees its type for a new request; the note to the candidate and the instructions follow the link rule
  (`message-link-not-allowed`); the outcome, note and refusal reason are null for a member without `checks.manage` (`outcome_visible`).
- **Coded 2026-10-31 (part 2, step 1):** the rejection reason `failed_pre_employment_check` (migration `000330` swaps the check constraint of `recruitment_application_stage_history.rejection_reason`; the enum gains the case). It is a label only: it
  is accepted whether or not the switch is on, and rejecting with it never depends on a check. Decided with the user 2026-10-31: the candidate's agenda and to-do sources wait for #25; `checks_completed` of the hires export is `yes` when the
  application has at least one check and all of them are `completed` or `waived`, `no` otherwise, empty when it has none.
- **Coded 2026-10-31 (part 2, step 2):** the hires export gains the last column `Checks completed`: `yes` when every check of the application that counts is `completed` or `waived`, `no` when one is still open, empty when it has none. A `cancelled` check does not count (a request made again after a cancellation must not leave a permanent `no`); only the status is read, never the outcome.
- **Coded 2026-10-31 (part 2, step 3, migration `000331`):** the conditional offer. `recruitment_offers` gains `conditional_on_checks` and `condition_check_type_ids` (json); the offer request (send and move, `OfferFields`) takes `conditional_on_checks` and `condition_check_type_ids`;
  the resource carries `conditional_on_checks` and `condition_check_types: [{id, name}]` (the current names; the generated letter names them as they were when the offer was sent: "Penawaran ini bergantung pada hasil {names}."). Interpretations: an offer is
  conditional exactly when it names at least one type (`conditional_on_checks: true` without types is 422, types without the flag are ignored); every type must be one of the company's and in use (422 `invalid-offer`); a conditional offer while
  the switch is off answers 403 `feature-disabled`; the member who sends the offer needs no `checks.manage` for it (the offer permission decides); a revised offer carries its own condition. Accepting never waits for the checks and a failed check leaves the offer as it is.
- **Coded 2026-10-31 (part 2, step 4, in-app only):** the event `CheckChanged` and the notifications `check.requested`, `check.scheduled`, `check.rescheduled`, `check.cancelled` (to the job seeker) and `check.consent_refused` (to the member who requested the check, in-app only), written by a queued listener. The data holds
  ids, job title, company name, check name and the scheduled time, never an outcome or a note. Interpretations: `scheduled` when a time is set on a check that had none, `rescheduled` when it moves (a change of place, vendor or note, a cleared time and a waiver are not announced); `requested` also
  when the request already carries a time. **Not yet:** the four emails to the job seeker (`application_update`), which need four templates in the catalog.
- **Coded 2026-10-31 (part 2, step 5):** the 90-day purge, `recruitment:purge-check-results` (daily, `PurgeCheckResults`). Decided with the user 2026-10-31: an application is final once it entered `rejected`, `withdrawn` or `hired` (`stage_entered_at`); 90 days later a `medical` check loses its outcome, its
  note and its file, every other check loses its file only (its outcome stays until the applicant retention of #26), and all stay `completed`. The file goes first, the row after; no audit entry. The handing of a hire's outcome to Employee (#13) is not coded and waits for legal question 21.
- **Coded 2026-10-31 (part 2, step 6):** bulk requests, `POST company/jobs/{id}/applications/bulk-checks` (`BulkRequestChecks`; body of the single request plus `application_ids`, at most `recruitment.max_bulk_applications`). The selection, the type and the shared input are checked
  as a whole first (422 `invalid-applications` or `too-many-applications`, 404 type, 422 `check-type-inactive`, `invalid-check`, `message-link-not-allowed`; a mistake changes nothing); then each application goes through the single request and the answer lists each in the order of the selection
  (`moved`: requested, or `skipped` with the single request's code such as `check-not-allowed-stage` or `check-already-open`). Each check notifies the candidate as the single request does.
- **Coded 2026-10-31 (part 2, step 7):** the four emails to the job seeker, as templates `recruitment.check_requested`, `check_scheduled`, `check_rescheduled` and `check_cancelled` (variables `name`, `job_title`, `company_name`, `check_name`, `schedule`, `place`, `vendor`, `note` and `consent`; the optional ones carry their label and line breaks, as the offer
  templates do; `consent` asks for the agreement while the check still awaits it). They follow the preference `application_update` like the offer emails, and the refusal tells the team in-app only.
- **Open (part 2):** the to-do and agenda sources (they wait for #25: the candidate's agenda and to-do do not exist yet).

## Front end coded 2026-10-30 (part 1)

- **Workspace:** the page `/perusahaan/cek-pra-kerja` (menu "Perusahaan, Jenis cek", `checks.manage`): the company's check types with the five suggested presets, consent forced for medical, background and police
  record, a type switched off rather than deleted. The tab "Cek pra-kerja" on the application (`applications.view`): the checklist with status, schedule and vendor for everyone, "Minta cek" (from the interview stage on), change of
  schedule, result (a medical one reads fit, fit with notes, unfit; a PDF for the other kinds), waive and cancel for `checks.manage`; outcome, team note and file only where the API sends them.
- **Career:** the card "Cek pra-kerja" on the application (`career.checks`): schedule, place, vendor and instructions; a check that needs consent shows "Setuju" (with the consent text and a tick) and "Tolak" (with an
  optional reason); a completed check reads "Selesai" with no result.
- While the switch is off both sides stay quiet: the tab and the page show that the feature is not switched on (403 `feature-disabled`), the Career card is absent (`checks: []`).
## Front end coded 2026-10-31 (part 2)

- **Workspace:** the box "Syarat cek pra-kerja" in the offer dialog (a tick and a multi-select of the company's active check types; the types are read with `checks.manage`, and an offer that is already conditional keeps its box with the types it names for a member without it); the line "Bergantung pada hasil: ..." on each
  offer of the offers tab; "Minta cek" in the selection bar of an applicant list (`checks.manage`, a dialog with the type, place, vendor and note; the time is set per applicant with "Ubah jadwal"), which reports how many were requested and how many skipped; the reason `failed_pre_employment_check` in every
  rejection dialog (it is offered whether or not the feature is on: the front end has no flag for the switch, and the API accepts the reason either way).
- **Career:** the offer page says which checks the offer depends on and that the candidate can answer without waiting for them (`OfferSummary`); the reason has its own text.
- **Open:** the to-do and agenda of the candidate (#25).
