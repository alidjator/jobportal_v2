# Gap analysis, round 2

Compares what the API v2 has built (Phases 0 to 4, plus the Phase 5 work already coded) with the practices recorded in
`benchmark-notes-round-2.md` (2026-10-11). Round 1 (`gap-analysis.md`) compared the legacy karir.pkp with the benchmarks and is closed.

**Method.** The v2 side comes from the route list (`php artisan route:list`, 2026-10-11) and the design documents in `docs/design/`; each row
names the document. Plan, billing and paid tiers stay out of scope. TopKarir is left out (nothing verifiable).

**Status.**

| Status | Meaning |
|---|---|
| **Yes** | v2 has an equivalent. |
| **Partial** | v2 has part of it; the missing part is named. |
| **No** | v2 has nothing equivalent. |
| **Differs (decided)** | v2 does it differently because of a recorded user decision; listed so the difference is known, not to reopen it. |

Benchmark codes: JS JobStreet, LI LinkedIn, GL Glints, IN Indeed, KA Kalibrr, KC karir.com, LK loker.id, DE Dealls.

## 1. Round-1 facts that changed

Corrections from round 2 that touch a v2 decision. None of them invalidates a decision; they are recorded so the design documents can be
read with the right reference.

| Round-1 statement | Round 2 | v2 | Effect |
|---|---|---|---|
| JS screening: up to 5 recommended questions | Up to 10 questions; **questions locked after posting** | A question with answers cannot be edited, only archived (`decisions.md` section 2) | Same principle; none |
| GL stages Applied, Connected, ... | Chat Initiated, In Communication, Skill Test, Interviewing, Offer Sent, Hired, Not Suitable; "Stop Receiving Application" when closed | Seven typed stages, renameable (`decisions.md` section 1) | None |
| GL employer: bulk status change (search summary) | Confirmed, same target status only | Bulk move, reject and message, up to 50 (`phase-2-bulk-actions.md`) | None |
| JS job alerts: up to 3 profiles | Up to 10 saved searches; alert is a toggle; at most 20 jobs per email; monthly when the user is inactive | Alerts with immediate, daily or weekly frequency (`phase-3-job-alerts.md`) | See G15 |
| Withdrawal (not covered in round 1) | **Not possible** on JS, LI, GL; possible on IN and KA | Allowed from every active stage (`phase-1-applications.md`) | v2 follows IN and KA |

## 2. Comparison per area

### 2.1 Job seeker profile and onboarding

| # | Practice (benchmarks) | v2 | Status | Evidence |
|---|---|---|---|---|
| P1 | Email verification (all) | Applying needs a verified email | Yes | `phase-1-applications.md`, `architecture.md` |
| P2 | Mobile number verification by code, at sign-up or before applying (KA, IN) | Phone and WhatsApp are stored, never verified | **No** | `phase-1-jobseeker.md` |
| P3 | Social sign-in (JS, IN, LK) | Google | Yes | `POST /auth/google` |
| P4 | Passwordless code or passkeys (JS, IN) | Password only | No | route list |
| P5 | Profile completeness prompt (KA, GL, KC) | `completeness` in `GET /me/profile` | Yes | `phase-1-jobseeker.md` |
| P6 | CV generated from the profile (GL, JS, KC) | CV generator with templates | Yes | `phase-3-cv-generator.md` |
| P7 | Several resumes (JS up to 10) | Several resumes | Yes | `phase-1-jobseeker.md` |
| P8 | Open-to-work / approachability signal (LI, JS, IN) | `availability` values `open_to_work`, `employed_open_to_offers`, ... | Yes | `phase-1-jobseeker.md` |
| P9 | Visibility levels (JS four, IN two, default hidden on IN) | Two opt-in switches `is_discoverable`, `resume_discoverable`, off by default | Yes | `phase-1-jobseeker.md` |
| P10 | Application is a snapshot (JS) | Profile snapshot frozen at applying | Yes | `phase-1-applications.md` |
| P11 | Who viewed my profile (JS, KA) | `GET /me/profile/views` | Yes | route list |
| P12 | Save an application as a draft (IN, 14 days) | None | No | route list |

### 2.2 Notifications

| # | Practice (benchmarks) | v2 | Status | Evidence |
|---|---|---|---|---|
| N1 | Preferences per topic and channel (JS, IN, GL) | `GET/PUT /me/notification-preferences/{key}/{channel}` | Yes | route list |
| N2 | Candidate told when the application is viewed (JS email, LI, IN, KA status) | `viewed_at` visible in the app; **no notification** (user) | Differs (decided) | `phase-5-application-viewed.md` |
| N3 | Candidate told on stage change (KA every default state, IN, GL interview and rejection) | Notified on interview, offer, hired, rejected | Yes | `decisions.md` section 1 |
| N4 | Rejection notice delayed 48 hours (JS) | Immediate (user) | Differs (decided) | `decisions.md` section 1 |
| N5 | Applicants told when the job closes or expires (JS, IN, GL status) | Applications stay unchanged; no notice to applicants | **No** | `phase-1-applications.md` ("Closed or expired job") |
| N6 | Status "response seems unlikely" after two weeks without activity (IN) | None | No | — |
| N7 | Interview reminders (KA: 3 days, 1 day, same day; v2 baseline) | One reminder the day before, to job seeker and interviewer | Partial | `phase-2-interviews.md` |
| N8 | Employer: every application, or a daily or weekly digest, per job and per user (JS) | In-app `application.received` to assignees; no email, no digest | Partial | `phase-1-applications.md` |
| N9 | Fewer alert emails for inactive users (JS) | Fixed schedule, immediate cap | No | `phase-3-job-alerts.md` |
| N10 | Push to the mobile app (GL, IN, JS) | Device tokens stored, nothing sent (deferred by the user) | Differs (decided) | `decisions.md` ("Deferred ... FCM push") |

