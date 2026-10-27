# Phase 1: Screening question presets per job level (design, 2026-10-27)

Status: **design fully approved by the user on 2026-10-27** (the four product questions, the three tables, refusing archived questions at apply time, no system presets). Parts 1 to 4 coded 2026-10-27/28 (see "Coded").
Extends `phase-1-screening.md` (library, job attachment, limits, locks). Nothing there changes except what is stated here.

## Decisions

- **2026-10-27: Preset = a named, ordered set of library questions with their per-job flags** (required, must-have, ideal answer), owned by a company. See "Model".
- **2026-10-27: Optional level hint.** A preset has a free name and an optional `job_level_id`; the level only drives a suggestion when a job is edited, it never restricts which preset can be applied. See "Level hint".
- **2026-10-27: Applying copies.** Applying writes the preset's content into `recruitment_job_questions`; later changes to the preset never touch jobs. See "Applying a preset".
- **2026-10-27: Apply replaces the whole list** of the job, like `PUT .../screening-questions`; the same locks and the same limit apply.
- **2026-10-27: Rights.** Company admins create, change and delete presets; admins and recruiters apply them to the jobs they may manage. A preset is at most `recruitment.max_screening_questions` long and follows `recruitment.screening_enabled`.

- **2026-10-27: Tables approved** (`recruitment_screening_presets`, `recruitment_screening_preset_questions`, `recruitment_screening_preset_ideal_options`, with the FK to `reference_job_levels`). See "Model".
- **2026-10-27: Archived question in a preset is refused at apply time** (422 `invalid-job-questions`, offending questions named), not skipped. See "Applying a preset".
- **2026-10-27: No system presets and no seed**, consistent with "no system templates".

## Why (the gap)

Today a company picks questions for every job by hand from its library; the library has no notion of level and the design says "no system templates". A company that posts "PHP Programmer Junior" and
"PHP Programmer Senior" repeats the same selection and flags each time. Presets remove the repetition without changing the library or the answers.

## Model

Bounded context: **Recruitment** (Job still knows nothing about screening). Proposed tables (**to be approved**):

1. `recruitment_screening_presets`: `company_id` (FK `company_companies`, RESTRICT), `name` varchar(100), `job_level_id` bigint unsigned null (FK `reference_job_levels`, RESTRICT), `created_by_user_id`
   (plain column, as in the library), timestamps. Unique (`company_id`, `name`), case-insensitive.
2. `recruitment_screening_preset_questions`: `preset_id` (FK CASCADE), `question_id` (FK `recruitment_screening_questions`, RESTRICT), `position`, `is_required`, `is_must_have`, `ideal_yes` tinyint null,
   `ideal_min` and `ideal_max` bigint unsigned null; unique (`preset_id`, `question_id`). Same columns and rules as `recruitment_job_questions`.
3. `recruitment_screening_preset_ideal_options`: `preset_question_id` (FK CASCADE), `option_id` (FK `recruitment_screening_question_options`, RESTRICT); unique pair. Like
   `recruitment_job_question_ideal_options`; foreign key names carry the table prefix and are explicit (64-character limit).

The content rules are the ones of a job's list and are reused, not copied: must-have forces required, a must-have needs an ideal answer, `text` cannot be must-have, an `ideal` on a non-must-have is refused.

## Level hint

- `job_level_id` is optional and only a hint. On the job edit screen the apply menu lists the presets of the company; those whose `job_level_id` equals the job's `job_level_id` come first and are marked as
  suggested. A job without a level, or a preset without one, simply gets no suggestion.
- The job's level is read through the existing port `JobDirectory` (to be extended with the job's `job_level_id`; the exact shape is fixed when coding). Several presets may share a level.
- `experience_level_id` is not used. Revisit only if companies ask for "years of experience" presets.

## Applying a preset

- `POST /api/v1/company/jobs/{id}/screening-questions/apply-preset` with `{preset_id}`. Result: the new ordered list of the job (same shape as `GET .../screening-questions`).
- It does exactly what `ReplaceJobScreeningQuestions` does with the preset's items: same access (`JobsCreate`, the job must be one the member may manage), same `screening-disabled`, `job-not-editable`,
  `job-questions-locked` and `screening-limit-reached` (a preset never exceeds the limit when saved, but the limit may have been lowered since; then 409 as today).
- **Archived library questions:** a preset keeps an archived question (like a job does), but applying a preset that contains one is refused with 422 `invalid-job-questions`, because an archived question cannot
  be attached anew. The apply response names the offending questions. (Alternative, not chosen: skip them silently.)
- A preset of another company, or an unknown id, is 404.
- Implementation idea: extract the draft-building and validation of `ReplaceJobScreeningQuestions` into a shared collaborator so the two use cases cannot drift; the apply use case maps preset rows to the same
  items array.

## Library endpoints (proposal)

Under `/api/v1/company/screening-presets`: `GET` (every company member, `filter[q]`, `filter[job_level_id]`, offset-paginated) and `GET {id}`; company admins only: `POST {name, job_level_id?, questions: [...]}`,
`PUT {id}` (replaces name, level and the ordered questions in one call, same item shape as `PUT /company/jobs/{id}/screening-questions`) and `DELETE {id}`. Errors: `invalid-screening-preset` (422, `errors` per
`questions.<n>.<field>`), `screening-limit-reached` (409, more questions than the limit), `screening-disabled` (403, every change while the switch is off; reading stays possible).

