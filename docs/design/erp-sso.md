# ERP sign-in: the Identity context as the IAM of the ERP (designed 2026-10-15, coded 2026-10-16)

Item #7 of `phase-8-index.md`. Replaces the "central IdP" direction of `erp-direction.md` section 2. Builds on `architecture.md` 2.2 (Sanctum
tokens with a lifetime per token), `phase-8-company-admin.md` section 1 (TOTP for company members, #2) and `erp-organization.md` (groups,
internal tenants). Plans, billing, payments, orders and invoices stay out of scope.

## Decisions

- 2026-10-15: **the Identity context is the IAM of the ERP**: no third-party or separate IdP; every ERP module in this codebase uses the same
  sign-in and Sanctum token (decision gate, decided by the user; [IAM](#1-iam)).
- 2026-10-15: **signing in with a customer's work account** (Microsoft 365, Google Workspace) comes **later, on request**; the design leaves room
  for it per group (decided by the user; [Work accounts](#2-work-accounts)).
- 2026-10-15: **TOTP required for internal tenants through a group setting, on by default** (decided by the user; [Two-factor](#3-two-factor)).
- 2026-10-15: tokens of members of internal tenants live **12 hours**; external tenants and job seekers keep 30 days (decided by the user;
  [Token lifetime](#4-token-lifetime)).
- 2026-10-15: **section 5** (token revocation when a tenant becomes internal, the two-factor rule of #2 reused, the group setting as a column)
  approved as written (decided by the user; [Changes of state](#5-changes-of-state)).

## 1. IAM

- The ERP modules share one codebase (`erp-direction.md` section 1), so one Sanctum token of the Identity context serves Workforce, Approval,
  Organization and, later, Employee. No single sign-on between modules is needed.
- Job seekers, company members and platform admins keep today's accounts, guards and sign-in (`architecture.md` 2.2). Members of internal tenants
  are company members, with the group roles of `erp-organization.md` section 3.
- **Applications outside this codebase** (Moodle, the old HRIS during the move, partner applications) that later need single sign-on are served by
  making Identity an OpenID Connect provider inside this codebase (for example Laravel Passport with OpenID Connect), designed when the first
  such application is asked for. Until then nothing is built for it.
- Accounts of employees who neither recruit nor approve (self-service in later ERP modules) are decided with the Employee context. Hiring
  managers and interviewers stay company members; their account may later be linked 1:1 to their employee record (decided 2026-10-15,
  `erp-direction.md` section 7).

## 2. Work accounts

- Not built now. When a customer asks, a group registers its work identity provider (OpenID Connect: Microsoft Entra ID, Google Workspace) and its
  members sign in through it; Identity verifies the ID token as it does for Google today (`GoogleIdentityVerifier`) and links the account through
  `identity_social_accounts` (provider plus subject). Two-factor is then the work provider's, not ours.

## 3. Two-factor

- New group setting `group.require_two_factor` (boolean, default `true`): when on, TOTP is required for every member of every internal tenant of
  the group, on top of the rules of `phase-8-company-admin.md` section 1 (a `company_admin` always needs it; the company setting
  `company.require_two_factor` can still require it for an internal tenant when the group setting is off).
- Same tables, sign-in answer, enrolment and reset as #2; nothing new in the storage.

## 4. Token lifetime

- A token created for a member of an internal tenant expires after **12 hours** (absolute, counted from creation, as for admins); job seekers and
  members of external tenants keep 30 days. The lifetime is chosen when the token is created (`architecture.md` 2.2).

## 5. Changes of state

- **A tenant becomes internal** (linked to a legal entity, `erp-organization.md` section 7): every token of its members is revoked in the same
  transaction, so no 30-day token survives; the members are told by email that the next sign-in follows the internal rules (12 hours, two-factor
  when required).
- **The group setting is switched on**, or a member joins an internal tenant whose group requires two-factor: the rule of #2 "becoming required"
  applies (tokens revoked for accounts without a confirmed factor, email `identity.two_factor_required`).
- **A tenant link is removed** (allowed only before ERP data depends on it): nothing is revoked; new tokens follow the external rules.
- **Settings and errors:** `group.require_two_factor` is a setting with the **group scope** of the settings (changed 2026-10-15 from a column on
  `organization_groups`, `erp-governance.md` section 3); no new error codes.

## Still open

- Coding order: sections 3 and 4 are coded with or after #2 (`phase-8-company-admin.md`), once `erp-organization.md` is coded.

## Coded (2026-10-16)

Item #7 of `phase-8-index.md`. No migration. Interpretations, where the design left room:

- **Group setting.** `group.require_two_factor` is registered by the Organization context (`OrganizationSettings`, boolean, default `true`, `groupEditable`) with its key in the shared `GroupSettingKeys`, so Company can
  read it without knowing Organization. A group admin with `group.settings.manage` changes it on `organization/settings`. It binds only the members of **internal** tenants; an external tenant keeps its own
  `company.require_two_factor`. The effective rule of `CompanyTwoFactorPolicy`: company admin, or the company setting, or (internal tenant and the group setting). The team page (`MemberTwoFactorStatuses`) shows
  `pending` for the same people.
- **Token lifetime.** `MemberTokenLifetime` (Identity) gives `+12 hours` to a company member whose company is linked to a legal entity (asked through `CompanyMembershipResolver` and `GroupDirectory`) and
  `+30 days` to everyone else. It applies at every place a company member's token is issued: password sign-in, Google sign-in, the end of the invitation accept and the second step of the two-factor
  sign-in (`CompleteTwoFactorSignIn` takes the lifetime as a value or a function of the account). Job seekers register and sign in with 30 days as before.
- **A tenant becomes internal.** `LinkTenant` ends every token of the tenant's members **inside the linking transaction** (new Shared port `MemberSessions`, provided by Identity) and queues the new email
  `identity.tenant_internal` ("your active sessions have ended; sign-ins last 12 hours and your group may require two-step verification"). It is one email, not two: the two-factor requirement is met at the next sign-in.
- **The group setting is switched on.** `RequireTwoFactorWhenGroupSwitchesItOn` (a synchronous listener of `GroupSettingChanged`) runs the "becoming required" rule of `phase-8-company-admin.md` section 1 for the
  non-admin members of every internal tenant of the group: members without a confirmed factor lose their tokens and get `identity.two_factor_required`; those who have a factor keep their session.
- **A tenant link is removed.** Nothing is revoked; new tokens follow the external rules (30 days, the company's own setting).
- Not built, as designed: sign-in with a work account, and OpenID Connect for applications outside this codebase.
