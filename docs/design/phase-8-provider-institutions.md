# Phase 8: partner institutions: team self-management, licence renewals, institution identity and signature, revising signed reports, review targets and questions about a report (designed 2026-10-15, not coded)

Gaps found from the partner psychologist's and institution's point of view, after `phase-8-provider-tools.md`, and against Mekari Talenta with
LPTUI (help center articles 60386481548697, 60148920477977 and 60149003542169: one partner, fixed batteries, reports with the institution's official
number and a recommendation; no provider area, no channel from the company or the candidate to the psychologist), chosen by the user from the
advice of 2026-10-15. Our model has many institutions working inside the platform, so most gaps have no Talenta counterpart. **Needed before the
first partner institution is switched on**, not for the go-live of the portal. Not taken now: consultation between the candidate and the
psychologist (the explanation page shows the institution's contact instead). Extends `phase-6-assessments.md` (parts 2, 6 and 7) and
`phase-8-provider-tools.md`. Coded in a coding session, after the provider tools.

**Read from the code (2026-10-15):** the platform admin invites psychologists and types their STR and SILP (`admin/assessment/providers/{id}/
invitations`, `PUT admin/assessment/psychologists/{id}`), with no copy of the document; `assessment_providers` holds `name`, `legal_name`, `code`
and status only; reports copy the signer's name, STR and SILP but no signature image; `revise` works on auto-issued reports only.

## Decisions

- 2026-10-15: an institution has **leads** (`role` on the psychologist) who manage the team: invite, deactivate, restore (chosen by the user).
- 2026-10-15: psychologists **submit licence renewals with the document**; the platform admin approves them (chosen by the user).
- 2026-10-15: the institution's **identity** (address, practice licence, contact, logo) and the psychologist's **signature image** appear on the
  report through template placeholders (chosen by the user).
- 2026-10-15: **manually signed reports can be revised** like auto-issued ones, by any licensed psychologist of the institution, with a required
  reason (chosen by the user).
- 2026-10-15: a **review target in working days** per institution, shown in the queue and to the company (chosen by the user).
- 2026-10-15: **questions about a signed report** from the company to the institution, routed to the signer and the leads (chosen by the user).
- 2026-10-15: the retention of raw test data against the account erasure goes to the legal review (question 17).
- 2026-10-15: the schema below (decided by the user).
- 2026-10-16: **in-house psychologists** (a customer's own psychologist): a provider may be **private to one group**; candidate scope as for partners; the report
  states the signer is in-house (legal review question 19); the add-on and `assessment.psychological_enabled` apply as for partners; the group admin registers
  and the platform admin approves (decided by the user; [section 8](#8-in-house-psychologists-designed-2026-10-16)).
- 2026-10-16: the private provider needs **no approval of its own**; the group admin **grants batteries per tenant**; **one account, two roles** (the
  psychologist role by area, private providers and group members only); invitations to group members or outsiders; **two-factor always required**;
  the in-house note **printed by the system**; schema, routes and rules approved (decided by the user; sections 8.1 to 8.6).

## 1. Leads and team self-management

- **Column:** `assessment_psychologists.role` (`member` default, `lead`; CHECK). The platform admin sets it (`PUT admin/assessment/psychologists/
  {id}` gains `role`); a new institution's first invitation can be a lead. An institution keeps at least one active lead once it has one
  (409 `last-lead`).
- **Lead endpoints** (`/provider/team`, leads of an active institution): `GET team` (psychologists with role, status, STR and SILP with their
  validity and pending submissions), `GET`/`POST team/invitations` (email, name, role; no licence) and `DELETE team/invitations/{id}`,
  `POST team/{psychologistId}/suspend` and `/restore` (never oneself, 409 `cannot-suspend-self`), `PUT team/{psychologistId}/role`. Audited with the
  existing psychologist actions; the platform admin keeps every action.
- **Invitations:** `assessment_psychologist_invitations.invited_by_admin_id` becomes nullable, `invited_by_psychologist_id` is added (CHECK exactly
  one). An invitation by a lead carries no licence: the psychologist submits it after accepting (section 2) and cannot sign until it is approved
  (`cannot-sign-reports`, as today).
- **Suspension:** releases the psychologist's claims (`phase-8-provider-tools.md` section 3); auto issue falls back as today when the
  responsible psychologist is suspended.
- Reset of two-factor stays with the platform admin.

**Section 1, coded 2026-10-27** (migration `000193`):

- `assessment_psychologists.role` (`member` default, `lead`; CHECK) and `assessment_psychologist_invitations.role` (the role the invitee joins with). Both tables' licence columns
  are nullable (a lead's invitation carries none); `Psychologist::canSignReports` is false until all four licence values are there. `invited_by_admin_id` is nullable,
  `invited_by_psychologist_id` is added, and a CHECK demands exactly one of them. Rolling the migration back keeps the columns nullable.
- Lead endpoints under `provider/team` as designed: `GET team`, `GET`/`POST team/invitations`, `DELETE team/invitations/{invitationId}`, `POST team/{id}/suspend` and `/restore`,
  `PUT team/{id}/role`. A member gets 403 `not-a-lead`; a psychologist of another institution is a 404. The list shows no pending submissions yet (section 2).
- The admin: `PUT admin/assessment/psychologists/{id}` and the admin invitation take an optional `role`. The rule "keep at least one active lead" (409 `last-lead`) binds
  the admin too: it stops suspending the last active lead and making them a member. The provider row is locked first, so two leads cannot remove each other at once.
- Code: `PsychologistRole` (Domain), `Application/Team/{TeamAccess,TeamMemberChanges,ListTeam,ListTeamInvitations,InviteTeamMember,RevokeTeamInvitation,ChangeTeamMember}`,
  `Invitations/IssueInvitation` (the part shared by the admin and the lead: no account for the email, one pending invitation per email, 60-second cooldown). Audit: the existing
  psychologist actions plus `assessment.psychologist_role_changed`; a lead's actions are recorded with actor type `user`.
- Tests: `ProviderTeamTest`.

## 2. Licence renewals with the document

- **Table** `assessment_licence_submissions`: `psychologist_id` (FK cascade), `kind` (`str`, `silp`), `number`, `valid_until`, `document_path`
  (PDF checked by content, at most 10 MB, private disk), `status` (`pending`, `approved`, `rejected`), `decided_by_admin_id`, `decided_at`,
  `decision_note`, timestamps. One `pending` per psychologist and kind (partial unique index).
- **Psychologist:** `GET`/`POST /provider/me/licences` (multipart; 409 `licence-pending`). The current licence stays in force until its own date;
  the expiry notices of `phase-8-provider-tools.md` link here.
- **Platform admin** (`assessment_providers.manage`): `GET admin/assessment/licence-submissions` (pending first), `GET .../{id}/document`,
  `POST .../{id}/approve` (copies number and date onto the psychologist) and `.../reject {note}`. In-app notice to the psychologist either way;
  audit `assessment.licence_approved`, `_rejected`. A queue card on the admin dashboard. Typing a licence directly stays possible for the admin.
- Documents of decided submissions are kept while the psychologist exists (they back the reports signed under that licence).

## 3. Institution identity and signature on the report

- **Institution:** `assessment_providers` gains `address` (≤500), `practice_licence_number` (≤100), `phone`, `email`, `logo_path` (PNG or JPG,
  re-encoded, private disk). Leads edit them through `GET`/`PUT /provider/institution` and `POST`/`DELETE /provider/institution/logo`; the platform
  admin through the existing provider update.
- **Signature:** `assessment_psychologists.signature_path` (PNG with transparency, at most 1 MB, re-encoded), `POST`/`DELETE /provider/me/signature`.
  At signing, auto issue and revision the file is copied to the report's folder and `signer_signature_path` is set on the report (and kept on the
  revision rows), so a later change never alters an issued report. Signing without a signature image stays allowed; the template decides.
- **Placeholders** for the report template: `{{provider_letterhead}}` (logo, name, address, practice licence, contact), `{{provider_address}}`,
  `{{provider_licence}}`, `{{signature_image}}`. The stored PDF of `phase-8-provider-tools.md` section 7 renders them.
- **Candidate:** the explanation page shows the institution's name, address, phone and email ("Untuk konsultasi, hubungi lembaga").

## 4. Revising a manually signed report

- `POST /provider/reviews/{id}/revise` accepts every `signed` report, not only auto-issued ones, by any psychologist of the institution with a valid
  STR and SILP; `revision_reason` (≤1,000) is required (422 `reason-required`) and stored on the report, then kept on its revision row. Same number
  with `-R1`, `-R2`; the earlier version is kept and marked superseded; the company team and the candidate are told (`assessment.explanation_revised`
  and the existing notices). The company's result and the verification page show the revision and its date, not the reason.
- `phase-6-assessments.md` "A manually signed report stays locked" no longer holds.

## 5. Review target

- `assessment_providers.review_target_days` (1 to 10, default **2**), set by leads and the platform admin.
- **Due** = the attempt's submission plus the target in working days (Monday to Friday, Jakarta, as the bulk interview planner); auto-issued reports
  have none.
- **Queue:** `GET /provider/reviews` gains `review_due_on` and `overdue`, `filter[overdue]=1`, and sorts overdue first. The reviews-waiting notice
  of `phase-8-provider-tools.md` section 5 adds the overdue count.
- **Company:** the result of a battery awaiting review shows `review_due_on` ("Perkiraan selesai").

## 6. Questions about a report

- **Table** `assessment_report_messages`: `report_id` (FK cascade), `author_kind` (`company`, `psychologist`), `author_user_id`, `body` (≤2,000, plain
  text), `created_at`.
- **Company:** `GET company/assessment/invitations/{id}/report-messages` (whoever may see the result) and `POST` (members with `assessments.send` who
  may see the application, and company admins); only for a `signed` report, until **30 days** after its signing (409 `report-questions-closed`);
  20 per hour.
- **Institution:** `GET /provider/reviews/{id}/messages` and `POST` (any psychologist of the institution with a valid licence); the review queue shows
  open questions (`filter[questions]=open`: the last message is from the company).
- **Notices:** a company question gives in-app `provider.report_question` and an email (`assessment.report_question`, preference `provider_reviews`)
  to the signer, and in-app to the leads; when the signer is no longer active, the leads get the email. An answer gives in-app
  `assessment.report_answered` to the members who asked.
- **Rules:** plain text, no attachments; the link rule of `phase-8-interview-calendar.md` section 6 applies to both sides. The messages are not part
  of the report and never reach the candidate. Erased with the assessment erasure step.

## 7. Raw data retention (legal question)

- `docs/legal-review.md` question 17: how long an institution must keep the raw data of an examination (answers, drawings, stored report), and
  whether that duty overrides a candidate's account erasure. Until answered the erasure stays as it is. If retention is required, the assessment
  erasure step moves the data to a locked institution archive instead of deleting it (designed then).

## 8. In-house psychologists (designed 2026-10-16)

Until 2026-10-16 the design modelled a psychologist only as staff of a partner institution registered by the platform admin. A customer's **own
psychologist** (for example the HR psychologist of a group) is now a psychologist of a **private provider** of the group. In the self-hosted edition the
customer's platform admin is the customer itself (`self-hosted.md`). Answered by the user on 2026-10-16:

1. ~~Private provider~~ **answered 2026-10-16: yes, a provider may be private to one group** (only that group's tenants use its batteries); partner providers
   stay shared as today.
2. ~~Candidate scope~~ **answered 2026-10-16: only the candidates who took the provider's batteries**, as for partners; because a private provider serves the
   group's tenants, that covers every tenant of the group that uses it.
3. ~~Conflict of interest~~ **answered 2026-10-16: allowed, with a note on the report** that the signer is an in-house psychologist; the question went to the
   legal review as **question 19** (`docs/legal-review.md`). The licence duty (STR and SILP, UU 23/2022) applies to the person, not to the institution.
4. ~~Add-on and setting~~ **answered 2026-10-16: yes, the same as for a partner**: the "Psychological tests" add-on of `self-hosted.md` section 8 and the
   company setting `assessment.psychological_enabled` both apply, one rule for every provider.
5. ~~Who registers and approves~~ **answered 2026-10-16: the group admin registers the private provider and invites its psychologists; the platform admin approves
   them** and checks the STR and SILP as for partners (in the self-hosted edition the platform admin is the customer itself). All questions of this section are
   answered; the design is below (8.1 to 8.6, designed 2026-10-16).

Further decisions of 2026-10-16 (the user):

6. **The private provider needs no approval of its own**: it is active once the group admin registers it; each psychologist still needs the platform
   admin's approval of the STR and SILP with the document (section 2) before signing.
7. **The group admin grants the batteries per tenant** of the group, through `assessment_company_batteries`, as the platform admin does for partners.
8. **One account, two roles**: an in-house psychologist who is a company member keeps one account; the psychologist role is chosen by the **area**
   (8.4). Only for private providers and only for members of a tenant of the provider's group; partner psychologists keep their own `psychologist`
   accounts.
9. **Invitations go to group members or to outsiders**: a member of a tenant of the group gets the role on the existing account, anyone else gets a new
   `psychologist` account (for example a contract psychologist).
10. **Two-factor is always required** for the psychologist role, whatever `group.require_two_factor` says.
11. **The in-house note is printed by the system**, not through a template placeholder.
12. The schema, routes and rules of 8.1 to 8.6 approved as written.

### 8.1 Schema (one migration)

- `assessment_providers.group_id` (nullable, FK `organization_groups` RESTRICT, index): `null` is a partner shared by every tenant the platform admin
  grants, a value makes the provider **private** to that group. `created_by_user_id` (nullable, FK `identity_users`): the group member who registered
  it. `code` stays unique across all providers (it starts the report number). The group of a provider is fixed after creation; turning a private
  provider into a partner, or back, is not offered.
- `assessment_psychologist_invitations.invited_by_user_id` (nullable, FK `identity_users`): the CHECK of section 1 becomes **exactly one** of
  `invited_by_admin_id`, `invited_by_psychologist_id`, `invited_by_user_id`. An invitation by a group member carries no licence (as one by a lead).
- `assessment_company_batteries.granted_by_admin_id` becomes nullable and `granted_by_user_id` (nullable, FK `identity_users`) is added, CHECK exactly
  one.
- `assessment_reports` and `assessment_report_revisions` gain `signer_in_house` (boolean, default `false`) and `signer_group_name` (≤200, nullable): set at signing, auto issue and
  revision when the signer's provider is private, so a later change never alters an issued report.
- `identity_users.type` is unchanged. `assessment_psychologists.user_id` (already unique) may point to a `company_member` account only when the provider
  is private and the member's tenant is in the provider's group (application rule, checked at acceptance and when a tenant leaves a group).

### 8.2 Group permission

- New group permission **`group.assessment.manage`** (the catalog of `erp-organization.md` section 4): register and edit the group's private providers,
  invite psychologists, set leads, suspend and restore them, and grant or revoke batteries for the tenants of the group. Never one's own role: a group
  member who is also a psychologist of the provider cannot change their own psychologist row (409 `cannot-change-own-psychologist`).
- It works only while the group's entitlement holds the add-on "Psychological tests" (`self-hosted.md` section 8); otherwise 403 `module-not-licensed`.
  Audited in the group log (`group.audit.view`).

### 8.3 Routes

Group member with `group.assessment.manage` (`auth:user`, under `organization/`):

| Route | Use |
|---|---|
| `GET`, `POST assessment-providers`; `GET`, `PUT assessment-providers/{id}` | the private providers of the group: name, legal name, code and the identity of section 3; `review_target_days` |
| `GET`, `POST assessment-providers/{id}/invitations`; `DELETE .../invitations/{invitationId}` | invitations (email, name, role) |
| `GET assessment-providers/{id}/psychologists`; `PUT .../{psychologistId}/role`; `POST .../{psychologistId}/suspend`, `/restore` | the team, as the leads' `/provider/team` |
| `GET assessment-batteries` | published batteries of the group's private providers |
| `GET`, `PUT`, `DELETE companies/{companyId}/batteries[/{testId}]` | grants per tenant; a tenant outside the group: 422 `company-not-in-group`; a battery of another provider: 422 `invalid-assessment-test` |

Platform admin: the provider list gains `filter[scope]=partner|private` and shows the group; `auto_issue_allowed`, licence approval (section 2),
suspension and two-factor reset stay with the platform admin for every provider. The platform admin does not grant a private provider's batteries.

### 8.4 The psychologist role on a member account

- **Actor by area:** on `/provider/*` a `company_member` token whose account has an **active** psychologist row resolves as `Actor::psychologist`;
  elsewhere the same token stays the member. One sign-in and one token: the member sign-in, its token lifetime (12 hours for internal tenants,
  `erp-sso.md` section 4) and its sessions.
- **Two-factor:** the provider area refuses an account without enrolled TOTP (403 `two-factor-required`), even when `group.require_two_factor` is off.
- **Acceptance** (`POST invitations/{token}/accept`):
  - the email belongs to a member of a tenant of the provider's group: the person must be signed in as that account, and the psychologist row is added
    to it;
  - the email belongs to another account (a job seeker, a partner psychologist, a member outside the group): 409 `email-in-use`;
  - the email is unknown: a new `psychologist` account is made, as for partners today.
- **End of the role:** removing the member deactivates the account (`phase-8-company-permissions.md`, Remove), which ends the role too; when the tenant
  leaves the group, or the group is suspended, the psychologist row is suspended and its claims are released (`phase-8-provider-tools.md` section 3).
  A suspended group also makes its private providers' batteries unusable (`invalid-assessment-test`).
- **Account erasure:** the refusal that holds for psychologist accounts today holds for a member account with a psychologist row.

### 8.5 Catalogue and invitations

- A company sees a private provider's battery only when it is granted to that tenant and the tenant is still in the provider's group;
  `InvitationChecks` adds the same rule. `assessment.psychological_enabled` applies as for partners.
- Candidate scope is unchanged: the psychologist sees only the candidates who took the provider's batteries.

### 8.6 The in-house note

- When `signer_in_house` is true the system prints a fixed line, "Ditandatangani oleh psikolog internal {group name}", on the PDF, the company result
  and the public verification page, whatever the report template holds. The group name is copied at signing with the flag (`signer_group_name`,
  ≤200, on the report and the revision rows).
- Legal review question 19 decides whether the note stays enough; until then the rule stands.

## Changes in short

| Piece | Change |
|---|---|
| Migration | `assessment_psychologists.role`, `signature_path`; invitations `invited_by_admin_id` nullable, `invited_by_psychologist_id`; `assessment_licence_submissions`; `assessment_providers.address`, `practice_licence_number`, `phone`, `email`, `logo_path`, `review_target_days`; `assessment_reports` and `assessment_report_revisions` `signer_signature_path`, `revision_reason`; `assessment_report_messages`; in-house (section 8): `assessment_providers.group_id`, `created_by_user_id`; invitations `invited_by_user_id`; `assessment_company_batteries.granted_by_admin_id` nullable, `granted_by_user_id`; reports and revisions `signer_in_house`, `signer_group_name` |
| API | `/provider/team` (list, invitations, suspend, restore, role); `/provider/me/licences`; `/provider/institution` and logo; `/provider/me/signature`; revise for every signed report; `review_due_on`, `overdue`; report messages (company and provider); admin licence submissions; `role` in the admin psychologist update; `organization/assessment-providers`, `organization/assessment-batteries`, `organization/companies/{id}/batteries` (group permission `group.assessment.manage`); `filter[scope]` on the admin provider list |
| Templates | `{{provider_letterhead}}`, `{{provider_address}}`, `{{provider_licence}}`, `{{signature_image}}` |
| Notifications | licence decided (in-app); `provider.report_question` (in-app, email `assessment.report_question`); `assessment.report_answered` (in-app) |
| Audit | `assessment.licence_approved`, `_rejected`; team actions by leads |
| Errors | 409 `last-lead`, `cannot-suspend-self`, `licence-pending`, `report-questions-closed`; 422 `reason-required`; in-house: 403 `two-factor-required`, `module-not-licensed`; 409 `email-in-use`, `cannot-change-own-psychologist`; 422 `company-not-in-group` |
| Legal | question 17 (raw data retention), question 19 (in-house signer) |
| Front end | provider app: team page, licence upload, institution profile and logo, signature upload, revise with reason, due dates, report questions; admin: licence queue (D19), lead role; company: "Perkiraan selesai" and the question thread on the result; candidate: institution contact on the explanation |
