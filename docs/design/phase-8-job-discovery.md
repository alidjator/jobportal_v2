# Phase 8: job discovery: relevance search, posting-date filter, similar jobs, staged contact data, sharing, disability-friendly jobs and following companies (designed 2026-10-15, not coded)

Gaps found from the job seeker's point of view while finding a job and starting an application, after `phase-8-jobseeker-privacy.md` and
`phase-8-candidate-experience.md`, against Mekari Talenta (help center articles 42469038713369: career page filters; 34576595122073 and
34630221946009: application form with a mandatory NIK, share, resume parsing) and the job portals of `docs/analysis/gap-analysis-round-2.md`
(G14), chosen by the user from the advice of 2026-10-15. Talenta has no job seeker accounts, so the discovery pieces follow the job portals. Not
taken now: draft applications (G16), mobile number verification (G5), fewer alert emails for inactive users (G15), passkeys (G18). Extends
`phase-1-jobs.md`, `phase-3-job-alerts.md`, `phase-3-matching.md`, `phase-4-business-intelligence.md` (source), `phase-1-applications.md`
(snapshot) and `frontend.md` (P2, P3). Sections 1 to 5 are **before go-live**; sections 6 and 7 after.

**Read from the code (2026-10-15):** every keyword word must match the title (`TextSearch`, unaccented `ILIKE`), a tag or a company name; the
description is not searched and there is no relevance order (`JobSearchSort`); the job alerts use the same builder (`JobSearchQueryBuilder`);
the match percentage is shown on rows and the detail but cannot sort; the application's resume is the job seeker's own upload, which usually
carries the contact data; `ApplicationSource` has `search`, `recommendation`, `alert`, `direct`, `invitation`; `frontend.md` already plans a share
button, Open Graph cards and `JobPosting` data on P3.

## Decisions

- 2026-10-15: keyword search moves to **PostgreSQL full text** on a stored `search_vector`, with a **relevance** order, default when a keyword is given;
  the alerts follow the same rule (decided by the user).
- 2026-10-15: a **"best match" order** for a signed-in job seeker with a complete profile.
- 2026-10-15: a **posting-date filter** and a **similar jobs** endpoint.
- 2026-10-15: for an **unverified company**, the applicant's contact data and resume download stay **closed while the application is in
  `applied`** (decided by the user).
- 2026-10-15: share links carry `ref=share` and a new source **`shared`**.
- 2026-10-15: a **disability-friendly** flag on jobs, set by the company and **always reviewed** (chosen by the user).
- 2026-10-15: job seekers **follow companies**: in-app notice at once and a weekly email (chosen by the user).
- 2026-10-15: the schema below (decided by the user).

## 1. Relevance search (before go-live)

- **Column:** `job_jobs.search_vector` (tsvector, GIN index), built with the `simple` configuration over `immutable_unaccent` text: title weight A,
  summary (once `phase-8-job-posting.md` adds it) and tag names B, company name C, description (Markdown stripped) D. Job fills it on every save; a listener refreshes the jobs of a company
  when its name changes and the jobs carrying a tag when the tag is renamed (queue `bulk`). The migration fills existing rows.
- **Matching:** the keyword becomes a `websearch_to_tsquery('simple', unaccent(q))` with prefix matching on the last word; every word must match
  somewhere in the vector (the description now counts). `TextSearch` on the title and the tag and company lookups of `JobSearchQueryBuilder` are
  replaced; the alerts use the same builder, so an alert may now match a job through its description.
- **Order** `sort=relevance` (`ts_rank_cd` with the weights, ties by newest): the default when `filter[q]` is given; without a keyword the default
  stays `-published_at`. The other orders stay selectable.
- **Best match** `sort=match`: for a signed-in job seeker with a match profile, the first **500** jobs of the filtered result (by relevance or
  newest) are scored by the matching engine and returned by percentage, then newest; jobs without a percentage last. Without a profile the order
  falls back to the default and the response `meta.sort` says which order was used. Not available to alerts.

## 2. Posting-date filter (before go-live)

- `filter[posted_within]` = `1`, `3`, `7` or `30` (days, counted from now on `published_at`), on `GET jobs` and in the job alert criteria (where it
  is ignored for immediate alerts). The front end adds "Tanggal posting" to the filters.

## 3. Similar jobs (before go-live)

- `GET jobs/{job}/similar`: up to **6** live jobs other than this one, from public companies: same role first, else same category; ranked by same
  city, same province, job level within one step, then newest; at most 2 from one company. Works for a live, closed or expired job (the 404 page of
  P3 uses it); a taken-down or hidden job answers 404. Cached 10 minutes per job.
