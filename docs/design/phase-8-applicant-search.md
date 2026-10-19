# Phase 8: applicants across jobs, keyword search and more pipeline filters (designed 2026-10-14, coded 2026-10-19)

Gaps found against Mekari Talenta's Candidates menu (help center article 34633532438169): one list of all applicants across jobs, a keyword search,
many filters and a candidate score column. Not copied on purpose: age and gender filters (protected attributes; our sensitive filters stay off until
`legal-review.md` is answered) and adding candidates by hand or by Excel (applicants have their own accounts). Extends `phase-1-pipeline.md` (list,
filters), `phase-1-applications.md` (snapshot), `phase-3-matching.md` (score) and `phase-2-notes.md` (ratings). Coded in a coding session.

## Decisions

- 2026-10-14: the snapshot stays one JSON column read whole (decision of 2026-10-06); searching and filtering use a **separate facet table** filled
  from the snapshot when the application is made (decided by the user).
- 2026-10-14: filters on the **profile** (city or province, minimum education, experience range, skills, maximum expected salary), on **score and
  rating** (minimum match score, sort by score, minimum average rating), on **origin and time** (date range, invited, returning applicant) and on
  **process state** (interview scheduled, offer pending, test not done). Age and gender are not offered (decided by the user).
- 2026-10-14: a **list across jobs** that shows active applications by default (decided by the user).
- 2026-10-19: the snapshot `location` block gains `province_id` and `city_id` (additive, version stays 1) so the facet reads them from the snapshot; a
  snapshot without ids gives null facets (decided by the user, section 1).
- 2026-10-19: `education_rank` comes from a new method of the `ReferenceValues` port (level id to rank), read when the facet is built; the snapshot
  keeps only the level id (decided by the user, section 1).

## 1. Facet table

`recruitment_application_facets` (one row per application, `application_id` primary key, FK cascade), written in the same transaction as the
application:

| Column | From |
|---|---|
| `company_id`, `job_id`, `applied_at` | the application (copied for the indexes) |
| `province_id`, `city_id` | the snapshot location |
| `education_rank` | the highest education level in the snapshot (the rank used by candidate search) |
| `experience_months` | the experience entries of the snapshot, overlaps counted once, an open entry ending at `applied_at` (the rule of `phase-3-matching.md`) |
| `expected_salary_monthly` | the snapshot's expected salary turned into a monthly amount (null when none) |
| `skill_ids` | the snapshot's skill ids (`integer[]`, GIN index) |
| `search_text` | headline, experience job titles and employers, skill names, education institutions and majors, city name; trigram index on `immutable_unaccent(search_text)` like the other text searches |
| `from_invitation` | whether the applicant held an invitation to this job when applying (asked through a new JobInvitation port at apply time) |
| `returning` | whether the applicant had an earlier application to this company |

- No contact data and no protected attributes in the table.
- **Existing applications:** a command `recruitment:build-application-facets` (idempotent, in batches) fills the rows from the stored snapshots,
  imported ones included; `from_invitation` is filled from the invitations that hold the application id.
- **Anonymisation** deletes the row with the snapshot; the application then matches only the stage and date filters.

## 2. Keyword search

- `filter[q]` on both lists searches the name (as today) **or** `search_text`, through `TextSearch` (case and accents ignored). At least 2
  characters (422 `invalid-filter`).
- The front end shows "Cari nama, jabatan, skill, sekolah".

## 3. Filters and sort

Added to `GET company/jobs/{id}/applications` and the new list across jobs; any other filter stays 422.

| Filter | Meaning |
|---|---|
| `filter[province_id]`, `filter[city_id]` | the applicant's location when applying |
| `filter[education_min]` | an education level id; higher ranks match too |
| `filter[experience_min]`, `filter[experience_max]` | whole years, from `experience_months` |
| `filter[skill_ids]` | comma-separated ids; all must be present |
| `filter[salary_max]` | expected monthly salary at most this; an applicant without one does not match |
| `filter[match_min]` | `match_score` at least this (0 to 100); an application without a score does not match |
| `filter[rating_min]` | average team rating at least this (1 to 5); unrated applications do not match |
| `filter[meets_must_have]` | as today |
| `filter[applied_from]`, `filter[applied_to]` | Jakarta dates |
| `filter[invited]`, `filter[returning]` | true or false |
| `filter[state]` | `interview_scheduled` (a pending or confirmed interview), `offer_pending`, `test_pending` (an assessment invitation not submitted yet, through the existing Assessment port); comma-separated, any matches |

