# Hand-off of a hire to Employee, with pre-boarding documents (#13; designed 2026-10-31, not coded)

Item #13 of `phase-8-index.md`, the design session promised by `erp-direction.md` section 3 (FG5 of `docs/analysis/actor-gap-analysis.md`). It changes no code and no
schema. It builds on `phase-8-hiring.md` (the hire and its join date), `phase-2-offers.md` (the accepted offer), `erp-organization.md` (internal tenants and
units) and the data principles of `erp-direction.md` section 9. Plans, billing and payments stay out of scope.

## Decisions

- 2026-10-31 (user): the hand-off is **started by HR** ("mulai onboarding"), not automatically at `hired` and not on the join date.
- 2026-10-31 (user): the company side is run by a **dedicated HR role in Employee** (new permissions); a recruiter or a company admin without it sees none of the documents.
- 2026-10-31 (user): the hand-off copies **identity, placement and the terms of the accepted offer** (employment type, contract, probation, salary and allowances), so HR does not type them again.
- 2026-10-31 (user): the pre-boarding documents are a **checklist per company** (required or optional, a due date relative to the join date); HR approves or rejects each with a reason and the candidate uploads again.
- 2026-10-31 (user): when the hire is **corrected or the offer is withdrawn** after onboarding started, HR is told and decides; the record is marked "needs review". Uploaded documents are deleted after a retention setting (default 30 days).
- 2026-10-31 (user): when the **join date arrives with required documents missing**, the employee record is still activated and marked incomplete; HR sees what is missing and the candidate keeps being reminded.
- 2026-10-31 (user): HR works in **Workspace**, a section "Karyawan baru", shown only for an internal tenant whose package includes the Employee module and only to a member with the HR role.
- 2026-10-31 (user): the retention of the documents of someone who does not join is a **setting** (Console "Pengaturan", default 30 days); the legal question is added to `legal-review.md` (step C3 of `erp-direction.md` section 9) and does not block the code.
- 2026-10-31 (user): **Employee's first phase replaces only the employee data and onboarding** of the HRIS; payroll (BPJS, PPh 21), attendance and leave stay in the existing HRIS until a separate decision (closes the scope question of `erp-direction.md` section 7).
- 2026-10-31 (user): the **data of the existing HRIS is not migrated**; Employee starts from recruitment, and an employee hired before it stays only in the old HRIS.
- 2026-10-31 (user): the **hires export stays**, and is switched **off per tenant that uses Employee**; other tenants and the old HRIS keep it.
- 2026-10-31 (user, proposal accepted): after activation a corrected **join date** is a **placement change in Employee** (with history, step D2), never a Recruitment event; an `other` document is **not** marked sensitive in the first slice (every document is already limited to `documents.view` and every read is logged; a flag can come with a real need such as medical results).
- Already decided earlier and kept: only **internal tenants** hand off (`erp-direction.md` section 3); the candidate opens the pre-boarding page with the **same account**; external tenants collect the documents outside the system (2026-10-19); psychological reports and test results are **not** carried (`phase-6-assessments.md` "ERP later", legal question 18); data is **copied once**, Employee owns it afterwards, no two-way sync (section 9).

## 1. The flow

1. A recruiter moves an application of an internal tenant to `hired` with its join date (`phase-8-hiring.md` section 1). Nothing is handed over yet.
2. In "Karyawan baru" HR sees the **hires waiting for onboarding** (hired applications of the tenant without a hand-off) and starts the onboarding of one, or of several at once.
3. Starting it creates a **pending employee record** (status `pending`) in Employee with the copied data (section 3) and opens the **pre-boarding** of that person: the checklist of the company becomes the items of the record, each with its due date (join date minus the days of the checklist line).
4. The candidate is notified (e-mail, in-app) and finds the items in the **to-do list** of Career (`ApplicantTaskSource`, key `preboarding_document`) and a page `/me/onboarding` where each document is uploaded, replaced or read.
5. HR reviews each upload (approve, or reject with a reason the candidate reads). A rejected item is open again for the candidate.
6. On the join date a scheduled command **activates** every pending record whose join date came: status `active`, flag `documents_complete` true or false. Missing required documents do not hold it back (decision above). HR can also activate earlier.
7. If the hire is corrected back to another stage ~~, or its offer is withdrawn,~~ (an accepted offer cannot be withdrawn; gap A1) while the record is `pending`: the record becomes `needs_review`, HR is notified (to-do and notification) and chooses **close** (record `cancelled`, candidate told, documents deleted after the retention setting) or **keep** (the hire was restored). An `active` record is never touched by Recruitment again.
8. A changed join date (`phase-8-hiring.md`) moves the due dates of the open items and tells the candidate and HR; it never changes an active record.