- P3 shows "Lowongan serupa" below "other jobs of the company".

## 4. Staged contact data for unverified companies (before go-live)

- **Rule:** while the company is **not verified** (the public company card's `verified`) and the application is in `applied`, the team does not get
  the snapshot's `email`, `phone` and `whatsapp` (they read `null` with `contact_hidden: true`), and the resume download answers 403
  `contact-not-available`. From `screening` on, or once the company is verified, everything opens as today. The rule sits in Recruitment
  (`RecruiterApplicationReader`, `GetCompanyApplicationResume`) and covers the detail, the pipeline rows, the export and the bulk export.
- **Unchanged:** the profile part of the snapshot (experience, education, skills, languages, links, expected salary), the screening answers, chat,
  emails sent by the platform, and the voice interview (the platform dials, the number is not shown).
- Front end: "Kontak dan CV terbuka setelah lamaran dipindah ke tahap screening" on the detail; the job seeker sees nothing new.
- The import's applications follow the same rule.

## 5. Sharing and the `shared` source (before go-live)

- **Front end (P3):** the share button uses the Web Share API on phones; on desktop it offers WhatsApp (`https://wa.me/?text=`), copy link and
  the usual networks. The shared address carries `?ref=share`.
- **Source:** `ApplicationSource::Shared` (`shared`; CHECK on `recruitment_applications.source` updated). The client sends `source=shared` when the
  visitor arrived with `ref=share` (kept for the session, as the other hints). BI's source analysis shows it.

## 6. Disability-friendly jobs

- **Columns:** `job_jobs.disability_friendly` (boolean, default false) and `accessibility_note` (≤500, plain text, nullable; for example access to
  the workplace or adjustments offered). Set in the job editor (`jobs.create`).
- **Review:** publishing, reopening or switching the flag on for a live job **always goes to the moderation queue**, whatever
  `jobs.require_approval` says (the review rules of `phase-8-platform-admin.md` section 2); the moderation view shows the flag and the note.
- **Search:** `filter[disability_friendly]=1` on `GET jobs` and in the alert criteria; a badge "Ramah disabilitas" on rows and the detail with the
  note; `JobPosting` data unchanged.
- **Reports:** the job report reasons of `phase-8-platform-admin.md` gain `false_accessibility_claim`.

## 7. Following companies

- **Table** `jobalert_company_follows` (`user_id`, `company_id` FK to `company_companies` CASCADE, `created_at`; unique pair; listed in
  `CrossContextForeignKeysTest`), at most **50** per job seeker (422 `follow-limit`). Not counted in the 5 job alerts.
- **Endpoints:** `GET me/followed-companies`, `PUT`/`DELETE me/followed-companies/{companyId}` (204; a hidden or unknown company is 404). The
  public company page and job detail gain `followed` for a signed-in job seeker, and `followers_count` is not shown.
- **Notices:** when a job of a followed company goes live for the first time (the event the immediate alerts use), in-app
  `company_follow.new_job` to each follower (queue `bulk`; skipped when an alert already told them about that job, through `jobalert_deliveries`).
  Every Monday 08:00 Jakarta, one email `jobalert.followed_companies_digest` with the week's new jobs of the followed companies, under the new
  preference `company_follows` (email, on by default). A suspended or hidden company sends nothing.
- **Erasure:** the account erasure deletes the follows; a blocked company (`phase-8-jobseeker-privacy.md`) cannot be followed (422
  `company-blocked`) and blocking removes the follow.

## Changes in short

| Piece | Change |
|---|---|
| Migration | `job_jobs.search_vector` (GIN, filled), `disability_friendly`, `accessibility_note`; `jobalert_company_follows`; `recruitment_applications.source` CHECK with `shared` |
| API | `sort=relevance` (default with a keyword) and `sort=match`; `filter[posted_within]`; `filter[disability_friendly]`; `GET jobs/{job}/similar`; `contact_hidden` and 403 `contact-not-available` for unverified companies in `applied`; `source=shared`; `me/followed-companies`; `followed` on company and job |
| Alerts | full-text matching; `posted_within` and `disability_friendly` criteria |
| Moderation | jobs flagged disability-friendly always reviewed; report reason `false_accessibility_claim` |
| Notifications | `company_follow.new_job` (in-app); email `jobalert.followed_companies_digest`; preference `company_follows` |
| Errors | 403 `contact-not-available`; 422 `follow-limit`, `company-blocked` |
| Front end | relevance and best-match orders; "Tanggal posting" filter; "Lowongan serupa" on P3 and its 404; share with Web Share, WhatsApp and copy link; the contact notice on the application detail; disability badge, filter and editor fields; "Ikuti" on the company page and a followed list |
