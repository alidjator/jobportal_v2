# Phase 3, step 4: matching engine (J8 and R12, designed 2026-10-09)

One scoring engine, used in both directions. J8: jobs recommended to a job seeker; R12: candidates recommended to a job. Spec in `decisions.md` (J8, R12: simple weighted rules, no machine learning) and `gap-analysis.md`.
It also settles the "fresh graduate" category deferred in `decisions.md` (recorded 2026-10-07).

## Confirmed by the user (2026-10-09)

- **Fresh graduate is computed from the history**, not chosen. The level the job seeker picks in the profile (`experience_level_id`) stays as it is and is not used for matching.
- **Signals:** skills, experience and education, location and work mode, expected salary and role.
- **Weights (total 100):** skills 40, location and work mode 20, experience 15, education 10, salary 10, role and title 5. A signal that cannot be judged (for example a job with no skills) is left out and the score is
  normalised over the signals that remain.
- **Threshold and size:** only scores of **40% and above** are recommended; the lists hold **20** entries. The percentage is still shown on job lists and details below 40%.
- **Surfaces in this step:** (1) recommended jobs for the job seeker (dashboard), (2) the match percentage on the job list and the job detail for a logged-in job seeker, (3) recommended candidates for a job (R12, the invite button is the
  existing invitation endpoint), (4) a periodic email: **every Monday 08:00 Jakarta**, on by default, switched off by a new notification preference `recommendation` (email), sent only to a verified address.

## Details to approve (proposed, not confirmed yet)

- **Context:** new bounded context `Matching` (`src/Matching`, table prefix `matching_`). The scoring is a pure domain service (`MatchScorer`) over two plain value objects, `CandidateSignals` and `JobSignals`. The data comes through Shared contracts:
  JobSeeker provides `CandidateSignals` (skill ids, computed experience, highest education rank, location, expected salary with period, experience titles and headline), Job provides `JobSignals` (skill ids, level range, minimum education,
  location, work mode, salary range, role name and title). Matching reads no other context's tables.
- **Computed experience (JobSeeker):** months are summed over the experience entries with overlapping periods counted once (an open entry ends today); `years = months / 12`.
  **Fresh graduate** = less than 12 months of experience **and** (an education entry that is still current, or whose end year is this year or last year). A job seeker with neither is not a fresh graduate.
  The two masters are told apart by this rule: **"Fresh Graduated"** is satisfied fully by a fresh graduate, half by someone with under 12 months of experience who is not one, and not at all by anyone else;
  **"Less than a Year"** and the other levels use the range test below, which a fresh graduate also passes.
- **Scoring of each signal (a fraction from 0 to 1):**
  - *Skills:* the share of the job's skills the candidate has. Left out when the job lists none.
  - *Location and work mode:* a remote job is 1; the same city 1; the same province 0.5; otherwise 0. Left out when the job has no location and is not remote.
  - *Experience:* by the job's level range (`min_years`, `max_years`): inside it, or above it, is 1 (being over-qualified is not penalised); below it is `years / min_years`. Left out when the job has no level.
  - *Education:* the candidate's highest level at or above the job's minimum is 1, one rank below 0.5, lower 0. Left out when the job has no minimum.
  - *Salary:* left out when the job hides or lacks a salary, the candidate has no expectation, or the periods differ. Expectation at or under the job's maximum is 1, up to 20% over it 0.5, more 0.
  - *Role and title:* the words of the job's role name and title against the words of the candidate's experience titles and headline (case-insensitive, words of three letters or more): at least half of them found is 1, any found 0.5, none 0.
  - *Score:* `round(100 x sum(weight x fraction) / sum(weights of the signals used))`. When the signals used weigh less than 40 in total, the job has no percentage (`null`) and is never recommended.
- **Where the job pool comes from (J8):** live jobs that are remote or in the candidate's province, newest first, at most 300, scored in PHP; the top 20 at 40% or more are returned. Jobs the candidate has already applied to or been invited to are left out.
- **Where the candidate pool comes from (R12):** the candidates the search already considers searchable, in the job's province (any province when the job is remote), most recently updated first, at most 500, scored in PHP; the top 20 at 40% or more.
  Candidates who already applied are left out; an invited candidate stays and carries `invited: true`. Contact data stays hidden exactly as in candidate search. Opening the list is not logged (only opening a profile is).
