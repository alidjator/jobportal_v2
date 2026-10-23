# Phase 8: restricted candidates, undoing a rejection and re-applying (designed 2026-10-14; the waiting period of section 3 coded 2026-10-23, the rest not coded)

Gaps found against Mekari Talenta's rejection tools (help center articles 47788506571289, 48569275253913 and 34773101103385). Not copied on purpose:
the "notify by email" toggle (the job seeker is always told, as for interviews), the AI auto-reject (it weighs gender and age, against the
non-discrimination rule of UU 13/2003 Articles 5 and 6 and our own rule on protected attributes), and deleting an applicant's data (the job seeker's
right, through anonymisation). Extends `phase-1-pipeline.md` (rejection), `phase-1-applications.md` (one application per job) and
`phase-2-bulk-actions.md`. Coded in a coding session.

## Decisions

- 2026-10-14: a company may mark a job seeker **restricted** (reason, period or permanent), by account: marked by members with
  `applications.manage`, changed or lifted by a company admin only (decided by the user).
- 2026-10-14: a restriction **flags** new applications to that company and **hides** the person from its candidate search and recommendations; it
  never rejects automatically and the person is not told (decided by the user).
- 2026-10-14: a rejection waits an **undo window** before it takes effect, an admin setting, 10 minutes by default (decided by the user).
- 2026-10-14: re-applying to the same job after a rejection is allowed after a **waiting period set per job** (default from a company setting;
  "never" stays the default) (decided by the user).
- 2026-10-23: the waiting period of section 3 is coded for the job editor (C4): the column, the job form field, the apply flow and the company setting. The apply state, the "Pernah melamar" note for the
  team and everything of sections 1 and 2 wait (see "Coded: the waiting period" below; user, 2026-10-23).

## 1. Restricted candidates

### Data

`recruitment_restricted_candidates`: `company_id`, `user_id`, `reason` (at most 1,000 characters, team-only), `starts_on`, `ends_on` (null for
permanent), `application_id` (where it was marked, nullable), `created_by`, `lifted_at`, `lifted_by`, timestamps. Active when `starts_on` ≤ today
(Jakarta) and (`ends_on` is null or ≥ today) and not lifted. At most one active row per company and person (409 `already-restricted`; a company
admin edits the existing one instead).

### Endpoints

- Mark while rejecting: the reject request (single and bulk) takes `restrict: {reason, starts_on?, ends_on?}` (`starts_on` defaults to today; no
  `ends_on` means permanent). Created when the rejection takes effect (section 2), so an undo drops it too.
- Mark from the application detail: `POST company/applications/{id}/restriction {reason, starts_on?, ends_on?}` (`applications.manage`, for an
  application the member may see).
- `GET company/restrictions` (company admin and `applications.manage`): active and ended ones, search by name or email, paginated.
- `PATCH company/restrictions/{id} {reason?, starts_on?, ends_on?}` and `DELETE company/restrictions/{id}` (lift): company admin only
  (`company-admin-required`).
- Errors: 422 `invalid-restriction` (dates: `ends_on` after `starts_on`, `starts_on` at most a year ahead), 409 `already-restricted`.

### Effects (only for that company)

- **Applications:** the pipeline list and the application detail carry `restricted: {reason, ends_on}` for an application whose applicant is
  restricted now; the front end shows a "Restricted" badge with the reason. The application is processed like any other.
- **Candidate search and recommendations** of that company leave the person out; a job invitation to them is 409 `candidate-restricted` (for a
  direct call; the person is not shown anyway).
- **Nothing for the job seeker:** no notice, no change on their side; other companies see nothing.
- **Erasure:** anonymising the account deletes its restrictions.
- **Audit:** `recruitment.candidate_restricted`, `recruitment.restriction_changed`, `recruitment.restriction_lifted` (reason not copied into the
  audit metadata, ids and dates only).
- **Legal:** a company blacklist is personal data that affects job chances; `docs/legal-review.md` question 13.

## 2. Undo window for a rejection

