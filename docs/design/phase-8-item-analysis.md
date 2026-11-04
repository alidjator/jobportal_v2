# Phase 8: item analysis and norms (designed 2026-11-04, not coded)

Asked by the user on 2026-11-04 after the figural reasoning test (`phase-8-figural-reasoning-test.md`): its items have no psychometric data, so the platform needs a report that says how every item works (difficulty, discrimination, distractors, reliability, speed, bias) and turns the data into norms. Extends `phase-6-assessments.md` (attempts, answers, scoring, norms of a battery) and `phase-8-provider-institutions.md` (Portal Mitra).

## Decisions

- 2026-11-04, D1 (user): **library tests and psychological batteries**, not the companies' own skill tests (their numbers of candidates are too small to mean anything). [Scope](#scope-and-access)
- 2026-11-04, D2 (user): **an anonymous response matrix** is written when an attempt is submitted: one row per attempt with the answer to every item and a few group attributes, **no** candidate, application, invitation or company id. It stays after the application is anonymised, so the statistics do not shrink with retention. [Response matrix](#response-matrix)
- 2026-11-04, D3 (user): **every submitted attempt counts**, auto-submitted ones too; an item never reached is counted apart (speed), sample questions never. [Formulas](#formulas)
- 2026-11-04, D4 (user): **norms are part of this design.** [Norms](#norms)
- 2026-11-04, D5 (user): battery items are analysed **per scale**: right/wrong items as in a skill test; Likert items by mean, SD, corrected item-scale correlation and Cronbach's alpha (with alpha if deleted); forced choice and most/least by choice frequencies only (ipsative scores make alpha misleading); drawing, speed columns and video are left out. [Formulas](#formulas)
- 2026-11-04, D6 (user): group attributes kept in a row: **education level, age band, gender, job level** of the vacancy, and the **device** class. [Response matrix](#response-matrix)
- 2026-11-04, D7 (user, schema confirmed): education = the highest `jobseeker_educations.education_level_id` by the order of `reference_education_levels`; age band = five years from `jobseeker_profiles.birth_date`; gender = `jobseeker_profiles.gender`; job level = `job_jobs.job_level_id` of the applied job; device = phone, tablet or desktop from the user agent at submit; a missing value is `unknown`. Read once at submit through Shared ports.
- 2026-11-04, D8 (user): a battery's statistics are seen by **the provider's lead psychologists** (Portal Mitra) and by **platform admins with `assessment_providers.manage`** (read only); a library test's by admins with `assessment_library.manage`. Member psychologists do not see them.
- 2026-11-04, D9 (user): a norm is **proposed by the report and approved by the author** (the admin for a library test, a lead psychologist for a battery) before it is used. [Norms](#norms)
- 2026-11-04, D10 (user): a battery's approved norms go into **a new version** (`CloneTest` with the new norm table, as a draft the psychologist publishes and grants); the old version and its reports keep the old norms. [Norms](#norms)
- 2026-11-04, D11 (user): **CSV download of the matrix** (one row per attempt) is allowed, with every attribute value held by fewer than `assessment.analysis_min_cell` rows of the test replaced by `other`; each download is in the audit log. [Endpoints](#endpoints)
- 2026-11-04, D12 (user): **backfill once** with an Artisan command from the submitted attempts whose answers still exist. [Response matrix](#response-matrix)
- 2026-11-04, D13 (user): thresholds are settings: statistics from **50** rows of a test, a norm group from **300** rows, no figure of a group smaller than **10**. [Settings](#settings-and-switches)
- 2026-11-04, D14 (this design): legally open, so built with **switches that default to off** (`assessment.response_matrix_enabled`, `assessment.response_matrix_gender`); legal only decides on or off. [Settings](#settings-and-switches)
- 2026-11-04, D15 (this design): statistics are **computed on request** from the matrix and cached per test until a new row arrives; nothing aggregate is stored, so a new analysis (another DIF attribute) can always be computed again.
- 2026-11-04, D16 (this design): a library test's approved norm is a **percentile table**, kept apart from the test content (so the test lock does not apply); the percentile is **frozen at submit** with the norm set that was active then, and shown next to the percentage.

## Scope and access

| Test | Who reads the analysis | Who approves a norm | Where |
|---|---|---|---|
| Library skill test | admin with `assessment_library.manage` | the same admin | Console, library test page, tab "Analisis" |
| Psychological battery | lead psychologist of the provider; admin with `assessment_providers.manage` (read only) | lead psychologist | Portal Mitra, battery page, tab "Analisis"; Console, provider battery page (read only) |

Company skill tests: no matrix row is written. A copy made with `CloneTest` is another test and starts with no rows; the report of the old test stays.

## Response matrix

Written by `ResponseMatrixRecorder` after the submit transaction (`AttemptSubmitter`, after `ScoreAttempt`; for a battery after scoring, not after signing), only when `assessment.response_matrix_enabled` is on and the test is a library test or a battery.

- **`assessment_response_rows`:** `id`, `test_id`, `source_hash` (unique; HMAC-SHA256 of the attempt id with the app key, so the backfill and a retry never write twice; once the attempt is erased nobody can link it back), `submitted_month` (first day of the month, no exact time), `auto_submitted`, `device` (`phone`, `tablet`, `desktop`, `unknown`), `education_level_id` (nullable = unknown), `age_band` (`18-22`, `23-27` and so on, `unknown`), `gender` (`male`, `female`, `unknown`; always `unknown` while `assessment.response_matrix_gender` is off), `job_level_id` (nullable), `total_score` (decimal; the test score of a skill test, null for a battery), `created_at`.
- **`assessment_response_items`:** `row_id`, `question_id`, `section_id`, `position` (in the attempt), `state` (`answered`, `omitted`: asked and reached but left empty, `not_reached`: after the last answered question of its section in a timed section), `option_positions` (jsonb, as `assessment_answers`), `score` (decimal, the item score of `ScoreAttempt`: 0 or the points for a right/wrong item, the option value for Likert), `scale_key`.
- **Not stored:** any id of a candidate, account, application, invitation, job or company; IP; answers to text, number, drawing and video questions (only their state and score).
- **Retention:** the rows are anonymous, so `EraseAssessmentData` and the application anonymisation leave them (an exception to `phase-6-assessments.md` "retention follows the application anonymisation"; recorded in `docs/legal-review.md`). They are deleted with their test.
- **Backfill:** `php artisan assessment:backfill-response-matrix`, idempotent through `source_hash`, reads the attributes as they are now (stated in the command's output).
- **Ports:** `Shared\Application\Candidate\CandidateTraits` (JobSeeker: highest education level, birth date, gender of a user) and the job level through `JobDirectory` (`JobSummary::$jobLevelId`); the application's job from the invitation as today.

## Formulas

Per test, computed over its rows (only when there are at least `assessment.analysis_min_rows`); a section is a subtest.

- **Difficulty** `p` = right / (answered + omitted). Not-reached items are left out of `p` and reported as their own rate.
- **Discrimination:** corrected point-biserial between the item (0/1) and the section total without the item; and the upper-lower index `D = p(upper 27%) − p(lower 27%)` on the section total.
- **Distractors:** per option A to E, the share of all rows, of the upper and of the lower 27%; plus omitted and not reached.
- **Reliability:** KR-20 per section and for the test, `k/(k−1) · (1 − Σpq / σ²)`, over the rows that were asked every item of the section (a section that draws questions at random gets `null` and the reason); SEM = `σ · √(1 − r)`.
- **Speed:** per section, the share of rows that reached the last item, and the not-reached rate per item.
- **Likert scales (D5):** item mean and SD, corrected item-scale correlation, Cronbach's alpha per scale and alpha if the item is deleted.
- **Forced choice, most/least:** the share of each choice.
- **Bias (DIF):** Mantel-Haenszel per right/wrong item for one attribute at a time (two values: the reference group is the larger one), matched on the section total in score strata; reports `Δ_MH = −2.35 · ln(α_MH)` and the ETS class A, B or C. Only for values with at least `assessment.analysis_min_rows` rows each.
- **Flags:** `p < 0.20` (hard), `p > 0.90` (easy), corrected point-biserial `< 0.20` (weak) or `< 0` (check the key), a distractor chosen by fewer than 5% (dead), a distractor the upper group chooses more often than the key (ambiguous), not reached above 20% (speeded), DIF class C.

## Norms

- **Proposal:** for a reference group (`all`, or one value of one attribute) with at least `assessment.norm_min_group` rows: the frequency of each raw score and its percentile rank `PR = (cf_below + f/2) / N · 100`, the normalised T score (`50 + 10 · Φ⁻¹(PR/100)`), mean, SD, n and the dates the rows cover. For a library test the raw score is `total_score`; for a battery each scale's raw score (the sum of its item scores).
- **Library test (D16):** approving stores an `assessment_norm_sets` row (`test_id`, `group_attribute`, `group_value`, `n`, `covers_from`, `covers_to`, `approved_by_admin_id`, `approved_at`, `retired_at`) with `assessment_norm_set_scores` (`raw_score`, `percentile`). One active set per group; approving a new one retires the old. At submit, the attempt outcome keeps `norm_set_id` and `percentile` of the most specific active set that matches the candidate (education level first, then `all`); a company sees "Persentil 72 (kelompok S1, n 412)" next to the percentage. Attempts submitted before stay without a percentile.
- **Battery (D10):** the lead psychologist gives the band cut points per scale (for example `Rendah` below PR 25, `Sedang` to 75, `Tinggi` above). Approving runs `CloneTest` and writes one `assessment_norms` row per raw score of each scale (`raw_min = raw_max`, `standard_score` = the T score, `band` from the cut points) into the new draft, which the psychologist publishes and grants; the response gives its id.

## Endpoints

Every response carries `n` and `available` (false with `reason` `too-few-rows` below the threshold, so the page says how many rows are still needed).

| Route | Access |
|---|---|
| `GET admin/assessment/library/tests/{id}/analysis` (sections, items, flags) | `assessment_library.manage` |
| `GET admin/assessment/library/tests/{id}/analysis/dif?attribute=` | same |
| `GET admin/assessment/library/tests/{id}/analysis/export` (CSV, D11) | same |
| `GET admin/assessment/library/tests/{id}/norm-proposal?group=` | same |
| `GET`, `POST admin/assessment/library/tests/{id}/norm-sets`, `DELETE .../norm-sets/{setId}` (retire) | same |
| `GET admin/assessment/batteries/{id}/analysis`, `.../analysis/dif`, `.../analysis/export`, `.../norm-proposal` | `assessment_providers.manage` |
| `GET provider/tests/{id}/analysis`, `.../analysis/dif`, `.../analysis/export`, `.../norm-proposal` | lead psychologist of the battery's provider |
| `POST provider/tests/{id}/norm-versions` (group, cut points per scale) → 201 with the new test id | same |

Errors: 422 `invalid-analysis-group` (an attribute or value that does not exist), 422 `norm-group-too-small` (fewer rows than `assessment.norm_min_group`), 422 `invalid-norm-cuts` (cut points not rising, outside 1 to 99, a scale missing), 422 `test-not-analysable` (a company skill test), 403 `psychologist-not-lead`, 409 `response-matrix-disabled` (the switch is off and no rows exist).

## Settings and switches

| Key | Default | Range | Meaning |
|---|---|---|---|
| `assessment.response_matrix_enabled` | off | | write matrix rows (legal switch, D14) |
| `assessment.response_matrix_gender` | off | | keep gender in new rows (legal switch) |
| `assessment.analysis_min_rows` | 50 | 20–1000 | rows before a test's (or a DIF group's) statistics are shown |
| `assessment.norm_min_group` | 300 | 100–5000 | rows before a norm group can be proposed |
| `assessment.analysis_min_cell` | 10 | 5–50 | the smallest group shown or exported as itself |

## Front end

- **Console**, library test page, tab "Analisis" (only with `assessment_library.manage`, as the API): summary cards per subtest (n, mean, SD, KR-20, SEM, finished in time), the item table (p, point-biserial, D, not reached, flags, sortable), a row opens the distractor chart A to E for all, upper and lower groups next to the item's pictures; a DIF panel with an attribute picker; the norm panel (group picker, proposal table, "Sahkan" and the active sets with "Nonaktifkan"); "Unduh CSV". A test under the threshold shows "Perlu 50 percobaan, baru 12".
- **Portal Mitra**, battery page, tab "Analisis" for a lead psychologist: the same, per scale, with alpha for Likert scales and the cut point form that creates the new version.
- **Console**, provider battery page: the analysis read only (no norm actions).

## Tests to write

- Matrix: a row and its items at submit for a library test and a battery, none for a company test, none while the switch is off; gender `unknown` while its switch is off; `not_reached` and `omitted`; no identifying column; anonymisation of the application leaves the rows; backfill twice writes once.
- Formulas: unit tests of p, corrected point-biserial, D, KR-20, SEM, alpha, Mantel-Haenszel and percentile ranks against hand-computed small matrices.
- Access: each route for the right admin permission, a lead and a member psychologist, another provider; thresholds and `too-few-rows`; the CSV replaces small cells with `other` and writes the audit log.
- Norms: a library set frozen into the outcome at submit and shown to the company; a new set retires the old; a battery norm version is a draft clone with one norm row per raw score; the errors above.
- `tests/Feature/Platform/SettingsTest.php` for the five settings.

## Open for the coding session

- G1: how the invitation reaches the job of the application today (read `src/Assessment` before adding a port).
- G2: where `ScoreAttempt` gives the item score of a Likert option, and reverse-keyed items.
- G3: the size of the cache (one test with thousands of rows): compute in SQL where it can (p, distractors), in PHP for correlations.
