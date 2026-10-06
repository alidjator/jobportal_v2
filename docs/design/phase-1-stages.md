# Phase 1: Hybrid application stages (step 4, DONE 2026-10-06)

Status: design confirmed by the user on 2026-10-06 (every answer is recorded below). **Coding in two parts (confirmed):** part 1 = the `Recruitment` context, the migration (run on the `test_`
tables first; the real tables only with the user's approval), the setting `recruitment.max_stages`, the Shared port called by `CreateCompany` inside its transaction, the seven defaults and the command that
fills existing companies, with tests; part 2 = the five endpoints with tests. A suspended company follows the existing convention (403).

**Coded (2026-10-06, not committed):** both parts. Context `src/Recruitment`, migration `000062` (run on the `test_` tables and, on the user's approval, on the real table: 62 migrations, 0 rows), setting
`recruitment.max_stages`, shared port `DefaultStageProvisioner` (called by `CreateCompany` inside its transaction), command `recruitment:provision-default-stages`, and the five endpoints. 39 tests in
`tests/Feature/Recruitment` (suite 917 passed, Pint and PHPStan clean). Implementation notes: error codes `stage-type-not-allowed` (422), `stage-limit-reached`, `stage-name-taken`,
`default-stage-cannot-be-deleted` (409) and `invalid-stage-order` (422, `errors.stage_ids`); a stage of another company is 404; every change locks the company's stage rows first; a new extra stage goes
just before the first terminal stage and positions are renumbered 1..n; the unique name key is the final guard (accents count as equal under the collation). No port for "applications in a stage" was written:
the move of applications on deletion is coded with applications (step 7). `CompanyDirectory` gained `idsAfter` (used by the backfill command).
The model itself (hybrid, seven stage types, defaults cannot be deleted, terminal stages) was decided earlier and is in `decisions.md` section 1.

## Confirmed decisions

- **Bounded context (2026-10-06):** a new context **`Recruitment`** (`src/Recruitment`, table prefix `recruitment_`) owns the stages and will also own applications (step 7) and the
  pipeline with stage history (step 8). Company only announces that a company was created and provides the ports Recruitment needs.
- **Creating the defaults (2026-10-06, replaces the first answer "event + synchronous listener"):** finding: `CreateCompany` dispatches `CompanyCreated` only **after** its transaction commits
  (`src/Company/Application/CreateCompany.php:76-93`), so a listener cannot join that transaction. Chosen by the user: a **port in `src/Shared`** (name to be fixed when coding, for example
  `CompanyStageProvisioner`), implemented by Recruitment and **called by `CreateCompany` inside its transaction**, so the company and its seven default stages commit together and a company never
  exists without them. Company depends on the port only. An idempotent command fills companies that already exist (none on the real database today).
- **Stage names (2026-10-06):** a single plain-text `name` (one language, not translated), up to 60 characters. Defaults are created with the English names `Applied`, `Screening`, `Interview`, `Offer`,
  `Hired`, `Rejected`, `Withdrawn`; the company may rename them freely. Clients may translate the label from the stage `type` while the name is still the default.
- **Extra stages (2026-10-06):** a company may add extra stages only of the types `screening`, `interview` and `offer`. `applied`, `hired`, `rejected` and `withdrawn` stay a single (default) stage each,
  so the entry point and the final outcomes are never ambiguous. (This narrows the earlier "extra stages mapped to an existing type" in `decisions.md`; update that file when the design is complete.)
- **Limits (2026-10-06):** at most **10 stages per company** in total (seven defaults plus up to three extras), set by the admin setting `recruitment.max_stages` (default 10, range 7 to 20, overridable per
  company; registered by the Recruitment provider). Names are **unique per company, ignoring letter case** (default stages included).
- **Endpoints (2026-10-06):** `GET /api/v1/company/stages` (company admins and recruiters), and for company admins only `POST /company/stages {name, type}`, `PATCH /company/stages/{id} {name}`,
  `DELETE /company/stages/{id}` (extra stages only) and `PUT /company/stages/order {stage_ids}` (replaces the whole order, checked against the order rule). A stage's `type` never changes after creation.
- **Audit (2026-10-06):** changes to stages (create, rename, delete, reorder) are **not audited**, like the company profile and job drafts; moves of applications are recorded in the stage history (step 8).
- **Deleting an extra stage:** its applications move to the default stage of the same type (decided earlier). Applications arrive in step 7, so until then Recruitment asks a port that reports none
  (as Jobs does for its lock); the move itself is coded with applications.
- **Table `recruitment_stages` (approved 2026-10-06):** `id` bigint unsigned auto-increment; `company_id` (FK `company_companies`, RESTRICT); `type` varchar(20) with a CHECK (`applied`, `screening`,
  `interview`, `offer`, `hired`, `rejected`, `withdrawn`); `name` varchar(60) with the case-insensitive collation `utf8mb4_unicode_ci`; `position` smallint unsigned; `is_default` tinyint(1); `created_at`,
  `updated_at`. Indexes: UNIQUE (`company_id`, `name`) (case-insensitive through the collation, so it enforces the unique-name rule), INDEX (`company_id`, `position`). One default per type is guarded by a
  virtual column `default_type` (the type when `is_default` is 1, else NULL) with UNIQUE (`company_id`, `default_type`). The order is kept by the application (no unique key on `position`, so the order
  replacement stays simple).
- **Order (2026-10-06):** active stages (types `applied`, `screening`, `interview`, `offer`, `hired`) can be reordered freely, except that the default `applied` stage is always first; stages of the
  terminal types (`rejected`, `withdrawn`) always stay at the end, in any order among themselves.