### 2.3 Admin and moderation

| # | Practice (benchmarks) | v2 | Status | Evidence |
|---|---|---|---|---|
| M1 | Review before publishing: first post of a new employer (JS), every post (KA, LK, KC), until verified (GL), sampled (LI) | Optional: `jobs.require_approval`, **default off**, global or per company; core-field edits go back to review | Partial | `phase-1-jobs.md` |
| M2 | Automated wording checks holding a post (JS: discrimination, sexual words, high-risk countries; LI) | None | No | — |
| M3 | Reason shown on rejection (KA); appeal (LI) | Reject and take-down carry a reason; no appeal flow | Partial | `phase-1-jobs.md` |
| M4 | Graduated enforcement: remove job, suspend posting, suspend company (LI, GL, KC) | Take down a job, suspend a company | Yes | route list |
| M5 | Messaging frozen while an employer is under review (IN) | Suspended companies lose access; no partial freeze | Partial | `phase-1-company.md` |
| M6 | Review moderation with reports (JS, IN) | Admin moderation of reviews and replies; reports from job seekers and companies | Yes | `phase-4-company-reviews.md` |
| M7 | Photo moderation (JS, IN) | Avatar checked and re-encoded, not moderated; other users never see it (companies through applications) | Partial | `phase-1-jobseeker.md` |

### 2.4 Trust and safety

| # | Practice (benchmarks) | v2 | Status | Evidence |
|---|---|---|---|---|
| T1 | **"Report job"** by job seekers with reasons (all eight platforms) | None; only reviews can be reported | **No** | route list |
| T2 | Report a candidate (GL) or a message as spam (IN) | None in chat; daily message cap and throttle exist | **No** | `phase-2-chat.md` |
| T3 | Block named companies from finding the profile (IN up to 100, KA, KC) | Only the global discoverability switch | **No** | `phase-1-jobseeker.md` |
| T4 | Contact alias instead of the real email (IN) | Email in the snapshot is visible to the company | No | `phase-1-applications.md` |
| T5 | No fees from candidates, stated for employers (GL, KC, DE) | Legal documents are admin-managed content | Content | `legal-review.md`, legal documents |
| T6 | Scam education pages, police guidance (GL, JS, IN, LI) | FAQ is admin-managed content | Content | `GET /faq` |
| T7 | Sensitive data kept out of what employers see (IN removed fields; JS scans uploads) | Snapshot excludes gender, religion, marital status, nationality; sensitive search filters off by default; no upload scanning | Partial | `phase-1-applications.md`, `HANDOFF.md` |
| T8 | Identity or right-to-work check when abuse is suspected (JS) | Daily application limit only | Partial | `phase-1-applications.md` |
| T9 | Report rate limits (JS daily flag limit) | Throttles on public endpoints | Yes | `phase-5-security-review.md` |

### 2.5 Company verification and onboarding

| # | Practice (benchmarks) | v2 | Status | Evidence |
|---|---|---|---|---|
| C1 | Company self-registration (JS, GL, KA, LK, KC) | Created by an admin (R1) | Differs (decided) | `decisions.md` R1 |
| C2 | Legal documents NPWP/NIB/SIUP (JS, GL, KC, LK) | Admin-managed document types, seeded NIB and NPWP required, deed optional | Yes | `phase-1-company.md` slice 6 |
| C3 | Verified badge on company page and job (GL, LI, KA) | `verified_at` set automatically when every required document is approved | Yes | `phase-1-company.md` slice 6 |
| C4 | **Verification as a gate** before the first job goes live (JS, GL "In Review", KC, LK) | Badge only; an unverified company can publish when approval is off | Partial | `phase-1-company.md`, `phase-1-jobs.md` |
| C5 | Key company fields locked after verification; change through support (JS, GL) | A company admin can change the name at any time (`PATCH /company/profile`); the change does not touch `verified_at`, so **a verified company keeps its badge under a new name** | **No** | `UpdateCompanyProfileRequest`, `UpdateCompanyProfile` (code, 2026-10-11) |
| C6 | Team roles and per-job access (JS, GL, KA) | Company admin, recruiter, per-job assignment | Yes | `decisions.md` R1 |
| C7 | Interviewer role (KA) | Interviewer is a person on the interview, not a role | Partial | `phase-2-interviews.md` |
| C8 | MFA for employer users (JS adaptive, GL new device, IN mandatory) | TOTP for platform admins only; company members have none | **No** | `phase-5-security-review.md` |

