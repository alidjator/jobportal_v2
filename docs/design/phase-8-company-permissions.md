# Phase 8: per-feature permissions for company members

Context: `Company` (catalog, storage, team API), read through `Shared` by every context with company routes (Job, Recruitment, Messaging, TalentPool,
JobSeeker candidates, JobInvitation, Assessment, Campaign, Review, Dashboard). Source: the user stories "invite a new member by email and set their
permissions" and "change a member's role and permissions so access always matches their responsibility" (company admin, 2026-10-13).

**Status: coded** (part 1 permissions, part 2 team; see the two "coded" sections at the end), uncommitted part 2, unchecked.

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
- 2026-10-13: the story "extra team actions" (resend, revoke, suspend, reactivate, detail and activity, make primary account) adds **one owner per
  company**, **temporary suspension of a membership**, **expired invitations with resend** and **member detail with activity from the audit log**
  (decided by the user, [detail](#team-actions)); its five interpretations decided the same day, the activity shows IP address and user agent.
- 2026-10-14: the story "see every team member with role and permissions" (AC1 to AC6): the member list is **paginated in the API** (20 per page,
  with the total for "N anggota tim"), each row carries its allowed **`actions`**, and the **owner comes first** (decided by the user, [API](#api)).
- 2026-10-14: the story "invite a member and set their permissions" (AC1 to AC8): the invitation carries a **name** the invitee may change; a
  member needs **at least one permission** (AC5, replacing "may be empty"); an optional **allowed email domains** setting per company, off by default;
  open invitations are **rows of the team list** with status "Menunggu" or "Kedaluwarsa", and `GET company/invitations` goes (decided by the user).
- 2026-10-14: the story "change a member's role and permissions" (AC1 to AC5): one `PATCH company/members/{userId}` for name, role and
  permissions; a company admin may change a member's **name** (audited, the member is notified in-app); pending invitations are editable
  (`PATCH company/invitations/{id}`) (decided by the user).
- 2026-10-14: the story "remove a member who no longer needs access" (AC1 to AC6): removing **oneself is refused** (409 `cannot-remove-self`);
  a job left **without assignees** goes to **the owner or a company admin picked** in the dialog (changed 2026-10-14: a job that still has assignees just loses the member); `created_by_user_id` **stays** as
  history (decided by the user, [Remove](#remove)).
- 2026-10-14: the story "see the team's change history" (AC1, AC2): a **team history** for company admins, `GET company/team/history`, from the
  audit log; the **12-month retention stays** ("cannot be deleted" means by no user); **no IP address or user agent** in it (decided by the user,
  [Team history](#team-history)).
- 2026-10-14: coding review (decided by the user): **`analytics.view` leaves the Recruiter preset** (12 permissions; analytics was company-admin only, so recruiters
  keep today's access); **reading test results needs `assessments.send` or being an assessor of that test**; the **unread conversations dashboard card is split
  per audience**, the company one needing `messages.send` ([detail](#part-1-coded-2026-10-14-permissions)).
- 2026-10-14: the story "search members by name or email": `filter[q]` on the member list ([API](#api)); debounce, the 2-character start, the empty
  message and clearing are front-end behaviour.

## Team actions

Story (company admin, 2026-10-13): extra actions per row of the team page.

| Action | Shown when | Endpoint |
|---|---|---|
| Resend invitation | pending or expired | `POST company/invitations/{id}/resend` (new) |
| Revoke invitation | pending | `DELETE company/invitations/{id}` (exists) |
| Suspend | member active | `POST company/members/{userId}/suspend` (new) |
| Reactivate | member suspended | `POST company/members/{userId}/reactivate` (new) |
| Detail and activity | always | `GET company/members/{userId}`, `GET .../activity` (new); `GET .../jobs` (exists) |
| Make primary account | only for the current owner | `POST company/members/{userId}/make-owner` (new) |
| Remove | not the owner, not oneself | `DELETE company/members/{userId}` (exists, changed, [Remove](#remove)) |

### Team history

- `GET company/team/history` (company admin, `company-admin-required`): the audit entries of the team of the caller's company (subject `company`
  with the company's id, team actions only): invitation sent, updated, resent, revoked; member joined, renamed, role changed, permissions changed,
  suspended, reactivated, removed (with the recipient of the jobs); owner transferred; allowed email domains changed.
- Newest first, cursor paginated; `filter[action]`, `filter[user_id]` (the member concerned) and a date range.
- Each entry: time, actor (name; a platform admin shows as "Admin platform" without a name), action, the member or invitation concerned (name and
  email), and the change (before and after, permissions added and removed). **No IP address or user agent** (they stay in the member activity).
- Read-only: there is no endpoint to change or delete an entry. Entries go only by the platform's 12-month retention purge (AC2, decided by the user).
- Read through the `Shared` port of the member activity (`MemberActivity`, extended with a team query), provided by Platform.
- Front end: a "Riwayat" tab on the team page (C22).

### Remove

- Stays as coded (`RemoveMember`): the membership is released, the account deactivated with every token revoked (sessions end at once), not
  anonymised; the last active company admin cannot be removed (`last-company-admin`).
- **New refusals:** the owner (409 `member-is-owner`) and oneself (409 `cannot-remove-self`, replacing "themselves included"; a company admin leaves
  by being removed by another company admin).
- **Jobs (AC5):** optional body `{transfer_to_user_id}`, an active company admin of the company other than the removed member (409
  `transfer-target-not-company-admin` otherwise); empty means the owner. The listener `ReassignJobsWhenMemberRemoved` ends the member's assignments
  and assigns the recipient to **every job that is left without assignees** (a job that still has other assignees keeps them and gets no recipient;
  changed 2026-10-14 from "every job the member held"), replacing "a job left without assignee goes to every company admin". Jobs are never deleted; `created_by_user_id` keeps the original creator. The event `CompanyMemberRemoved` carries the
  recipient; the recipient gets the existing assignment notification.
- The confirmation dialog with the member's name (AC1), hiding the icon through `actions` (AC3) and refreshing `meta.member_count` (AC6) are
  front-end behaviour.

### Owner (primary account)

- **Exactly one owner per company**, a company admin flagged `company_members.is_owner` (partial unique index per company).
- **Protected:** nobody can suspend, remove or demote the owner (409 `member-is-owner`); the owner leaves or is demoted only after handing the
  ownership over.
- **Transfer:** only the owner (403 `owner-required`), to an **active company admin** of the same company (409 `owner-target-not-company-admin`
  otherwise), with the owner's current password in the request (422 `invalid-password`). The former owner stays a company admin. Audited
  (`company.owner_transferred`); both get an in-app notification and an email.
- **Platform admin:** `PUT admin/companies/{id}/owner {user_id}` (D4, `companies.manage`) when the owner is gone; same target rule, audited.
- **First owner:** the first company admin who joins a new company (the admin-created invitation). Migration and legacy import: the oldest company
  admin of each company.
- `GET company/members` and `GET company/me` show `is_owner`.

### Temporary suspension

- `company_members.status`: `active` or `suspended` (plus `suspended_at`, `suspended_by_user_id`); separate from the account status a platform admin
  sets (D21), and both block.
- **Suspend:** a company admin, not on themselves (409 `cannot-suspend-self`) or the owner; every token of the member is revoked; sign-in answers 403
  `membership-suspended`; an email tells the member (optional `message` in the request, at most 500 characters). Audited
  (`company.member_suspended`).
- **Kept while suspended:** job assignments, interview panels, permissions, notes and conversations, so reactivation restores everything.
- **Not sent while suspended:** notifications and emails to the member. A job whose assignees are all suspended sends its assignee notifications to
  the company admins instead.
- **Last company admin:** a suspended company admin does not count, so the last active company admin cannot be suspended (`last-company-admin`).
- **Reactivate:** a company admin; status back to `active`; email to the member; audited (`company.member_reactivated`). The member signs in again.
- The member list shows the status; suspended members stay in the list and in the member jobs list.

### Invitations: expired and resend

- The team list (`GET company/members`, rows of `type` `invitation`) shows `pending` and `expired` invitations; an expired one is listed for 30
  days after it expired.
- **Resend** (pending or expired): a new token and 7 more days, same email, role and permissions; the old link stops working; the one-minute cooldown of
  inviting applies (429). Audited as `company.invitation_resent`.
- An accepted or revoked invitation is not listed. Rows are deleted 30 days after expiry, revocation or acceptance (daily purge; they hold an email).

### Member detail and activity

- `GET company/members/{userId}`: name, email, role, owner flag, permissions with the preset name, membership status (with suspension time and who),
  joined, last sign-in, number of assigned jobs.
- `GET company/members/{userId}/activity`: the audit entries whose actor is the member (one account belongs to one company), newest first, cursor
  paginated, 12 months (the audit retention); `filter[action]` and a date range. Each entry: time, action, subject type and id with a readable title
  when the subject still exists, **IP address and user agent** (shown, decided by the user, for spotting suspicious sign-ins; the privacy notice must
  say that company admins see them, `docs/legal-review.md`).
- The audit log belongs to Platform: Company reads it through a new `Shared` port (`MemberActivity`), provided by Platform.
- Team management rule: all six actions need role `company_admin` (`company-admin-required`), the owner transfer needs the owner.

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
| | `analytics.view` | analytics reports | C2 | no |

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
- `company_invitations.name` (string 100): the name the company admin typed; the invitee may change it when accepting.
- `company_companies.invitation_email_domains` (jsonb list, null = no limit): checked on invitation and resend by a company admin, not on an
  invitation by a platform admin.
- `company_invitations.permissions` (jsonb, null for a company admin invitation): the checkboxes chosen at invitation, applied when it is accepted;
  `company_invitations.role` takes `company_admin` or `member`.
- Migration: every `recruiter` membership gets the Recruiter preset rows; every pending `recruiter` invitation gets the Recruiter preset.
- Legacy import: legacy companies have one user, imported as `company_admin` (unchanged).

## API

| Endpoint | Change |
|---|---|
| `GET company/permissions` | New (any member): the catalog with groups and the presets (name and permissions), for the invitation and edit dialogs. |
| `POST company/invitations` | `name` (required, at most 100 characters), `email`, `role` (`company_admin` or `member`) and, for `member`, `permissions` (**at least one**, 422 `permissions-required`; unknown names 422). Refused with 422 `email-domain-not-allowed` when the company limits the email domains. |
| `GET company/invitations` | **Removed**: invitations are rows of the team list. |
| `GET`/`PUT company/team/email-domains` | New (company admin): the allowed email domains of invitations (list of domains, empty = no limit, the default). |
| `GET auth/invitations/{token}` | Adds `name`, which prefills the acceptance form; the invitee may change it on `accept`. |
| `GET company/members` | **One list of members and open invitations** (decided 2026-10-14): each row has `type` (`member` or `invitation`) and `status` (`active`, `suspended`, `pending`, `expired`); an invitation row shows name, email, role, permissions, sent and expiry times and the inviter. `meta.member_count` is the number of members of the company, unfiltered, for "N anggota tim". Each member shows `role`, `is_owner`, `status`, `permissions` (a company admin lists every permission) and `actions`, the row actions the caller may take now (`edit`, `remove`, `suspend`, `reactivate`, `make_owner`), computed by the same rules as the use cases. **Paginated** (`page`, `per_page` default 20, at most 100, with the total); fixed order: the owner, then the other company admins by name, then the members by name; an invitation sits in the group of its role, by name. **Search** `filter[q]`: name or email, case-insensitive, partial (`TextSearch::contains`), 2 to 100 characters (422 otherwise), invitations included; the total then counts the matches. |
| `PATCH company/members/{userId}` | New, **replaces `PUT .../role` and `PUT .../permissions`** (decided 2026-10-14): `{name?, role?, permissions?}` saved in one transaction. `name` changes the member's account name through a `Shared` port to Identity. `role` `company_admin` or `member`; a demotion without `permissions` gives the Recruiter preset; the last active company admin (`last-company-admin`) and the owner (`member-is-owner`) cannot be demoted. `permissions` for a `member` only (at least one, `permissions-required`; on a company admin 409 `member-is-company-admin`). Nothing changed: nothing saved or audited. The email cannot be changed. |
| `PATCH company/invitations/{id}` | New: `{name?, role?, permissions?}` of a pending invitation, same rules as inviting; the link stays the same; the email cannot be changed (revoke and invite again). |
| `GET company/team/history` | New (company admin): the team change history ([Team history](#team-history)). |
| `DELETE company/members/{userId}` | Optional `{transfer_to_user_id}`; refuses the owner and oneself ([Remove](#remove)). |
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

- New audit actions `company.member_permissions_changed` (before, added, removed, actor), `company.member_renamed` (old and new name, actor),
  `company.invitation_updated`, `company.invitation_email_domains_changed` (before and after), the existing removal entry gains the recipient of the jobs, and the existing role change entry gains the permissions after a
  demotion. The invitation entry records role and permissions (no email address, as today).
- In-app notifications `company.permissions_changed` and `company.member_renamed` to the member (no email), in the notification catalog of `phase-8-api-translations.md`.

## Front end

- C22: title "Tim Anda" with the total of the unfiltered list (kept while searching); a search field that queries after 2 characters with a
  300 ms debounce, shows "Anggota tim tidak ditemukan" on an empty result and reloads the full list when cleared; one "Semua izin" chip for a company admin instead of 21; row icons and the three-dot menu from `actions`;
  invitation dialog with a preset picker and the grouped checkboxes; the member list shows the preset name when the set matches one, else
  "Kustom"; an edit dialog per member.
- The company dashboard menu (section 8 of `frontend.md`) follows `GET company/me` permissions instead of the role.

## Team actions: interpretations decided

Proposed while designing and decided by the user on 2026-10-13:

1. **Owner transfer asks for the owner's current password** (agreed): it stops a transfer from a session left open.
2. **Jobs whose assignees are all suspended** (agreed): their assignee notifications go to the company admins until an assignee is active again.
3. **Activity shows IP address and user agent** (changed by the user): the proposal hid them; they are shown, and the privacy notice must say so.
4. **Invitation rows are deleted 30 days** after they expire, are revoked or accepted (agreed); the audit entries keep no email address.
5. **Notices** (agreed): suspension and reactivation email the member; an owner transfer sends an in-app notification and an email to both owners.

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

## Part 1 coded (2026-10-14): permissions

Coded: catalog (`Shared` enum `CompanyPermission`, 21), `CompanyMembership::can/require/effectivePermissions` and `Actor::can`, role `member`, presets
(`MemberPreset`), `MemberPermissionRules` (`permissions-required`, `permission-requires-view`), migration `000136` (**new, run `test:database:migrate
--env=testing` then `migrate`**), `GET company/permissions`, `POST company/invitations` with name and permissions, `PATCH company/members/{userId}`
(`role`, `permissions`; replaces `PUT .../role`), `company/me` with `permissions`, audit `company.member_permissions_changed` and in-app
`company.permissions_changed`, and the permission checks in Job, Recruitment, Assessment, Campaign, Review, TalentPool, JobSeeker candidates,
JobInvitation, Messaging, Dashboard and Company (profile, images, documents).

Part 2 (team) is coded, see "Part 2 coded (2026-10-14): team" below. The time-to-fill report is a separate piece.

Interpretations made while coding (to confirm):

1. `RecruitmentAccess::viewer` (needs `applications.view`) guards every job and applicant read; `permitted` and `anyOf` guard the libraries, which need
   no reading (rule 1). Ratings, notes, move, reject and bulk need `applications.manage`; scorecards, interview questions and panel, rooms, calls and
   recordings need `interviews.manage`; setting or clearing a job's default kit needs `jobs.create`; stages `stages.manage`.
2. `assessments.send` guards sending, cancelling and listing the tests of an application, and reading results, the report, integrity events and
   candidate videos (`VisibleInvitations::findForResults`); an assessor of that test reads what they score without it (decided by the user).
3. Any company-side chat use needs `messages.send`; the unread conversations card is one provider per audience, the company one with `messages.send`
   (decided by the user).
4. `analytics.view` is not in the Recruiter preset (decided by the user, replacing the catalog's earlier "yes"); migration `000136` does not grant it.
5. Reading the stage list, the company profile and the reviews list stays open to every member.
6. `CompanyTeam::viewAllUserIds` answers "does this account see every job" for interview and assessor assignment checks.

## Part 2 coded (2026-10-14): team

Coded: migration `000137` (**new, run `test:database:migrate --env=testing` then `migrate`**: `company_members.is_owner` with a partial unique index per
company and a check that only a company admin owns, `status`, `suspended_at`, `suspended_by_user_id`, `company_companies.invitation_email_domains`; the
oldest company admin of each company becomes its owner), `name` in `PATCH company/members/{userId}` (account renamed through `CompanyMemberAccounts::rename`,
audit `company.member_renamed`, in-app `company.member_renamed`), `PATCH company/invitations/{id}`, `POST company/invitations/{id}/resend`, the merged
paginated and searchable team list (`GET company/members`: `type`, `status`, `actions`, `is_owner`, `meta.member_count`), `GET company/members/{userId}`
and `.../activity`, `GET company/team/history`, `GET`/`PUT company/team/email-domains`, `POST company/members/{userId}/suspend`, `.../reactivate` and
`.../make-owner`, `PUT admin/companies/{id}/owner`, the new remove rules (`cannot-remove-self`, `member-is-owner`, `transfer_to_user_id`), `company/me`
with `is_owner`, the nightly `company:purge-invitations` (03:15), and four email templates (`company.member_suspended`, `company.member_reactivated`,
`company.ownership_gained`, `company.ownership_handed_over`). `GET company/invitations` is gone.

Interpretations made while coding (to confirm):

1. **A suspended member** is stopped at sign-in by a `Shared` port (`MemberSignInGuard`, Identity asks Company); the suspension revokes every token. The
   last-admin rule counts **active** admins only (`lockCompanyAdminIds` skips suspended ones).
2. **Notifications while suspended** are held back centrally: `DatabaseInAppNotifier` and `StoredNotificationPreferences` (which every email and push asks)
   ask the `Shared` port `SuspendedMembers`. A job whose assignees are all suspended notifies the active company admins
   (`JobDirectory::notificationRecipients`, used by the application, decision and expiry listeners).
3. **Removing a member:** a job left without assignees goes to `transfer_to_user_id` or else to the owner; a job with other assignees keeps them
   (`ReassignJobsOfRemovedMember`, decided by the user 2026-10-14); when the company has no owner (the owner's account was erased) such a job goes to the remaining company admins. Anonymising an account
   releases it as a removal without a recipient.
4. **The owner** cannot start deleting their own account while another admin exists (`member-is-owner`, after the `last-company-admin` check); they hand the
   ownership over first. A company whose owner is gone gets one from a platform admin (`PUT admin/companies/{id}/owner`, no password).
5. **Email domains** match exactly (`mail.example.com` is not `example.com`); at most 20; lower case; a leading `@` is stripped; an empty list lifts the limit.
   The refusal carries `allowed_domains`.
6. **Activity** shows a readable title for `job` subjects only; other subjects show type and id. The history resolves the name and email of the member
   or invitation from the live records, so they are empty once an account or invitation is gone.
7. **The team list is built in memory** (a team is small), so `per_page` slices the sorted list; a pending invitation sits in the group of its role.
8. `member_count` counts members only (invitations excluded), search aside.
