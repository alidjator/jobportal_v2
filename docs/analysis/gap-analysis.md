# Gap analysis (step 3)

Compares the legacy web application flows (`legacy-flow-map.md`) with the benchmark platforms (`benchmark-notes.md`),
checked against the **production schema** read on 2026-10-03 (125 tables, schema only, no data).
Status: **Yes / Partial / No**. "Not verified" means the benchmarks do not state it in the sources read.
Plan, billing and featured/highlight are out of scope.

## Job seeker

| # | Flow | Benchmarks | karir.pkp today | Status | Production schema |
|---|---|---|---|---|---|
| J1 | Apply | One-tap (Glints), 1-click (karir.com), quick apply (loker.id), Easy Apply (LinkedIn) | Apply modal: resume + cover letter, profile must be complete | Partial | `applied_jobs` has no unique (candidate, job), no status or withdrawn marker |
| J2 | Application tracking | All | "Applied Jobs" shows stage name | Partial | `application_groups` exists; no history, no "viewed by recruiter" (viewed: coded 2026-10-11, `viewed_at`, finding 17) |
| J3 | Notification on stage change | Glints, LinkedIn (viewed, resume downloaded) | None | No | `notifications` exists; no such type |
| J4 | Standard stages | Glints and JobStreet have fixed stages | Free-form per company | Partial | Only `application_groups(name, order, is_deleteable)` |
| J5 | Job alerts | Multi-criteria, daily/weekly, email/app (JobStreet, LinkedIn) | One criterion: role | Partial | `candidate_job_alerts` has only `job_role_id` |
| J6 | Saved jobs / saved searches | Saved jobs (JobStreet, LinkedIn), saved searches (JobStreet) | Favorite jobs only | Partial | `bookmark_candidate_job` exists; no saved-search table |
| J7 | Profile and CV | CV generator (karir.com) | Rich profile, several PDF resumes; no generator | Partial | Profile tables exist |
| J8 | Job recommendations | Profile matching (karir.com), matching notifications (Glints) | None | No | None |
| J9 | Chat with recruiter | Glints (plus online status) | Messenger (real time); no attachments or presence | Partial | `messengers`, `messenger_users` |
| J10 | Salary and company info | Salary reports (JobStreet), salary benchmark (karir.com); reviews not verified | Blog and company page only | Partial | None for salary data or reviews |
| J11 | Account and privacy | Not verified | Email/password change, visibility, close account, Google login | Yes | `users`, `candidates.visibility`, `cv_visibility` |

## Recruiter

| # | Flow | Benchmarks | karir.pkp today | Status | Production schema |
|---|---|---|---|---|---|
| R1 | Company onboarding / team | JobStreet: team members with roles, per-ad invites; loker.id: public company registration page | Admin-created accounts; one user per company; document verification not in menu | No | `companies.user_id`; `is_profile_verified`, `document_verified_at` |
| R2 | Post a job | All | Full form | Yes | `jobs` |
| R3 | Screening questions | Types and must-have (LinkedIn), recommended set (JobStreet), auto filter (loker.id) | Text + required flag; answers not stored | Partial (broken) | `company_questions(title, required)`; `applied_jobs.answers` exists but is never written |
| R4 | Pipeline | Fixed stages (Glints, JobStreet), rating/filter (LinkedIn), ATS (loker.id) | Kanban with custom stages | Partial | `application_groups`, `applied_jobs.application_group_id/order` |
| R5 | Bulk actions | Bulk status change (Glints, JobStreet) | None | No | No table needed |
| R6 | Rejection | Rejection flow (Glints), automatic email (LinkedIn), 48-hour notice (JobStreet) | None | No | `company_applied_job_rejected` exists but is unused |
| R7 | Notes, rating, interviews, offers | Rating (LinkedIn), Interviewing/Offer stages (Glints, JobStreet) | None | No | No tables |
| R8 | Skill test | Skill Test stage (Glints), online test (karir.com) | None | No | None |
| R9 | Candidate search | Talent search (JobStreet, loker.id), resume search (karir.com) | Candidate list with filters, CV-view log | Yes | `candidate_cv_views` |
| R10 | Invite candidates | Job Invitation (loker.id), invite to apply (JobStreet) | Direct message only | Partial | No invitation table |
| R11 | Talent pool | Talent Pool (loker.id) | Saved candidates with categories | Yes | `bookmark_company`, `company_bookmark_categories` |
| R12 | Candidate recommendations | Candidate Matches (Glints, JobStreet) | None | No | None |
| R13 | Applicant export | CSV (loker.id) | Admin-only candidate export | No | No table needed |
| R14 | Company page | Employer branding (karir.com) | Public company profile | Yes | `companies` |
| R15 | Campaigns and source attribution | None verified | Links, QR, attribution | Own strength | `campaigns` |

## Scope decisions

All gaps above are in scope for the project (see `docs/design/decisions.md`); J2, J3, J4, R3, R4 and R6 are covered by the
stage-model and screening decisions, and J6 is merged into J5.
