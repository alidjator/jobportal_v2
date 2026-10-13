# Phase 8: per-feature permissions for company members

Context: `Company` (catalog, storage, team API), read through `Shared` by every context with company routes (Job, Recruitment, Messaging, TalentPool,
JobSeeker candidates, JobInvitation, Assessment, Campaign, Review, Dashboard). Source: the user stories "invite a new member by email and set their
permissions" and "change a member's role and permissions so access always matches their responsibility" (company admin, 2026-10-13).

**Status: designed, not coded.** Coding happens in a coding session, before go-live.

## Decisions

- 2026-10-13: the stories require per-feature permissions; the two fixed roles are not enough (decided by the user).
- 2026-10-13: model **preset plus checkboxes per member**: a fixed platform catalog of permissions; inviting a member picks a preset and may add or remove
  permissions per person. The company admin stays a role apart that holds every permission and cannot be reduced. Job assignment stays the data boundary
  (decision gate, decided by the user; custom roles per company and extra fixed roles were the alternatives).
- 2026-10-13: the catalog below agreed as proposed (20 permissions, 5 groups).
- 2026-10-13: **team management stays with company admins only** and is not in the catalog, so no member can raise their own permissions.
- 2026-10-13: a change takes effect **at once, tokens kept**: permissions are read on every request; the change is audited with before and after and
  the member gets an in-app notification.