- Setting `recruitment.rejection_undo_minutes` (admin, default 10, 0 to 60; 0 keeps today's immediate rejection).
- **Pending rejection:** `POST company/applications/{id}/reject` (and the bulk reject and the "Tandai terpenuhi" dialog of
  `phase-8-hiring.md`) stores `recruitment_pending_rejections` (`application_id` unique, the stage it was in, reason, note, the `restrict` payload,
  `requested_by`, `requested_at`, `commit_at`, `batch_id`) and answers **202** with `commit_at` (and `batch_id` for bulk). Nothing reaches the job
  seeker yet; the application stays in its stage.
- **Team view:** the pipeline list and detail carry `pending_rejection: {commit_at, requested_by}`; the front end shows "Akan ditolak dalam N menit"
  with **Batalkan**. Other moves and rejections of that application are 409 `rejection-pending` until it is undone or done.
- **Undo:** `DELETE company/applications/{id}/pending-rejection` or, for a whole bulk action, `DELETE company/rejection-batches/{batchId}`, by
  whoever may reject the application, before `commit_at` (409 `rejection-committed` after). Audited only through the absence of a rejection; no
  history entry.
- **Taking effect:** `recruitment:commit-rejections` runs every minute and commits the due rows one by one with the application row locked: the
  normal rejection (stage history with the requester as actor and both times, cancelled interview, withdrawn offer, cancelled test, notification and
  email, the restriction). If the application **left the stage** meanwhile (the job seeker accepted the offer, withdrew, or it was moved), the
  pending rejection is dropped and the requester gets the in-app notice `rejection.dropped`.
- **Job seeker during the window:** may still confirm an interview, take a test, accept an offer or withdraw; those actions win, as above.

## 3. Re-applying to the same job

- New column `job_jobs.reapply_after_days` (null for never, else 30 to 365), set in the job form; the default comes from the company setting
  `recruitment.reapply_after_days` (company admin, `company.profile` once the permissions are coded; null by default), prefilled by the front end.
- **Apply:** a job seeker whose application to this job was **rejected** at least that many days ago may apply again: the old application's
  `active_key` is cleared and a new application starts at `applied` (the daily limit counts it). Earlier, or when the job says never, it stays 409
  `already-applied`, now with `reapply_on` (the date) when a waiting period exists. A `hired` application still blocks for good; a withdrawn one works
  as today.
- **Team:** the new application shows "Pernah melamar: ditolak {date} ({reason code})" with a link to the earlier application, and the restricted badge
  when that applies.
- **Job seeker:** the apply state (`GET me/jobs/{id}/apply-state`, `phase-8-job-posting.md` section 6) carries the application and `reapply_on`,
  so the button reads "Anda dapat melamar lagi mulai {date}". (Corrected 2026-10-14: not on the public job page, which is cached and holds no
  per-user data.)

## Coded: the waiting period (2026-10-23)

Migration `2026_10_23_000186`: `job_jobs.reapply_after_days` (null: never; the job form allows 30 to 365).

- **Job form:** `reapply_after_days` on `POST`/`PATCH company/jobs` (30 to 365 or null) and on the company detail. It is not a core field and a clone copies it.
- **Company setting `recruitment.reapply_after_days`** (company admin on the company settings page): an integer from 0 to 365, **0 means never** (the settings catalogue has no null), default 0. It is only the value
  the front end prefills in a new job; the job keeps its own. A value from 1 to 29 is accepted by the setting but refused by the job form.
- **Apply:** a job seeker whose application to the job is **rejected** (`stage_type = rejected`, still holding the key) may apply again from the Jakarta day that is `reapply_after_days` days after the day it was rejected
  (the time the application entered the rejected stage). The key of the old application is freed (the application itself stays rejected and keeps its history) and a new application starts at `applied`; the daily limit counts
  it, and a second rejection starts a new waiting period. Earlier, 409 `already-applied` carries `reapply_on` (`Y-m-d`). A job without a waiting period, and any application that is `hired` or in another stage, answers
  409 `already-applied` without `reapply_on`, as before. The recruiter moving the old application later never takes the key back from the newer one.
- **Not coded:** `GET me/jobs/{id}/apply-state`, the "Pernah melamar" note on the new application, the restricted badge, restrictions, the undo window.

## Changes in short

| Piece | Change |
|---|---|
| Migration | `recruitment_restricted_candidates`, `recruitment_pending_rejections` (gains `candidate_note` in `phase-8-candidate-experience.md`), `job_jobs.reapply_after_days` |
| Settings | `recruitment.rejection_undo_minutes` (admin); `recruitment.reapply_after_days` (company) |
| API | `restrict` in reject and bulk reject; `POST company/applications/{id}/restriction`; `GET`/`PATCH`/`DELETE company/restrictions`; reject answers 202 with `commit_at`; `DELETE company/applications/{id}/pending-rejection`, `DELETE company/rejection-batches/{batchId}`; `reapply_after_days` on jobs; `reapply_on` on `already-applied` and in the apply state |
| Scheduler | `recruitment:commit-rejections` every minute |
| Notifications | `rejection.dropped` (in-app, team) |
| Audit | `recruitment.candidate_restricted`, `restriction_changed`, `restriction_lifted` |
| Errors | 422 `invalid-restriction`; 409 `already-restricted`, `candidate-restricted`, `rejection-pending`, `rejection-committed` |
| Front end | restrict option in the reject dialog; restricted badge; "Batalkan" on pending rejections; restrictions list in the company settings (company admin); waiting period field in the job form; "melamar lagi" on the job page |
