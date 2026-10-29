# Phase 8: merging duplicate reference items (designed 2026-10-20)

Closes MG-C02 of `docs/analysis/multi-persona-gap-analysis.md` (decision 11). Builds on `phase-1-migration.md` (every unmatched legacy skill, job role and job category became a new master row) and `phase-0-schema.md` ("Masters"). Design only: nothing is coded.

## Decisions

- 2026-10-20 An admin **merges** a duplicate into another item of the same master: every reference moves to the target, the source is retired, and its old slug resolves to the target (gap-analysis decision 11; [section 1](#1-the-merge)).
- 2026-10-20 **Only three masters can be merged:** `skills`, `job-roles` and `job-categories`, the ones the import filled with unmatched rows; the other masters are fixed lists (user; [section 1](#1-the-merge)).
- 2026-10-20 **Past daily summaries move too** (`job_market_daily` per category is re-pointed and summed), so the reports stay consistent (user; [section 3](#3-who-points-at-an-item)).

## 1. The merge

- **Endpoint:** `POST admin/reference/{master}/{id}/merge` with `{target_id}` (master is `skills`, `job-roles` or `job-categories`; 422 `master-not-mergeable` otherwise), permission `reference_data.manage` (the one D15 uses), 200 `{moved: {<context>: n}, removed_duplicates: n}`. A dry run is `GET admin/reference/{master}/{id}/merge-preview?target_id=` (same counts, writes nothing); D15's button shows the preview before it asks to confirm.
- **Rules:** the target exists, is active and is not the source (422 `invalid-merge-target`); a source that was merged before is not an item any more (404); the source and the target are in the same master. One transaction; the two rows are locked in ascending id.
- **Effects:** every reference of section 3 moves to the target; the source becomes inactive (`is_active = false`) and keeps its row, its translations and its slug; a row in `reference_item_merges` (`master`, `source_id`, `target_id`, `merged_by_admin_id`, `moved` jsonb, `created_at`; unique `(master, source_id)`) records it; audit `reference.item_merged` (admin, master, source and target slugs, counts). **No undo endpoint:** the references cannot be told apart afterwards, but the retired row and the log allow a manual reversal.
- **Slugs:** `idBySlug` and the public filters that take a slug (`GET jobs?skill=…`, candidate search, alert criteria by id are re-pointed) look the slug up in `reference_item_merges` and return the target when the slug belongs to a merged source; a merged source is not listed in the public lists and the pickers (it is inactive).
- **Not built:** merging two items of different masters, merging more than one source at once (repeat the call instead), and renaming the target on merge.

## 2. A port per context

- **Why:** ReferenceData may not write other contexts' tables. The new Shared port `ReferenceMergeTargets` (`kinds(): list<string>`, `usage(kind, id): int`, `repoint(kind, fromId, toId): int`) is implemented once per context that holds references and registered in a `ReferenceMergeCatalog` from its service provider, as the other catalogs are. `MergeReferenceItem` calls every registered target inside its transaction and sums the counts; a context that is not registered keeps its references pointing at the retired item (the test below names each).
- **Order of work inside the transaction:** re-point, then retire, then log, then the audit entry; any exception rolls everything back.

## 3. Who points at an item

| Kind | Where (context, table or column) | Rule at a conflict |
|---|---|---|
| skills | JobSeeker `jobseeker_skills.skill_id` (unique with the user) | the user keeps the target's row; the source row is deleted and the positions are closed up |
| skills | Job `job_job_skills.skill_id` (unique with the job) | the job keeps one row; the source row is deleted |
| skills | Recruitment `recruitment_application_facets.skill_ids` (integer array) | the source id is replaced by the target, duplicates removed |
| skills | JobAlert `jobalert_alerts.criteria` (`skill_id` list) and its `keywords` are untouched | the source id is replaced, duplicates removed, the 20-value limit still holds |
| job roles | Job `job_jobs.role_id` | none (one value per job) |
| job roles | Organization `organization_position_versions.job_role_id` | none |
| job roles | JobAlert criteria `role_id` list | as above |
| job categories | Job `job_jobs.category_id` | none |
| job categories | Job `job_market_daily.category_id` (part of the primary key) | rows of the same day, company, province, experience and period are **summed** into the target's row |
| job categories | JobAlert criteria `category_id` list | as above |
| all three | Matching: stored signals and the recommendation caches that name the item | the cache is cleared for the affected jobs and candidates; nothing is re-pointed |
| all three | Application `job_snapshot` (`phase-8-job-copy-in-application.md`) and a stored `profile_snapshot` | **not changed:** a snapshot is history |

## 4. Schema and tests

- **Schema:** `reference_item_merges` as above; no column on the master tables.
- **Tests:** one test per row of section 3 (a conflict case included: a user with both skills keeps one, a job with both keeps one, the market rows add up); the preview equals the real counts and writes nothing; a failure in one context rolls every context back; an old slug resolves to the target and the source is not listed; a target that is inactive, the same item, or in another master is refused; only the three masters are mergeable; the permission is required; the audit entry and the log row exist; a repeated merge of the same source is 404.

## Open points

None.