## 2. Employee context (the first slice, step D1 of `erp-direction.md`)

New context `src/Employee` (Domain, Application, Infrastructure, Interface), tables prefixed `employee_`, owned by it alone. Only what the hand-off needs now; the large
data model of section 9 (addresses, family, bank accounts with history, placement history) comes in the steps D2 and later.

| Table | Holds |
|---|---|
| `employee_employees` | `id`, `company_id` (the internal tenant), `user_id` (the member or job-seeker account; one open record per user, gap A2), `source_application_id` (nullable, unique: one hand-off per application), `status` (`pending`, `needs_review`, `active`, `cancelled`), `join_date`, `documents_complete`, timestamps, `activated_at`, `cancelled_at` |
| `employee_employment` | the copied terms: position title, `requisition_id` and unit (nullable), employment type, contract end date, probation months, salary, allowances (JSON by kind), the offer id they came from. Effective dating (`valid_from`) from the start, so D2 only adds rows |
| `employee_profile` | the copied identity: full name, phone, domicile (the `Address` value object of `src/Shared`), personal e-mail. Plain columns now; NIK and the other sensitive fields arrive with D3 under the encryption rules of section 9 |
| `employee_preboarding_templates` and `_template_lines` | the checklist of a company: document kind, required flag, `due_days_before_join` (can be 0 or negative for "after joining"), order, an instruction text |
| `employee_preboarding_items` | one per line per employee: `kind`, `required`, `due_on`, `status` (`open`, `uploaded`, `approved`, `rejected`), `rejection_reason`, the file (private disk, encrypted at rest, random path, size, mime), `reviewed_by`, `reviewed_at` |

Document kinds (an enum, one more kind is a migration): `ktp`, `npwp`, `kk`, `diploma`, `transcript`, `skck`, `bank_account`, `bpjs_kesehatan`, `bpjs_ketenagakerjaan`,
`photo`, `other` (a line with `other` carries its own label). Files: images and PDF, the size limit is a setting, decoded or checked by content and re-encoded for images
(the pattern of `UploadDrawing`), never served by a public URL; a read goes through the API and is logged.

## 3. What the hand-off copies

One time, at the moment HR starts it, inside one transaction (a domain port in Shared, filled from the Recruitment and Job Seeker providers, so Employee does not read
their tables): the account link; the full name, phone and domicile from the job seeker profile (an applicant who came in by an invitation or from outside the profile
uses what the application carries); the position, join date and requisition from the application and its job; the terms of the **last accepted offer**. If the
offer terms of #12 are not coded when this is built, the copy takes what `recruitment_offers` has (position, salary, start date) and the other terms stay empty for HR.
What is **never** copied: test results and reports, interview notes and scorecards, the candidate's messages, the notes of the recruiters, the rejection history.

## 4. Roles and permissions

New permission group `employee` in the Company permission catalogue (the presets and the chips of the team page show it like any other group):
`employee.onboarding.view`, `employee.onboarding.manage` (start, close, activate), `employee.documents.view`, `employee.documents.review`,
`employee.checklist.manage`. A preset "HR onboarding" bundles them. Documents are readable only with `employee.documents.view`, and **every read is logged**
(`employee.document_viewed`, never the content). The audit entries hold ids and kinds, never names or file contents. Recruitment endpoints do not return anything of Employee.

