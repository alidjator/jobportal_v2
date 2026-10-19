# Phase 2, step 5: applicant CSV export (R13, designed and coded 2026-10-08)

Context: `Recruitment` (the export), `Platform` (the audit entry). Spec in `decisions.md` (R13): applicant CSV export per job by company admins and assigned recruiters; every export is audit-logged
(action `export.applicants`, already named in `phase-0-schema.md`). No table is needed (`gap-analysis.md`).

## Confirmed by the user (2026-10-08)

- **Delivery:** a streamed download. `GET company/jobs/{id}/applications/export` answers `text/csv` at once; nothing is stored and no queue is used.
- **Scope:** the same filters as the pipeline list (`stage_id`, `stage_type`, `meets_must_have`, `search`); with no filter, every application of the job. The order is the list's `newest_first` default.
- **Columns:** core plus the screening answers: application id, applied at, name, email, phone, city, age, headline, latest job (title and company), expected salary, stage, must-have result, average rating,
  rating count, and one column per screening question of the job (the question text as the header).
- **Limits:** a rate limit of 10 exports per member per hour (throttle); row cap added 2026-10-11 (architecture review finding 16): setting `recruitment.max_export_rows` (default 50,000, 1 to 200,000, overridable per company); a larger selection is refused with 422 `export-too-large` (extension `max_rows`) before the audit, and the CSV is streamed (`StreamedResponse`), not built in memory.
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
- **Interviews:** four columns after the rating columns, the summary of the latest interview with a sent scorecard (`phase-8-interview-results.md` section 4). **Not in the export:** notes, ratings by person, interview notes and per-person scores, offers, resume files and rejection reasons (the last stays hidden from applicants and is not a column by decision).
- **No migration, no new permission, no new setting.** The audit action constant is added to `AuditAction`; the throttle limit lives in the route definition (a named rate limiter).

## Coded (2026-10-08)

- No migration. Endpoint `GET company/jobs/{id}/applications/export` (filters and `sort` as the list, no paging; throttle `applicant-export`, 10 per member per hour, then 429).
- Use case `ExportJobApplications`; `RecruiterApplicationReader::export` yields the rows (ids first, then chunks of 200); `CsvDownload` writes the file (BOM, CRLF, formula guard). Audit action `export.applicants`
  through `ApplicantsExported` and the **non-queued** listener `RecordRecruitmentAuditEntries` (so a failed audit fails the export). Metadata: `filters`, `row_count`, `company_id`.
- Choices made while coding: the use case collects the rows (a job has few applications) so the count is exact before the audit; the audit is written before the file is returned; the file date is the use case clock's (UTC);
  the salary period column holds the English name; answers to a multiple choice are joined with `; `.
- Tests: `ApplicantExportTest` (8).
