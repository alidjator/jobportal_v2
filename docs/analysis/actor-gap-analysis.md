# Feature gap analysis per actor (2026-10-19)

A system analyst's and HR specialist's review of ONESIS Career / ERP TA, done separately from each actor's point of view, and for every
feature that two actors share (a **paired feature**), from both sides. Earlier rounds compared the product with benchmarks
(`gap-analysis.md`, `gap-analysis-round-2.md`) and the phase 8 designs closed many of those gaps per actor; this round asks, per actor, "what
does this person need to do their job end to end, and is it there?", and lists only what is still missing.

**Sources.** The API route list (`php artisan route:list`, 748 routes, 2026-10-19), `docs/design/phase-8-index.md` and the design documents it
names, `erp-direction.md`, and a few code reads (named where used). Plans, billing, payments, orders and invoices stay out of scope.

**Status.**

| Status | Meaning |
|---|---|
| **Exists** | Coded (endpoint or behaviour in the code today). |
| **Designed** | A design document covers it, not coded yet; the phase 8 item number is given. |
| **Not taken** | Left out by a recorded user decision; listed so it is not forgotten, not to reopen it. |
| **Known gap** | Already listed in `gap-analysis-round-2.md` (G number) with no design yet. |
| **Gap** | New in this round: no design, no decision. |

**Priority of the new gaps.** **P1** before go-live of the portal; **P2** for the ERP TA core (internal tenants); **P3** later or on request.

## 1. Actors

Five actor kinds in the code (`ActorKind`); the personas below are the roles that appear in user stories.

| Actor kind | Persona | How it is told apart |
|---|---|---|
| Job seeker | Job seeker / candidate | `identity_users.type = job_seeker` |
| Company member | Company admin | `CompanyRole::CompanyAdmin` ("Admin" in user stories) |
| Company member | Recruiter | member preset `recruiter`, job assignment role `recruiter` |
| Company member | Hiring manager | preset `hiring_manager`, job assignment role `hiring_manager` |
| Company member | Interviewer | preset `interviewer` (`interviews.participate`) |
| Company member | Group admin / approver (ERP) | group membership with `group.*`; approver slots of an approval flow |
| Psychologist | Partner psychologist; institution lead (designed) | `identity_users.type = psychologist` |
| Admin | Platform admin (`super_admin` and custom roles) | `identity_admins`, own guard |
| System | Scheduler, queue jobs | `Actor::system()` |

## 2. Per actor

### 2.1 Job seeker