- **Sort:** `applied_at` (default, newest first), `match_score`, `rating` (average; unrated last), each with `-` for the other direction.
- **Counts per stage** follow the filters, so the pipeline columns show the filtered numbers.
- Rows gain `match_score` and `rating {average, count}` when not already there; the front end shows the score as a column (C6) and the list's
  columns can be hidden per member in the browser (no API).

## 4. Applicants across jobs

- `GET company/applications` (new): the same filters, search, sort and paging, plus `filter[job_id]` (comma-separated) and `filter[stage_type]`.
  Without a stage filter it shows **active** applications only (not `hired`, `rejected` or `withdrawn`); `filter[stage_type]` opens the others.
- **Scope:** the jobs the member may see (company admin and `jobs.view_all`: all; others: the jobs assigned to them), the same rule as the job lists.
  Needs `applications.manage` or `applications.view` as the per-job list does.
- Each row is the per-job card plus `job {id, title}`; counts are per stage type (stages of different jobs share the company's stages, so per stage
  also works and is answered too).
- Bulk actions (`phase-2-bulk-actions.md`) work on the selected rows as today, since they already take application ids.
- **Export:** not added here; the per-job export and the hires export stay.
- Front end: a new page "Semua pelamar" (C5b) with the job filter, the search and the filter panel.

## Changes in short

| Piece | Change |
|---|---|
| Migration | `recruitment_application_facets` (GIN index on `skill_ids`, trigram index on `search_text`, indexes on `company_id, applied_at` and `job_id`) |
| Command | `recruitment:build-application-facets` (one-off fill, idempotent) |
| Ports | JobInvitation: whether a person held an invitation to a job; Assessment: applications with a pending test |
| API | `GET company/applications`; new filters, `filter[q]` over `search_text` and sorts on both lists; `match_score` and `rating` on rows |
| Errors | 422 `invalid-filter` for a malformed value or a `q` shorter than 2 characters |
| Front end | filter panel and score column on the pipeline list (C6); "Semua pelamar" page (C5b) |

## Coded (2026-10-19)

Migration `000172` (`recruitment_application_facets`; plus the foreign keys to `company_companies`, `job_jobs`, `reference_provinces` and `reference_cities`, all RESTRICT).
Committed `f760853` on `master` (2026-10-19). Checked (`composer check` green, 3205 tests).

- **Code:** `ApplicationFacetBuilder` (snapshot to `ApplicationFacets`), `ExperienceMonths` (the matching rule, in Recruitment's domain), `ApplicationFacetRepository`
  (save, `hasEarlierApplication`, `sourcesWithoutFacets`), `BuildApplicationFacets` and the command `recruitment:build-application-facets`; `ApplyToJob` writes the row in
  its transaction. `ApplicantFilters` and `ApplicantFilterResolver` carry and complete the filters; `ApplicationRowEnricher` is the per-page enrichment both lists share.
  `RecruiterApplicationQuery` gained `filters`, the across-jobs scope (`companyId`, `jobIds`, `activeOnly`) and `hiringManagerBoundJobIds` (replaces the boolean).
- **Ports:** `ReferenceValues::educationRanks()`, `InvitedCandidates::applicationIdsHeld()` (backfill only; at apply time the existing source check decides
  `from_invitation`), `AssessmentInvitations::applicationIdsWithPendingTest()` (`test_pending` is an invitation that is invited or started).
- **API:** `GET company/applications` (`CompanyApplicantResource`: the job card plus `job {id, title}`); both lists take the new filters and sorts; rows carry `match_score`.
- **Interpretations to confirm:**
  - Only an expected salary given **per month** is kept in `expected_salary_monthly`; an hourly or per-project amount gives null (no hours-per-month rule exists), so
    `filter[salary_max]` never matches it.
  - `filter[experience_min]` and `filter[experience_max]` are whole completed years: 2 to 5 means 24 to 71 months.
  - A malformed filter, an unknown filter, or a `q` under 2 characters is the usual 422 `validation-failed` with the field errors, as every other list; the separate
    code `invalid-filter` of this design is not used (the same code is named in `phase-8-job-posting.md`, not coded yet).
  - **Behaviour change:** the stage counts of the per-job list now follow the search and the filters (the stage filters and the "active only" default excluded), as
    section 3 says; before they ignored every filter. `filter[q]` also needs 2 characters on the per-job list now (the tests were changed).
  - An application with no facet row (anonymised, or imported and not built yet) matches only the stage, date, score and rating filters.
  - A hiring manager bound to a job sees only marked applications of that job in the across-jobs list too, and the counts follow.
  - The facet of an application is not updated later (the snapshot is frozen); `returning` is true when the person had an earlier application to the company at that moment.
- **Before the final import:** run the command (`docs/deployment-checklist.md` section 3); the import writes applications without facets.
