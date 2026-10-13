# Phase 8: public company directory

New API work asked by the front end (`docs/design/frontend.md`, section 7.4, page P12 `/perusahaan`). Designed on 2026-10-13, coded after the legacy
import rehearsal together with the front end. Code is not started.

## Decisions

- 2026-10-13: add `GET /api/v1/companies`, a paginated public list of companies (decided in `frontend.md` 7.4).
- 2026-10-13: the open-job count comes from a new `architecture.md` 2.11 exception: the Company list reader reads `job_jobs` (`company_id`, `status`,
  `deadline`) in the same query ([detail](#open-jobs-per-company)).

## Design

Agreed by the user on 2026-10-13.

- **Which companies:** the same rule as the public profile `GET companies/{slug}`: status `active` and `is_visible` true (`RepositoryPublicCompanies`).
  A suspended or hidden company is never listed.
- **Route:** `GET /api/v1/companies`, no account needed, the public IP limiter of the other public lists, `cache.headers:public;max_age=60;etag` like the
  profile.
- **Filters:** `filter[q]` (part of the name; the trigram index on `company_companies.name` exists), `filter[industry_id]`, `filter[province_id]`,
  `filter[city_id]`, `filter[verified]`, `filter[has_open_jobs]`; `page` and `per_page` with the shared pagination rules. Any other filter is refused
  with 422.
- **Sort:** `name` (default, A to Z), `-open_jobs` (most open jobs first, then name), `-created_at` (newest first, then name). Any other value is refused
  with 422.
- **Item:** `name`, `slug`, `industry`, `city`, `province`, `verified`, `logo_url` (versioned like the profile), and `bio` cut to about 200 characters.
- **Open jobs:** `open_jobs_count` per item (section below).

## Open jobs per company

**Decided by the user on 2026-10-13: option 1.** The reader is `Company/.../DatabasePublicCompanyDirectory` (name settled when coding); the exception is
added to the 2.11 table and to `CrossContextReadContractTest` in the same change. "Open" is the rule of `GET jobs` (`DatabaseJobSearchReader::live`):
`status = active` and `deadline` today or later, so the count matches the jobs the visitor finds on the company page.

The options weighed:

A directory usually shows how many jobs each company has open and lets the visitor sort by it or hide companies without jobs. The count lives in the Job
context (`job_jobs.company_id`, `status = active`); `architecture.md` 2.11 forbids reading another context's table except for the listed exceptions.
Options:

1. A new 2.11 exception: the Company list reader reads `job_jobs` (`company_id`, `status`) in the same query (count, sort, `filter[has_open_jobs]`).
2. A count column on `company_companies`, kept by a Company listener on the Job events (published, closed, expired, taken down, restored); no
   cross-context read, but one more thing to keep in step (and a rebuild command after the import).
3. No count in the directory; the job count shows only on the company profile through `GET jobs?filter[company_id]`.