## 5. API (v1, Workspace; Career for the candidate)

Company (permission in brackets):

- `GET employee/hand-offs/waiting` hires without a hand-off, with the join date and the job (`onboarding.view`); `POST employee/hand-offs` `{application_ids: [...]}` starts one or several; a skip list answers like the bulk moves (`application-not-hired`, `already-handed-off`, `tenant-not-internal`, `module-unavailable`) (`onboarding.manage`).
- `GET employee/employees` and `GET employee/employees/{id}` with `status`, completeness and the items without file data (`onboarding.view`); `POST employee/employees/{id}/activate` and `.../close` (`onboarding.manage`); `POST employee/employees/{id}/keep` clears `needs_review`.
- `GET employee/employees/{id}/items/{itemId}/file` the document (`documents.view`, logged); `PUT .../items/{itemId}/review` `{approve: bool, reason?}` (`documents.review`; a reason is required to reject, 422 `reason-required`).
- `GET/PUT employee/checklist` the company's template (`checklist.manage`); a copy of the lines is made into the items at hand-off, so a later change does not touch started onboardings.

Candidate:

- `GET me/onboarding` the items with their state, due date and rejection reason; `PUT me/onboarding/items/{id}/file` (multipart; while the item is `open` or `rejected`, 409 `item-locked` otherwise); the **to-do source** gives one task per open or rejected item due, and the **agenda source** the due dates.

Errors follow the catalogue (`api:error-catalog`), documentation group "Employee".

## 6. Notifications

Candidate: `employee.onboarding_started`, `.item_rejected`, `.item_due` (reminders **7 and 3 days before** and **on the day** of a due date, and **every 7 days while overdue** until the join date plus 30 days), `.activated`. HR: `employee.item_uploaded` (digest, not one per file),
`.needs_review`, `.item_overdue` (one digest a day). All in-app and e-mail under the user's notification preferences.

## 7. Settings (Console "Pengaturan")

`employee.preboarding_enabled` (default off until legal question 29 is answered; gap A6), `employee.document_retention_days` (default 30, 1 to 365), `employee.max_document_megabytes` (default 10), `employee.reminder_days` (default `7,3,0`). The module switch follows the module entitlements of `self-hosted.md` section 8.

## 8. Legal

Written as **question 29** of `legal-review.md` (2026-10-31, step C3; priority D, Indonesian copy updated): how long the pre-boarding documents of someone who does not join may be kept; the basis and the notice for collecting KTP, NPWP, KK and bank details before the first day; whether the copy of the offer salary into Employee needs the candidate's notice. The code does not wait for them; the retention is a setting.

## 9. Order of coding

Each part is coded, checked by the user (`vendor/bin/pint --test`, `php artisan test`, `scramble:clear && scramble:cache` after route or response changes, `api:error-catalog --check`) and committed on its own. Migrations from `000400`, run by the user. The C1 questions are answered (section 10), so nothing waits for the user before part 1.