- **Endpoints:** `GET me/recommendations` (job seeker; the same job card as `GET /jobs` plus `match`), `GET company/jobs/{id}/candidates` (a member who may see the job; the same candidate row as `GET company/candidates` plus `match`, `invited`; rate limited like
  the candidate search), and a `match: {percent}` field on `GET /jobs` rows and `GET /jobs/{id}` when the caller is a logged-in job seeker with a complete profile (the detail also gets `breakdown`, the fraction of each signal as 0 to 100). These public routes take
  the token optionally; a response that depends on the caller must not be cached by a shared cache (`Cache-Control: private`).
- **Email:** command `matching:send-recommendations` (scheduled Monday 08:00 Jakarta, queue `bulk`, the command only queues the first batch; each queued batch handles 200 accounts and queues the next): up to 5 best jobs the person was **not sent before**, nothing when there are none; template `matching.recommendations`
  (titles and the percentage; no link, as no client page exists yet). Table `matching_deliveries` (user, job; unique) remembers what was sent. Deactivated, anonymised and suspended accounts, incomplete profiles and unverified addresses are skipped;
  anonymising the account deletes its rows.
- **No new setting and no new permission.** Weights, threshold and sizes are constants of the domain (a change is a code change with a test). **Not in this step:** in-app notifications for recommendations, ordering the public search by match,
  and recommending jobs to visitors who are not logged in.
- **Tests:** each signal with and without data, normalisation, the threshold, the fresh graduate rule (both masters), overlapping experience, the two pools and their exclusions, visibility and hidden contact fields, `match` on the public list and detail,
  access by role and assignment, the email (once per job, preference, verified address, schedule), erasure.

## Coded (2026-10-09, checked with 1635 tests, committed)

Context `src/Matching` (provider `MatchingServiceProvider`, in `bootstrap/providers.php`; table `matching_deliveries`, migration `000092`), the Shared contracts in `src/Shared/Application/Matching` (`CandidateSignals`, `JobSignals`, `MatchScore`, `MatchScoring`, `CandidateSignalSource`, `JobSignalSource`)
and `Shared/Application/JobInvitation/InvitedCandidates`, new methods `ApplicantRelations::appliedJobIds` and `appliedUserIds`. Tests: `tests/Unit/Matching/MatchScorerTest.php`, `tests/Unit/JobSeeker/Domain/CareerSummaryTest.php`, `tests/Feature/Matching/RecommendationsTest.php`;
`EmailTemplatesTest` and `NotificationPreferencesTest` list the new template and preference. The proposed details were coded as written; what was decided while coding:

- **Who owns what:** `Matching` holds the scorer (`MatchScorer`, weights in `MatchSignal`) and the weekly email; it has no routes. The endpoints sit with the data they list, as for job alerts: `GET me/recommendations` and the `match` on `GET /jobs`, `GET /jobs/{id}` and `GET me/job-alerts/{id}/jobs`
  are Job (`RecommendJobs`, `ScoreJobsForSeeker`, `JobPool`, `JobSignalProvider`), `GET company/jobs/{id}/candidates` is JobSeeker (`RecommendCandidates`, `CandidateSignalSource` implemented by `DatabaseCandidateSignalSource`).
- **Experience and fresh graduate** are computed by `JobSeeker\Domain\CareerSummary` (months of each period counted once, both end months included). The level "Fresh Graduated" is found by its master slug `fresh-graduated` (generated from the English name by the seeder).
- **The job pool reuses the public search reader** (one query for the province, one for remote jobs, 100 rows a page up to 300 each, merged newest first), so what is live is what the search shows. A profile counts as complete by the same query as the candidate search (`CompleteProfiles`).
- **`match` on the public routes:** `{percent}` on list rows; the page also gets `signals`. The key is absent for anonymous callers, company members and job seekers with an incomplete profile. The routes use the new middleware `PublicCacheHeaders` (public cache without a token; `private, no-store` with one; `Vary: Authorization` both ways).
- **Recommended candidates** (`GET company/jobs/{id}/candidates`): the row of the candidate search plus `match {percent, signals}` and `invited`; not paged (the 20 best); `404` for a job the member cannot see, `422 job-not-live` for a job that is not live; same route limiter as the candidate search.
- **The email:** command `matching:send-recommendations`, Monday 08:00 Jakarta; a person who was sent one in the last 6 days is skipped, so a second run does nothing; the recommendation line reads "Title (82% match)" in both languages. Every active job seeker with a complete profile is walked in batches of 200, each batch reads contacts, preferences, earlier emails, applications and invitations once, and loads the pool once per province;
  that is fine for a few thousand job seekers and the first thing to rework (a queue job per batch) if it grows.