### 2.6 Account and privacy

| # | Practice (benchmarks) | v2 | Status | Evidence |
|---|---|---|---|---|
| A1 | Self-service account deletion (JS, GL, KA) | Anonymisation with a 30-day grace period and restore | Yes | `decisions.md`, `POST /me/deletion`, `POST /auth/account/restore` |
| A2 | Download my data (LI, IN; access and portability rights in the DE notice under the Indonesian data protection law) | None | **No** | route list |
| A3 | Withdraw an application (IN, KA) | Yes, from every active stage | Yes | `phase-1-applications.md` |
| A4 | Review authors anonymous (JS, IN; all reviews anonymous) | Author name always shown (user) | Differs (decided) | `decisions.md` J10b |
| A5 | Who may review: past or present employees (JS, IN) | Job seekers who applied to the company | Differs (decided) | `decisions.md` J10b |

### 2.7 Recruiter workflow (extra, from the round-2 employer articles)

| # | Practice (benchmarks) | v2 | Status | Evidence |
|---|---|---|---|---|
| W1 | Must-have screening with optional auto-reject (JS) | Must-have is mark-only, no automatic rejection (user) | Differs (decided) | `decisions.md` section 2 |
| W2 | Rating and notes, shared with the team (JS, KA with @mentions) | Rating and notes per application | Yes | route list |
| W3 | Previous applications to the company on the candidate card (JS) | Not in the company application resource | No | `CompanyApplicationResource` (code, 2026-10-11) |
| W4 | Interview self-scheduling or calendar integration (DE Google Calendar, KA scheduler) | Schedule, propose, confirm, decline | Partial | `phase-2-interviews.md` |
| W5 | Reference checks (JS) | None | No | — |
| W6 | Job post performance and funnel analytics, source, speed (JS, KA) | Funnel and speed, source, rejection reasons, per job performance | Yes | `phase-4-business-intelligence.md` |
| W7 | Candidate experience survey (KA) | None | No | — |
| W8 | Response rate shown to candidates (KA) | None | No | — |
| W9 | Job syndication to Google, LinkedIn, Indeed (KA, GL) | None | No | — |

## 3. Candidate gaps

The rows marked **No** or **Partial**, grouped. None is scheduled; each needs the user's decision on scope before any design work. Items in
bold are on every or nearly every benchmark.

| Id | Gap | Rows | Seen on |
|---|---|---|---|
| G1 | **Job seekers report a job** (reasons, admin queue, take-down) | T1 | all |
| G2 | Report a candidate or a chat message; block a conversation | T2 | GL, IN |
| G3 | Hide the profile from named companies | T3 | IN, KA, KC |
| G4 | **MFA for company members** | C8 | JS, GL, IN |
| G5 | Mobile number verification | P2 | KA, IN |
| G6 | **Company verification as a gate** for publishing (or approval on until verified) | C4, M1 | JS, GL, KC, LK |
| G7 | Automated wording checks on job posts | M2 | JS, LI |
| G8 | Appeal of a rejected or taken-down job | M3 | LI |
| G9 | Notice to applicants when a job closes or expires | N5 | JS, IN, GL |
| G10 | Automatic "response unlikely" status after inactivity | N6 | IN |
| G11 | Employer email for new applications, with digest | N8 | JS |
| G12 | More interview reminders; self-scheduling or calendar | N7, W4 | KA, DE |
| G13 | Download my data | A2 | LI, IN (DE notice) |
| G14 | Contact alias for candidate email | T4 | IN |
| G15 | Alert emails reduced for inactive users | N9 | JS |
| G16 | Draft applications | P12 | IN |
| G17 | Reference checks; candidate experience survey; response rate on jobs; job syndication | W5, W7, W8, W9 | JS, KA, GL |
| G18 | Passwordless sign-in or passkeys | P4 | JS, IN |
| G19 | Avatar moderation; sensitive-data scan of uploads | M7, T7 | JS, IN |
| G20 | **Renaming a verified company keeps the badge** (lock the name after verification, or withdraw the badge on rename) | C5 | JS, GL |
| G21 | Previous applications to the same company on the candidate card | W3 | JS |

**Content tasks, not code:** a rule that employers may not charge candidates (T5) and scam guidance pages (T6) can be written into the
legal documents and the FAQ the admins already manage; they fit the legal review in `docs/legal-review.md`.

**Finding for the user (trust, 2026-10-11):** G20 is not only a missing feature. Today a company that passed document verification can change
its public name and keep the badge, so the badge no longer proves that the shown name was checked. Reported for a decision; nothing changed.

## 4. Differences kept by decision

Listed for awareness only; each was decided by the user and is not reopened here: N2 (no "viewed" notification), N4 (immediate rejection
notice), N10 (push deferred), C1 (admin-created companies), A4 and A5 (review author shown; only applicants review), W1 (no auto-reject).
A4 is the only one that no benchmark shares (every platform read keeps reviews anonymous); the risk was already reported in `decisions.md` J10b.
