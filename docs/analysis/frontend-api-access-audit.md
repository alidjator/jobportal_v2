# Workspace menu against the API: who sees a page and who may call it (audit of 2026-10-29)

The user's rule: **the front end and the API agree on permissions. When the API refuses a member, the front end does not show the page to them; hiding the menu item is not enough.**
This audit compares, for each entry of the Workspace menu (`apps/workspace/app/utils/menu.ts`), three things: the `access` of the menu entry, the `access` of the page (`<PageAccess>`), and what the API asks for on the list call the page makes first.
Read by hand from the use cases (no test was run). Group pages and company pages only; Console and Portal Mitra are not covered.

## How the front end decides today

- **Menu:** `access` per entry in `menu.ts`.
- **Page:** a second, separate copy of the rule in `<PageAccess :access="...">` on each page. A page without a rule (`<PageAccess>`) is open to every member. There is **no route guard**: a member who types the address reaches the page, which shows the "not allowed" panel only when `PageAccess` says so, and otherwise the API answers 403 inside the page (the screening question bank on 2026-10-29).
- **Contract with the API:** none. The permission strings are typed by hand in about 50 places and nothing compares them with the API.

## Status (2026-10-29)

Proposal A is coded in the front end: one `definePageMeta({ access })` per page, the route guard `middleware/route-access.global.ts`, the page `/tidak-diizinkan`, and the menu reading the page's rule; `<PageAccess>` was removed. Findings 1 and 2 are fixed by it. Proposals B, C and D are open. Detail pages and write buttons are still to audit.

## Findings: the front end shows a page the API refuses (fixed by A)

| # | Page | Front end | API (list call) | Who is wrongly shown the page |
|---|---|---|---|---|
| 1 | Screening question bank `/pertanyaan-screening` | menu: fixed 2026-10-29 (`jobs.create` or `screening_questions.manage`); `PageAccess` has **no rule** | `GET company/screening-questions`: `jobs.create` or `screening_questions.manage` | Any member who opens the address (the interviewer in the report) |
| 2 | Tests `/asesmen/tes` | menu: `assessments.author`; `PageAccess` of the list: `assessments.author` **or `assessments.send`** | `GET company/assessment/tests`: `assessments.author` | A member with only `assessments.send` |
| 3 | My interviews `/wawancara` | menu: `interviews.participate`; `PageAccess`: `interviews.participate` **or `interviews.manage`** | `GET company/interviews`: any member (the "all of the company" view needs `interviews.manage`) | Not a refusal; the menu is narrower than the page (see "Menu and page disagree") |

Only #1 and #2 are refusals. Everything else in the table below agrees or is stricter in the front end.

## Menu and page disagree (same page, two rules)

