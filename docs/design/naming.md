# Naming: ONESIS (decided 2026-10-15; section 1 coded 2026-10-15)

The project is no longer only a job portal (`erp-direction.md`), so the name "jobportal" is replaced where people or clients see it. This
document records the names and when each place changes.

## Decisions

- ~~2026-10-15: the **ERP platform** is named **ONESIS**, short for *One Solution Integrated System*~~ **changed 2026-10-16: the platform is named
  ONESIS ERP, ONESIS standing for *One Solutions Integrated System*** (decided by the user). "ONESIS" stays the short name and the technical
  identifier (the problem `type`, the infrastructure names) until the user decides otherwise.
- 2026-10-16: **no change to the technical identifiers or to ONESIS Career** for now (decided by the user: both stay `onesis` and "ONESIS Career" until a later decision).
- 2026-10-15: the **portal for job seekers** follows the parent brand: **ONESIS Career** (decided by the user).
- 2026-10-15: the problem `type` of the API changes from `urn:jobportal:error:<code>` to **`urn:onesis:error:<code>`**, **before go-live**
  (decided by the user; [API contract](#1-api-contract)).
- 2026-10-15: the **infrastructure names** (folder, Linux user, database, repository, Supervisor programs, Asterisk names) change **together with
  separating the live site from the working folder** (decided by the user; [Infrastructure](#3-infrastructure)).
- 2026-10-15: the API domain **stays `api-karir.satu.solutions`** for now; a neutral domain comes when the ERP goes to other customers (decided by
  the user; [Domains](#4-domains)).
- 2026-10-20: the **three front-end applications** are named **ONESIS Career**, **ONESIS Workspace** and **ONESIS Console** (with **Portal
  Mitra**), each on its own development domain (decided by the user; [Front-end applications](#5-front-end-applications)).

## 1. API contract (coded 2026-10-15)

- `ErrorCatalog` and `ErrorCatalogDocument` (`src/Shared/Infrastructure/Documentation`) build `urn:onesis:error:<code>`; the codes themselves do
  not change. `architecture.md` 2.1, `docs/api/` and the tests that assert the `type` follow.
- No compatibility period: no client uses the API yet (the front end is not coded).

## 2. Names people see (before go-live, configuration)

- `APP_NAME="ONESIS ERP"` in `.env.example` and the production `.env` (the sender name of the emails reads it through `MAIL_FROM_NAME`).
- The job seeker site uses **ONESIS Career** as `{brand}` (`frontend.md` 11.4): page titles, emails to job seekers, structured data. Pages and
  emails of the ERP side (company members, group members, approvals) use **ONESIS**.
- Email templates that name the product read the configured name; none hard-codes "JobPortal".

## 3. Infrastructure (with the live-site separation)

- Folder (`/usr/local/src/jobportal` and the live copy), Linux user `jobportal` and its crontab and ACLs, the PostgreSQL databases
  (`jobportal_v2`, `jobportal_v2_test` with `TestDatabaseGuard`, and the parallel copies), the GitHub repository `jobportal_v2` and its deploy key
  alias, the Supervisor programs (`deploy/supervisor/jobportal-*.ini`), the Asterisk Stasis application `jobportal`, its dialplan context
  `jobportal-team` and the sound `jobportal/recording-announcement`, and the nginx log names.
- New names use `onesis` (for example `onesis` database and user, `onesis-queue` program). The exact list and order are written as a runbook in
  `deployment-checklist.md` when the separation is planned; root steps are done by the user.
- The PHP namespace `Src\<Context>` is neutral and stays.

## 4. Domains

- The API stays at `api-karir.satu.solutions`. When ONESIS is offered to other customer groups, a neutral API domain is chosen and the old one is
  redirected for a period; that needs its own decision then.

## 5. Front-end applications (decided 2026-10-20)

Industry-standard product names, one per application of `frontend.md` section 3:

| Name | Who | Identifier, folder | Development domain |
|---|---|---|---|
| ONESIS Career | visitors and job seekers | `public`, `apps/public` | `karir.satu.solutions` |
| ONESIS Workspace (was "back office") | company and group members | `workspace`, `apps/workspace` | `workspace.satu.solutions` (was `preview.satu.solutions`) |
| ONESIS Console (was "admin panel") | platform admins | `console`, `apps/console` | `console.satu.solutions` (was `admin-karir.satu.solutions`) |
| Portal Mitra (was "psychologist area"), inside Console | partner psychologists | paths under `/mitra` | Console's domain |

- UI text uses the names, for example "Masuk ke Workspace".
- "Admin" in the user stories means the company admin, so Console never calls the platform role bare "Admin": it reads "Operator platform" or
  "Admin platform".
- Unchanged: the API domain (section 4), the API paths `/api/v1/admin/**`, the admin permissions and guard, and the page numbers (D1 to D28, M1 to M8).
