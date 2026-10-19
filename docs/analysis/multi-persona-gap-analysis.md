# Multi-persona feature gap analysis of the front-end design (2026-10-20)

A review of the front-end design by six personas, done separately for each actor, and for every **paired feature** (a feature that two or more
actors meet on) from the side of each actor involved. It runs in four turns: section 1 (visitor and job seeker), section 2 (company side), section 3
(psychologists and platform admin), sections 4 and 5 (paired features and synthesis).

**Personas and their lens.**

| Code | Persona | Looks at |
|---|---|---|
| BA | Senior Business Analyst | Stakeholder needs, business rules, business value, parity with the benchmarks, success metrics |
| SA | Senior System Analyst | Flow completeness, status and state transitions, edge cases, data consistency across actors |
| HR | Senior HR Specialist | Fit with real recruitment practice, candidate and recruiter experience, compliance |
| FE | Senior Front-end Developer | Screens, components, UI states (empty, loading, error), validation, responsiveness, accessibility |
| BE | Senior Back-end Developer | Endpoint per page, response fields the UI needs, authorization per actor, validation parity, events and notifications, pagination, idempotency |
| DBA | Senior Database Administrator | Tables and columns behind each feature, integrity, concurrency, indexes, history and audit, retention, data carried from the karir.pkp import |

**Sources.** `docs/design/frontend.md` (page maps 7 to 9) and the phase 8 designs it names; `php artisan route:list --path=api/v1` (2026-10-20);
`docs/api`; the schema (`phase-0-schema.md`, `cross-context-foreign-keys.md`, `database/migrations`); the import mapping (`phase-1-migration.md`); a
few code reads, named where used. Plans, billing, payments, orders and invoices stay out of scope.

**Earlier rounds are not repeated.** `actor-gap-analysis.md` (FG1 to FG18), `frontend-gap-analysis.md` (FEG1 to FEG15) and
`gap-analysis-round-2.md` (G1 to G21) already cover the functional and page-map gaps. This round adds what the new lenses (BA, BE, DBA) and the
imported data bring; an older gap is only referred to by its id.

**Priority** (this document; it differs from the earlier rounds, where P1 meant "before go-live"):

| Priority | Meaning |
|---|---|
| P1 | A main flow is broken or cannot be finished |
| P2 | A supporting feature is missing |
| P3 | An improvement |

**Status API:** Exists (endpoint or field coded) | Designed (in a design document, not coded) | Missing (neither).
**Status data:** Exists (table and column present) | Needs schema (new table, column, constraint or index) | Legacy empty (the schema is there,
but the karir.pkp import does not fill it).

**Ids:** MG-A (section 1), MG-B (section 2), MG-C (section 3), MG-P (section 4).

**Decisions:** 2026-10-20, the user answered all 17 open questions; the record is section 5.6.

## 1. Visitor and job seeker

### 1.1 Visitor (not signed in)

**Present in the design.** Search with filters and relevance order (P2), job detail with structured data, salary insight, similar jobs, share and
report (P3), company profile with reviews (P4), salary page (P5), training (P6, P6b after import), campaign landing (P7), legal documents (P8), FAQ (P9),
newsletter (P10), report verification (P14, #23), the manual-candidate invitation (P15, #31), the announcement banner, the SEO rules of 11.4 (titles,
`JobPosting`, sitemap, `robots.txt`, `hreflang`). Every master the filters need has a public, cached endpoint.

**Gaps.**

| ID | Actor | Feature | Gap | Persona | Business impact | Status API | Status data | Evidence | Priority |
|---|---|---|---|---|---|---|---|---|---|
| MG-A01 | Visitor | Sitemap | The sitemap is "rebuilt from the API on request" (11.4), but the only job source is the search endpoint `GET jobs` (paged, with relevance and filters), and `PublicJobResource` has no `updated_at` for `<lastmod>`. Companies come from the P12 endpoint, which is **frozen**, so company pages (P4) have no sitemap source at go-live. | BE, BA | Slower indexing of new jobs and no indexing of company pages: less organic traffic, which is the main free acquisition channel. | Missing (a light feed: id, slug, `updated_at`, for live jobs and visible companies) | Exists | `frontend.md:722`; `PublicJobResource.php` (no `updated_at`); P12 marked frozen | P2 |
| MG-A02 | Visitor | Conversion measurement and cookie notice | No product analytics is designed: nothing measures the funnel visit → register → verify → apply, nor the source of a sign-up beyond the campaign code and `source=shared`. If an analytics tool is added, the design has no cookie or tracking notice (UU PDP). | BA, HR, FE | No success metric for go-live; no way to tell whether the new portal performs better than karir.pkp. | Missing | Exists (job views and sources are stored) | `grep analytics\|cookie` in `frontend.md`: nothing | P2 (ASSUMPTION: no analytics tool was chosen outside the design) |
| MG-A03 | Visitor | Help and contact | A visitor (an employer who wants to post jobs, or a job seeker who cannot sign in) has no way to reach the platform except FAQ and the static P13. Already **FG7**; listed here because the cases that need it most are the imported accounts of MG-A06 and MG-A09. | BA, HR | Imported users who are stuck have no channel; support load lands on the companies. | Missing (FG7) | — | FG7 | P2 |

### 1.2 Job seeker