| Page | Menu | `PageAccess` |
|---|---|---|
| `/wawancara` | `interviews.participate` | `interviews.participate`, `interviews.manage` |
| `/asesmen/tes` | `assessments.author` | `assessments.author`, `assessments.send` |
| `/pertanyaan-screening` | `jobs.create`, `screening_questions.manage` | none |
| `/asesmen/penilaian` | none | none (the API needs only a company member; the list is the member's own invitations to score) |
| `/persetujuan`, `/persetujuan/delegasi`, `/persetujuan/[id]` | `APPROVALS` (approvals or workforce permission, a group role, or a place in an approval) | none |

## Entry by entry

`any member` = a member of an active company. `viewer` = `applications.view` (`RecruitmentAccess::viewer`). "Stricter" means the front end asks for more than the API; it is safe, but a member the API would serve never sees the page.

| Menu entry | Front end (menu) | API requirement | Result |
|---|---|---|---|
| Dashboard `/` | open | any member (`GetCompanyDashboard`; `jobs.view_all` widens it) | agrees |
| My interviews | `interviews.participate` | any member; `interviews.manage` for all | stricter |
| Candidate reviews `/review` | `applications.review` | `applications.review` (`applications.manage` for the requester side) | agrees |
| Tests to score `/asesmen/penilaian` | open | any member | agrees |
| Calendar `/kalender` | `applications.view` or `interviews.participate` | the same (panel-only view when only `interviews.participate`) | agrees |
| Analytics `/analitik` | `analytics.view` | `analytics.view` | agrees |
| Jobs `/lowongan` | `applications.view` | any member (sees assigned jobs; `jobs.view_all` sees all) | stricter |
| Applicants `/pelamar` | `applications.view` | `applications.view` | agrees |
| Candidates `/kandidat` | `candidates.search` | `candidates.search` | agrees |
| Talent pool | `talent_pool.manage` | `talent_pool.manage` | agrees |
| Messages `/pesan` | `messages.send` | `messages.send` | agrees |
| Tests `/asesmen/tes` | `assessments.author` | `assessments.author` | agrees (page: finding 2) |
| Interview kits | `interview_kits.manage` | `jobs.create`, `interviews.manage` or `interview_kits.manage` | stricter |
| Screening questions | `jobs.create`, `screening_questions.manage` | the same | agrees (page: finding 1) |
| Screening presets | `screening_questions.manage` | `jobs.create` or `screening_questions.manage` | stricter |
| Stages | `stages.manage` | any member | stricter |
| Campaigns | `campaigns.manage` | `campaigns.manage` | agrees |
| Approvals | `APPROVALS` | any member (`MyApprovals`) | stricter |
| Approval delegations | `APPROVALS` | not read (the use case was not located); assumed any member | to verify |
| Approval flows | `approvals.manage` or `group.approvals.manage` | `approvals.manage` (company), `group.approvals.manage` (group) | agrees |
| Workforce plans | `workforce.plans.view` or `.manage` | the same | agrees |
| Requisitions | `workforce.requisitions.view` or `.create` | the same | agrees |
| Group, units, positions, grades, cost centres | any group member | any group member | agrees |
| Group members | `group.members.manage` | `group.members.manage` | agrees |
| Group workforce plans | `group.workforce.view` or `.manage` | the same | agrees |
| Group recruitment | `group.recruitment.view` | `group.recruitment.view` | agrees |
| Group settings | `group.settings.manage` | any group member reads; `group.settings.manage` changes | stricter |
| Group portal | `group.settings.manage` | `group.settings.manage` | agrees |
| Group log | `group.audit.view` | `group.audit.view` | agrees |
| Support access | `group.members.manage` | `group.members.manage` | agrees |
| Company profile, locations | `company.profile` | any member reads | stricter |
| Documents, company portal | `company.profile` | `company.profile` | agrees |
| Reviews of the company `/ulasan` | `reviews.reply` | any member reads; `reviews.reply` replies | stricter |
| Team, settings, activity | company admin | company admin (team, activity); any member reads settings | agrees / stricter |
| Notifications, account | open | any signed-in user | agrees |

Not verified by reading: the detail pages (`/lowongan/[id]/...`, `/pelamar/[id]`, `/wawancara/[id]`, the review, approval and workforce details) and every write action (buttons that call a POST/PATCH the member may not hold). Their rules are in `PageAccess` and in `permissions.has(...)` checks inside the pages.

## Why this keeps happening

1. The rule is written twice in the front end (menu and `PageAccess`) and once in the API, by hand.
2. A page may call several endpoints with different permissions; nothing says which one decides whether the page is shown.
3. No route guard; no test compares the front end with the API.

## Proposal (to decide)

**A. One rule per page, enforced on the route.** The page declares `definePageMeta({ access })`; a global middleware (`access.global.ts`) checks it and sends the member to a forbidden page before the page renders; the menu reads the same declaration. `<PageAccess>` and the second copy in `menu.ts` go away. The pages of the list above are the first to move.

**B. A shared access matrix that both sides test.** A file `docs/api/access-matrix.json` lists, per list endpoint, the permissions that open it (`anyOf`). The API has a test that calls each endpoint as a member holding exactly one permission and checks 200 or 403 against the matrix; the front end has a test that every menu and page `access` is **a subset of** what the matrix allows for its endpoint. A new endpoint or a changed rule then fails a test on one side or the other.

**C. The stricter-than-API entries** (stages, approvals, group settings, company profile and locations, reviews, jobs list, presets, interview kits): decide per entry whether the front end keeps the stricter rule (a documented choice in the matrix) or the API tightens to match.

**D. Write actions.** A button that calls an endpoint the member may not use is shown today unless the page checks it. Audit every write button after A and B.

## Round 2: tabs, cards, queries and links inside the pages (2026-10-29)

Rule: `onesis-talent/CLAUDE.md` item 20 (the front end shows only what the API lets the member do, for every element that calls an endpoint). Read by hand from the use cases; no test was run.

| # | Where | Front end | API | Status |
|---|---|---|---|---|
| F1 | Tab "Tes" of the application detail | `applications.view` | `GET company/assessment/applications/{id}/invitations`: `assessments.send` | **Fixed 2026-10-29** (tab and pane follow `assessments.send`) |
| F2 | Card "default test per stage" of the job's tab "Tahapan" | query always runs | `GET company/jobs/{id}/assessments`: `assessments.send` | **Fixed 2026-10-29** (query `enabled` and card drawn only with `assessments.send`) |
| F3 | Delegate picker of `/persetujuan/delegasi` (E2) | any approver or holder of a workforce permission opens the page and reads `GET company/members` | company admin only (`CompanyAdminGuard`) | **Fixed 2026-10-29**: new `GET company/approval-people` (any company member; id and name of the active colleagues of the own company, nothing else). |
| F4 | Names in the flow text (`useFlowText`: approval flow editor and summary) | `GET company/members` for everyone | company admin only | **Fixed 2026-10-29** (same endpoint). |
| F5 | Member filter of `/organisasi/log-aktivitas` | the call is made with `group.audit.view` | `GET organization/members`: `group.members.manage` | **Fixed 2026-10-29** (query `enabled` with `group.members.manage`). |
| F6 | Dialog "replace the approver" (`ApprovalReplaceDialog`) | `GET company/members` | company admin only, but the replacer may be a holder of `group.approvals.manage` who is not a company admin | **Fixed 2026-10-29** (same endpoint; the dialog shows names only, no email). |
| F7 | Dialog "add a group member" (`OrganizationRoleDialog`) | `GET company/members` for a holder of `group.members.manage` | company admin only | **Fixed 2026-10-29**: new `GET organization/member-candidates` (`group.members.manage`; id, name and tenant of the active members of every tenant of the group). Also fixes the list, which held only the own company although the role accepts any tenant of the group. |
| F8 | "Undang ke lowongan" of the talent pool (list and entry) and its job list | page and button: `talent_pool.manage` | `POST company/jobs/{id}/invitations/bulk`: `candidates.search`; `GET company/jobs` (the job picker): `applications.view` | **Fixed 2026-10-29**: the selection column, the selection bar and the invite button need `candidates.search` and `applications.view`. |
| F9 | Result page of a test `/asesmen/hasil/{id}` (C16) | the integrity log was always read, the scoring answers for every non-psychological test | `integrity-events`: an assessor of the test or `assessments.send`; `scoring`: an assessor of the test only (403 `not-an-assessor`) | **Fixed 2026-10-29** (front end): the integrity card and call need an assessor or `assessments.send`; the scoring call needs an assessor; the dead text "read only" is removed. Decided: a holder of `assessments.send` who is not an assessor does not read the answers and scores. |
| F10 | Dialog "close the job?" offered after a hire that fills the positions (`ApplicationActions`, `ApplicantMoveFlow`) | shown to everyone who may hire (`applications.manage`) | `POST company/jobs/{id}/fill` with `close: true` closes through `CloseJob`: `jobs.publish` | **Fixed 2026-10-29**: the dialog is offered only with `jobs.publish`. |
| F11 | Boundary select of "Ubah tim" (`JobTeamDialog`) | shown with the dialog (`jobs.assign`) whenever the team has a hiring manager | `PUT company/jobs/{id}/hiring-manager-boundary`: `jobs.create` (`SetHiringManagerBoundary`) | **Fixed 2026-10-29**: the select, its stage list and its call need `jobs.create`. |

Checked and consistent (front end equal to or stricter than the API): tabs Catatan, Rating, Wawancara, Penawaran, Penyaringan, Kinerja and hasil wawancara; the tabs of the job; the requisition of the job (`requisitions.view` or `create`); the workforce options (opened only in an edit); the menu counts (gated by the page rule); the pages of the talent pool, campaigns, messages, candidate search and invitations; the group structure reads (`groupMember`); the interview detail (`panelOpens`); the review inbox; the company reviews (any member).

Round 3 (all categories of rule 20; read by hand from the use cases): **notifications**: the page each type opens against who receives it (company admins, job assignees, reviewers, interview and assessment participants, the sender of an invitation, the members of a conversation) is consistent, since every recipient group holds the permission of the page. **Dashboard tasks**: each provider's permission (`MyReviewsTask`, `OffersWaitingTask`, ...) matches the page its link opens, except two edge cases that need a custom permission set: `offers_waiting` (`offers.manage`) and `jobs_draft` (`jobs.create`) open pages that need `applications.view`. **Row actions** of jobs, offers and interviews follow the API; notes may be edited and deleted only by their author in the front end while the API also lets a company admin delete (stricter, accepted). **Data scope**: the recruiter's assignee scope and the group pages (`group.*`) agree with the API; only the hiring manager's stage boundary needed a notice (H2).

Not verified: Console and Portal Mitra, and the fields of forms that the API silently ignores for a member (only the cost fields of requisitions were read, and they follow `workforce.costs.view`).
