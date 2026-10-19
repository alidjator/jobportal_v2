# Front-end feature gap analysis per actor (2026-10-19)

A review of the front-end design (`docs/design/frontend.md`) by a senior system analyst, a senior HR specialist and a senior front-end developer, done
separately from each actor's point of view, and for every **paired feature** (two actors on the two ends of one flow) from both sides. The
functional review of the API is `actor-gap-analysis.md` (FG1 to FG18); this round asks a different question: **for each thing an actor must do,
is there a page, a dialog or a state in the front-end design that lets them do it?**

**Why now.** The page maps of `frontend.md` (sections 7 to 9) were built from the routes of 2026-10-13 (284 OpenAPI paths). Since then the phase 8
items, the ERP direction and the company locations added about 300 more paths (589 paths with 751 routes under `/api/v1` on 2026-10-19). Each phase 8 design ends with a
"Front end" row, but those rows were never carried back into `frontend.md`, so the page maps, the menus and the role rules describe the product of
2026-10-13.

**Sources.** `php artisan route:list --path=api/v1` (751 routes, 2026-10-19), compared with every endpoint `frontend.md` names; the "Front end" rows
of the phase 8 designs; `erp-direction.md`, `erp-organization.md`, `erp-requisitions.md`, `erp-sso.md`, `naming.md`; `actor-gap-analysis.md`.
Plans, billing, payments, orders and invoices stay out of scope.

**Status.**

| Status | Meaning |
|---|---|
| **Mapped** | A page of `frontend.md` covers it, with the endpoint named. |
| **Partial** | The page exists, but the element (button, tab, dialog, state) the feature needs is not in the design. |
| **Unmapped** | The API is **coded**, but no page or element of `frontend.md` uses it. |
| **Design row** | The API is designed (phase 8), and only that design's "Front end" row names the UI; `frontend.md` does not. |
| **Not taken** | Left out by a recorded user decision. |
| **API gap** | Needs API work too (the FG number of `actor-gap-analysis.md` where there is one). |

**Priority.** **P1** before go-live of the portal; **P2** for the ERP TA core (internal tenants); **P3** later. FEG numbers are new in this round.

## 1. Cross-cutting findings

These change the shape of the design, not only a page.