**Present in the design.** Account (A1 to A6, two-factor, terms gate, restore), profile with address picker (J2), resumes and CV generator (J3),
apply with screening, consent and the #20 apply state (J4), applications with stage history, interview card with reschedule and no-show,
withdraw and decline reasons, hired state (J5), video room (J6), assessments with the runner (J7), invitations (J8), saved jobs (J9), alerts (J10),
messages with block and report (J11), notifications and preferences (J12), reviews (J13), training (J14), settings with two-factor, data export,
sign-out everywhere and deletion (J15), agenda (J16, #15), offer page (J17). The designed items (#12, #14, #15, #20, #25, #31, #32, #33) are marked
in the page map. The open functional gaps of earlier rounds remain: FG4 (test problem report), FG5 (pre-boarding), FG7 (help), FG18 (report outcome).

**Gaps.**

| ID | Actor | Feature | Gap | Persona | Business impact | Status API | Status data | Evidence | Priority |
|---|---|---|---|---|---|---|---|---|---|
| MG-A04 | Job seeker | Discoverability switches | `PATCH me/profile` accepts `is_discoverable` and `resume_discoverable`, but no page of `frontend.md` names them (J2 has only the #25 "open to offers" switch). Every imported profile starts with both **off** (opt-in), so without the switches the whole legacy base stays invisible to candidate search and invitations. FEG9 did not list them. | FE, BE, DBA, BA | The talent database, the main asset carried from karir.pkp, cannot be searched by companies; sourcing (search, invitations, campaigns) starts empty. | Exists | Legacy empty (both columns `false` for imported rows) | `UpdateMyProfileRequest.php:43-44`; `phase-1-migration.md:66`; `frontend.md` J2, J15 | P1 |
| MG-A05 | Job seeker | Change the sign-in email | There is no endpoint to change the account email (no `me/email`); karir.pkp had it (`legacy-flow-map.md:18`) and every benchmark has it with a code sent to the new address. Imported accounts keep the email they used years ago (often a school or former employer address). | BA, HR, SA, BE | A user who lost the old mailbox cannot reset the password (the reset code goes there) and cannot be reached by companies: a dead account and a lost candidate. | Missing | Exists (`identity_users.email`, unique) | Route list (no email change); `benchmark-notes-round-2.md:121, 253, 391` | P1 |
| MG-A06 | Job seeker | First sign-in of an imported account | Nothing in the design greets an imported user or tells them what did and did not move: password missing when the legacy hash was not bcrypt (reset needed), terms to accept again, resumes skipped by the new file rules, the single `candidates.cv` not migrated, skills and languages that matched no master, language level set to `conversational` for all, discoverability off (MG-A04), and the items of MG-A07. The import report holds legacy ids and reason codes only, but with `legacyimport_id_map` a per-user list can be derived. | HR, BA, FE, DBA | Imported users find an incomplete profile with no explanation; they lose trust or apply with a weaker profile. Go-live is their first impression of the new brand. | Missing (a "what changed" read model or a one-time notice built at import) | Legacy empty (skipped items exist only in the JSON report) | `phase-1-migration.md:56-70, 102-108, 125, 195`; `identity:send-imported-password-resets` | P1 |
| MG-A07 | Job seeker | Saved jobs, alert opt-in and chat history of imported users | karir.pkp had bookmarks, job-alert roles with opt-in, and a messenger with recruiters; there is no importer for any of them (importers exist for accounts, companies, profiles, jobs, screening, applications, campaigns and newsletter only). After go-live J9, J10 and J11 are empty, and conversations open at the cut-over are lost on both sides. | HR, DBA, BA | Candidates in the middle of a recruitment lose the recruiter's last messages; alert users stop receiving jobs (lower return visits). | Exists (target endpoints) | Legacy empty (no importer) | `legacy-flow-map.md:18, 25, 45`; `src/LegacyImport/Infrastructure` (no saved-job, alert or message writer) | P2 |
| MG-A08 | Job seeker | Who withdrew an application | `MyApplicationDetailResource::withdrawal()` returns `by: recruiter` only for the recruiter and **`applicant` for every other actor**, the `system` actor included. The import withdraws older duplicate applications as the system, so J5 would tell the candidate "you withdrew" for something they never did; any later system withdrawal would do the same. | BE, SA, HR | A false statement on the candidate's own record; confusing and, under UU PDP, an inaccurate record of the person. | Exists (needs `system` as a value) | Exists (`actor_kind` already stores `system`) | `MyApplicationDetailResource.php:86`; `StageMoveActor` enum; `phase-1-migration.md:84` | P2 |
| MG-A09 | Job seeker | Imported applications on J5 | An imported application has a two-entry history and a snapshot marked `migrated`, but the job seeker resources expose no `migrated` flag, so J5 shows a thin timeline (applied, then a jump to the current stage at the import time) with no explanation, and the "viewed" time is empty. | FE, BE, HR | Candidates think the company skipped steps or that data was lost. | Missing (flag on the application resources) | Exists (the snapshot carries `migrated` and `migrated_at`) | `phase-1-migration.md:81-88, 276-278`; `grep migrated` in `My*Resource.php`: nothing | P3 |
| MG-A10 | Job seeker | Job content after the job closes | `GET jobs/{job}` answers 404 once a job is not live (rule 11.4), and the application resources carry only the job id and title. A candidate who is still in the process (interview, test, offer) cannot read the job description they applied for, and the job title on J5 links to a 404. | HR, SA, BE | Candidates prepare for interviews from the job description; the offer is compared with what was advertised. Loss of trust and more chat questions to recruiters. | Missing (job content readable by its applicants, or kept in the snapshot) | Exists (the job row stays) | `frontend.md:721`; `MyApplicationDetailResource.php:30` | P2 |
| MG-A11 | Job seeker | Language of emails | The account `locale` is set at registration (imported accounts: `id`) and no endpoint changes it; the interface language follows a cookie (11.1). A user who switches the site to English keeps receiving Indonesian emails and notifications. | BE, FE | Inconsistent experience for English-speaking users (foreign nationals, international companies' candidates). | Missing (`PATCH me` with `locale`, or the cookie change written back) | Exists (`identity_users.locale`) | `User.php:26`; route list (no `PATCH me`); `frontend.md:245` | P3 |
| MG-A12 | Job seeker | Partial addresses of imported profiles | Imported locations are cut at the deepest valid BPS level (often province or city only). The shared address picker (11.7) must open with a partial chain and must not force the lower levels on an unrelated save, or editing the headline would fail on the address. | FE, DBA | Imported users blocked from saving their profile. | Exists | Legacy empty (lower levels empty) | `phase-1-migration.md:105`; `UpdateMyProfileRequest.php:35-39` (all `sometimes`); `frontend.md` 11.7 | P3 (ASSUMPTION: 11.7 does not say how a partial chain is shown) |
| MG-A13 | Job seeker | Active sessions | Only "sign out everywhere" exists; no list of signed-in devices with their last use, and no notice of a new sign-in. | BE, FE | Lower account security for accounts that hold CVs and identity data. | Missing | Exists (`personal_access_tokens` keeps `last_used_at`) | J15; route list | P3 |
| MG-A14 | Job seeker | Retention notice | The company side shows `retention_due_on` on an application (C7); the job seeker side shows nothing about how long the company keeps the application and when it is deleted. | HR, BA | Transparency asked by UU PDP; fewer support questions after a deletion. | Designed for the company side (#26) | Exists | `frontend.md` C7; J5 | P3 |

### 1.3 Candidate paired features (analysed in turn 4)

| Candidate | Actors | Origin |
|---|---|---|
| Discoverability and candidate search / invitations | job seeker, recruiter | MG-A04 |
| Email change and the company's view of the candidate's contact, chat and notifications | job seeker, recruiter, platform admin (support) | MG-A05 |
| Activation of imported accounts (password reset batch, terms gate, welcome notice) | platform admin, system, job seeker | MG-A06 |
| Conversations open at the cut-over | job seeker, recruiter | MG-A07 |
| Withdrawal by the candidate, by the team, by the system | job seeker, recruiter, system | MG-A08 |
| Job closing, expiring or taken down and the applicants still in process | recruiter, platform admin, system, job seeker | MG-A10, #15 |
| Job report, review report and chat report and their outcome | job seeker, company admin, platform admin | FG18 |
| Company reviews and their moderation | job seeker, company admin, platform admin | P4, J13 |
| Job invitations to apply | recruiter, job seeker | J8 |
| Manual candidate claim | recruiter, job seeker | P15, #31 |
| Talent pool consent and its withdrawal | job seeker, recruiter, system (nightly clearing) | J4, J5 |
| Job alerts and new jobs | job seeker, recruiter (publishing), system | J10 |
| Account deletion and the applications the companies hold | job seeker, recruiter, system | J15, #26 |
| Announcements | platform admin, every user | 11.1 |
| Psychological report verification | visitor, psychologist | P14, #23 |
| Interview, assessment and offer flows | recruiter, interviewer, psychologist, job seeker, system | J5 to J7, J17 (also in the earlier rounds) |

### 1.4 Open questions

1. **MG-A07:** import saved jobs, alert opt-ins and the chat history from karir.pkp (new importers), or announce before the freeze that they
   are not carried?
2. **MG-A05:** may a job seeker change the sign-in email themself (code to the new address)? And for a user who lost the old mailbox, is there an
   assisted path through the platform admin with an identity check, or is a new account the answer?
3. **MG-A10:** should a candidate read the job content after the job closes: a copy in the application snapshot taken at apply, or a read of the
   live job allowed to its applicants?
4. **MG-A02:** is a product analytics tool planned (and which), so the cookie notice and the funnel events can be designed?
5. **MG-A09:** should the candidate see an "imported from karir.pkp" label on old applications?

## 2. Company side

The back office (`frontend.md` section 8) serves every company and group member; menus and the landing page follow the 29 company permissions and
the group permissions (8.0). **Fact that shapes most of this section:** the import makes each legacy company owner the **only** member of the
company, as `company_admin`, creator and only assignee of every imported job (`phase-1-migration.md:111-113, 122`); team members, notes, ratings,
saved candidates, screening answers and the CV-view log are not carried. The open gaps of earlier rounds remain: FG8 (integration API), FG10
(employee referral), FG11 (internal postings), FG13 (requisition fulfilment), FG14 (group talent pool), FG15 (approve from email), G12
(self-scheduling, not taken), G17 (syndication).

### 2.1 Company admin

**Present in the design.** Sign-in with forced enrolment for company admins (B1), invitation (B4), suspended pages (B5), profile, slug, logo,
banner and name change (C20), verification documents (C21), team with presets, permission editor, suspend, reset two-factor, transfer ownership and
email domains (C22), activity log with export (C27), settings and message defaults (C29), work locations (C32), reviews and replies (C24),
campaigns (C23), analytics (C2), and everything a recruiter has.

| ID | Actor | Feature | Gap | Persona | Business impact | Status API | Status data | Evidence | Priority |
|---|---|---|---|---|---|---|---|---|---|
| MG-B01 | Company admin | Lost authenticator of the only admin | Every imported company has **one** member, a `company_admin`, and TOTP is **required** for every company admin, so each owner enrols at the first sign-in. A company admin's factor can be reset only by another company admin of the same company (never one's own); the platform admin's reset (`admin/users/{id}/reset-two-factor`) is designed in `phase-8-user-management.md` and marked **after import**, and only the psychologists' reset is coded. The only path back today (found in turn 3) is a workaround: the platform admin invites a **new** company admin with another email (D4, `POST admin/companies/{id}/invitations`), moves the ownership to that account (`PUT admin/companies/{id}/owner`), and the new owner resets the old owner's factor. It needs a second mailbox, makes a new account, and is written down nowhere. | SA, BE, HR | The company account is blocked (jobs, applicants, offers in progress) until the platform team runs an undocumented three-step workaround. | Designed (after import) | Exists | `phase-8-company-admin.md` decisions and lines 39-41; admin route list (no member 2FA reset); `IssueInvitation.php`; `phase-1-migration.md:122` | P1 |
| MG-B02 | Company admin | First sign-in of an imported company | Nothing tells the owner what the import changed: jobs imported as `draft` (masters unmatched or incomplete), apply-by-email or URL jobs imported `closed`, pending edits skipped, no verification documents (badge kept when verified in legacy), contact fields empty, screening answers and the single legacy CV not carried, team members and saved candidates not carried, and that two-factor enrolment and new terms are required. As for job seekers (MG-A06), the import report holds only legacy ids and reason codes. | HR, BA, FE, DBA | The first contact of every customer company with the new product is confusing; support load on the platform team in the first weeks. | Missing (a per-company "import summary", built from the report and `legacyimport_id_map`) | Legacy empty | `phase-1-migration.md` sections "Legacy jobs", "Job status", "Company and job mapping", "Account mapping" | P1 |
| MG-B03 | Company admin | Assign many jobs to a new team member | All imported jobs have the owner as the only assignee, and the Recruiter preset has no `jobs.view_all`. A recruiter invited after go-live sees **no job** until the admin opens each job and edits its assignees (C4 `PUT company/jobs/{id}/assignees`); C22 member detail lists the member's jobs but cannot add any. | SA, HR, FE, BE | Slow onboarding of the team after go-live; admins grant `jobs.view_all` to everyone to work around it, which defeats the access model. | Missing (bulk assign from C3 selection or from C22) | Exists | `phase-8-company-permissions.md:144, 176`; route list (assignees per job only) | P2 |
| MG-B04 | Company admin | One person, several companies of a group | "One company per account" holds also inside a group (`organization_group_members` adds only a group role, E14 is read-only). A shared-services HR team that recruits for several legal entities needs one account, and one **email**, per tenant. | BA, HR, SA | The ERP TA core (internal tenants) works against how a holding's central HR team operates; duplicate accounts weaken audit and two-factor. | Missing (recorded decision) | Exists (`identity_users.email` unique) | `erp-organization.md:15`; `frontend.md` 8.0 "no company switcher" | P2 (decision to re-check, see 2.6) |

### 2.2 Recruiter

**Present in the design.** Jobs list, editor, detail and lifecycle (C3 to C5), all applicants (C5b), pipeline with facets, bulk actions, review
requests (C6), application detail with notes, rating, interview, offer, hire, withdraw, talent pool, assessment (C7), interview workspace and rooms
(C8 to C10), candidate search, invitations, talent pool (C11 to C13), messages (C14), assessments (C15, C16), kits, screening bank, stages (C17 to
C19), calendar (C26), tasks on C1, the designed #14, #15, #20, #22, #31, #32 elements.

| ID | Actor | Feature | Gap | Persona | Business impact | Status API | Status data | Evidence | Priority |
|---|---|---|---|---|---|---|---|---|---|
| MG-B05 | Recruiter | Who withdrew, on the company side | Same defect as MG-A08 on C7: `CompanyApplicationDetailResource` reports `by: applicant` for every non-recruiter move into `withdrawn`, so the imported older duplicates (withdrawn by the system) read as "the candidate withdrew". | BE, HR | Wrong reading of candidate behaviour (a candidate looks like one who drops out). | Exists (needs `system`) | Exists | `CompanyApplicationDetailResource.php:116-120` | P2 |
| MG-B06 | Recruiter | Imported applications on C6 and C7 | The snapshot of an imported application is built from the profile **at import time** and marked `migrated`, the history has two entries, the screening answers and legacy notes are missing, and the "viewed" time is empty; no company resource exposes `migrated`, so C7 presents it as what the candidate submitted, and C6 shows the import time as the time in stage (stale marks on day one). | HR, BE, FE, DBA | Recruiters judge candidates on data the candidate did not send at that time; the stale task (`stale_applications`) fills with imported rows on go-live day. | Missing (flag and import time on the company resources) | Exists (snapshot `migrated`, `migrated_at`) | `phase-1-migration.md:81-88, 276-282`; `grep migrated` in `src/Recruitment`, `src/Dashboard`: nothing | P2 |
| MG-B07 | Recruiter | Legacy saved candidates | karir.pkp had saved candidates with categories and a CV-view log; no importer exists, so C13 (talent pool) starts empty and job seekers' "who viewed my profile" loses its history. | DBA, HR, BA | The companies' own sourcing lists, built over years, are lost at the cut-over. | Exists (target endpoints) | Legacy empty (no importer) | `legacy-flow-map.md:41`; `src/LegacyImport/Infrastructure` (no talent pool writer) | P2 (ASSUMPTION: the legacy saved-candidate data is in use; count to be read in the approved run) |
| MG-B08 | Recruiter | Two people editing the same thing | Job edits (`PATCH company/jobs/{id}`), offer terms (`PUT .../offer`), interview details (`PATCH .../interview`) and notes have row locks for moves, but no version check: the second save silently overwrites the first, and the UI shows no "changed by someone else" state. | BE, FE, DBA | Lost changes in teams of several recruiters, e.g. an offer sent with terms another recruiter had just changed. | Missing (version or `If-Match` on these writes, 409 with the current state) | Needs schema (a version column, or `updated_at` used as one) | `lockForUpdate` in the Recruitment repositories; no `If-Match` or version in `src/Recruitment` | P3 |

### 2.3 Hiring manager

**Present in the design.** Landing on C28 (review inbox with Lanjut / Tidak / Mungkin), bounded pipeline on C6, interviews and scorecards (C8, C30),
interview results comparison (C5), requisitions (E5), approvals (E1), tasks `my_reviews` and `reviews_overdue`.

| ID | Actor | Feature | Gap | Persona | Business impact | Status API | Status data | Evidence | Priority |
|---|---|---|---|---|---|---|---|---|---|
| MG-B09 | Hiring manager | Approval tasks | The approvals inbox (E1) has no task on C1: `company/tasks` has providers for reviews, scorecards, RSVP, scoring, offers and jobs, but none for approval steps waiting for the member (a hiring manager approving a requisition or workforce plan, any approver). The notification `approval_task` exists, so the only reminder is one notice that is easy to miss. | SA, BE, FE | Requisitions and plans wait unseen; time to fill starts late. | Missing (task provider for pending approval steps) | Exists | `src/**/Tasks/*Task.php` (12 providers, no approval one); `frontend.md:920` | P2 |

FG13 (requisition fulfilment) stays the hiring manager's main open gap.

### 2.4 Interviewer

**Present in the design.** Landing on C30 "Wawancara saya" with RSVP, interview workspace C8 (kit, questions, scorecard with revisions, attendance,
recordings), rooms C9 and C10, limited layout of C7 for a panel seat, calendar C26, `.ics`, reminders, tasks `my_rsvp` and `my_scorecards`.

No new gap from the six lenses. A note for the front end (FE, P3, ASSUMPTION): the scorecard of C8 is often filled on a phone right after the
interview; the design does not state a narrow-screen layout for C8 (11.1 states the layouts only in general).

### 2.5 Group admin and approver (ERP, internal tenants)

**Present in the design.** E1 to E16: approvals inbox, delegations, approval flows, workforce plans, requisitions, group structure, members,
settings, activity log, group plans and requisitions, read-only recruitment across tenants, support access, assessment providers (#24).

| ID | Actor | Feature | Gap | Persona | Business impact | Status API | Status data | Evidence | Priority |
|---|---|---|---|---|---|---|---|---|---|
| MG-B10 | Approver | Escalation of a waiting approval | Reminders exist (`approvals:send-reminders`, every `approvals.reminder_days`, default 2, repeated until decided; corrected in turn 4, the first version of this row said there were none). What is missing is the **escalation**: an approver who ignores the reminders and set no delegation (E2) holds the step for ever; the requester sees the waiting time but has nobody to turn to except the admin's manual replace (`can_replace`). | BA, SA, HR | A requisition stuck at one approver blocks hiring for the whole unit. | Missing (escalation to the group admin or the approver's superior after N reminders) | Exists (`reminded_at` on the step) | `SendApprovalReminders.php`; `erp-requisitions.md:98, 104`; E1, E2 | P3 |
| MG-B11 | Group admin | Analytics on imported history | The funnel and speed reports (time in stage, time to hire, conversion) and the group's E14 analytics count imported applications as if their stage moves happened at the import time. Nothing excludes or separates `migrated` rows. | BA, DBA, BE | Management sees a time to hire of years and wrong conversion rates in the first reports of the new system. | Missing (exclude or separate migrated rows; or a "from" date at the cut-over) | Exists (`migrated` in the snapshot; summary tables may need a flag) | `phase-4-business-intelligence.md` "Analyses"; `grep migrated` in `src/Dashboard`: nothing | P2 |

### 2.6 Candidate paired features (added in turn 2)

| Candidate | Actors | Origin |
|---|---|---|
| Two-factor reset of a company member (company admin, owner, platform admin) | company admin, platform admin | MG-B01 |
| Activation of imported companies (invitation of the owner, enrolment, import summary) | platform admin, system, company admin | MG-B02 |
| Job assignment and visibility | company admin, recruiter, hiring manager | MG-B03 |
| Approval of requisitions and plans, with delegation and escalation | hiring manager, approver, group admin, recruiter, system | MG-B09, MG-B10 |
| Review requests | recruiter, hiring manager | C6, C28 |
| Interview panel (invitation, RSVP, scorecards, results) | recruiter, interviewer, hiring manager, job seeker | C7, C8, C30 |
| Support access to a group | group admin, platform admin | E15 |
| Company verification and name change | company admin, platform admin, job seeker (badge) | C20, C21 |
| Team invitation | company admin, invited member | C22, B4 |
| Reviews of the company and the reply | job seeker, company admin, platform admin | C24, P4 |
| Concurrent edits | recruiter, recruiter | MG-B08 |

### 2.7 Open questions

1. **MG-B01:** bring the platform admin's two-factor reset for company members forward to before go-live (out of the "after import" group), or
   make every imported company invite a second admin before the owner's enrolment?
2. **MG-B04:** keep "one company per account" for internal tenants, or let one account belong to several tenants of the same group (with a tenant
   switcher in the back office)?
3. **MG-B07:** import the legacy saved candidates (with their categories) into the talent pool? This touches the talent pool consent rule of
   `phase-8-talent-pool.md`, which legacy saves never asked for.
4. **MG-B11:** for imported applications in analytics: leave them out, show them as a separate "before the cut-over" period, or count only from
   the cut-over date?
5. **MG-B10:** after how many reminders should a waiting approval step escalate, and to whom (the group admin, or a superior taken from the
   organisation structure)?

## 3. Psychologists and platform admin

Both live in the admin application (`frontend.md` section 9): `/masuk` and pages D for admins, `/mitra/masuk` and pages M for psychologists.
Admin accounts and roles stay **console-only** by the user's decision of 2026-10-13 (9.3); that is not counted as a gap here. The four legacy
admin features left out on 2026-10-13 (candidate export, cross-company applications, job bulk import, platform interview templates) are not
reopened. Open gaps of earlier rounds: FG7 (help requests), FG16 (queue alert, designed #30), G8 (appeal of a rejected job).

### 3.1 Partner psychologist

**Present in the design.** Invitation with password and two-factor (M1), sign-in with two-factor (M2), own tests with norms, report and
explanation templates and auto-issue (M3, M4), review queue with drawings, mark reviewed, sign and revise (M5), account page (M6); #23 adds norm
groups, candidate context, claim and release, recommendation and the register (M7); #24 adds licences, signature, due dates and report questions.

| ID | Actor | Feature | Gap | Persona | Business impact | Status API | Status data | Evidence | Priority |
|---|---|---|---|---|---|---|---|---|---|
| MG-C01 | Psychologist | Forgotten password | The shared `POST auth/password/forgot` and `auth/password/reset` work for any `identity_users` row (no type check), so a psychologist can reset by code, but the admin application has **no** forgot or reset page under `/mitra` (M2 has none) and the public A3 page belongs to the job seeker application. Changing the password while signed in has no endpoint at all (FEG13). | FE, BE, SA | A psychologist who forgets the password cannot sign in without the platform team; signed reports wait (and with #24, go overdue). | Exists (reset by code); Missing (change while signed in) | Exists | `RequestPasswordReset.php` (no type filter); `frontend.md` 9.2 and its FEG13 note | P2 |

### 3.2 Institution lead

**Present in the design.** All of it is #24 (M8 institution profile and team: invitations, suspend with release of claims, roles, the last-lead
rule; licence queue; signature). No new gap from the six lenses. The retention of raw test data against an account erasure is already legal
question 17 (`phase-8-provider-institutions.md` section 7).

### 3.3 Platform admin

**Present in the design.** D1 to D28: dashboard with work queues and the operations card, analytics, companies with documents, settings, owner and
first-admin invitation, job, review and report moderation, name changes, CMS (FAQ, legal, email and CV templates, announcements), newsletter,
campaigns, reference data, settings, audit log, assessment library and providers, users (read; suspend after import), groups and tenants, support
reads under a group grant, undelivered email.

| ID | Actor | Feature | Gap | Persona | Business impact | Status API | Status data | Evidence | Priority |
|---|---|---|---|---|---|---|---|---|---|
| MG-C02 | Platform admin | Merge reference items | The import creates every unmatched legacy skill, job role and job category as a new active master (641 skills alone), merging only by normalised name, so "MySQL" and "MySQL Database" stay apart; the decision says "an admin tidies them later". D15 can create, edit and deactivate (`is_active`), but **cannot merge**: deactivating a duplicate leaves the candidates, alerts and matching rows pointing at it. | DBA, BA, BE | Fragmented filters and weaker matching (two skills that mean one thing); candidate search misses people; the cleanup is a database job instead of an admin task. | Missing (merge: re-point every reference, retire the source, keep the old slug resolving) | Needs schema (`merged_into_id` or a redirect table) | `phase-1-migration.md:303-325`; `MasterItemRequest.php:31`; D15 (`GET`/`POST`/`PUT` only) | P2 |
| MG-C03 | Platform admin | Import and activation follow-up | The import report lives as a JSON file on the server and the reset emails go out by a console command in batches of 200 (`identity:send-imported-password-resets`); no page shows how many imported accounts and companies there are, how many reset emails went out or bounced, and how many users have signed in since (`first_login_at` exists). | BA, SA | The go-live success measure (how much of the legacy base came back) is not visible to the business; batches and bounces are tracked by hand. | Missing (an admin report over the id map, `first_login_at` and the email suppressions) | Exists (`legacyimport_id_map`, `identity_users.first_login_at`, `email_suppressions`) | `docs/HANDOFF.md` items waiting for the user; `phase-8-jobseeker-privacy.md` (`first_login_at`) | P2 |
| MG-C04 | Platform admin | Account help for a user | D21 reads users and (after import) suspends and reactivates them; the admin cannot correct a user's email (pair of MG-A05), resend the verification code, send a password reset, or reset a company member's two-factor before `users.manage` lands (MG-B01). With FG7 open, these are the questions the help channel will receive first. | HR, SA, BE | Every stuck account becomes a developer task in the first weeks after go-live. | Designed (suspend, reactivate, member 2FA reset, after import); Missing (email correction, resend verification, send reset) | Exists | `phase-8-user-management.md`; `phase-8-company-admin.md:41`; admin route list | P2 |
| MG-C05 | Platform admin | Support access to an ordinary company | Support access (E15, D28) exists only for groups and only for the organisation structure; for an ordinary company there is no consented, time-boxed read of what its members see, so a support case is handled through screenshots. | HR, BA, SA | Slow support for external tenants, the larger part of the customer base. | Missing | Needs schema (grants keyed by company, or the group grant widened) | `erp-governance.md` support grants; D28 | P3 |
| MG-C06 | Platform admin | Work queues on the dashboard | The dashboard shows two queues (jobs waiting for approval, companies waiting for verification). Name changes (D24), review and reply moderation (D8), job and message reports (D23), and the #24 licence queue have no count, so they are found only by opening each page. | FE, BE, SA | Moderation and reports wait longer; a reported job can stay live. | Missing (more cards in `GET admin/dashboard`) | Exists | `phase-4-dashboards.md:34` | P3 |

### 3.4 Candidate paired features (added in turn 3)

| Candidate | Actors | Origin |
|---|---|---|
| Forgotten password and lost authenticator of a psychologist | psychologist, institution lead, platform admin | MG-C01 |
| Reference data cleanup and what it changes for profiles, jobs, alerts and matching | platform admin, job seeker, recruiter, system | MG-C02 |
| Activation follow-up of imported users and companies | platform admin, system, job seeker, company admin | MG-C03, MG-A06, MG-B02 |
| Account help (email correction, verification, reset, two-factor) | platform admin, job seeker, company member | MG-C04, MG-A05, MG-B01, FG7 |
| Job moderation (review, reject, take down, restore) and the applicants of a taken-down job | platform admin, recruiter, job seeker | D7, #15 |
| Company suspension and its members, jobs and applicants | platform admin, company admin, job seeker | D4, B5 |
| Assessment review and signed report | job seeker, psychologist, recruiter, institution lead | M5, C16, J7 |
| Licence renewal of a psychologist | psychologist, institution lead, platform admin | #24 |
| Newsletter and campaigns | platform admin, visitor, job seeker, company admin | D9, D10, C23, P7, P10 |
| Email delivery failure (suppression) | system, platform admin, every user | D25 |

### 3.5 Open questions

1. **MG-C02:** build a merge for reference items before go-live (so the 641 imported skills can be tidied in the panel), or tidy them with a
   one-off script after the import and leave the panel without a merge?
2. **MG-C04:** may a platform admin correct a user's sign-in email (with an identity check written down as a procedure), or is the email
   change only self-service (MG-A05)?
3. **MG-C03:** is the share of imported users who sign in again a go-live success measure the business wants to see, and over what period?

## 4. Paired features

The candidates of 1.3, 2.6 and 3.4, without duplicates. Paired features that the earlier rounds analysed end to end (`actor-gap-analysis.md`
section 3, `frontend-gap-analysis.md` section 3) and where the six lenses found nothing new are listed in 4.1 only. Features with a new gap, or
whose sides do not match, get a matrix in 4.2: one column per actor, then the business rule, the endpoints and events, the data, and the gap in
synchronisation or notification.

### 4.1 Paired features with no new gap

| Paired feature | Actors | Where it is covered |
|---|---|---|
| Interview scheduling, reschedule, no-show, reminders | recruiter, job seeker, interviewer, system | FG1 to FG3 (#15, #29); `frontend-gap-analysis.md` 3.1 |
| Video and voice interview | company participants, job seeker | `frontend-gap-analysis.md` 3.2 |
| Interview panel and results (RSVP, scorecards, comparison) | recruiter, interviewer, hiring manager | `actor-gap-analysis.md` 3.3 |
| Assessment, review and signed report | recruiter, job seeker, psychologist, institution lead | FG4 open; #23, #24 |
| Offer | recruiter, job seeker | `actor-gap-analysis.md` 3.5; #12 |
| Review requests | recruiter, hiring manager | C6, C28 |
| Job invitations to apply; manual candidate claim | recruiter, job seeker | J8, C12; P15, #31 |
| Job alerts and new jobs | job seeker, recruiter, system | J10, `NotifyAlertsOfLiveJob` |
| Company verification and name change | company admin, platform admin, job seeker | C20, C21, D5, D24; G20 open |
| Company reviews and replies, reports and their outcome | job seeker, company admin, platform admin | P4, J13, C24, D8, D23; FG18 open |
| Team invitation | company admin, invited member | C22, B4 |
| Support access to a group | group admin, platform admin | E15, D28 (MG-C05 is the non-group case) |
| Announcements, newsletter, campaigns | platform admin, every user | D9, D10, D26, P7, P10, C23 |
| Licence renewal of a psychologist | psychologist, institution lead, platform admin | #24 |
| Psychological report verification | visitor, psychologist | P14, M7, #23 |
| Job closing, expiry and take-down for the applicants | recruiter, platform admin, system, job seeker | #15 (notice, label, reject remaining); MG-A10 is the content part |

The gaps already listed per actor that are paired by nature are not repeated as MG-P rows; their pairs are: MG-A04 (job seeker) with candidate
search and invitations (recruiter, C11, C12); MG-A05, MG-C04 and FG7 (account help); MG-A08 with MG-B05 (who withdrew); MG-B01 with MG-C04
(two-factor reset); MG-B03 (job assignment, company admin and recruiter); MG-B09 with MG-B10 (approvals); MG-C02 (reference cleanup, which reaches
profiles, jobs, alerts and matching).

### 4.2 Paired features with new gaps

#### 4.2.1 Activation of imported users and companies

| Platform admin | System | Job seeker | Company admin | Business rule | Endpoint / event | Data / constraint | Sync / notification gap |
|---|---|---|---|---|---|---|---|
| Runs the import and `identity:send-imported-password-resets` (batches of 200) from the console; no page (MG-C03) | Sends the reset emails; skips suppressed addresses; the nightly and weekly jobs start to reach the imported base (MG-P02) | Gets a reset email, lands on A3 (a code, not a link), then the A5 terms gate; finds an incomplete profile with no explanation (MG-A06) | Gets the same reset email, then the forced TOTP enrolment on B1, then a company with draft and closed jobs and no team (MG-B02) | The import sends no email itself; terms are accepted again; discoverability starts off; TOTP is required for company admins | `identity:send-imported-password-resets`; `POST auth/password/reset`; `me/terms-acceptance`; `first_login_at` | `legacyimport_id_map`; JSON report (legacy ids only); `identity_users.first_login_at` | No designed sequence: who is told what, in which order and with which landing page; A3 does not know the reader is an imported user; nothing is said to legacy users before the freeze. |

| ID | Gap | Persona | Business impact | Status API | Status data | Evidence | Priority |
|---|---|---|---|---|---|---|---|
| MG-P01 | **The activation of the legacy base is not designed as one flow.** Each piece exists (reset command, terms gate, enrolment), but there is no plan for: the notice to karir.pkp users before the freeze (announcement on the old site, newsletter), the wording and landing of the reset email for imported users, the first-sign-in summary (MG-A06, MG-B02), the order (company owners first, so jobs are live when job seekers come back), and the follow-up of who came back (MG-C03). | BA, HR, SA | The return rate of the legacy base decides whether the new portal starts with users or empty; a reset email out of the blue from a new brand reads as phishing. | Missing (the pieces exist; the flow and its copy do not) | Exists | `phase-1-migration.md:125`; `deployment-checklist.md` section 3; `SendImportedPasswordResetsCommand.php` | P1 |

#### 4.2.2 System emails to the imported base before activation

| System | Job seeker (imported) | Company admin (imported) | Business rule | Endpoint / event | Data / constraint | Sync / notification gap |
|---|---|---|---|---|---|---|
| From the first scheduler run after the import: `job:expire-due` (hourly), `matching:send-recommendations` (weekly), `job-seeker:send-profile-reminders` (daily, counts from `first_login_at`, so it waits), `company:send-team-digests` (daily), `messaging:send-unread-emails` | Notification preferences start at their defaults (on); may receive recommendation emails before they have chosen a password or seen the new terms | The owner may receive expiry notices for every imported job whose deadline has passed, and team digests full of imported "stale" items (MG-B06) | Legacy alert flags are not carried; preferences start at defaults | `NotifyAssigneesOfExpiry`; recommendation and digest commands | Imported jobs keep `active` when legacy said active, whatever the deadline (ASSUMPTION: the import does not expire them itself) | The first email of the new brand to a legacy user may be a digest or a recommendation, not the activation email. |

| ID | Gap | Persona | Business impact | Status API | Status data | Evidence | Priority |
|---|---|---|---|---|---|---|---|
| MG-P02 | **Scheduled emails reach imported accounts before activation.** Nothing holds the scheduled emails (recommendations, digests, expiry notices) for accounts that have not signed in since the import (`first_login_at` empty), and the first `job:expire-due` run may send a burst of expiry notices for imported jobs past their deadline. | BA, HR, BE, DBA | Spam complaints and suppressions in the first week hurt the sending reputation exactly when the activation emails must arrive; legal basis of marketing-like emails to users who have not accepted the new terms. | Missing (a rule: no scheduled email before the first sign-in or before the terms are accepted, except the activation email) | Exists (`first_login_at`, terms acceptances) | `php artisan schedule:list`; `phase-8-jobseeker-privacy.md` (`first_login_at`); `phase-1-migration.md:125` | P2 |

#### 4.2.3 An email that cannot be delivered

| System | Job seeker | Recruiter | Platform admin | Business rule | Endpoint / event | Data / constraint | Sync / notification gap |
|---|---|---|---|---|---|---|---|
| Records bounces and complaints as suppressions (parser waits for the SMTP provider); stops sending to the address | `GET me` returns `email_deliverable: false`, but no page of `frontend.md` shows it, so the user never learns that emails stopped | Sends interview, test and offer emails as usual; no company resource says the candidate's email is suppressed | Sees `email_suppressed` and the reason on D21 and the list on D25 | A suppressed address gets no email until removed (D25) | `CurrentUserResource` `email_deliverable`; `AdminUserResource` `email_suppressed` | `email_suppressions` | The two people who could act (the user, the recruiter) are the two who are not told. |

| ID | Gap | Persona | Business impact | Status API | Status data | Evidence | Priority |
|---|---|---|---|---|---|---|---|
| MG-P03 | **Undeliverable email is shown to nobody who can act.** The job seeker area and the back office do not use `email_deliverable`; the company resources of an application do not carry it; the user cannot fix it without the email change of MG-A05. | FE, BE, HR | Candidates miss interview invitations and offer deadlines they only get by email; recruiters mark them as no-shows. | Exists for the user (`GET me`); Missing for the company side | Exists | `CurrentUserResource.php:9`; `AdminUserResource.php:29-30`; `grep email_deliverable` in `frontend.md`, `src/Recruitment`: nothing | P2 |

#### 4.2.4 Account deletion while a recruitment is running

| Job seeker | System | Recruiter | Interviewer | Business rule | Endpoint / event | Data / constraint | Sync / notification gap |
|---|---|---|---|---|---|---|---|
| `POST me/deletion`: the account is deactivated at once, every token revoked, 30-day grace period; signing in again restores it | `AccountDeletionRequested` is only audited; after 30 days `identity:anonymize-due-accounts` raises `UserAnonymized`, and the listeners erase the applications' personal content, resumes, recordings, scorecards, chats, talent pool entries | Keeps seeing the active application, the confirmed interview and the open offer for 30 days, can still move, schedule and send; then sees an erased application in whatever stage it was | Keeps the interview in "Wawancara saya" and gets the reminders | Deletion is self-service; erasure happens at the end of the grace period | `POST me/deletion`; `AccountDeletionRequested`; `UserAnonymized` | Application status is not changed by either event | The company is never told; interviews and offers stay open for a person who has left. |

| ID | Gap | Persona | Business impact | Status API | Status data | Evidence | Priority |
|---|---|---|---|---|---|---|---|
| MG-P04 | **A deletion request does not close the person's active recruitment.** No listener of `AccountDeletionRequested` or `UserAnonymized` withdraws the active applications, cancels the coming interviews (with the panel notified) or withdraws the open offers, and no company notice says the candidate left. | SA, HR, BE | Panels wait for a candidate who will not come; offers sit open; pipelines and analytics keep ghost applicants. | Missing (a listener that withdraws as `system` with reason `account_deleted`, notifying the team) | Exists (needs the `system` withdrawal of MG-A08 and a reason value) | `RequestAccountDeletion.php:23`; listeners of `UserAnonymized` (erase only); `EraseApplicantData.php:11` | P2 |

#### 4.2.5 Company suspension while candidates are in process

| Platform admin | Company members | Job seeker | System | Business rule | Endpoint / event | Data / constraint | Sync / notification gap |
|---|---|---|---|---|---|---|---|
| `POST admin/companies/{id}/suspend` with a reason (admin-only) | Refused at sign-in (403 `company-suspended`, B5); cannot cancel interviews or answer chats | Keeps the confirmed interview, the open offer and the test invitation; the company's jobs leave the search; no notice | `CompanySuspended` is only audited; reminders keep going to the candidate | Suspension hides the company; the reason stays with the admins | `CompanySuspended` | Applications, interviews and offers keep their state | Candidates are reminded of interviews that nobody from the company can hold. |

| ID | Gap | Persona | Business impact | Status API | Status data | Evidence | Priority |
|---|---|---|---|---|---|---|---|
| MG-P05 | **Suspending a company leaves its candidates uninformed.** No listener pauses the candidate-facing reminders, and no notice tells the candidates that the company's process is on hold; restoring the company does not say anything either. | SA, HR, BA | Candidates travel to interviews that will not happen; the platform's own trust suffers, since the suspension is usually for a trust reason (fraud, fake jobs). | Missing (listener on `CompanySuspended` and the restore event: notice to active applicants, pause of reminders) | Exists | `SuspendCompany.php:50`; listeners of `CompanySuspended` (audit only) | P2 |

### 4.3 The system actor

Scheduled tasks (`php artisan schedule:list`, 2026-10-20): 37, covering identity (anonymisation, erasure reconciliation, challenge and token
pruning), recruitment (interview reminders hourly and "starting soon" every 5 minutes, review notices, offer deadlines, room and session closing,
recording and upload purges), assessment (invitation reminders, expiry, auto-submit, video purges), approvals (reminders), jobs (expiry, view
folding), alerts and recommendations, team digests, talent pool consent purges, analytics roll-up, notification and audit purges, and the scheduler
watch. Their effect on other actors' screens is covered by the earlier rounds, except:

- **MG-P02** (scheduled emails before activation) and **MG-B06** (stale marks and `stale_applications` filled by imported rows on day one).
- **MG-B11**: `analytics:rollup` counts imported history.
- **MG-P04** and **MG-P05**: events that only audit (`AccountDeletionRequested`, `CompanySuspended`) where other actors' screens need a change.
- The open part of the system known from before: the immediate clearing of talent pool notes needs a job seeker trigger (`docs/HANDOFF.md`).

## 5. Synthesis

### 5.1 Where the personas disagree

| Topic | View A | View B | Way forward |
|---|---|---|---|
| Discoverability of imported profiles (MG-A04) | BA: switch the legacy base on, it is the asset the companies pay attention to | HR (UU PDP): the user decided opt-in on 2026-10-07; the legacy default said nothing about a choice | Keep opt-in; ask for it in the first-sign-in flow with one clear switch (MG-A06), and show the switches on J2 and J15. |
| Legacy saved candidates (MG-B07) | BA, recruiters: the lists are years of sourcing work | HR: the talent pool now needs the candidate's consent, which legacy saves never asked for | Question 2.7.3; one option is to import them as a private list with no contact action until the candidate consents. |
| TOTP for every company admin (MG-B01) | SA: required by design and by the security review | HR, BA: each imported company has a single admin, often a small-company HR person; a lost phone blocks the company | Keep TOTP; bring the platform admin's reset forward to before go-live (question 2.7.1). |
| Chat history at the cut-over (MG-A07) | HR: open conversations must continue | DBA: a third importer with attachments and the S10 legacy findings (`legacy-findings.md`) to handle | Question 1.4.1; at least a notice to both sides before the freeze. |
| One company per account (MG-B04) | SA: simple identity, clear audit, one tenant per token | BA, HR: holding HR teams recruit for several entities | Question 2.7.2; decide before the ERP TA core is sold to internal tenants. |
| Version checks on edits (MG-B08) | DBA, BE: lost updates are silent data loss | FE: every edit form needs a conflict state | P3; start with the offer form, where a lost change costs the most. |
| Emails before activation (MG-P02) | BA: recommendations bring users back | HR, legal: the first email must be the activation one, after the new terms | Hold scheduled emails until the first sign-in; send only the activation email to imported accounts. |

### 5.2 All gaps by priority

| Priority | Gaps |
|---|---|
| **P1** (6) | MG-A04 discoverability switches; MG-A05 email change; MG-A06 imported job seeker first sign-in; MG-B01 lost authenticator of the only admin; MG-B02 imported company first sign-in; MG-P01 activation flow of the legacy base |
| **P2** (21) | MG-A01 sitemap feed; MG-A02 conversion measurement and cookie notice; MG-A03 help channel (FG7); MG-A07 saved jobs, alerts and chats of imported users; MG-A08 who withdrew (job seeker side); MG-A10 job content after close; MG-B03 bulk job assignment; MG-B04 one company per account; MG-B05 who withdrew (company side); MG-B06 imported applications on C6 and C7; MG-B07 legacy saved candidates; MG-B09 approval tasks; MG-B11 analytics on imported history; MG-C01 psychologist forgotten password; MG-C02 merge reference items; MG-C03 import and activation follow-up; MG-C04 account help for a user; MG-P02 emails before activation; MG-P03 undeliverable email; MG-P04 deletion during a recruitment; MG-P05 company suspension and candidates |
| **P3** (9 and a note) | MG-A09 imported applications on J5; MG-A11 email language; MG-A12 partial addresses; MG-A13 active sessions; MG-A14 retention notice; MG-B08 concurrent edits; MG-B10 approval escalation; MG-C05 support access to an ordinary company; MG-C06 dashboard work queues; C8 narrow-screen scorecard (2.4, note) |

36 gaps in all: MG-A 14, MG-B 11, MG-C 6, MG-P 5 (the C8 note of 2.4 has no id).
Five of the six P1 gaps come from the **import**: the legacy base is the product's starting asset, and the design so far treats the import as
a data task, not as a user journey.

### 5.3 Value versus effort (Business Analyst's estimate)

Effort is a rough guess from the lenses (FE, BE, DBA), not a plan: **low** is a field, a flag or one page element; **high** is a new flow,
an importer or a schema change.

| | Low effort | High effort |
|---|---|---|
| **High value** | MG-A04 (two switches, API ready); MG-A08 and MG-B05 (one value in two resources); MG-B09 (one task provider); MG-P03 (show `email_deliverable`, add it to the company resource); MG-C06 (dashboard counts); MG-B03 (bulk assign) | MG-P01 with MG-A06, MG-B02, MG-C03 (activation flow and summaries); MG-A05 and MG-C04 (email change and account help); MG-B01 (admin reset forward, part of `users.manage`); MG-P02 (hold rule over all scheduled emails); MG-P04, MG-P05 (listeners and notices); MG-C02 (merge); MG-A10; MG-B11 |
| **Lower value** | MG-A09, MG-B06 (a `migrated` flag on the resources); MG-A11 (`PATCH me` locale); MG-A13; MG-A14; MG-A12 (picker state) | MG-A07 and MG-B07 (new importers); MG-B04 (multi-tenant accounts); MG-B08 (version checks); MG-C05; MG-B10; MG-A01; MG-A02 |

**Suggested order:** the low-effort, high-value cell first (it fits the "endpoints the front end asks for" coding rule of `decisions.md`
section 5), then one design session for the **activation of the legacy base** (MG-P01 with MG-A05, MG-A06, MG-B01, MG-B02, MG-C03, MG-C04,
MG-P02), which must be ready before the freeze date is set.

### 5.4 API needs of the front end

| Need | For | Gaps |
|---|---|---|
| `system` as a value of the withdrawal actor (job seeker and company resources), with an `account_deleted` reason | J5, C7 | MG-A08, MG-B05, MG-P04 |
| `migrated` and `migrated_at` on the application resources (both sides) | J5, C6, C7 | MG-A09, MG-B06 |
| `email_deliverable` of the candidate on the company application resources | C6, C7 | MG-P03 |
| Email change: request with a code to the new address, confirm; admin correction under `users.manage` | J15, C25, D21 | MG-A05, MG-C04 |
| Account language: `PATCH me` with `locale` | J15, C25 | MG-A11 |
| Import summary per user and per company (read once, dismissable) | J1, C1 | MG-A06, MG-B02 |
| Admin: import and activation report; resend verification; send a password reset; company member two-factor reset before go-live | D2, D21 | MG-C03, MG-C04, MG-B01 |
| Job content for its applicants after the job closes (or in the snapshot) | J5 | MG-A10 |
| Sitemap feed of live jobs and visible companies with `updated_at` | Nuxt sitemap | MG-A01 |
| Bulk job assignment | C3, C22 | MG-B03 |
| Task provider for pending approval steps | C1 | MG-B09 |
| Dashboard cards for name changes, review moderation, reports, licences | D2 | MG-C06 |
| Merge of reference items | D15 | MG-C02 |
| Listeners: deletion request and company suspension (withdraw, cancel, notify, pause reminders) | J5, C7, C30 | MG-P04, MG-P05 |
| Hold rule for scheduled emails before the first sign-in | system | MG-P02 |
| Active sessions list and sign-out per device | J15, C25 | MG-A13 |
| Version check (`If-Match` or a version field) on job, offer and interview edits | C4, C7 | MG-B08 |
| Forgot and reset password pages in the psychologist area (API exists); password change for psychologists | M2, M6 | MG-C01 |

### 5.5 Schema needs

| Need | Gaps | Effect on the import |
|---|---|---|
| `merged_into_id` on the reference tables (or one redirect table) | MG-C02 | The 641 created skills and the created roles and categories can be merged after the import without breaking profiles, alerts or matching. |
| Pending email change (new address, code, expiry), or reuse of the verification code tables | MG-A05 | None; helps imported users whose old mailbox is gone. |
| Import summary rows per user and company (derived from the report and `legacyimport_id_map`), or keep the per-row report keyed by new id | MG-A06, MG-B02 | The importer writes them in the final run; the rehearsal reports have legacy ids only. |
| A `migrated` flag reachable by analytics (column on the application, or a flag in the summary tables) | MG-B06, MG-B11 | The importer sets it; today it lives only inside the snapshot JSON. |
| Withdrawal reason value `account_deleted` (and the actor `system` already allowed by the check constraint) | MG-P04 | None. |
| Support grants keyed by company | MG-C05 | None. |
| Version column (or `updated_at` used as one) on jobs, offers, interviews | MG-B08 | None. |
| Escalation state on approval steps (count of reminders, escalated to) | MG-B10 | None. |
| Importers for saved jobs, job-alert opt-ins and saved candidates (decided 2026-10-20; the messenger history is not imported) | MG-A07, MG-B07 | New importers; the alert opt-in maps legacy roles to the new job roles (already partly created as masters); saved candidates land in a private list without contact actions. |
| Several tenants of one group per account (not now: reviewed after go-live with #13, decided 2026-10-20) | MG-B04 | Large if taken later: company membership becomes many-to-many; the importer is unaffected (imported companies have one member). |

### 5.6 Decisions on the open questions (2026-10-20)

All 17 questions of sections 1.4, 2.7, 3.5 and section 4 were answered by the user on 2026-10-20. The per-section question lists above are kept
as asked; this list is the record. Nothing here is designed or scheduled yet: each item goes to a design session.

| # | Gap | Decision |
|---|---|---|
| 1 | MG-A07 | **Import saved jobs and job-alert opt-ins** (two new importers). The chat history is **not** imported; both sides are told before the freeze. |
| 2 | MG-A05 | **Self-service email change**: request from J15 or C25, a six-digit code to the new address, confirmed with the password; the old address gets a notice. |
| 3 | MG-A10 | **Copy of the job content in the application snapshot** at apply (title, description, requirements, salary when shown), so the candidate sees what they applied for. |
| 4 | MG-A02 | **No analytics tool for now**; the go-live measures use the API's own data (job views, application sources). MG-A02 is deferred. |
| 5 | MG-A09 | **Label "Dipindahkan dari karir.pkp" on both sides** (J5 and C6, C7), with an explanation that the history and snapshot were built at the import; stale marks do not count from the import time (also MG-B06). |
| 6 | MG-B01 | **Bring the platform admin's two-factor reset for company members forward to before go-live** (part of `users.manage`), with a written identity-check procedure. |
| 7 | MG-B04 | **Keep "one company per account"** (`erp-organization.md:15`) **and review it after go-live**, in the ERP TA core design session (#13). Revised by the user on 2026-10-20: the first answer of the same day (several tenants per account before go-live) is withdrawn. |
| 8 | MG-B07 | **Import the legacy saved candidates as a private company list without contact actions**; contact and invitations open only after the candidate's consent or when the profile is discoverable. |
| 9 | MG-B11 | **Speed and conversion reports count only applications created after the cut-over date**; totals may include the imported ones. |
| 10 | MG-B10 | **Escalate after 3 reminders to the group's `group.approvals.manage` holders** (task and notice to replace the approver with the existing replace action); the count is a setting. |
| 11 | MG-C02 | **Merge of reference items in the admin panel** (D15 "Gabungkan ke…"): every reference moves to the target, the source is retired, its old slug resolves to the target. |
| 12 | MG-C04 | **A platform admin may correct a user's sign-in email** under `users.manage`, after a written identity check; audited, both addresses told, every token revoked. |
| 13 | MG-C03 | **The return of imported users is a go-live success measure**: an admin report of imported accounts and companies, reset emails sent and bounced, and first sign-ins, at 30, 60 and 90 days. |
| 14 | MG-P01 | **Company owners are activated first** (a few days ahead, so their jobs are ready), then job seekers in batches, after a notice on karir.pkp before the freeze. **Claude drafts** the notice, the reset email and the newsletter text in the activation design session, for the user to review. |
| 15 | MG-P02 | **Imported accounts that have not signed in since the import get only the activation (reset) email**; every other scheduled email waits for the first sign-in. |
| 16 | MG-P04 | **Close at the deletion request:** active applications withdrawn by the system (reason `account_deleted`), coming interviews cancelled with the panel told, open offers withdrawn, the team notified; a restored account does not reopen them. |
| 17 | MG-P05 | **Neutral in-app notice and paused reminders on suspension:** active candidates get "proses rekrutmen sementara ditunda" (no reason), interview and test reminders pause, and a second notice follows the restore. |

**Effect on the order of 5.3.** Decisions 6 and 15 move work before go-live. The activation design session
(MG-P01) now covers decisions 1, 2, 5, 6, 8, 12, 13, 14 and 15; decisions 3, 9, 10, 11, 16 and 17 fit the item-by-item phase 8 designs; decision
7 waits for the ERP TA core session (#13).
