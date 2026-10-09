# Phase 3, step 5: CV generator (J7, designed 2026-10-09)

A job seeker turns their profile into a PDF CV, to download or to attach to an application. Spec in `decisions.md` (J7): the PDF is built from the structured profile; **several built-in templates in code; an admin can enable or disable templates
and set the default**; at apply time the job seeker picks an uploaded resume **or** a CV generated at that moment from the latest profile, and the generated PDF becomes the application's own file (`phase-1-jobseeker.md`, decision for step 7).
This closes Phase 3 (discovery and matching).

## Confirmed by the user (2026-10-09)

- **Engine:** `dompdf/dompdf` (pure PHP, HTML to PDF, no program to install on the server; new Composer dependency).
- **Templates:** three, in code: **Classic** (one column, formal), **Modern** (two columns, coloured side) and **Compact** (dense, one page where it fits).
- **Contents:** by default the name, headline, summary, contact (email, phone, city and province), work experience, education, skills, languages and links. **The job seeker chooses per CV** whether to add the photo, the birth date, the gender,
  the religion, the marital status and the nationality; none of them is included unless ticked.
- **Language:** chosen when the CV is made, `id` or `en` (default: the account language). Only the section titles and the dates follow it; what the job seeker wrote is not translated.
- **Not stored:** a CV is made when it is asked for (download) or when it is used (application) and is never added to the list of resumes, so the limit of five resumes and the resume list are untouched. An application keeps its own copy as for any resume.
- **Limit:** a global admin setting `job_seeker.cv_generations_per_hour` (default 10, range 1 to 200) per job seeker, for downloads and applications together; over it the answer is 429 `cv-limit`.

## Details to approve (proposed, not confirmed yet)

- **Context:** inside `JobSeeker` (it owns the profile): `Application/Cv` (use cases and the `CvDocument` the templates are filled from) and `Infrastructure/Pdf` (the dompdf renderer behind a `CvRenderer` port). The templates are Blade views under `resources/views/cv/`;
  every value goes through Blade's escaping and dompdf runs with remote files and PHP off, images only as embedded data, so a profile cannot pull a file or run code. The font is DejaVu Sans (bundled with dompdf): Latin letters with accents are fine, scripts it lacks (Chinese, Arabic) are not.
- **Data:** the profile, experience, education, skills and languages come from the JobSeeker tables; the name, email and language of the account from the existing `ApplicantContacts` contract; the photo is the account picture (`identity_users.avatar_path`, read as the candidate search does) through `FileStorage`.
  No expected salary, no street address and no WhatsApp number are ever printed. Sections with nothing in them are left out; a profile with no history at all still gives a CV (name and contact).
- **Template availability:** the set of templates is fixed in code; their state is the table `jobseeker_cv_templates` (`key`, `enabled`, `is_default`), filled by the migration (all three enabled, Classic the default). A template with no row (added later in code) counts as enabled and not default.
  The default must be enabled and at least one template must stay enabled (409 `cv-template-required`).
- **Endpoints (job seeker):** `GET me/cv/templates` (the enabled ones: `key`, `name`, `is_default`), `POST me/cv` `{template?, language?, include?: {photo, birth_date, gender, religion, marital_status, nationality}}` answers the PDF
  (`application/pdf`, `attachment`, named `CV <name>.pdf`, `no-store`, `nosniff`, `Content-Security-Policy: default-src 'none'`). Errors: 422 `template-unavailable` (unknown or disabled), 422 validation, 429 `cv-limit`; only a job seeker (403 `job-seeker-only`).
  **Endpoints (admin):** `GET admin/cv-templates` (all three with `enabled`, `is_default`), `PATCH admin/cv-templates/{key}` `{enabled?, is_default?}` under a new permission `cv_templates.manage`.