- **Library questions in use by a preset:** a preset is not an application, so it never blocks editing; but deleting a library question that a preset uses is 409 `question-in-use` (matches the RESTRICT foreign
  key); the company removes it from the preset first. Archiving is allowed. Deleting a choice that a preset uses as an ideal answer is 409 `question-in-use`. (`ScreeningQuestionUsage` gets this second source.)
- Presets are **not audited**, like the library.
- Deleting a preset never touches jobs (nothing points back to it).

## Frontend (Workspace)

- A "Presets" screen next to the question library: list, create, edit (the same question picker and flags editor as the job screen), delete.
- On the job's screening questions screen: a "Use preset" button (menu, suggested ones first); applying asks for confirmation because it replaces the current list; hidden while the list is locked.
- Frontend rules of `CLAUDE.md` apply (icons, spacing scale, card per section).

## Approved open items (2026-10-27)

1. The three tables above (names, columns, the FK to `reference_job_levels`): approved.
2. Archived questions in a preset at apply time: refused.
3. No system presets, nothing seeded.

The endpoint shapes, the `JobDirectory` extension and the shared validation collaborator stay implementation details to settle while coding.

## Coding plan (for a fresh session)

Part 1: three migrations (test database first), domain (`ScreeningPreset`, reuse of `IdealAnswer` and `QuestionRules`), repository. Part 2: preset endpoints and the second source in `ScreeningQuestionUsage`.
Part 3: the `JobDirectory` level read, the apply endpoint, shared validation extraction. Part 4: Workspace screens. Scramble cache after each API change.

## Coded

- **Part 1 (2026-10-27):** migrations `000196` (`recruitment_screening_presets`, unique `company_id` + `lower(name)`), `000197` (`recruitment_screening_preset_questions`), `000198` (`recruitment_screening_preset_ideal_options`).
  Domain `ScreeningPreset` (its questions are `JobQuestion`s, its drafts are `JobQuestionDraft`s, so flags and `IdealAnswer` are reused as they are) and the port `ScreeningPresetRepository`
  (`find`, `nameTaken`, `create`, `update` rewrites the whole list, `delete`); `DatabaseScreeningPresetRepository`, bound in `RecruitmentServiceProvider`. Validation of the content (must-have rules, archived
  questions, limit) is not in the repository: it comes with the shared collaborator of part 3. Test: `tests/Feature/Recruitment/ScreeningPresetRepositoryTest.php`.
- **Part 2 (2026-10-27):** `GET/POST company/screening-presets`, `GET/PUT/DELETE company/screening-presets/{id}` (`ScreeningPresetController`; reads for `JobsCreate` or `ScreeningQuestionsManage`, changes for
  `ScreeningQuestionsManage` and while `screening_enabled`). List sorted by name, `filter[q]`, `filter[job_level_id]`. Validation lives in `ScreeningPresetCheck` (name 1 to 100 characters, unique per company ignoring case,
  active job level via `ReferenceValues`, limit like a job's list) and the new shared `ScreeningItemDrafter`, which `ReplaceJobScreeningQuestions` now uses too (the extraction planned for part 3 was needed here); an
  archived question already in the preset is kept. Codes: `invalid-screening-preset` (422, `errors` on `name`, `job_level_id`, `questions.<n>.<field>`), `screening-limit-reached`, `screening-disabled`.
  Interpretation: the "second source" of `question-in-use` is in `ScreeningQuestionRepository::isAttached` and `idealOptionIds` (job or preset), not in `ScreeningQuestionUsage` (that one is about applications); the two
  messages say "a job or a preset". Test: `ScreeningPresetTest`.
- **Part 3 (2026-10-27):** `JobSummary` carries `jobLevelId` (`JobDirectory::visibleToMember` fills it from the job content). `POST company/jobs/{id}/screening-questions/apply-preset` `{preset_id}`
  (`ApplyScreeningPreset`): refuses a preset with an archived question (422 `invalid-job-questions`, `errors` on `questions.<n>.question_id`), then hands the preset's items to `ReplaceJobScreeningQuestions`, so access,
  locks, switch and limit are the job list's own; returns the new list like `GET .../screening-questions`. `GET company/jobs/{id}/screening-presets` (`ListJobScreeningPresets`, `page` and `per_page` only) is the menu:
  the presets of the company by name, those whose level hint equals the job's level first, each with `suggested`. `IdealAnswer::toInput()` writes a stored ideal back in the shape callers send. Test: `ApplyScreeningPresetTest`.
- **Part 4 (2026-10-28, Workspace, `onesis-talent`):** page `/preset-screening` (menu "Screening presets" under Tools, `screening_questions.manage`): search, list with level badge and question count, form in the end offcanvas
  (`ScreeningPresetForm`: a card for name and level hint, a card for the questions), delete with confirmation. The list editor of the job dialog became the shared `ScreeningItemsEditor`, used by both.
  The job's screening tab has a "Use preset" menu (`GET company/jobs/{id}/screening-presets`, suggested ones first with a badge) and a confirmation before `apply-preset`. Interpretation: the API has no
  lock flag on the job's list, so the menu is not hidden up front; the 409 `job-questions-locked` (and `invalid-job-questions`, `screening-limit-reached`) text is shown when applying. Needs `pnpm api:refresh`.
