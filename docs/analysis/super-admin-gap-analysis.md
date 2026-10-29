# Super Admin gap analysis (2026-10-27)

Persona: Arief Hidayat, Platform Administrator / IT System Owner. "Super Admin" here is the platform admin, not a company admin.

## Basis

- The Console (`/usr/local/src/jobportal-frontend/onesis-talent/apps/console`) has only: Dashboard, Companies (list, detail, documents), Job moderation, Portal (career site identity) and Account.
- The API has about 250 `/admin/*` endpoints (audit log, settings, master data, users, email templates, ...). Console pages D21, D27 and D28 are not coded.
- Some abilities exist only as artisan commands on the server (admin and role management).
- A "tenant" is a Company, plus a Group of companies. There is no separate tenant concept.
- Plans, billing and paid quotas are out of scope in this phase, so they are not gaps.
- Sources: Console code, `packages/api/openapi/api.json`, `docs/design/*`, `docs/deployment-checklist.md`.
- **[design]** = designed, not necessarily coded. **[confirm]** = could not be established from what was read.

## Gaps (largest impact first)

| # | Area | As-Is | Pain point | To-Be | Gap | Priority |
|---|---|---|---|---|---|---|
| 1 | Tenant data isolation | Per-company data is separated by `company_id` and per-use-case access checks. A security review exists per context. A read rule exists for Groups. A company name can be hidden from the moderator. | No evidence of automated cross-tenant tests; one query missing the `company_id` filter could leak data. **[confirm]** | Automated "company A cannot read B" tests for every company endpoint, plus a coverage report. | Proof of isolation is missing. | **Must** (security, high risk) |
| 2 | Roles, permissions and admin accounts | Roles and admins are managed by artisan commands (`identity:create-role`, `set-admin-role`, `suspend-admin`, ...). One role per admin. `super_admin` is protected and holds every permission. The last admin cannot be locked out. No UI. | Every change needs shell access. No second-person approval. No break-glass. Admin token lifetime **[confirm]** (Sanctum `expiration` is null). | Admin and role pages in the Console, second approval for role changes, an audited emergency account, a clear admin session lifetime. | Separation of duties exists only at server level. | **Must** (security) |
| 3 | Cross-tenant audit log | Table `platform_audit_logs` with `company_id`. Read through `GET admin/audit-logs` (`audit_logs.view`). 12-month retention, scheduled purge. Reading the log is not audited. | No page, search or export in the Console. Immutability is not visible: the table has only a CHECK on `actor_type` and the purge deletes rows. **[confirm]** Whether master data and global setting changes are audited is not established. | A log page with filters and export (the export itself audited), write protection in the database, audit coverage for every global change. | UI, export, immutability guarantee. | **Must** (security) |
| 4 | Backup, restore, DR | Nothing in code. Only the rule "back up before migrating; rollback means restore" and the backup duty sits with the operator. Migrations have no tested `down()`. | Backup is untested; RPO and RTO are not defined. **[confirm]** No backup schedule appears in the checklist. | Backup schedule, periodic restore test with evidence, written RPO/RTO, a runbook. | The whole area. | **Must** (availability, high risk) |
| 5 | Platform security | Admin TOTP is mandatory, with recovery codes, password change and sign-out everywhere. Rate limit exists but needs Redis (still a checklist item). Secrets only in `.env`. | No IP allowlist, no admin SSO (**[design]** `erp-sso.md`), no configurable password policy, no active-session list, no secret rotation. Client IP behind the Nuxt proxy is undecided (rate limit and audit may share one IP). | IP allowlist for the Console, session list and revoke, configurable password and session policy, a trusted-proxy decision. | Several controls are missing. | **Must** (security) |
| 6 | Support and impersonation | No impersonation (safe). Group support access exists. Access to an ordinary company is **[design]** only (#44): read-only, 1 to 72 hours, no applicant data, every read audited. | Troubleshooting depends on another path that is not visible. **[confirm]** That path may not be audited. | Code #44, with the pages in Workspace and Console. | Not coded for ordinary companies. | **Must** (security; prevents shortcuts) |
| 7 | Global configuration and master data | Full API: `settings`, 17 master data types, email templates, legal documents, FAQ, announcements, per-company setting overrides. No Console page for any of them. | Global changes cannot be made or reviewed in the Console. Audit trail of master data **[confirm]**. Branding and time zone per tenant **[confirm]**. | Global settings pages with before/after history and review. | Almost all UI. | **Should** (becomes **Must** if master data is not audited) |
| 8 | Compliance and privacy (UU PDP) | Account erasure, per-company applicant retention (anonymisation), 90-day video retention, 12-month audit log. The 25-question legal review is **not sent**. | No register for data subject requests and incidents (a written procedure was chosen instead). Data residency not covered in the documents read. **[confirm]** | Finish the legal review, write the DSR and incident procedures, decide data location. | Procedures and decisions, not code. | **Should** |
| 9 | Company and Company Admin provisioning | The Console creates a company, invites the owner, sets the owner, suspends and restores. Password and two-factor reset endpoints exist but have no page. No company deletion. | Owner change is only for a company whose owner is gone. Access reset for a company admin needs another path. **[confirm]** | An ownership transfer flow with approval, an access-reset page in the Console, a tenant deletion policy. | UI and flow. | **Should** |
| 10 | Monitoring and incidents | Dashboard operations card (failed and waiting queue jobs, late scheduler). Email to `platform.operations_email`. `GET health/scheduler` for an outside monitor. | The outside monitor and operations address are not set yet (waiting for the user). No error tracking, performance view or incident record. | Set up the outside monitor, error tracking and an incident procedure. | Operations. | **Should** |
| 11 | Deployment, versions, rollback | Front end `release` and `rollback` scripts. An "unavailable" page when the API is down. API rollback means restoring a backup. | Scheduled maintenance mode is not visible. **[confirm]** Rollback is never tested. | Maintenance mode with a banner, a routine rollback test. | Procedure. | **Should** |
| 12 | Platform integrations | Assessment providers and psychologists through the admin API. Email suppression list. SMTP not chosen yet (waiting for the user). | No platform API key and no integrations page in the Console. Storage is the private disk only. **[confirm]** | An integration status page with a connection test. | No UI. | **Could** |
| 13 | Feature flags and modules | Only per-company setting overrides. Module entitlements per package are designed in `self-hosted.md` section 8; coded status **[confirm]**. | Cannot switch a module on or off per tenant or control the release version. | Per-tenant module flags. | Not present. | **Could** |

## To confirm (cannot be established from the code)

1. Whether automated cross-tenant isolation tests exist.
2. The admin token lifetime that applies now.
3. Whether the audit table is protected from update and delete at database level, and whether master data and global settings produce audit entries.
4. The backup schedule and restore tests running on the server.
5. How support handles company problems today.
6. Data residency, and branding and time zone per tenant.
7. The coded status of module entitlements per package.
8. Whether a Super Admin can read sensitive data (salary, psychological results) through admin endpoints, and the controls on it. Not traced.

## User stories

**G1 (Must): Proof of tenant isolation.** As a Super Admin, I want test proof that one company's data cannot be read by another, so cross-tenant leaks do not reach production.
- Every `company/*` endpoint has a test that fails when a token of company A reads or writes data of company B.
- The tests run in CI, and a new endpoint without a test is flagged.
- A coverage report names the endpoints not yet tested.

**G2 (Must): Manage admins and roles from the Console.** As a Super Admin, I want to create roles and assign admins with a second approval, so no single person can change authority alone.
- A change of role or permission waits for another Super Admin, and the requester cannot approve their own request.
- An emergency (break-glass) account can be activated, expires on its own and triggers a notification.
- The last admin still cannot be locked out.
- Every change is recorded with before and after values.

**G3 (Must): A usable, tamper-resistant audit log.** As a Super Admin, I want to search and export the audit log across tenants and trust that it cannot be altered, so I can trace incidents.
- The log page filters by company, actor, action and time range.
- An export records who exported.
- The table refuses UPDATE and DELETE outside the retention process.
- Changes to global settings and master data produce audit entries with before and after values.

**G4 (Must): Tested backups.** As a Super Admin, I want scheduled backups with periodic restore tests, so I know data can be recovered.
- RPO and RTO are written down and approved.
- Each periodic restore test produces a report with date and result.
- A backup failure notifies the operations address.

**G5 (Must): Admin security controls.** As a Super Admin, I want to restrict Console access and manage sessions, so a stolen admin account is not immediately dangerous.
- The Console opens only from allowed IP addresses.
- Active sessions are listed and can be revoked.
- The password policy and session lifetime are configurable.
- The IP used for rate limits and audit is the real client IP.

**G6 (Must): Controlled support access to a company.** As a Super Admin, I want limited read access to one company granted by its Company Admin, so I can troubleshoot without touching applicant data.
- The Company Admin grants access for 1 to 72 hours and can revoke it at once.
- No applicant data is exposed.
- Every read is recorded in the audit log and the company activity log.
- After expiry or revocation, access answers 403.

**G7 (Should): Global configuration with history.** As a Super Admin, I want to change global settings and master data from the Console with history, so changes can be reviewed and undone.
- Each change shows the old and new value, who and when.
- A value can be reset to its default.
- The change appears in the audit log.

**G8 (Should): Privacy procedures.** As a Super Admin, I want written procedures for data subject requests and incidents, so we meet UU PDP.
- The legal review questions required before go-live are answered.
- The DSR and incident procedures have an owner and a deadline.
- The data location decision is recorded.

**G9 (Should): Owner transfer and access reset in the Console.** As a Super Admin, I want to transfer company ownership and reset a Company Admin's access from the Console, so shell access is not needed.
- An ownership transfer needs the confirmation of the current owner, or a recorded reason when the owner is gone.
- Password and two-factor resets show a confirmation and revoke tokens.
- Every action is audited.

**G10 (Should): Monitoring and incidents.** As a Super Admin, I want to be told when the system has problems, so I can react before users complain.
- An outside monitor checks `GET health/scheduler`.
- A queue or scheduler failure emails the operations address.
- An incident is recorded with start, end and impact.

**G11 (Should): Maintenance and rollback.** As a Super Admin, I want a maintenance mode with a banner and a rollback that has been tested, so releases do not surprise users.
- Maintenance mode shows a message and schedule in the Console and the other apps.
- A rollback is tested in a test environment before go-live, with the result written down.