- **Applying:** `POST jobs/{id}/applications` takes **either** `resume_id` (as today) **or** `cv` (the same fields as `POST me/cv`), not both and not neither (422 `invalid-application`). With `cv`, the PDF is made at that moment from the latest profile, counts against the hourly limit
  (429 `cv-limit` refuses the application, nothing is created), and is copied into the application like any resume, named `CV <name>.pdf`. Recruitment asks JobSeeker for it through the existing `ApplicantProfileSource` contract (a new method for the generated case); the application stores no sign of how the file was made.
- **The limit** is counted in a small table `jobseeker_cv_generations` (user, created_at): the count of the last hour is read, the PDF made, and one row added only when it succeeded (read before write, like the chat quota: two requests at the very same moment may overshoot by one);
  rows older than a day are deleted when a new one is added. Anonymising the account deletes its rows.
- **No new notification, no new email.** The legacy has nothing to import.
- **Tests:** the content of the document for each option (a fake renderer records it), each template really rendered to a PDF (starts with `%PDF-`), section order and omitted empty sections, language, access, template availability rules (default, last one), the admin endpoints and permission,
  the limit and its setting, an application with `cv` (file copied, limit, both-or-neither rule), erasure.

## Coded (2026-10-09, checked with 1661 tests, committed)

Inside `src/JobSeeker`: `Application/Cv` (use cases, `CvDocumentBuilder`, `CvTemplates`, `CvQuota`, `CvLabels`), `Domain` (`CvTemplate`, `CvInclusion`, `CvTemplateState` and the two repositories), `Infrastructure/Pdf/DompdfCvRenderer`, the views `resources/views/cv/{classic,modern,compact}.blade.php`,
`CvController` and `AdminCvTemplateController` (routes in `routes.php` and the new `routes.admin.php`), permission `cv_templates.manage` (`JobSeekerPermissions`), setting `job_seeker.cv_generations_per_hour`, migrations `000093` (`jobseeker_cv_templates`, with its three rows) and `000094` (`jobseeker_cv_generations`).
Shared: `GeneratedCvSpec` and the new method `ApplicantProfileSource::forApplicationWithGeneratedCv`; Recruitment: `ApplyToJobInput`, `ApplyToJobRequest` and `ApplyToJob`. New dependency `dompdf/dompdf` ^3.1. Tests: `tests/Feature/JobSeeker/CvGeneratorTest.php`; `SettingsTest` lists the new setting.
The proposed details were coded as written; what was decided while coding:

- **A job seeker with no uploaded resume can apply with a generated CV.** The CV stands in for the resume, so for this path the profile needs only a phone, a location and one experience or education entry; a profile that still lacks something gets `422 profile-incomplete` and no PDF is made (and no slot used).
- **`cv: {}` means the default choices.** `resume_id` and `cv` are both optional in the request; the use case refuses both or neither with `422 invalid-application` (error under `resume_id`). Bad `cv` fields (language other than `id`/`en`, `include` not a map of booleans) are ordinary validation errors; an unknown key in `include` is `422 invalid-cv`.
- **Photo:** the account picture when it is a JPEG or PNG (the avatar processor stores nothing else); otherwise the photo is silently left out.
- **The default of the templates:** the stored default that is on; with none stored, the first template that is on. The default cannot be taken away directly (make another template the default); switching the default off hands the flag to the first template that stays on.
- **File name:** `CV <account name>.pdf` (control characters and path separators removed, name cut at 100 characters).
- **The PDF renderer** keeps dompdf's own font folder (DejaVu Sans) and writes only its font cache and temporary files under `storage/framework/dompdf`. The layout was checked by rendering the three templates, not by eye: a visual check of each template is worth doing once.
- **After deploying:** run `php artisan identity:sync-permissions` so the permission `cv_templates.manage` exists, then give it to the admins who should manage the templates.
- **Visual check (2026-10-09):** the three templates were rendered with a full, a sparse and a very long profile in both languages and looked at as images. The `modern` template, a two-column table, made dompdf push the whole row to a later page (three blank pages before the content); it is now a coloured header over one column. Lesson: do not lay a page out with one tall table row in dompdf; rows do not split across pages.
