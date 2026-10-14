# Phase 8: tools for partner psychologists (designed 2026-10-14, not coded)

Gaps found from the psychologist's point of view and against Mekari Talenta with LPTUI (help center articles 60386481548697, 60148920477977 and
60149003542169: example reports in the library, a recommendation and a report number that can be validated), chosen by the user from the advice
of 2026-10-14. **Everything here is needed before the first partner institution is switched on** (`assessment.psychological_enabled` and
`docs/legal-review.md`); none of it blocks the go-live of the portal. Not taken now: aggregate data for norm calibration (after a partner runs,
with a legal review of the purpose). Extends `phase-6-assessments.md` (Assessment, parts 2, 3, 6 and 7). Coded in a coding session.

## Decisions

- 2026-10-14: **norms by group** (age, education), the most specific matching row wins (chosen by the user).
- 2026-10-14: the review page shows the **candidate's context** (age, education, the job) to the psychologist only (chosen by the user).
- 2026-10-14: a psychologist **claims** a review before editing or signing (chosen by the user).
- 2026-10-14: a **structured recommendation** on every report (chosen by the user).
- 2026-10-14: **notifications** for psychologists: new reviews and licence expiry (chosen by the user).
- 2026-10-14: an **example report** per battery in the company catalogue (chosen by the user).
- 2026-10-14: a public **report verification** by number, backed by the stored signed PDF (chosen by the user).
- 2026-10-14: a **report register** per provider with an export (chosen by the user).

## 1. Norms by group

- `assessment_norms` gains `age_min`, `age_max` (whole years, nullable) and `education_rank_min`, `education_rank_max` (the education ranks used by
  candidate search, nullable). A row without them applies to everyone.
- **Choosing a row:** among the rows of the scale whose raw range holds the score, the rows whose group holds the candidate; the most specific
  wins (both groups set beats one, one beats none; within a tie the narrower age range). No row: the scale reads `n/a`, as today.
- **Checks on `PUT .../norms`:** rows of one scale and one group may not overlap in raw range (422 `invalid-norms`); groups may overlap each other
  (the specific one wins). Publishing an `auto_issue` battery needs a general row (no group) for every scale, so nobody falls through.
- **Candidate data:** the age and the highest education rank come from the application's snapshot (age at the time of applying; the test follows
  within `max_due_days`), through a new Recruitment port `ApplicantNormData`. They are copied onto the attempt at start (`norm_age`,
  `norm_education_rank`) so a later erasure of the snapshot does not change a report, and cleared by the assessment erasure step.
- **Auto issue** falls back to the manual queue when any scale reads `n/a`.
- The report and the review show which norm group was used per scale (`norm_group`: "usia 18-25, SMA/SMK").

## 2. Candidate context on the review

- `GET /provider/reviews/{id}` gains `context {age, education_level, job {title, level, category}, company_name}`: age and education from the
  attempt copy above, the job through the existing job port. Shown to psychologists only; never to the company (age stays a protected attribute
  there) and never on the PDF, unless the provider's template uses the new placeholders `{{candidate_age}}` and `{{job_title}}`.
- The queue list (`GET /provider/reviews`) gains `job_title` and `company_name` for sorting work.

## 3. Claiming a review

