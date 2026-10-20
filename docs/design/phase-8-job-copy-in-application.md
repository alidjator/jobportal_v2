# Phase 8: the job content a candidate applied for (designed 2026-10-20)

Closes MG-A10 of `docs/analysis/multi-persona-gap-analysis.md` (decision 3). Builds on `phase-1-applications.md` (the profile snapshot at apply) and `phase-8-activation.md` (imported applications). Design only: nothing is coded.

## Decisions

- 2026-10-20 A copy of the **job content** is stored with the application when the candidate applies, so the candidate can read what they applied for after the job closes (gap-analysis decision 3; [section 1](#1-what-is-copied-and-where-it-is-read)).
- 2026-10-20 **Content without company data:** title, description, work mode, location (province, city, address line), salary when the company shows it, skills, tags, benefits. Not copied: vacancies, deadline, company name and logo (they are on the application already) (user; [section 1](#1-what-is-copied-and-where-it-is-read)).
- 2026-10-20 **Existing applications, imported ones included, are filled once by a migration** from the job row as it is then, marked `as_of_migration`; new applications keep the content as it was when they applied (user; [section 2](#2-applications-that-exist)).

## 1. What is copied and where it is read

- **Column:** `recruitment_applications.job_snapshot` (jsonb, nullable, written once and never changed), built by `ApplyToJob` next to `profile_snapshot` from a new method of the `JobDirectory` port, `contentForApplicant(jobId)`, which returns exactly the public job page's content (the same rules: the salary only when the job shows it; no assignee, creator or review data).
- **Shape:** `{version: 1, title, description, work_mode, locations: [{province, city, address_line}], salary: {min, max, currency, period}|null, skills: [{id, name: {id, en}}], benefits: [{id, name: {id, en}}], tags: [], as_of_migration: bool}`. The master names are kept in both languages so the candidate reads them in their own; the location names are the reference names of the day (one language).
- **Read:** `GET me/applications/{id}` gains `job_content` (the object above, with the names in the request language, `as_of_migration` included), and the title on J5 links to the application, not to the job. The list (`GET me/applications`) does not carry it. The company side does not need the copy (the company reads its own job).
- **Frozen:** a later edit of the job never changes the copy; that is the point. The copy is erased with the application (`EraseApplicantData`, which already clears the snapshot) and exported with the other application data (`ApplicationsDataExporter`).

## 2. Applications that exist

- A migration fills `job_snapshot` for every application from its job row **once**, with `as_of_migration: true`. The migration does not fail on an application whose job is gone (`job_id` has a foreign key, so none is).
- `ApplicationsImporter` builds the same copy from the imported job row (`as_of_migration: true`) so the final import carries it; this changes the import and joins the second rehearsal.
- J5 shows a short line when `as_of_migration` is true: "Isi lowongan di bawah ini adalah versi terakhir sebelum Anda dipindahkan; mungkin sudah berubah sejak Anda melamar."

## 3. Tests

Apply copies the content; a later job edit and close leave the copy unchanged; the salary is hidden when the job hides it; names come back in the request language; an application of the migration has `as_of_migration`; the importer sets it; the list does not carry it; erasure clears it; the export holds it.

## Coded (2026-10-20)

Migration `000183_add_job_snapshot_to_recruitment_applications` (new; the column only). Changes the import, so it joins the second rehearsal with #34 and #36. Interpretations made while coding:

- **The fill is a command, not the migration.** `recruitment:backfill-job-snapshots` (run once after the migration; idempotent, id order, batches of 200, the content of each job built once per run) fills the applications that have no copy, from the job as it is then, with `as_of_migration: true`. A migration that builds the content would depend on the application's classes and master translations; a command can be repeated. Until it has run, `job_content` is null. An erased application (no profile snapshot) is left out, so an erasure is never undone by a rerun.
- **The port is `JobDirectory::contentSnapshot`** (Shared, provided by Job through `JobContentSnapshot`); it builds the content of any job, live or not, and holds nothing about the company, the assignees or the status. `ApplyToJob` saves it in the same transaction as the application; `ApplicationsImporter` writes it with the application (marked `as_of_migration`).
- **Keys:** `version`, `title`, `description`, `work_mode`, `locations` (one item from the job's own address, empty without one), `salary` (`min`, `max` and the period with both names, only when the job shows it), `skills` and `benefits` (`id` and a name in `id` and `en`), `tags`, `as_of_migration`. There is no currency in the schema, so `salary` has the period instead.
- **Read:** `GET me/applications/{id}` carries `job_content` in the language of the request (the other language when an item has no name in it); the list does not. `GetMyApplication` takes the language. The company resources do not carry it.
- **Erased and exported:** the applicant erasure sets `job_snapshot` to null with the profile snapshot, and the personal data export holds it as `job_content`.
- **Not done:** the line of J5 for `as_of_migration` is the front end's; `locations` carry the reference names of the day (one language).

## Open points

None.
