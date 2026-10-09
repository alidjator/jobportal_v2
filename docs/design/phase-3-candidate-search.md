# Phase 3, step 2: candidate search (R9, designed 2026-10-09)

Companies search job seekers who opted in (`is_discoverable`, see `phase-1-jobseeker.md`). Spec in `decisions.md` (R9, R12 later) and `gap-analysis.md` (R9: candidate list with filters and a view log, table `candidate_cv_views` in the legacy).
Recommendations (R12), invitations (R10) and the matching engine are separate steps that build on this one.

## Confirmed by the user (2026-10-09)

- **Who searches:** every active member of a company that is not suspended, both roles (`company_admin` and `recruiter`). Company members have roles, not permissions, so no new permission is created.
- **What is seen before an application or an accepted invitation:** the name, avatar, headline, summary, city and province, experience, education, skills, languages and availability. **Hidden:** email, phone, WhatsApp, street address and
  the links (social media). Where a company already receives an application from the candidate, the existing rule stays (that company sees the full applicant profile and the attached resume).
- **View log:** every opening of a profile or download of a resume by a member is logged: company, member, candidate, kind (`profile` or `resume`) and time. One entry per member, candidate and kind per Jakarta day.
  The **candidate sees** the list of companies that opened their profile (company and time, never the member's name).
- **Filters:** keyword (headline, summary and job titles of the experiences), skills, languages, province and city, experience level (the one the candidate chose; the computed "fresh graduate" category stays deferred to the matching step),
  highest education level, availability, maximum expected salary, **and** religion, nationality, age (range from the birth date) and gender. The last four are allowed by the earlier decision (`decisions.md`, section 4) but carry a legal risk
  (religion and age based selection); the legal review noted there is still open and must happen before release. Name and contact data cannot be searched.
- **Order:** the most recently updated profile first.
- **Resume:** when the candidate also switched on `resume_discoverable`, a member may download the resume PDF; each download is logged (kind `resume`).
- **Profile identifier:** a permanent random slug (a word plus random digits, never built from the name), unique, not changeable, used only in the company API. No public profile page.

## Details to approve (proposed, not confirmed yet)

- **Context:** the read side lives in `JobSeeker` (it owns the profiles); routes under `company/candidates`; company membership and suspension come from the existing Shared company contracts (as in the other company endpoints). No new bounded context.
- **A candidate is searchable** when `is_discoverable` is on, the account is active (not deactivated, anonymised or suspended) and the profile is complete (the rule used for applying).
- **Endpoints (company):** `GET company/candidates` (cursor or offset paging like `GET /jobs`; the filters above), `GET company/candidates/{slug}` (the profile as above, logs a `profile` view), `GET company/candidates/{slug}/resumes/{id}` (download, needs `resume_discoverable`, logs a `resume` view).
  **Endpoint (job seeker):** `GET me/profile/views` (companies that viewed, newest first, paged).
- **Tables:** `jobseeker_profiles.slug` (migration, backfilled for existing rows, unique, not null) and `jobseeker_profile_views` (profile, company, member, kind, day, created_at; unique per member, profile, kind and day).
- **Rate limit:** per member (searching and opening profiles), a setting `jobseeker.candidate_views_per_day` (default 200 profile opens per member per Jakarta day) so the pool cannot be scraped.
- **Audit:** none beyond the view log (a search is not an export); the log is kept as long as the profile exists and deleted when the account is anonymised.
- **Tests:** visibility (opt-in, inactive account, incomplete profile), hidden contact fields, each filter, the protected-attribute filters, the day limit, the view log (once per day) and what the candidate sees, resume rule, slug uniqueness, erasure.