| Part | Work | Depends on |
|---|---|---|
| 1 | **Foundation.** The context `src/Employee` (layers, Arch tests, service provider); the `Employee` aggregate with its status rules (`pending`, `needs_review`, `active`, `cancelled`) and the tables `employee_employees`, `employee_employment`, `employee_profile`; the settings of section 7 and `recruitment.hires_export_enabled` (gap A7); the check of the module `employee` through the `ModuleEntitlements` port (parts E1 and E2 of `self-hosted.md` section 8 are coded **before** this part, so there is no interim switch); the `employee` permission group and the preset "HR onboarding" in the Company catalogue; documentation group "Employee" in `ApiTags` | #27 (coded), E1 and E2 of the entitlements |
| 2 | **Checklist template.** `employee_preboarding_templates` and `_lines`, `GET/PUT employee/checklist` (`checklist.manage`), the document kinds, validation and audit | 1 |
| 3 | **Hand-off.** The port in Shared and its providers in Recruitment and Job Seeker (copy of section 3); `GET employee/hand-offs/waiting`, `POST employee/hand-offs` (bulk, skip codes), the items created from the template, `GET employee/employees` and `{id}`, `activate`, `close`, `keep`; listeners for a hire corrected, an offer withdrawn and a changed join date (`needs_review`, due dates moved); the scheduled activation command | 1, 2 |
| 4 | **Documents.** `employee_preboarding_items` upload from the candidate (`PUT me/onboarding/items/{id}/file`, checks of content, size and type, encrypted private disk), `GET me/onboarding`, the HR review (`approve` or reject with a reason), the logged file read, the retention command that deletes the files of a cancelled record | 3 |
| 5 | **Reminders and the candidate's lists.** The notification types and e-mail templates of section 6, the reminder and digest commands, the to-do source `preboarding_document` and the agenda source for the due dates (the ports of #15) | 3, 4 |
| 6 | **API close.** Demo data (an internal tenant with an HR member and a hired candidate), the error catalogue and its translations, `scramble:cache`, `HANDOFF.md` and `phase-8-index.md`, then `composer check` of the whole suite | 1 to 5 |
| 7 | **Workspace, "Karyawan baru".** The section and its permission checks, the waiting list with the bulk start, the employees list and detail, the document review, the checklist settings; `pnpm api:refresh`, i18n id and en | 6 |
| 8 | **Career, `/me/onboarding`.** The items with their due dates and rejection reasons, the upload, the to-do and agenda entries and the dashboard card | 6 |

Parts 7 and 8 do not depend on each other. Parts 1 to 3 are the smallest working slice (a record with its terms, the start, the status changes); documents come in part 4.

## 10. Open

- The existing HRIS is called **"the HR application"** (aplikasi HR) by the user, who does not know a product name (2026-10-31). Nothing in the design needs one: the hires export stays as it is, and its end is decided per tenant when that tenant starts Employee.
- The step 1 of section 9 is answered, so step 2 (the context `Employee`) can be coded when the user decides to start it; it still depends on #27 (company locations, coded).

## 11. Gaps found 2026-10-31 (before coding)

A check of this design against the code (nothing of `src/Employee` exists yet). Group A is decided with the user one at a time, before coding (all seven decided 2026-10-31); B corrects the design to the code; C is settled while coding and recorded here.

**A. Decisions needed**

