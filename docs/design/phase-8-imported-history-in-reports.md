# Phase 8: imported history in the speed and conversion reports (designed 2026-10-20)

Closes MG-B11 of `docs/analysis/multi-persona-gap-analysis.md` (decision 9). Builds on `phase-4-business-intelligence.md` (the analyses and the daily summaries), `phase-8-activation.md` section 6 (`recruitment_applications.migrated_at`). Design only: nothing is coded.

## Decisions

- 2026-10-20 **Speed and conversion count only what happened on the new platform:** every imported application (`migrated_at` set) is left out of the figures that rest on stage moves and times; totals may include them (gap-analysis decision 9; [section 1](#1-the-rule)).
- 2026-10-20 **A flag, not a date:** applications are told apart by `migrated_at` (already stored); jobs get the same column `job_jobs.migrated_at`, set by the importer and backfilled for the rehearsal databases from the id map; no cut-over date setting (user; [section 2](#2-the-job-flag)).
- 2026-10-20 **Whole imported applications leave** the speed and conversion figures, also their later real moves (they stay in the application's own history) (user; [section 1](#1-the-rule)).
- 2026-10-20 **Totals include them** (applications per stage, offers, applications per day), and the response says so with `includes_imported: true` and a separate `imported` count (user; [section 1](#1-the-rule)).

## 1. The rule

- **Out of the figures:** the stage moves read by `TransitionRows` (time in a stage, time to an offer, conversion between stages, the rollups of `TransitionsDailyRollup` and the live medians of `LiveMedians`), the hire moments of `HireTimes` (time to hire), and the time to fill of `TimeToFill` and `FillReport` (a job with `migrated_at` is not a filled job for that report). The condition lives in the one place each reader selects its rows, as `UntouchedImportScope` does for the stale rule (`a.migrated_at IS NULL`), so the daily rollups need no backfill: they are written from that query.
- **In the totals:** the counts of applications per stage, offers sent, hires counted (not their time), rejections by reason and the applications per day by `applied_at`. They include imported applications; the response of `funnel` (and the card of the company dashboard that reads it) carries `includes_imported: true` and `imported: {applications, hired}` for the range so the page can explain the first months.
- **Small groups:** the minimum group size (`analytics.min_group_size`) is applied to the figures after the exclusion; a median over fewer than that many values stays `null`.
- **Reports without a time or move:** the market, matching, rejection reasons and sources tables are counts and stay as they are.

## 2. The job flag

- Migration: `job_jobs.migrated_at` (nullable timestamp, partial index where not null), backfilled from `legacyimport_id_map` (entity `job`, the `imported_at` of the map row) in the databases that have one; `JobsImporter` sets it to the import time. Like the application flag it needs the second rehearsal.
- The time to fill ignores a job with the flag; the job market analysis (counts) does not.

## 3. Tests

An imported application's moves are not in the medians, the conversion or the daily rollup; a native application's are; the totals include both and say so; a job with the flag is not in the time to fill; the small-group rule uses the counts after the exclusion; the importer sets the job flag; a company without imported data gets `includes_imported: false`.

## Open points

None.

## Coded (2026-10-20)

- Migration `000184` adds `job_jobs.migrated_at` (partial index), backfilled from `legacyimport_id_map` (entity `job`, its `imported_at`); `JobsImporter` sets it through `NewJob::$migratedAt`. Run `php artisan migrate`; no command is needed.
- The exclusion is `a.migrated_at IS NULL` in `TransitionRows` (daily rollup), `LiveMedians` and `HireTimes`. `PublishedJob::$imported` makes `TimeToFill` skip an imported job, in the filled list and in the open list.
- **Interpretation:** the funnel's stage counts, offers and withdrawals come from the transition rollup, so they leave imported applications together with the times; only the applications per day (`applied` row) still count them. The response is a flat table, so `includes_imported` and the `imported` count are a one-row table `imported` (`includes_imported`, `applications`, `hired`, by `applied_at` in the range) added to the funnel report, instead of fields beside the tables.
- A daily rollup written before this change still holds the imported moves of its days (no imported data exists in the real database yet); rebuild a range with `analytics:rollup --from --to` if one is needed.