- New columns on `assessment_reports`: `claimed_by_psychologist_id`, `claimed_at`, `claim_touched_at`.
- `POST /provider/reviews/{id}/claim` (200; 409 `review-claimed` with the holder's name when someone else holds a live claim) and
  `POST .../release`. A claim lapses after **24 hours without a write** by the holder; then anyone may claim.
- Editing (`PUT`), scoring drawings, signing, marking reviewed and revising need the caller's own live claim (409 `claim-required`); each write
  refreshes `claim_touched_at`. Reading stays open to the provider's psychologists.
- The queue list shows `claimed_by {id, name}` and takes `filter[claimed]=mine|others|none`. Signing or revising releases the claim.

## 4. Structured recommendation

- New column `assessment_reports.recommendation`: `recommended` (Disarankan), `consider` (Dipertimbangkan), `not_yet` (Belum disarankan).
  Required to sign (422 `recommendation-required`); kept in revisions.
- **Auto issue:** the battery gets rules `recommendation_rules` (for example "every key scale at band ≥ X: recommended; any key scale at band Y:
  not_yet; otherwise consider"), set by a psychologist and checked at publishing for `auto_issue` (`test-incomplete` when missing).
- **Shown:** the report PDF, the company's result (`GET .../result`), the applicant card and the assessment comparison of
  `phase-8-assessment-automation.md`. Not in the candidate's explanation (Pasal 44 asks for an explanation of the results, not the hiring advice);
  `docs/legal-review.md` question 11 gains this point.
- The template placeholder `{{recommendation}}` renders the Indonesian label.

## 5. Notifications for psychologists

- **New reviews:** the hourly assessment task sends each active psychologist of a provider one in-app notification `provider.reviews_waiting`
  (count of unclaimed `pending_review` reports and of unreviewed auto-issued ones) and one email (`assessment.provider_reviews_waiting`) when the
  count went up since the last notice; nothing when nothing changed. The email follows the preference `provider_reviews` (`phase-8-recruiter-workday.md` section 3; on by default).
- **Licence expiry:** a daily task tells the psychologist (in-app and email `assessment.licence_expiring`) and the admins with
  `assessment_providers.manage` (in-app) 60, 30 and 7 days before the STR or SILP ends, and on the day it ends ("tidak dapat menandatangani").
  Once per threshold and licence (`assessment_psychologists.licence_notified` JSON).
- **Responsible psychologist of auto issue:** when their licence ends, the battery's auto issue falls back as today, and the provider's
  psychologists get `provider.auto_issue_paused` (in-app) naming the battery.

## 6. Example report in the catalogue

- `POST /provider/tests/{id}/example-report` (multipart PDF checked by its content, at most 10 MB; replaces the earlier one) and `DELETE`.
  Stored on the private disk under `assessment/examples/{test}/`. Not locked by invitations (it is marketing, not content).
- `GET /company/assessment/catalog/{id}/example-report` downloads it for company members (headers of the company documents); the catalogue entry
  shows `has_example_report`.
- The provider is responsible for using a fictitious candidate; the upload screen says so.

## 7. Report verification

- **Stored PDF:** at signing, at auto issue and at each revision, the PDF is rendered once and stored on the private disk
  (`assessment/reports/{report}/{number}.pdf`) with `pdf_sha256`; downloads serve the stored file, so its hash stays valid. Each PDF carries the
  verification address and the number in its footer.
- **Public check:** `GET verify/assessment-reports?number=CODE/2026/00001` (no sign-in; limited to 30 requests per IP per hour): `{valid,
  provider {name, code}, psychologist {name}, signed_at, revision (R1, R2 or null), superseded: bool, sha256}`. No candidate, company, job,
  scale or recommendation. Unknown number: `{valid: false}`. A front-end page lets a reader drop the PDF to compare its SHA-256 in the browser.
- **Erasure:** anonymising the candidate deletes the stored PDF; the verification then answers `valid: true` with `document_erased: true`
  (the number, signer and date stay, as today).

## 8. Report register

- `GET /provider/register` (psychologists of the provider): signed and auto-issued reports with `filter[from]`, `filter[to]` (signing date,
  Jakarta), `filter[psychologist_id]`, `filter[test_id]`, paged: number, revision, signed at, signer, battery, company name, `auto_issued`,
  `reviewed`, `recommendation`, erased or not. No candidate name.
- `GET /provider/register/export` (same filters, at most 366 days): streamed CSV under the rules of `phase-2-export.md` (byte order mark,
  formula guard, 10 per hour), audit `assessment.register_exported`. It answers UU 23/2022 Pasal 37 (keeping a record of the reports).
- The platform admin reads the same register per provider under `/admin/assessment/providers/{id}/register`.

## Changes in short

| Piece | Change |
|---|---|
| Migration | `assessment_norms` group columns; `assessment_attempts.norm_age`, `norm_education_rank`; `assessment_reports.claimed_by_psychologist_id`, `claimed_at`, `claim_touched_at`, `recommendation`, `pdf_path`, `pdf_sha256`; `assessment_tests.recommendation_rules`, `example_report_path`; `assessment_psychologists.licence_notified` |
| Ports | Recruitment `ApplicantNormData` (age at applying, education rank) |
| API | norm groups on `PUT .../norms`; `context` on the review; `claim`/`release`; `recommendation` on sign, result and card; example report upload and download; `GET verify/assessment-reports`; `GET /provider/register` and export; admin register |
| Scheduler | hourly reviews-waiting notice; daily licence check |
| Notifications | `provider.reviews_waiting` (in-app, email), `assessment.licence_expiring` (in-app, email), `provider.auto_issue_paused` (in-app) |
| Audit | `assessment.register_exported` |
| Errors | 422 `invalid-norms`, `recommendation-required`; 409 `review-claimed`, `claim-required` |
| Legal | question 11 gains the recommendation and the norm groups |
| Front end | provider app: norm group editor, context panel, claim button, recommendation, register; company catalogue example report; public verification page |