| Journey step | Need | Today | Status |
|---|---|---|---|
| Account | Sign-up, email verification, Google, password reset, 2FA, terms acceptance | all present | Exists |
| | Mobile number verification | phone stored, never verified | Not taken (G5) |
| | Passkeys or one-time code | password only | Not taken (G18) |
| Profile | Profile, education, experience, skills, languages, links, avatar, several resumes, CV generator, completeness | all present | Exists |
| | Who viewed my profile; discoverability switches | present | Exists |
| | Block named companies; "last active" and "open to offers" | — | Designed (#25) |
| Discovery | Search with relevance, filters, similar jobs, recommendations, alerts, saved jobs, salary insight, report a job | present | Exists |
| | Follow companies; disability-friendly jobs | — | Designed, frozen (job discovery 6, 7) |
| Apply | Apply with screening answers, snapshot, daily limit, talent pool consent | present | Exists |
| | Apply state before applying, extra required profile items | — | Designed (#20) |
| | Re-apply after a rejection when the job allows it | blocked for ever | Designed (#14) |
| | Draft application | — | Not taken (G16) |
| Track | Application list and detail with stage history, viewed time, rejection reason | present | Exists |
| | "Job closed" label and notice; "under review" notice; rejection message from the company | — | Designed (#15) |
| | One to-do list ("Perlu tindakan") | — | Designed (#15) |
| | **Agenda of my confirmed interviews and test windows** across applications | to-do covers only items waiting for an answer | Designed (#15 section 5, `GET me/agenda`) |
| | Expected response time of the company or stage | — | Known gap (G17, W8) |
| Interview | Confirm, decline, propose another time (2x), `.ics`, join video room, recording consent | present | Exists |
| | **Ask to reschedule a confirmed interview** | only decline, which ends the interview (`Interview::assertAwaitingJobSeeker`) | Designed (#29) |
| | **Reminder shortly before the start** (e.g. 1 to 2 hours) | day-before email only; the 30-minute notice goes to the panel only | Designed (#29 section 6, 2 hours before) |
| Assessment | Invitations, consent, attempt with autosave, video answers, integrity events, decline, explanation | present | Exists |
| | Practice questions and device check | — | Designed (#15) |
| | **Report a technical problem during a test / ask for more time** | none; the team can only resend a new invitation | **Gap** (FG4) |
| | Accommodation (disability, time) | — | Not taken (legal question) |
| Offer | Offer with terms, letter PDF, accept, decline with note | present | Exists |
| | Accept with password and evidence | — | Designed (#12 section 3, legal question 12) |
| | Ask about or negotiate the offer | chat only | Not taken |
| Hire | Join date and note | present | Exists |
| | **Pre-employment documents** (KTP, NPWP, diploma, SKCK, bank account, BPJS) and onboarding checklist | — | Decided: Employee pre-boarding with #13 (FG5) |
| Communication | Chat with companies applied to, block, report, attachments | present | Exists |
| | **WhatsApp or SMS notices** for time-critical events | channels are email and push (push deferred) | **Gap** (FG6) |
| | Contact the platform (help request) | FAQ only | **Gap** (FG7) |
| Privacy | Data export, account deletion with grace period, notification preferences | present | Exists |

### 2.2 Company admin

| Need | Today | Status |
|---|---|---|
| Company profile, logo, banner, slug, locations, legal documents, name change request | present | Exists |
| Team: invite, edit permissions (29), presets, suspend, reactivate, transfer ownership, reset 2FA, email domains, team history | present | Exists |
| Require 2FA for the team; company activity log with export | present | Exists |
| Company settings page; stages; screening questions; message defaults | present | Exists |
| Applicant retention per company | — | Designed (#26, legal question 15) |
| Restricted candidates (blacklist) list | — | Designed (#14, legal question 13) |
| Embeddable career page or job widget | — | Not taken |
| Single sign-on for external tenants | — | Not taken (internal tenants use the ERP IAM) |
| Close the company account, export the company's data | — | Not taken |
| **Integration API (API keys, webhooks) for an external tenant's own HRIS or ATS** | hires export (CSV) only | **Gap** (FG8, P3) |

### 2.3 Recruiter

| Need | Today | Status |
|---|---|---|
| Jobs: create, clone, publish, close, reopen, fill, assignees, screening questions, interview kits per stage | present | Exists |
| Job preview, summary, hidden vacancies, salary note, more list filters | — | Designed (#20) |
| Several locations per job in search, matching and alerts | one location per job | Designed (#20) |
| Pipeline: list, keyword search, filters, facets, move, bulk move, reject, bulk reject, bulk message, notes, rating, export | present | Exists |
| Time in stage, task list, daily digest, team notification preferences, other applications | present | Exists |
| Undo a rejection; reject remaining applicants of a closed job | — | Designed (#14, #15) |
| Sourcing: candidate search, job invitations (single and bulk), talent pool with categories, campaigns with QR | present | Exists |
| Add a candidate manually (walk-in, referral, CV by email, job fair without sign-up) | only candidates with an account | Designed (#31) |
| **Employee referral** program (referrer, status back to the referrer) | — | **Gap** (FG10, P2) |
| **Internal job posting and internal applicants** (mutasi, promosi) | — | **Gap** (FG11, P2, needs Employee) |
| Interviews: schedule, bulk slots, schedule later, panel, note, `.ics`, video and voice rooms, recording, calendar, email preview | present | Exists |
| **Self-scheduling** (candidate picks from offered slots) and interviewer availability | — | Not taken (G12) |
| Reference check, background check, medical check-up (MCU) as tracked steps with results | only free notes or a custom stage name | Designed (#32) |
| Assessments: own tests, library, batteries, scoring, results, comparison | present (comparison of tests designed) | Exists / Designed (#22) |
| Default test per stage, auto-advance on score | — | Designed (#22) |
| Offers with terms and letter; hire with join date; hires export | present | Exists |
| Saved filters | — | Not taken (browser keeps them) |
| Job syndication (Google for Jobs data, other boards) | front end plans `JobPosting` data | Known gap (G17) |

### 2.4 Hiring manager

| Need | Today | Status |
|---|---|---|
| Role on the job, stage boundary, review requests (Lanjut / Tidak / Mungkin), tasks | present | Exists |
| Interviews: schedule, take part, scorecards, interview results comparison | present | Exists |
| Requisition: create, submit, amend, track; workforce plan view | present (internal tenants) | Exists |
| Assessment summary on the review card | — | Designed (#23) |
| **Approve an offer or the hire decision** | — | Not taken for external tenants; internal tenants Designed-to-be (#12, approval needs a design) |
| **See which hires filled my requisition, and the join dates** | requisition links to jobs; no rollup of hires | **Gap** (FG13, P2) |

### 2.5 Interviewer

| Need | Today | Status |
|---|---|---|
| Invitation with `.ics`, RSVP, my interviews, reminders (day before, 30 minutes), kit and questions, scorecard with revisions, follow-up | present | Exists |
| Briefing page, being told the outcome, availability and two-way calendar sync, personal calendar feed | — | Not taken |

No new gap; the open items are the "not taken" ones above. FG2 (reschedule request) also reaches the interviewer through the RSVP of the new
time.

### 2.6 Group admin and approver (ERP, internal tenants)

| Need | Today | Status |
|---|---|---|
| Legal entities, units, unit types, positions with versions, grades, cost centres | present | Exists |
| Workforce plans, requisitions, approval flows (group and company), approvals inbox, delegations | present | Exists |
| Read across tenants (jobs, applications, analytics), group settings, group activity log, support access grants | present | Exists |
| Hand-off of a hire to the Employee record | — | Designed-to-be (#13, design session needed) |
| **Group talent pool**: a candidate who fits another legal entity of the group, with the candidate's consent | talent pools are per tenant | **Gap** (FG14, P2) |
| **Approve from the email or notification** (one click with re-authentication) | approve inside the app only | **Gap** (FG15, P3) |

### 2.7 Psychologist and institution lead

| Need | Today | Status |
|---|---|---|
| Invitation, sign-in with 2FA, own tests, norms, report and explanation templates, auto-issue, review queue, sign, revise | present | Exists |
| Norms by group, candidate context, claim a review, structured recommendation, notifications, example report, report verification, register | — | Designed (#23) |
| Leads, licence renewals, institution identity and signature, revising signed reports, review targets, questions about a report | — | Designed (#24) |
| Consultation with the candidate | — | Not taken |

No new gap; the psychologist side is fully designed and waits for #23 and #24.

### 2.8 Platform admin

| Need | Today | Status |
|---|---|---|
| Companies, documents, owners, settings per company, name changes, groups and tenants | present | Exists |
| Job moderation (approve, reject, take down, restore), job reports, review moderation, message reports | present | Exists |
| Reference data, CMS (FAQ, announcements, legal documents, email templates, CV templates), newsletter, campaigns | present | Exists |
| Assessment providers, psychologists, library, batteries per company | present | Exists |
| Audit log, analytics, dashboard, email suppressions, support access to group data | present | Exists |
| Suspend and reactivate job seekers and company members | list and view only (`GET admin/users`) | Designed (`phase-8-user-management.md`, after the import) |
| Company self-registration review | — | Designed, frozen |
| Appeal of a rejected or taken-down job | — | Known gap (G8) |
| **Help requests from users** (queue, answer, link to account) | — | **Gap** (FG7) |
| Failed jobs and outgoing email health in the console | operations card shows failed and waiting jobs; email health waits for the SMTP provider | Exists / Designed (#17) |

### 2.9 System

| Need | Today | Status |
|---|---|---|
| Reminders, expiries, digests, retention purges, anonymisation, facets | present | Exists |
| Bounce and complaint handling | parser waits for the SMTP provider | Designed (#17 open part) |
| Alert on failed queue jobs and a stalled queue | scheduler alert only | Designed (#30) |

## 3. Paired features

Each pair lists what each side must have. **Bold** rows are the missing pieces.

### 3.1 Interview scheduling (recruiter ↔ job seeker, with the interviewer)

| Recruiter must have | Status | Job seeker must have | Status |
|---|---|---|---|
| Schedule one or in bulk, format, place or link, panel, note | Exists | Invitation by email and in-app, with `.ics`, place or link, note, interviewer name | Exists |
| See confirmations, proposals, declines on the pipeline and calendar | Exists | Confirm, decline, propose another time (2x) | Exists |
| Accept a proposal or set a new time | Exists | Answer before the interview without signing in again (link in the email) | Exists (link to the app) |
| Unscheduled interviews in the task list | Exists | One agenda of upcoming interviews | Designed (#15) |
| Receive and answer a reschedule request on a confirmed interview | Designed (#29) | Ask to reschedule a confirmed interview with a reason | Designed (#29) |
| Panel reminders day before and 30 minutes before | Exists | Reminder 2 hours before (in-app, email); **WhatsApp** | Designed (#29); **Gap (FG6)** |
| Offer time slots for the candidate to pick | Not taken | Pick a slot | Not taken |
| Mark no-show | Designed (#29); attendance log exists | Told the interview was missed, with the company's next step | Designed (#29) |

**Interviewer side:** invitation, RSVP, reminders and scorecard exist. A declined RSVP tells the scheduler; nothing tells the candidate,
which is right. FG2 (#29) sends a new invitation and RSVP to the panel when an accepted proposal moves the time.

### 3.2 Video and voice interviews (interviewer ↔ job seeker)

| Interviewer must have | Status | Job seeker must have | Status |
|---|---|---|---|
| Lobby, admit, join, record with consent, recordings | Exists | Lobby, join, recording consent and its withdrawal | Exists |
| Voice call to the candidate's phone | Exists | Phone number confirmed at confirmation | Exists |
| Device and connection check before joining | Designed (`frontend.md` J6, C9) | Device check before the interview | Designed (`frontend.md` J6, C9) |

### 3.3 Interview results (interviewer ↔ recruiter and hiring manager ↔ job seeker)

| Team must have | Status | Job seeker must have | Status |
|---|---|---|---|
| Scorecard per panel member, lock rule, revisions, comparison per job | Exists | Next step after the interview (moved, rejected with a message) | Exists / Designed (#15) |
| Follow-up for missing scorecards | Exists | Feedback on the interview | Not taken (rejection message covers it) |

### 3.4 Assessment (recruiter ↔ job seeker ↔ psychologist)

| Recruiter must have | Status | Job seeker must have | Status |
|---|---|---|---|
| Send a test with a window, assessors, attachment | Exists | Invitation, window, instructions, length per section | Exists |
| Cancel or resend with a new window | Exists | Reminder before the due time | Exists |
| See integrity events and results, scoring | Exists | Practice and device check | Designed (#15) |
| **See and answer a candidate's problem report or time request** | **Gap (FG4)** | **Report a technical problem or ask for more time** | **Gap (FG4)** |
| Default test per stage, auto-advance | Designed (#22) | Explanation of a psychological result | Exists |

| Company must have (psychologist pair) | Status | Psychologist must have | Status |
|---|---|---|---|
| Signed report with recommendation, report number | Exists / Designed (#23) | Queue, claim, norms, candidate context | Exists / Designed (#23) |
| Ask a question about a report | Designed (#24) | Receive and answer questions, review targets | Designed (#24) |

### 3.5 Offer (recruiter ↔ job seeker, with the approver)

| Recruiter must have | Status | Job seeker must have | Status |
|---|---|---|---|
| Terms, benefits, allowances, labour-law checks, generated or own letter, deadline | Exists | Letter PDF, terms, deadline, reminder before expiry | Exists |
| Revise, withdraw, see the answer | Exists | Accept, decline with a note | Exists |
| Evidence of acceptance | Designed (#12 section 3) | Accept with password, evidence PDF | Designed (#12 section 3) |
| **Approval of the offer before it is sent (hiring manager, group)** | **Designed-to-be (#12, internal tenants; no detail)** | — | — |
| See questions about the offer in chat | Exists (chat) | Ask about the offer from the offer page | Not taken (chat) |

### 3.6 Rejection and withdrawal (recruiter ↔ job seeker)

| Recruiter must have | Status | Job seeker must have | Status |
|---|---|---|---|
| Reject with reason, note, bulk, after "fill" | Exists | Notice and reason code | Exists |
| Message for the candidate, default text | Designed (#15) | Message from the company | Designed (#15) |
| Undo window, restriction, re-apply period | Designed (#14) | Re-apply date shown | Designed (#14) |
| Withdraw on the candidate's behalf, see the reason | Exists | Withdraw from any active stage with a reason | Exists |

### 3.7 Hire and onboarding (recruiter / HR ↔ new hire)

| Recruiter / HR must have | Status | New hire must have | Status |
|---|---|---|---|
| Move to hired with join date and note, change later, hires export | Exists | Join date and note, told of changes | Exists |
| Request pre-employment documents and track them | Decided (#13, Employee) | Upload them once, see what is missing | Decided (#13, Employee) |
| MCU and background check before the join date | Designed (#32) | See the appointment, give consent, see "Selesai" | Designed (#32) |
| Hand-off to the Employee record (internal tenants) | Designed-to-be (#13) | — | — |

### 3.8 Messaging (recruiter ↔ job seeker ↔ platform admin)

| Recruiter must have | Status | Job seeker must have | Status |
|---|---|---|---|
| Start a thread, bulk message, close and reopen, attachments, report | Exists | Start with a company applied to, block, report, attachments | Exists |
| Online status shown to the candidate | Exists (needs Reverb live) | Unread email after 15 minutes | Exists |
| Platform admin: message reports queue with actions | Exists | | |

### 3.9 Sourcing (recruiter ↔ job seeker)

| Recruiter must have | Status | Job seeker must have | Status |
|---|---|---|---|
| Candidate search, invitation to a job, talent pool, consent shown | Exists | Discoverability switches, invitations list, decline, talent pool consent per application | Exists |
| Hide restricted candidates | Designed (#14) | Block named companies | Designed (#25) |
| Add an offline candidate | Designed (#31) | Claim the invitation by email and agree | Designed (#31) |
| **Referral by an employee** | **Gap (FG10)** | **Referred person told who referred them and asked to apply** | **Gap (FG10)** |

### 3.10 Shortlist review and requisition (recruiter ↔ hiring manager ↔ approver)

| Recruiter must have | Status | Hiring manager / approver must have | Status |
|---|---|---|---|
| Ask for a review with due date, see answers | Exists | Review page, answer, reminders | Exists |
| Job from an approved requisition | Exists | Create, submit, amend a requisition; approve, reject, return, delegate | Exists |
| | | **Hires per requisition and join dates** | **Gap (FG13)** |

### 3.11 Company verification and moderation (company admin ↔ platform admin ↔ job seeker)

| Company admin must have | Status | Platform admin must have | Status |
|---|---|---|---|
| Upload documents, see approval or rejection reason, name change request | Exists | Review documents, approve or reject, name changes | Exists |
| See why a job was rejected or taken down | Exists | Reports queue, dismiss, take down | Exists |
| **Appeal a decision** | Known gap (G8) | **Appeal queue** | Known gap (G8) |
| Answer reviews, report a review | Exists | Review and reply moderation | Exists |

Job seeker side: report a job (Exists), report a review (Exists). The reporter is **not told the outcome** of a report: **Gap** (FG18, small).

### 3.12 Support access (group admin ↔ platform admin) and help (any user ↔ platform admin)

| Group admin | Status | Platform admin | Status |
|---|---|---|---|
| Grant temporary access to named admins, revoke, see their reads | Exists | Read group data under a grant | Exists |

| Any user | Status | Platform admin | Status |
|---|---|---|---|
| **Send a help request about the account, an application or a company** | **Gap (FG7)** | **Queue, answer, link to the account** | **Gap (FG7)** |

### 3.13 Account suspension (platform admin ↔ user)

| Platform admin | Status | User | Status |
|---|---|---|---|
| Suspend with reason and message, reactivate, audited | Designed (user management) | Email with the message; sign-in refused with a code | Designed / Exists |

## 4. New gaps

| Id | Gap | Actors | Priority | Note |
|---|---|---|---|---|
| FG1 | Agenda of the job seeker's upcoming interviews and test windows | job seeker | P1 | Designed 2026-10-19: `phase-8-candidate-experience.md` section 5 (#15). |
| FG2 | Reschedule request on a confirmed interview, and a no-show notice | job seeker, recruiter, interviewer | P1 | Designed 2026-10-19: `phase-8-interview-reschedule.md` (#29). |
| FG3 | Short reminder (1 to 2 hours) to the job seeker before an interview | job seeker | P1 | Designed 2026-10-19: 2 hours, `phase-8-interview-reschedule.md` section 6 (#29). |
| FG4 | Technical problem report and extra-time request during or before a test | job seeker, recruiter | P2 | The team answers by resending; needs the integrity rules kept. |
| FG5 | Pre-employment documents and onboarding checklist | new hire, HR | P2 | Decided 2026-10-19: Employee pre-boarding, designed with #13 (`erp-direction.md` section 3). |
| FG6 | WhatsApp (or SMS) channel for time-critical notices | job seeker | P2 | Highest reach in Indonesia; needs a provider (WhatsApp Business API), opt-in, cost; phone verification (G5) becomes a dependency. |
| FG7 | Help requests from users to the platform | all, platform admin | P2 | Or point to an outside helpdesk; a decision is enough for go-live. |
| FG8 | Integration API (API keys, webhooks) for external tenants | company admin | P3 | The hires export is the bridge today. |
| FG9 | Manual candidate entry (offline, walk-in, CV by email) with a claim-and-consent step | recruiter, candidate | P2 | Designed 2026-10-19: `phase-8-manual-candidates.md` (#31); legal question 20. |
| FG10 | Employee referral program | employee, recruiter, referred person | P2 | Needs the Employee context for "employee"; external tenants could use members. |
| FG11 | Internal job posting and internal applicants (transfer, promotion) | employee, recruiter, group admin | P2 | Needs Employee; the group read rule and the positions already exist. |
| FG12 | Reference check, background check and MCU as tracked steps | recruiter, candidate, (vendor) | P2 | Designed 2026-10-19: `phase-8-pre-employment-checks.md` (#32); legal question 21. |
| FG13 | Requisition fulfilment: hires and join dates per requisition | hiring manager, group admin | P2 | Read model over requisition, job and hires. |
| FG14 | Group talent pool across the group's tenants, with consent | group admin, recruiter, candidate | P2 | The talent pool consent today names one company. |
| FG15 | Approve from an email or notification link | approver | P3 | Re-authentication required; convenience only. |
| FG16 | Alert on failed queue jobs and a stalled queue | platform admin, system | P1 | Designed 2026-10-19: `phase-8-platform-admin.md` section 9 (#30). The operations card already shows the counts. |
| FG17 | ~~Device and connection check before a video interview~~ | job seeker, interviewer | — | Withdrawn 2026-10-19: already designed in `frontend.md` (J6, C9). |
| FG18 | Reporter told the outcome of a job or review report | job seeker, company | P3 | In-app notice on dismiss or take-down. |

## 5. Points for the user

1. **P1 candidates for go-live:** FG1 (#15), FG2 and FG3 (#29) and FG16 (#30) were decided and designed on 2026-10-19; FG17 was withdrawn
   (already in `frontend.md`). FG2 changes the rule of `phase-2-interviews.md` that a confirmed interview can only be declined.
2. **HR process steps:** FG12 (#32) and FG9 (#31) were designed on 2026-10-19 and wait for legal questions 21 and 20; FG5 goes to Employee
   pre-boarding with #13.
3. **ERP TA core:** FG10, FG11, FG13 and FG14 depend on the Employee context or the group; they fit the design session of #13.
4. **Channel:** FG6 (WhatsApp) is a cost and provider decision, not only a feature.

Nothing here is scheduled; each item needs the user's choice before a design session.
