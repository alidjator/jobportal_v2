# Phase 8: demo data for the development domains

Work the front end asks for (`frontend.md` section 13.1 point 1, step 0). Designed and coded on 2026-10-21.

## Decisions

- 2026-10-21: one artisan command, `demo:seed`, in a new operational module `src/DemoData` (no API); refused when `APP_ENV=production` ([detail](#command)).
- 2026-10-21: the data is written through the use cases the API runs, with admin and member actors, not by direct table writes; no new exception to the
  code standard ([detail](#how-it-writes)).
- 2026-10-21: one random password per run for every demo account, printed once at the end of the output, never stored in docs, logs or `.env` ([detail](#command)).
- 2026-10-21: the run is refused when a demo account already exists; to seed again: `migrate:fresh`, `db:seed`, `demo:seed` ([detail](#command)).
- 2026-10-21: both demo companies have `recruitment.requisition_required` overridden to off (by the demo admin), so Workspace posts jobs without the
  ERP requisition flow, which is tried with the ERP pages (step 7); the override can be reset in Console ([detail](#contents)).
- 2026-10-21: a demo platform admin (`super_admin`) for Console is created too; it enrols its second factor at the first sign-in ([detail](#contents)).
- 2026-10-21: the contents as proposed: a group with two companies (one verified, one with documents waiting), one account per preset, 8 job seekers,
  7 jobs, 14 applications at every stage, emails on `@demo.onesis.test` ([detail](#contents)).

## Command

`php artisan demo:seed` (after `migrate` and `db:seed`, and `identity:sync-permissions` so the `super_admin` role exists). It prints a table of the
accounts (application, role, name, email) and then the one password, made with 20 random letters and digits. The password is only in the output: keep
it in a password manager. A second run stops at once with `demo-data-exists` and changes nothing. A run that fails halfway leaves what it wrote, so the
database is rebuilt as for a second run.

## How it writes

`src/DemoData` drives the use cases of the other contexts as a client does (like a controller, from outside the context): `CreateAdmin`, `CreateGroup`,
`CreateCompany`, `AcceptInvitation`, `InviteMember`, `SetGroupRole`, `CreateLegalEntity`, `LinkTenant`, the document submit and approve use cases,
`CreateJob`, `ReplaceJobAssignees`, `PublishJob`, `ApproveJob`, `RegisterJobSeeker`, `VerifyEmail`, the profile use cases, `ApplyToJob`,
`ProcessApplication`, `RespondToOffer`, `WithdrawMyApplication` and `CloseJob`. The reference ids come from the reference use cases (the first active
items, the first city of the first province). So the history, events, audit and analytics records are those of real use.

For the run only, the command swaps four ports in its own process (`DemoDataServiceProvider::useDemoAdapters`): the invitation and verification-code
senders keep the token and the code in memory (`InMemoryDemoInbox`), the invitation link is the plain token (no `COMPANY_INVITATION_URL` needed),
and the registration limit per IP is off. Other emails the use cases send go to the `.test` addresses through the configured mailer (`log` on the
development site). Each session that an acceptance or a registration opens is ended. A company admin gets no session from the acceptance but the
second-factor challenge (a company admin always enrols one, at the first real sign-in), so the run finds the account through `ListAdminUsers`.

## Contents

| What | Detail |
|---|---|
| Platform admin | `admin@demo.onesis.test`, `super_admin` |
| Group | "Grup Demo Nusantara", two legal entities, one per company; both companies have `recruitment.requisition_required` overridden to off |
| PT Demo Sinar Teknologi | verified (its required documents approved); company admin (also a group member with every group permission), recruiter, hiring manager, interviewer (`*.sinar@demo.onesis.test`) |
| PT Demo Arunika Ritel | company admin only (`admin.arunika@demo.onesis.test`); required documents submitted and waiting for review (Console D5) |
| Job seekers | 8, verified email, profile with phone, location, one past job and one PDF resume (`<first>.<last>@demo.onesis.test`) |
| Jobs | Sinar: Backend Developer, UI/UX Designer, Data Analyst (active), Mobile Developer (draft), Customer Support Officer (closed); Arunika: Store Supervisor, Merchandiser (active at once: a tenant of a group counts as verified, `erp-governance.md` 1.2) |
| Second factor | the group asks every member of its tenants for one, so every Workspace account enrols TOTP at its first sign-in, like the platform admin |
| Applications | 14: applied 5, screening 3, interview 2 (online, with the interviewer, from three days ahead), offer 1, hired 1 (an accepted offer), rejected 1, withdrawn 1 (by the job seeker) |

Names and texts are fictitious. The texts the front end shows (job descriptions, headlines, the offer note) are Indonesian, as real postings are;
code, comments and documents stay English.
