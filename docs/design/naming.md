# Naming: ONESIS (decided 2026-10-15; section 1 coded 2026-10-15)

The project is no longer only a job portal (`erp-direction.md`), so the name "jobportal" is replaced where people or clients see it. This
document records the names and when each place changes.

## Decisions

- 2026-10-15: the **ERP platform** is named **ONESIS**, short for *One Solution Integrated System* (decided by the user).
- 2026-10-15: the **portal for job seekers** follows the parent brand: **ONESIS Career** (decided by the user).
- 2026-10-15: the problem `type` of the API changes from `urn:jobportal:error:<code>` to **`urn:onesis:error:<code>`**, **before go-live**
  (decided by the user; [API contract](#1-api-contract)).
- 2026-10-15: the **infrastructure names** (folder, Linux user, database, repository, Supervisor programs, Asterisk names) change **together with
  separating the live site from the working folder** (decided by the user; [Infrastructure](#3-infrastructure)).
- 2026-10-15: the API domain **stays `api-karir.satu.solutions`** for now; a neutral domain comes when the ERP goes to other customers (decided by
  the user; [Domains](#4-domains)).

## 1. API contract (coded 2026-10-15)

- `ErrorCatalog` and `ErrorCatalogDocument` (`src/Shared/Infrastructure/Documentation`) build `urn:onesis:error:<code>`; the codes themselves do
  not change. `architecture.md` 2.1, `docs/api/` and the tests that assert the `type` follow.
- No compatibility period: no client uses the API yet (the front end is not coded).

## 2. Names people see (before go-live, configuration)

- `APP_NAME=ONESIS` in `.env.example` and the production `.env` (the sender name of the emails reads it through `MAIL_FROM_NAME`).
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
