# Phase 2, step 5: applicant CSV export (R13, designed 2026-10-08, not coded)

Context: `Recruitment` (the export), `Platform` (the audit entry). Spec in `decisions.md` (R13): applicant CSV export per job by company admins and assigned recruiters; every export is audit-logged
(action `export.applicants`, already named in `phase-0-schema.md`). No table is needed (`gap-analysis.md`).

## Confirmed by the user (2026-10-08)

- **Delivery:** a streamed download. `GET company/jobs/{id}/applications/export` answers `text/csv` at once; nothing is stored and no queue is used.
- **Scope:** the same filters as the pipeline list (`stage_id`, `stage_type`, `meets_must_have`, `search`); with no filter, every application of the job. The order is the list's `newest_first` default.
- **Columns:** core plus the screening answers: application id, applied at, name, email, phone, city, age, headline, latest job (title and company), expected salary, stage, must-have result, average rating,
  rating count, and one column per screening question of the job (the question text as the header).
- **Limits:** a rate limit of 10 exports per member per hour (throttle); no row cap and no new setting.
- **Who (R13):** company admins and recruiters assigned to the job, the same access as the pipeline list (404 for a job the member may not see; 403 for a non-member or a suspended company).

## Details (approved by the user, 2026-10-08)

- **Reuse, not a second reader:** the use case `ExportJobApplications` checks access like `ListJobApplications`, reads the rows through `RecruiterApplicationReader` (a cursor variant of `page`, so memory stays flat),
  adds the contact fields and the screening answers, and hands the rows to a `CsvWriter` in Infrastructure. The controller only streams.
- **Audit:** one entry `export.applicants`, actor the member, subject `job` and its id, metadata `{filters, row_count}` (stage ids and types and the must-have flag; the search text is **not** stored because it is a
  piece of a person's name). Recorded through a domain event `ApplicantsExported` and a Platform listener (the pattern of `RecordJobAuditEntries`), after the stream has been built so the count is exact.
  If the entry cannot be written the export is not sent (the audit is part of the request, not a best effort).
- **Format:** UTF-8 with a byte order mark (so Excel opens it correctly), comma separated, CRLF line ends, every field quoted when needed. Dates are ISO 8601 in UTC; the salary is the amount in Rupiah with
  the period in its own column. Booleans are `yes` or `no`; an unknown must-have result is empty.
- **Spreadsheet formula injection:** a text field that starts with `=`, `+`, `-`, `@`, a tab or a carriage return gets a leading apostrophe. Applicant text is untrusted.
- **Anonymised applicants** (account erased): the row stays for the count; every personal column is empty and the name column reads `Deleted account`.
- **File name:** `applicants-job-{id}-{YYYYMMDD}.csv`, sent as an attachment (`Content-Disposition`), `Cache-Control: no-store`.
- **Screening answers:** a multi-choice answer is its choices joined with `; `; an unanswered question is empty; archived questions that the job still holds are included, in the job's question order.
- **Not in the export:** notes, ratings by person, interviews, offers, resume files and rejection reasons (the last stays hidden from applicants and is not a column by decision).
- **No migration, no new permission, no new setting.** The audit action constant is added to `AuditAction`; the throttle limit lives in the route definition (a named rate limiter).