| # | Gap | Status |
|---|---|---|
| A1 | The trigger "the offer is withdrawn" cannot happen: `Offer::withdraw` accepts only a `pending` offer, and a job seeker cannot withdraw a `hired` application (`phase-8-withdrawals.md`). The real triggers are a correction out of `hired` and the candidate's **account erasure**, which is not designed: what happens to a `pending` and to an `active` record when the account is erased. | Decided 2026-10-31 (user): the trigger "offer withdrawn" is dropped. **Erasure: a `pending` or `needs_review` record is cancelled**, HR is told and its documents are deleted after the retention setting; **an `active` record is kept** (the employment is its basis), its `user_id` link is cleared and HR is told. Employee provides an `ErasureStep` and a `PersonalDataExporter` (B5). |
| A2 | `user_id` is 1:1 and unique, which blocks a rehire, a move between legal entities of the group, and a person whose onboarding was cancelled and who is hired again. | Decided 2026-10-31 (user): **one open record per user** across the installation (`pending`, `needs_review` or `active`; a `cancelled` one does not count); a rehire or a move opens a new record once the old one has ended (the end states come with D2). `source_application_id` stays unique. A second start answers the skip code `already-employed`. |
| A3 | The waiting list ("hired applications of the tenant without a hand-off") holds every old hire, the imported ones and those from before Employee, against the decision that an employee hired before Employee stays in the old HRIS. A cut-off is needed. | Decided 2026-10-31 (user): **only hires that entered `hired` on or after the day the module Employee first became active for the tenant's group** (kept by the entitlements, which already record the modules a group ever had, `self-hosted.md` section 8, gap A4); imported hires never show. No setting. The moment of the hire is the last entry into `hired` in `recruitment_application_stage_history` (no `hired_at` column exists), read by the Recruitment provider of the port. |
| A4 | Overlap with the pre-employment checks (#32): the check kinds `police_record` (SKCK) and `education` (diploma) already collect files, and the checklist has `skck` and `diploma`, so the candidate may be asked twice; the results of the checks are not carried either. | Decided 2026-10-31 (user): **different purposes, nothing copied.** A pre-employment check verifies before the hire for the recruiting team (its outcome a category); a pre-boarding document is a copy for the employee file. Both stay; HR leaves `skck` or `diploma` out of the checklist when a check covered it; check outcomes and files are not carried (the "never copied" rule of section 3). |
| A5 | The entitlements (`self-hosted.md` section 8, gaps A3 and A4): while Employee is read-only, may an onboarding already started finish (the candidate's uploads, the HR review, the scheduled activation)? The skip code `module-not-enabled` differs from `module-unavailable` and `module-expired`. | Decided 2026-10-31 (user): **what started may finish.** A `pending` record keeps going while Employee is read-only (the candidate's uploads, the HR review, the scheduled activation, the reminders); only a new start and a change of the checklist are refused (409 `module-expired`). The skip code `module-not-enabled` becomes `module-unavailable`. |
| A6 | `decisions.md` section 5 ships legal-blocked work switched off. Question 29 is open, and this design says the code does not wait but names no switch: is the module licence the switch, or a setting of its own (off by default)? | Decided 2026-10-31 (user): **a setting of its own**, `employee.preboarding_enabled` (Console "Pengaturan", default off). While off, the hand-off and the record run (identity and terms), but no document items are made and `/me/onboarding` is empty; the legal answer only turns it on. |
| A7 | "The hires export is switched off per tenant that uses Employee" has no mechanism in any part of section 9 (off by itself while the module is active, or a company setting). | Decided 2026-10-31 (user): **a setting overridable per company**, `recruitment.hires_export_enabled` (default on); HR turns it off when the old HRIS stops for that tenant, so both can run side by side during the change. While off, the export answers 404 `hires-export-disabled`. Coded in part 1. |

**B. Design corrected to the code**

- B1. The fallback of section 3 ("if the offer terms of #12 are not coded") is stale: the terms are coded (2026-10-18, migration `000165`). `employee_employment` lacks `work_mode`, `work_location`, `salary_basis` (gross or net) and `benefits`; the offer holds `contract_months`, not an end date (computed from the join date); its `allowances` are lines of a name and an amount, not "by kind".
- B2. A hire without an accepted offer exists (`phase-8-hiring.md`, the export): its terms stay empty for HR.
- B3. A join date may lie up to 90 days in the past (`HireDetails::PAST_DAYS_MAX`), so an onboarding started after the join date is activated at the next run, its due dates are past and the overdue reminders start at once; a rule is needed.
- B4. No file is encrypted at rest today (`FileStorage` has no such option; only two-factor secrets and portal preview tokens are encrypted). Part 4 needs that storage as new infrastructure.
- B5. Every context with personal data has an `ErasureStep` and a `PersonalDataExporter`; Employee has neither in section 9 (follows A1).
- B6. The Company catalogue grows from 30 to 35 permissions; the `employee.*` permissions are hidden and filtered while the module is off (`self-hosted.md` section 8, B4).

**C. Settled while coding**

- C1. `employee.reminder_days` ("7,3,0") fits no setting type (`Integer`, `Boolean`, `Text`, `Choices`): `Choices` of fixed days, or `Text` with a check.
- C2. The overdue rhythm (every 7 days until the join date plus 30 days) is fixed while the reminders before the due date are a setting.
- C3. Retention covers only a cancelled record; rejected or replaced files and the documents of an active employee have no rule.
- C4. A notification preference category and the e-mail templates of Employee in Console.
- C5. Migrations from `000400`; the latest is `000390`, so check the number when coding.
- C6. "Karyawan baru" reads the modules of the session data (`self-hosted.md` E3) and the permissions (front-end rule 20).
- C7. The limit of a bulk start: `recruitment.max_bulk_applications`.
