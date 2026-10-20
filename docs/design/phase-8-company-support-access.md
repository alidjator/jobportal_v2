# Phase 8: support access to an ordinary company (designed 2026-10-20)

Closes MG-C05 of `docs/analysis/multi-persona-gap-analysis.md`. Mirrors the group support access of `erp-governance.md` section 1.1 and `erp-organization.md` (E15, D28). Design only: nothing is coded.

## Decisions

- 2026-10-20 A company admin gives **one named platform admin** read-only access for **1 to 72 hours**, revocable at once, every read audited and shown in the company activity log; the same rules as the group access (this design, following the precedent).
- 2026-10-20 **Scope: team, jobs, settings and the activity log of the company, and no personal data of applicants** (no applications, CVs, ratings, interviews or chats with candidates) (user; [section 2](#2-what-the-admin-reads)).

## 1. The grant

- Table `company_support_grants` (`company_id` RESTRICT, `admin_id` RESTRICT to `identity_admins`, `granted_by_user_id` RESTRICT, `expires_at`, `revoked_at`, `revoked_by_user_id`, `created_at`; one running grant per company and admin, 409 `support-grant-exists`). Migration, new.
- **Company side** (a company admin; `company.settings`-class page C24): `GET company/support-admins` (active admins holding the new permission `companies.support`), `GET company/support-grants` (the last 30 days with `status` `active`, `revoked`, `expired`), `POST company/support-grants` `{admin_id, hours}` (1 to 72; 422 `invalid-support-admin`, `invalid-support-duration`), `DELETE company/support-grants/{id}` (ends it at once, 204). A suspended company can still end a grant.
- The permission `companies.support` joins the admin catalog (`CompanyPermissions`); `identity:sync-permissions` gives it to `super_admin`.

## 2. What the admin reads

- **Admin side** (the admin's permission and a running grant): `GET admin/support/companies` (the companies with a grant for this admin), then read-only views of one company: `GET admin/support/company/{id}/members` (the team list as C21 shows it), `.../jobs` and `.../jobs/{jobId}` (the list and detail as C3/C4, **without applicant counts and without the candidates**), `.../settings` (as C24), `.../activity` (the company activity log as C27). No write, no export.
- **Refused:** anything about applicants (applications, resumes, ratings, interviews, offers, chat, talent pool, scoring) is not exposed here at all; there is no route for it, so a grant cannot widen by accident. Reading personal data for a case stays with the user and the company (the legal-review question is not needed).
- **Audit:** each read is written as `company.support_read` (admin, company, the view) and appears in the company activity log; a grant given or ended is `company.support_granted` and `company.support_revoked`. A read after the expiry or the revocation is 403 `support-access-expired`.

## 3. Tests

A grant lasts at most 72 hours and one per admin is running; the admin reads the four views during it and gets 403 after the expiry or the revocation; the views carry no applicant data (counts included); each read is audited and in the company log; only a company admin gives or ends a grant; only an admin with `companies.support` is offered; a suspended company can revoke.

## Open points

None.