- 2026-10-13: presets **Company admin, Recruiter, Interviewer**.
- 2026-10-13: a company admin who is demoted gets the **Recruiter preset**; the company admin changes the checkboxes afterwards.
- 2026-10-13: coded **before go-live** (decided by the user), in a separate coding session.
- 2026-10-13: the five interpretations decided by the user ([detail](#interpretations-decided)): role value `member`; **no base access**, reading
  needs the new permission `applications.view` (catalog now 21), which every job and applicant action requires; interviewers must be assigned to the
  job; category edits follow `talent_pool.manage`; "the person concerned or a company admin" overrides stay on the role.

## Catalog

The catalog is code (an enum in `Shared`, like the admin `PermissionCatalog`), not a table a company edits. "Recruiter" marks the Recruiter preset,
which is exactly what a recruiter may do today, so imported and existing recruiters keep their access.

| Group | Permission | Covers | Pages | Recruiter |
|---|---|---|---|---|
| Jobs | `jobs.create` | create, edit, clone, delete a draft | C4, C5 | yes |
| | `jobs.publish` | publish, close, reopen | C5 | yes |
| | `jobs.assign` | replace the assignees of a job | C4 | no |
| | `jobs.view_all` | every job of the company, not only the assigned ones (the scope a company admin has today) | C3 to C10 | no |
| Applicants | `applications.view` | read the assigned jobs (every job with `jobs.view_all`) and their applicants: job list and detail, pipeline, application detail, CV | C3, C5, C6, C7 | yes |
| | `applications.manage` | move, reject, notes, ratings, bulk actions | C6, C7 | yes |
| | `applications.export` | applicant CSV export (R13) | C6 | yes |
| | `interviews.manage` | schedule, panel, kit copy, video and voice rooms, recordings | C7 to C10 | yes |
| | `offers.manage` | make, revise and withdraw an offer | C7 | yes |
| | `assessments.send` | send a test to an applicant, read results; scoring stays with the job's assessors (`not-an-assessor`) | C7, C16 | yes |
| Sourcing | `candidates.search` | candidate search, suggestions, invitations to apply | C11, C12 | yes |
| | `talent_pool.manage` | the talent pool and its categories | C13 | yes |
| | `messages.send` | conversations with candidates | C14 | yes |
| Library | `interview_kits.manage` | interview kits | C17 | yes |
| | `screening_questions.manage` | writing to the screening question bank (reading stays open to every member) | C18 | no |
| | `assessments.author` | writing company tests (`AuthoringAccess`) | C15 | no |
| | `stages.manage` | pipeline stages | C19 | no |
| Company | `company.profile` | profile, slug, logo and banner, verification documents | C20, C21 | no |
| | `campaigns.manage` | campaigns | C23 | no |
| | `reviews.reply` | reply to and report reviews | C24 | no |
| | `analytics.view` | analytics reports | C2 | yes |

**Presets** (labels for the front end; not stored):

- **Company admin:** role `company_admin`, every permission, plus team management (C22). Cannot be changed by checkboxes.
- **Recruiter:** role `member` with the permissions marked "yes" above.
- **Interviewer:** role `member` with `applications.view` and `interviews.manage`: reads the applicants of the jobs they are assigned to and takes part
  in interviews, without moving, rejecting, offers or export.

## Rules

1. **No base access:** a member with no permission signs in and reaches only their own account and notifications (C25) and an empty dashboard;
   reading jobs and applicants needs `applications.view`. The dashboard (C1) shows only the cards whose permission the member holds. Reading the
   screening question bank comes with `jobs.create` or `screening_questions.manage`; reading interview kits with `jobs.create`, `interviews.manage` or
   `interview_kits.manage`.
2. **Scope:** without `jobs.view_all` a member sees only the jobs they are assigned to and what hangs on them (applications, interviews,
   conversations about them), as a recruiter does today. With it, the scope is the company admin's.
3. **Reading is required:** `jobs.create`, `jobs.publish`, `jobs.assign`, `jobs.view_all`, `applications.manage`, `applications.export`,
   `interviews.manage`, `offers.manage` and `assessments.send` require `applications.view`. The front end ticks it with any of them; the API refuses a
   set without it (422 `permission-requires-view`, on invitation and on change). Other combinations are free.
4. **Team management** (`company/members`, `company/invitations`, the member jobs list) needs role `company_admin`; error `company-admin-required` stays.
5. **Last company admin** rules stay (`last-company-admin`).
6. **Company-level notifications** that go to company admins today (`NotifyCompanyAdmins`: verification, reviews) keep going to company admins.
7. **"The person concerned or a company admin" rules stay on the role:** where today an action is allowed to a specific person or a company admin (the
   scheduler, the main interviewer or a company admin edits an interview's questions or panel; the panel or a company admin deletes a recording; a
   note's author or a company admin deletes a note), the person still needs the permission of the action, and the override stays with role
   `company_admin`, not with `jobs.view_all`.

## Storage

- `company_members.role`: `company_admin` or `member` (the value `recruiter` is migrated to `member`; check constraint changed).
- New table `company_member_permissions` (`company_member_id` FK cascade, `permission` string, unique pair); no rows for a company admin.
- `company_invitations.permissions` (jsonb, null for a company admin invitation): the checkboxes chosen at invitation, applied when it is accepted;
  `company_invitations.role` takes `company_admin` or `member`.
- Migration: every `recruiter` membership gets the Recruiter preset rows; every pending `recruiter` invitation gets the Recruiter preset.
- Legacy import: legacy companies have one user, imported as `company_admin` (unchanged).

## API

| Endpoint | Change |
|---|---|
| `GET company/permissions` | New (any member): the catalog with groups and the presets (name and permissions), for the invitation and edit dialogs. |
| `POST company/invitations` | `role` (`company_admin` or `member`) and, for `member`, `permissions` (list, may be empty; unknown names 422). |
| `GET company/invitations` | Each pending invitation shows `role` and `permissions`. |
| `GET company/members` | Each member shows `role` and `permissions` (a company admin lists every permission). |
| `PUT company/members/{userId}/permissions` | New: replaces a member's permissions (`member` only; on a company admin 409 `member-is-company-admin`); same set changes and audits nothing. |
| `PUT company/members/{userId}/role` | `company_admin` or `member`; a demotion gives the Recruiter preset. |
| `GET company/me` | Adds `role` and the effective `permissions`, so the front end builds the menu. |

New error: 403 `company-permission-required` with the missing permission in the problem body (`permission`), replacing `company-admin-required`
everywhere except team management.

## Checks in the code

- `Shared\Application\Company\CompanyMembership` carries the permissions; a `can(CompanyPermission)` answers true for a company admin. The resolver
  reads them in the same query as the membership.
- Each context's access class (`JobAccess`, `RecruitmentAccess`, `CampaignAccess`, `TalentPoolAccess`, `CandidateAccess`, `InvitationAccess`,
  `ConversationAccess`, `AuthoringAccess`, `ReviewGate`, Company profile, documents and images) replaces "is a company admin" with the permission of
  the action, and the scope rule "company admin sees every job" with `jobs.view_all`. About 40 places in 12 contexts (counted 2026-10-13).
- `JobDirectory::visibleToMember` takes the scope flag from `jobs.view_all` instead of `isCompanyAdmin`.
- Reads that every member could do today (job list and detail, pipeline, application detail, CV, interviews of an assigned job) now check
  `applications.view` first, so the read paths change too, not only the company-admin-only ones.

## Audit and notification

- New audit actions `company.member_permissions_changed` (before, added, removed, actor) and the existing role change entry gains the permissions after a
  demotion. The invitation entry records role and permissions (no email address, as today).
- In-app notification `company.permissions_changed` to the member (no email), in the notification catalog of `phase-8-api-translations.md`.

## Front end

- C22: invitation dialog with a preset picker and the grouped checkboxes; the member list shows the preset name when the set matches one, else
  "Kustom"; an edit dialog per member.
- The company dashboard menu (section 8 of `frontend.md`) follows `GET company/me` permissions instead of the role.

## Interpretations decided

Proposed while designing and decided by the user on 2026-10-13:

1. **Role value `member`** (agreed): `recruiter` becomes `member`, because a member with the Interviewer preset is not a recruiter; "Recruiter" stays a
   preset name. No client uses the API yet, so the rename costs nothing outside the code and tests.
2. **No base access** (changed by the user): the proposal let a member without permissions read their assigned jobs and applicants; instead reading
   needs `applications.view` (one permission for jobs and applicants, chosen over separate `jobs.view` and `applications.view`), and the actions require
   it (rule 3).
3. **Interviewers are assigned to the job** (agreed): being on an interview panel alone does not open the application; one scope rule for everyone.
4. **Talent pool categories** (agreed): renaming and deleting a category is part of `talent_pool.manage`, so the Recruiter preset gains it.
5. **Overrides on the role** (agreed): rule 7; a member with `jobs.view_all` sees every interview but cannot delete another panel's recording or another
   author's note, nor edit another scheduler's interview questions or panel.
