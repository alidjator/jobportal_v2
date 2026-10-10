# Saved jobs and the talent pool (designed and coded 2026-10-10)

Two gaps from the legacy system that the first gap analysis marked as present (J6 favourite jobs, R11 talent pool) but that were never rebuilt: `bookmark_candidate_job` and `bookmark_company` with `company_bookmark_categories`. The legacy bookmark toggles were `GET` routes (finding S11); both features here use `PUT`, `POST`, `PATCH` and `DELETE`.

## Decided (2026-10-10, user)

1. Both features are built now.
2. The talent pool has **free categories per company** (as in the legacy system), not a fixed list and not none.
3. The importer for the legacy bookmark tables **waits for the whole legacy import** (`phase-1-migration.md`: a feature brings its own importer when the full import is run).

## Saved jobs (J6b)

A job seeker saves a live job and finds it again in a list. It lives in the **Job** context (it reads `job_jobs` and the public search rules, so no cross-context access).

- **Who:** a signed-in job seeker. Any other account is refused with `job-seeker-only`.
- **Saving:** `PUT /me/saved-jobs/{id}` answers `204`. Only a live job can be saved (the rule of the public page: `active`, deadline not passed, company visible); anything else is `404`. Saving a job again is not an error and changes nothing.
- **Removing:** `DELETE /me/saved-jobs/{id}` answers `204`, also for a job that is closed and for one that is not saved.
- **List:** `GET /me/saved-jobs` (`page`, `per_page`), the most recently saved first. A live job comes as the row of the public search under `job`; a job that is no longer live (closed, expired, taken down, hidden company) stays in the list with `available: false` and `job: null`, still carrying `job_id`, `title` and `saved_at`, so it does not vanish silently. No moderation reason is exposed.
- **Limit:** 200 saved jobs per job seeker, `422` `saved-jobs-limit` (a job that is already saved can still be saved again at the limit). Limiter `saved-jobs`: 60 writes per account per minute.
- **Erasure:** the rows of an anonymised account are deleted (`EraseSavedJobsWhenUserAnonymized`, queue `default`).
- **Table:** `job_saved_jobs` (migration `000108`): `user_id`, `job_id` (both RESTRICT keys), `saved_at`; unique `(user_id, job_id)`. Saving uses `ON CONFLICT DO NOTHING`, so a double click never raises a unique violation.
- **Reader:** `JobSearchReader::hitsOf()` (new) returns the live hits among given ids, with the same rule and rows as the search; `ListSavedJobs` turns them into list items with `PublicJobListItems`.

Not done: a `saved` flag on the public job list and page, so a client can draw a filled heart (it can call `GET /me/saved-jobs` once). Say so if it is wanted.

## Talent pool (R11)

A company keeps candidates it found, to come back to. A new context **TalentPool** (`src/TalentPool`); it reaches candidates only through `CandidateDirectory` (JobSeeker), which gained `findableIds()`.

- **Who:** every member of an active company (company admin and recruiters). The pool belongs to the company, not to the member who saved: all members see the same list. A suspended company gets `company-suspended`.
- **Saving:** `POST /company/talent-pool` with `candidate` (the slug from the candidate search) and an optional `category_id`. The candidate must be findable right now (opted in, active account, complete profile), else `404`. One entry per company per candidate, `409` `already-in-talent-pool`. A category of another company is `404`. A company keeps at most 1,000 candidates, `422` `talent-pool-limit`.
- **Moving:** `PATCH /company/talent-pool/{id}` with `category_id` (the key must be sent; `null` takes the candidate out of any category). **Removing:** `DELETE /company/talent-pool/{id}`, `204`, also when already gone.
- **List:** `GET /company/talent-pool` (`filter[category_id]`, `page`, `per_page`), the most recently saved first. An entry shows `candidate` (`slug`, `name`) and `category`.
- **Privacy:** when a candidate is no longer findable (profile switched off, account closed, profile incomplete) the entry stays but shows `available: false`, `candidate: null` and no other data about them, so the company can remove it; if the candidate becomes findable again it shows again. An anonymised account's entries are deleted in every company (`EraseTalentEntriesWhenUserAnonymized`). No private note is kept (a free-text note about a person is left out on purpose).
- **Categories:** `GET|POST /company/talent-pool/categories`, `PATCH|DELETE /company/talent-pool/categories/{id}`. Any member lists and creates; **renaming and deleting need a company admin** (`company-admin-required`). A name is trimmed, 1 to 50 characters (`category-name-invalid`), unique per company whatever its case (`409` `category-name-taken`, also by a unique index on `lower(name)`), at most 30 per company (`category-limit`). The list shows how many candidates each category holds. Deleting a category keeps its candidates, uncategorised.
- **Tables** (migration `000109`): `talentpool_categories` (`company_id` RESTRICT, `name`), `talentpool_entries` (`company_id`, `candidate_user_id` RESTRICT, `category_id` SET NULL; unique `(company_id, candidate_user_id)`). Both are listed in `CrossContextForeignKeysTest`.
- **Limiter** `talent-pool-write`: 120 writes per account per minute.

## Documentation

`ApiTags`: `me/saved-jobs` is in "Job search", `company/talent-pool` in "Candidate search". New error codes (to regenerate in `docs/api/error-catalog.md`): `saved-jobs-limit`, `already-in-talent-pool`, `talent-pool-limit`, `category-limit`, `category-name-taken`, `category-name-invalid`.

## Tests

`tests/Feature/Job/SavedJobTest.php` (11) and `tests/Feature/TalentPool/TalentPoolTest.php` (17), plus the two new groups in `ApiTagsTest` and the new keys in `CrossContextForeignKeysTest`. Not run at the time of writing (rule: only the user runs checks).

## Legacy import (waits for the full import)

`bookmark_candidate_job` (candidate, job) becomes `job_saved_jobs`; a bookmark of a job that is not live is still imported (it shows as unavailable). `company_bookmark_categories` becomes `talentpool_categories` and `bookmark_company` becomes `talentpool_entries`; a bookmark whose candidate has no slug or an anonymised account is skipped. Duplicates collapse on the unique keys. This importer is not written yet.