| Id | Finding | Effect | Priority |
|---|---|---|---|
| FEG1 | **The ERP has no front end.** 62 `organization/*` routes (group, legal entities, units, unit types, positions with versions, grades, cost centres, members, group settings, group activity, approval flows, support grants, read across tenants, workforce plans, requisitions), plus `company/approvals`, `approval-flows`, `approval-delegations`, `requisitions` and `workforce/plans`, are coded with no page, no menu and no application chosen to hold them. | Group admins and approvers of internal tenants cannot use anything they were built for. | P2 |
| FEG2 | **Roles in the company dashboard are out of date.** Section 8 knows two roles (company admin, recruiter). The code has 29 permissions with the presets Company admin, Recruiter, Hiring manager and Interviewer (`phase-8-company-permissions.md`, `phase-8-hiring-managers.md`, `phase-8-interviewers.md`), and a hiring manager bound to a job sees only the stages from the boundary. The menu (11.1) and the "Role" column show nothing for a hiring manager or an interviewer. | An interviewer would land on a recruiter menu full of 403 panels; a hiring manager has no home. | P1 |
| FEG3 | **The page numbers have split.** Designs added pages that are not in `frontend.md`: C5b "Semua pelamar" (`GET company/applications`), C27 "Log aktivitas", C28 review page; and they call the pipeline "C5" while `frontend.md` calls it C6 (C5 is the job detail). | Front-end coding would build the wrong page or miss one. | P1 |
| FEG4 | **No contract for the links in emails.** The API builds links from `app.company_app_url` (`<COMPANY_APP_URL>/review-requests/<id>`), `JOB_SEEKER_PROFILE_URL`, `JOB_SEEKER_APPLICATION_URL`, `COMPANY_INVITATION_URL`, and the provider and admin links, while the front end uses Indonesian paths with an `/en` variant (`/rekruter/...`, `/me/...`). No list says which path each link must open, nor what happens when the reader is signed out or is the wrong kind of user. | Links in emails break or open a 404. | P1 |
| FEG5 | **Two-factor for public users is not in the sign-in flow.** `POST auth/two-factor` and `me/two-factor` (enrol, confirm, recovery codes, disable) are coded, and company members may be required to use it (`company.require_two_factor`, group setting for internal tenants); A1 has no second step, J15 and C25 have no two-factor section, and there is no forced-enrolment screen. | A company member with 2FA required cannot sign in through the front end. | P1 |
| FEG6 | **Notification and task targets are not mapped.** About 60 in-app notification types and the task list items (`company/tasks`, the job seeker's "Perlu tindakan") each need a page to open. 11.6 plans the wording per type, but not the target page per type. | A notification click goes nowhere. | P1 |
| FEG7 | **Address pickers.** `provinces`, `cities`, `subdistricts`, `villages` and `postal-codes` are coded for the company locations (`company-locations.md`) and profiles; no shared component is designed (cascading pickers, search, postal-code lookup). | Every address form is built differently. | P1 |
| FEG8 | **Announcements banner.** `GET announcements` is public and coded (D26 publishes them); no layout of 11.1 shows a banner or says where it is dismissed. | Admin announcements are never seen. | P1 |

## 2. Per actor

### 2.1 Visitor (not signed in)

| Need | Front end | Status |
|---|---|---|
| Search, job detail, company profile, salary, training, campaigns, legal, FAQ, newsletter | P1 to P13 | Mapped |
| Similar jobs on a job page and on its 404 (`GET jobs/{job}/similar`) | P3 names no endpoint for it; 11.4 promises "similar jobs to continue" on a closed job | Partial |
| Share a job (Web Share, WhatsApp, copy link) | only in `phase-8-job-discovery.md` | Design row |
| Report a job (`POST jobs/{id}/reports`, sign-in needed) | P3 has no "Laporkan" button and no sign-in step for it | Partial |
| Announcement of maintenance or a policy change | none | Unmapped (FEG8) |
| Verify a psychological report by its number (public page) | only in `phase-8-provider-tools.md` (#23) | Design row |
| Apply state before signing in ("Masuk untuk melamar") | P3 Apply leads to sign-in | Mapped |

### 2.2 Job seeker

| Journey step | Need | Front end | Status |
|---|---|---|---|
| Account | Sign-in with password or Google, register, verify, reset, terms, restore | A1 to A6 | Mapped |
| | Two-factor at sign-in, enrol and recovery codes in settings | none | Unmapped (FEG5) |
| | Download my data (`me/data-export`: request, status, download) | J15 lists password, sign-out everywhere, deletion only | Unmapped |
| Profile | Profile, resumes, CV generator, avatar, completeness | J2, J3 | Mapped |
| | Who viewed my profile | J1 (`me/profile/views`) | Mapped |
| | Discoverability switches; block named companies; "open to offers" (#25) | none in J2 or J15 | Partial / Design row |
| | Address with province, city, sub-district, village, postal code | J2 has no address picker | Partial (FEG7) |
| Discover | Search, alerts, saved jobs, recommendations, invitations | P2, J8 to J10 | Mapped |
| | Follow a company; disability badge and filter | frozen designs | Design row (frozen) |
| Apply | Apply with screening answers and a resume | J4 | Mapped |
| | Talent pool consent at apply, and the switch later (`PUT`/`DELETE me/applications/{id}/talent-pool-consent`) | J4 and J5 have no consent | Unmapped |
| | Apply state with its reason and fix ("Verifikasi email", "Lengkapi profil"), extra required profile items (#20) | J4 shows none | Design row |
| | "Anda dapat melamar lagi mulai {date}" after a rejection (#14) | none | Design row |
| | Apply from a company's invitation by email (manual candidate claim, #31) | no claim page | Design row |
| Track | Application list and detail with stage history | J5 | Mapped |
| | Closed-job label, "under review" notice, rejection message (#15) | none | Design row |
| | "Perlu tindakan" and "Jadwal mendatang" (agenda) on the dashboard and as a page (#15) | J1 lists "upcoming interviews and assessments" without the agenda page or the to-do list | Partial / Design row |
| | Join date and note after being hired (`phase-8-hiring.md`) | J5 does not show the hired state | Unmapped |
| | Contact notice of the company on the application (job discovery) | none | Design row |
| Interview | Confirm, decline, propose, `.ics`, join, recording consent | J5, J6 | Mapped |
| | **Ask to reschedule a confirmed interview until the cut-off**: the existing `propose` on a `confirmed` interview, with a reason (#29, coded; 409 `reschedule-too-late`, `reschedule_until` in the resource) | J5 offers propose only while waiting for an answer | Partial |
| | Told of a no-show and the company's next step (#29, coded) | no state in J5 | Unmapped |
| | Reason choices in the decline dialog (`phase-8-withdrawals.md`, coded) | J5 has no reason list | Partial |
| | Location of an in-person interview with its address and map (`location_id`, #27) | J5 shows no place block | Partial |
| Assessment | Invitations, consent, runner, video, explanation | J7, 11.5 | Mapped |
| | Attachment from the company (`GET .../attachment`) | J7 names none | Partial |
| | Practice questions and device check (#15) | 11.5 has the device check; the practice question is only in #15 | Partial |
| | Report a problem or ask for more time | none | API gap (FG4) |
| Offer | Offer, accept, decline | J5 | Mapped |
| | Offer page with the letter PDF viewer, the terms (`GET me/applications/{id}/offer`, `letter.pdf`) | J5 names accept and decline only | Partial |
| | Accept with password and evidence (#12 section 3) | none | Design row |
| Pre-employment | Consent card and schedule for each check (MCU, background, reference, #32) | none | Design row |
| | Pre-boarding documents (#13, Employee) | none | API gap (FG5) |
| Withdraw | Withdraw from any active stage with a reason | J5 `withdraw` without the reason list | Partial |
| Communication | Messages, notifications, preferences | J11, J12 | Mapped |
| | Block and report a company in chat (`conversations/{id}/block`, `report`) | J11 names neither | Unmapped |
| | WhatsApp card with the code dialog (#33) | none | Design row (after go-live) |
| | Help request to the platform | FAQ only | API gap (FG7) |
| Reviews | My reviews; write one on P4 | J13, P4 | Mapped |
| | Told the outcome of a report | none | API gap (FG18) |

### 2.3 Company admin

| Need | Front end | Status |
|---|---|---|
| Company profile, logo, banner, slug, verification documents | C20, C21 | Mapped |
| **Work locations** (`company/locations`: list, add, edit, main, activate, deactivate, remove) | C20 has no locations; interviews and requisitions pick a `location_id` | Unmapped |
| Name change request with its document, cancel (`company/name-change-requests`) | none in C20 | Unmapped |
| **Team** with permissions, presets, suspend, reactivate, make owner, reset 2FA, resend invitation, email domains, team history, a member's activity | C22 describes it as "designed"; it is coded | Partial (the table must be rewritten from the code) |
| Permission editor (preset plus 29 checkboxes; `GET company/permissions`, dependency errors such as `permission-requires-view`) | no dialog designed | Unmapped |
| **Company settings page** (`company/settings`: require 2FA, stale days, retention), with the message defaults (`company/message-defaults`) | C20 "tab" only in `phase-8-company-admin.md` | Unmapped |
| **Activity log with filters and export** (`company/activity`, `/export`) | C27 only in `phase-8-company-admin.md` | Unmapped |
| Stages, screening question bank, interview kits, campaigns, reviews | C17 to C19, C23, C24 | Mapped |
| Approval flow of the company (`company/approval-flows`, internal tenants) | none | Unmapped (FEG1) |
| Restricted candidates list (#14); check types for pre-employment checks (#32) in the settings | none | Design row |
| Hires export (`company/hires/export`) | none | Unmapped |

### 2.4 Recruiter

| Need | Front end | Status |
|---|---|---|
| Jobs list, editor, lifecycle, screening questions, kits, assignees | C3 to C5 | Mapped |
| **Mark a job filled** (`fill`) | C5 lists publish, close, reopen, clone | Unmapped |
| Hiring team in the job editor with the hiring manager's stage boundary (`hiring-manager-boundary`) | C4 has assignees only | Unmapped |
| Job from an approved requisition (`requisitions/{id}/jobs`; required when `recruitment.requisition_required`) | C4 creates a job freely | Unmapped (FEG1) |
| Preview, summary, vacancies, salary note, several locations, required profile items (#20) | none | Design row |
| Close dialog with "Tolak pelamar yang masih aktif" (#15) | none | Design row |
| **All applicants across jobs** (`GET company/applications`, filters, facets, keyword) | C5b only in `phase-8-applicant-search.md` | Unmapped |
| Pipeline: filter panel, facets, score column, time in stage and stale mark | C6 table and kanban without them | Partial |
| Hire dialog with join date and note (`PATCH .../hire`) | C6 and C7 have no hire step | Unmapped |
| Withdraw on the candidate's behalf with reason and note (`POST company/applications/{id}/withdraw`) | none | Unmapped |
| Reject with candidate message, restriction, undo window (#14, #15) | C6 reject only | Design row |
| Email preview before sending (`email-preview`) and the note prefilled from the defaults | none | Unmapped |
| Offer dialog with terms, benefits, allowances, generated letter or own PDF (`offer-letter-uploads`, `letter.pdf`) | C7 "offer" | Partial |
| Interviews: no-show, refuse a proposal, the reschedule-request banner (#29, coded) | C7 names `accept-proposal`, `cancel`, `complete` only | Unmapped |
| In-person interview place from the locations | none | Unmapped |
| **Interview results comparison** of a job (`GET company/jobs/{id}/interview-results`) and kit "Duplikat" | none | Unmapped |
| Assessment attachment, comparison of results, default test per stage (#22) | C16 without attachment | Partial / Design row |
| **"Perlu tindakan"** (`GET company/tasks`) on the dashboard | C1 names `dashboard` and `me` only | Unmapped |
| Calendar (C26) with the `.ics` of an interview | C26 | Mapped |
| Talent pool categories, save from an application, bulk invite from the pool (#18) | C13 names categories; the rest is only in `phase-8-talent-pool.md` | Partial |
| Bulk invitations from a job (`invitations/bulk`) | C12 single only | Unmapped |
| Add a candidate manually, "Menunggu kandidat" (#31) | none | Design row |
| "Cek pra-kerja" panel on the application (#32) | none | Design row |
| Team notification preferences, daily digest | J12 is the job seeker's list | Partial |
| Chat: report a conversation (`conversations/{id}/report`) | C14 without report | Unmapped |

### 2.5 Hiring manager

| Need | Front end | Status |
|---|---|---|
| A menu and a dashboard for the role | none (FEG2) | Unmapped |
| **Review requests inbox and the review page** (`company/review-requests`, Lanjut / Tidak / Mungkin per candidate, close) | C28 only in `phase-8-hiring-managers.md` | Unmapped |
| Pipeline limited to the stages from the boundary, with "Lamaran lain dikirim oleh recruiter" | none | Unmapped |
| Interviews, scorecards, results comparison | C8, results comparison unmapped (2.4) | Partial |
| **Requisitions**: create, submit, amend, cancel, track, close; the job it became | none | Unmapped (FEG1) |
| Workforce plan of my unit (`company/workforce/plans`) | none | Unmapped (FEG1) |
| Hires of my requisition with the join dates | none | API gap (FG13) |

### 2.6 Interviewer

| Need | Front end | Status |
|---|---|---|
| A short menu: my interviews, calendar, notifications | none (FEG2) | Unmapped |
| **"Wawancara saya"** (`GET company/interviews`: upcoming, past, my scorecard pending) | only in `phase-8-interviewers.md` | Unmapped |
| **RSVP** in the interview page, the email link and the calendar (`interviews/{id}/rsvp`) | none | Unmapped |
| Limited application view (no notes or ratings; resume, screening answers, assessment results) | C7 has one layout for everyone | Partial |
| Kit questions and scorecard, also inside the room | C8, 11.5 | Mapped |
| Video and voice rooms | C9, C10 | Mapped |

### 2.7 Group admin and approver (ERP, internal tenants)

Every row is FEG1: the API is coded, and the front end has no place for it.

| Need | API |
|---|---|
| Group structure: legal entities, units and unit types, positions with versions, grades, cost centres | `organization/*` |
| Group members and their roles; tenants of the group | `organization/members`, `organization/tenants` |
| Group settings and group activity log | `organization/settings`, `organization/activity` |
| Approval flows (group and company), delegations | `organization/approval-flows`, `company/approval-flows`, `company/approval-delegations` |
| **Approvals inbox**: detail, approve, reject, return, change an approver | `company/approvals` |
| Workforce plans: create, submit, revise; requisitions across the group | `organization/workforce/plans`, `organization/requisitions` |
| Read across tenants: jobs, applications, resumes, analytics | `organization/read/*` |
| Support access: grant, revoke, see the admins and their reads | `organization/support-grants`, `support-admins` |

Approve from an email link (FG15) and the group talent pool (FG14) stay API gaps.

### 2.8 Psychologist and institution lead

| Need | Front end | Status |
|---|---|---|
| Invitation, sign-in, tests, norms, report template, review queue, sign | M1 to M6 | Mapped |
| Explanation template, auto-issue of a test (`explanation-template`, `auto-issue`) | M4 names norms and report template only | Unmapped |
| Mark reviewed and revise a signed report (`mark-reviewed`, `revise`) | M5 names sign only | Unmapped |
| Two-factor recovery codes and password in "Akun saya" | M6 names `me`, sign-out | Partial |
| Norm groups, candidate context, claim, recommendation, register (#23) | none | Design row |
| Team, licences, institution profile, signature, report questions, due dates (#24) | none | Design row |

### 2.9 Platform admin

| Need | Front end | Status |
|---|---|---|
| Companies, documents, settings, moderation, CMS, reference data, audit, assessment library and providers | D1 to D19 | Mapped |
| Reports, name changes, suppressed emails, announcements (D23 to D26) | in 9.1, but not in the admin sidebar of 11.1 | Partial |
| **Groups and tenants** (`admin/groups`: create, members, link a legal entity to a tenant, suspend, restore) | none | Unmapped (FEG1) |
| **Support access reads** (`admin/support/*`: group, units, positions, grades, cost centres, activity, under a grant) | none | Unmapped (FEG1) |
| Message report action (`message-reports/{id}/action`) | D23 names the list | Partial |
| Users list and detail (`admin/users`, `users.view`, coded) | D21 is planned for "after the import" with suspend; the read part is live | Partial |
| Operations card (failed and waiting jobs, queue watch #30) on the dashboard | D2 names `admin/dashboard` only | Partial |
| Provider auto-issue (`admin/assessment/providers/{id}/auto-issue`) | D19 without it | Unmapped |
| Help requests queue | none | API gap (FG7) |

## 3. Paired features

Each pair lists what each side must see and do in the front end. **Bold** rows are missing from `frontend.md`.

### 3.1 Interview scheduling and rescheduling (recruiter ↔ job seeker, with the interviewer)

| Recruiter (C6, C7, C26) | Status | Job seeker (J1, J5) | Status |
|---|---|---|---|
| Schedule dialog: format, date and time with the zone, place from the company locations or a link, panel, note prefilled from the defaults, **email preview** | Partial | Invitation card: date and time in the device zone with its label, format, **place with address and map** or link, interviewer names, note, `.ics` | Partial |
| Bulk slots and "schedule later" from the pipeline selection | Partial (bulk move only) | One agenda of upcoming interviews and tests ("Jadwal mendatang") | Design row (#15) |
| State badges on the pipeline and calendar: waiting, confirmed, proposal, declined, **reschedule asked**, **no-show** | Partial | Confirm, decline **with a reason**, propose another time (2x, counter shown) | Partial |
| **Proposal banner: Terima / Tolak / Jadwalkan lain** (`accept-proposal`, `refuse-proposal`) | Unmapped | **"Minta jadwal ulang" (`propose` with a reason) until `reschedule_until`**, then the button hides with the reason | Partial |
| **"Tidak hadir" after the start** (`no-show`) | Unmapped | **Missed-interview state with the company's next step** | Unmapped |
| Cancel with a note | Mapped | Cancelled state with the note | Partial |
| Panel RSVP badges on the interview | Unmapped | — | — |

**Interviewer side:** RSVP buttons (in the page, the email link and the calendar), "Wawancara saya", a new RSVP when an accepted proposal moves the time.
All Unmapped (2.6).

### 3.2 Video and voice interview (company participants ↔ job seeker)

| Company participant (C9, C10) | Status | Job seeker (J6) | Status |
|---|---|---|---|
| Device check, admit from the lobby, record with consent, screen share, interview panel inside the room | Mapped (11.5) | Device check, waiting screen, recording consent and its withdrawal, screen share | Mapped |
| Recordings list, play, delete (C8) | Mapped | Recording indicator for everyone | Mapped |
| Voice call states and attempts left | Mapped | Phone number confirmed at confirmation | Partial (J5 confirm dialog has no phone field) |

### 3.3 Interview results (interviewer ↔ recruiter and hiring manager ↔ job seeker)

| Team | Status | Job seeker | Status |
|---|---|---|---|
| Scorecard with scale anchors, draft, submit, revisions | Partial (anchors only in the design) | Next step after the interview (moved, rejected with a message) | Design row (#15) |
| **Results comparison per job** | Unmapped | — | — |

### 3.4 Assessment (recruiter ↔ job seeker ↔ psychologist)

| Recruiter (C7, C16) | Status | Job seeker (J7) | Status |
|---|---|---|---|
| Send with a window, assessors, **attachment** | Partial | Invitation with the window, length per section, **the attachment** | Partial |
| Cancel and resend | Mapped | Reminder before the due time (notification target) | Partial (FEG6) |
| Results, integrity events, scoring, report PDF | Mapped | Practice question and device check | Partial (#15) |
| **Answer a problem report or time request** | API gap (FG4) | **Report a problem, ask for more time** | API gap (FG4) |

| Company (C16) | Status | Psychologist (M5) | Status |
|---|---|---|---|
| Signed report, "Perkiraan selesai", question thread (#24) | Design row | Queue, claim, context, sign, **mark reviewed, revise** | Partial |

### 3.5 Offer (recruiter ↔ job seeker, with the approver)

| Recruiter (C7) | Status | Job seeker (J5) | Status |
|---|---|---|---|
| **Offer form with terms, benefits, allowances, labour-law warnings, generated letter or own PDF, deadline** | Partial | **Offer page with the PDF viewer, terms, deadline countdown** | Partial |
| Revise, withdraw, history of offers | Mapped | Accept, decline with a note and a reason | Partial |
| Condition box for pre-employment checks (#32) | Design row | Accept with password and evidence (#12 section 3) | Design row |
| Offer approval before sending (internal tenants) | API gap (#12) | — | — |

### 3.6 Rejection and withdrawal (recruiter ↔ job seeker)

| Recruiter | Status | Job seeker | Status |
|---|---|---|---|
| Reject with reason, candidate message, restriction, "Akan ditolak dalam N menit" and Batalkan (#14, #15) | Design row | Rejected state with the company message, re-apply date | Design row |
| **"Tandai mengundurkan diri" with reason and note; reason and actor on the detail** | Unmapped | **Withdraw with a reason choice** | Partial |

### 3.7 Hire and pre-employment (recruiter / HR ↔ new hire)

| Recruiter / HR | Status | New hire | Status |
|---|---|---|---|
| **Hire dialog with join date and note; change it later** | Unmapped | **Hired state with the join date and note, told of changes** | Unmapped |
| **"Tandai terpenuhi" for the job; hires export** | Unmapped | — | — |
| "Cek pra-kerja" panel (#32) | Design row | Consent card and schedule per check (#32) | Design row |
| Pre-boarding documents (#13) | API gap (FG5) | Upload them once (#13) | API gap (FG5) |

### 3.8 Messaging (company ↔ job seeker ↔ platform admin)

| Company (C14) | Status | Job seeker (J11) | Status |
|---|---|---|---|
| Start, bulk message, close, reopen, attachments | Mapped | Start with a company applied to, attachments | Mapped |
| **Report a conversation** | Unmapped | **Block and unblock a company, report** | Unmapped |
| Platform admin (D23): report queue, **action** and dismiss | Partial | | |

### 3.9 Sourcing and talent pool (recruiter ↔ job seeker)

| Recruiter | Status | Job seeker | Status |
|---|---|---|---|
| Candidate search with "open to offers" and activity badges (#25) | Design row | Discoverability switches, block companies, "open to offers" | Design row |
| **Bulk invitations from a job and from the pool** | Unmapped | Invitations list, decline | Mapped |
| **Save an applicant to the pool; consent shown** | Unmapped | **Consent at apply and the switch on the application** | Unmapped |
| Add a candidate manually, waiting list (#31) | Design row | Claim page from the email, "Gunakan CV yang dikirim ke perusahaan" (#31) | Design row |

### 3.10 Shortlist review (recruiter ↔ hiring manager)

| Recruiter (C6) | Status | Hiring manager | Status |
|---|---|---|---|
| **"Minta review" from the pipeline selection with due date and message; answers on the cards and the detail** | Unmapped | **Review inbox and page (C28) with Lanjut / Tidak / Mungkin; reminders open it** | Unmapped |

### 3.11 Requisition and approval (hiring manager ↔ approver ↔ recruiter)

| Hiring manager | Status | Approver | Status | Recruiter | Status |
|---|---|---|---|---|---|
| **Create, submit, amend, cancel a requisition; see its state** | Unmapped | **Inbox: approve, reject, return with a note; delegate while away** | Unmapped | **Create the job from the approved requisition** | Unmapped |

### 3.12 Company team (company admin ↔ invited member)

| Company admin (C22) | Status | Invited member (A7) | Status |
|---|---|---|---|
| Invite with a preset and permissions, resend, allowed email domains | Partial | Invitation page, set password or Google, **enrol 2FA when required** | Partial (FEG5) |
| Suspend, reactivate, reset 2FA, make owner, remove with the recipient of the member's jobs | Partial | Told of suspension at sign-in | Mapped (A6) |

### 3.13 Company verification and name change (company admin ↔ platform admin)

| Company admin | Status | Platform admin | Status |
|---|---|---|---|
| Upload documents, see the reason of a rejection | Mapped (C21) | Review, approve, reject (D5) | Mapped |
| **Name change request with its document, cancel, see the outcome** | Unmapped | Name change queue (D24) | Partial (not in the sidebar) |
| See why a job was rejected or taken down | Partial (C5 shows no moderation state) | Job moderation, reports (D7, D23) | Mapped |

### 3.14 Reports (job seeker or company ↔ platform admin)

| Reporter | Status | Platform admin | Status |
|---|---|---|---|
| **"Laporkan lowongan" on P3**, report a review (P4), report a chat | Partial | Queues and actions (D8, D23) | Mapped / Partial |
| Told the outcome | API gap (FG18) | — | — |

### 3.15 Support access (group admin ↔ platform admin)

| Group admin | Status | Platform admin | Status |
|---|---|---|---|
| **Grant to named admins, revoke, see their reads** | Unmapped | **Read the group's structure and activity under a grant** | Unmapped |

### 3.16 Announcements (platform admin ↔ everyone)

| Platform admin (D26) | Status | Every user | Status |
|---|---|---|---|
| Write, schedule, end | Mapped | **Banner in the layouts, dismiss** | Unmapped (FEG8) |

## 4. Gap list

| Id | Gap | Actors | Priority | Note |
|---|---|---|---|---|
| FEG1 | ERP pages: group structure, members, settings, activity, approval flows, approvals inbox, delegations, workforce plans, requisitions, read across tenants, support grants; admin groups and support reads | group admin, approver, hiring manager, recruiter, platform admin | P2 | Needs the decision of section 5 point 1 first. |
| FEG2 | Menus and page access by permission, with presets for hiring manager and interviewer | all company members | P1 | Replace the "Role" column of section 8 by the permission of each page. |
| FEG3 | One page numbering: add C5b, C27, C28 and the new pages, and fix the C5/C6 references of the designs | front end | P1 | Documentation only. |
| FEG4 | Route contract for every link in emails, notifications and `.ics` files, with the signed-out and wrong-user cases | all | P1 | Pairs with the `.env` URLs of the deployment checklist. |
| FEG5 | Two-factor in the public sign-in, enrolment (forced when required), recovery codes, settings | job seeker, company member, invited member | P1 | API coded. |
| FEG6 | Target page per notification type and per task item | all | P1 | Extends the notification catalog of 11.6. |
| FEG7 | Shared address picker (province to village, postal code) and the company locations page | company admin, recruiter, job seeker | P1 | API coded. |
| FEG8 | Announcement banner in the layouts | all | P1 | API coded. |
| FEG9 | Job seeker: data export, block and report in chat, talent pool consent, reschedule request, no-show and hired states, offer page, withdraw and decline reasons, interview place | job seeker | P1 | API coded. |
| FEG10 | Recruiter: all applicants (C5b), pipeline facets and stale marks, hire and fill dialogs, withdraw for the candidate, proposal refuse and no-show, results comparison, tasks card, bulk invitations, save to pool, email preview, offer form with terms and upload, hires export | recruiter | P1 | API coded. |
| FEG11 | Company admin: settings page, message defaults, activity log (C27), name change, team page and permission editor as coded | company admin | P1 | API coded. |
| FEG12 | Hiring manager and interviewer: review inbox and page (C28), bounded pipeline, "Wawancara saya", RSVP, limited application view | hiring manager, interviewer | P1 | API coded. |
| FEG13 | Psychologist: explanation template, auto-issue, mark reviewed, revise, account security | psychologist | P2 | API coded; #23 and #24 rows come later. |
| FEG14 | Admin: sidebar items D21 to D26, users read view, operations card, message report action, provider auto-issue | platform admin | P1 | API coded. |
| FEG15 | Carry every phase 8 "Front end" row (designed, not coded) into the page maps, marked with its item number so it is built when the API lands | all | P2 | #12, #14, #15, #20, #22 to #25, #31 to #33. |

## 5. Points for the user

1. **Where the ERP lives (FEG1, decision).** Options: (a) inside the company dashboard of the public Nuxt application, as more menu groups under
   `/rekruter` shown by group membership; (b) a third application `apps/erp` (Vite SPA like the admin panel) on its own domain for the ONESIS ERP
   side, with the company dashboard staying in the public application; (c) move the company dashboard out of the public application into one
   back-office SPA for all company and group work (ONESIS Talent, Workforce, Core), leaving the public application to visitors and job seekers.
   Recommended: **(c)**. Company members and group members are the same accounts, the ERP pages are back-office pages that do not need server
   rendering, and `naming.md` already splits the brand (ONESIS for the ERP side, ONESIS Career for job seekers); it changes the 2026-10-13 decision
   of two applications, so it needs your confirmation.
   **Decided by the user on 2026-10-19: (c)**, recorded in `frontend.md` section 3; its follow-up decisions are listed there.
2. **Menus by permission (FEG2).** Recommended: the menu of the company area is built from `GET company/me` permissions and group membership, with
   a landing page per preset (interviewer to "Wawancara saya", hiring manager to the review inbox, recruiter and company admin to the dashboard).
   **Decided by the user on 2026-10-19: as recommended** (`frontend.md` section 8).
3. **Bringing `frontend.md` up to date (FEG3, FEG9 to FEG15).** Recommended: one design session that rewrites the page maps of sections 7 to 9 and
   the menus of 11.1 from the routes of today and the phase 8 rows, before any front-end coding starts.
   **Decided by the user on 2026-10-19: as recommended** (`frontend.md` section 3).
4. **Email link contract (FEG4).** Recommended: a table in `frontend.md` of every link the API sends (setting, path, signed-out case), and the API
   settings pointed at those paths.
   **Decided by the user on 2026-10-19: as recommended**, written in the page-map session (`frontend.md` section 3).
