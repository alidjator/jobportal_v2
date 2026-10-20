# Phase 8: version checks on edits (designed 2026-10-20)

Closes MG-B08 of `docs/analysis/multi-persona-gap-analysis.md`. Builds on the row locks of the Recruitment and Job repositories. Design only: nothing is coded.

## Decisions

- 2026-10-20 A write of a **job**, an **offer** or an **interview** can carry the version it was read at (`If-Match`); another version is **409 `edit-conflict`** with the current state (gap analysis; [section 1](#1-the-check)).
- 2026-10-20 **Mechanism: an integer column `lock_version`**, not `updated_at` (user; [section 2](#2-schema)).
- 2026-10-20 **Optional first:** a write without `If-Match` works as today; the setting `api.require_if_match` (default off) makes it mandatory (428 `if-match-required`) once the front end sends it (user; [section 1](#1-the-check)).

## 1. The check

- **Where:** the writes that replace content: `PATCH company/jobs/{id}` and the job's publish, close and reopen; `PUT company/applications/{id}/offer` and the offer revisions; `PUT` and `PATCH company/applications/{id}/interview` and the reschedule and cancel actions. Notes are not covered (each is the author's own).
- **Read side:** the detail resource of each carries `version` (the `lock_version`) and the response has `ETag: "<version>"`.
- **Write side:** `If-Match: "<version>"`. A match writes and raises the version; a different version is **409 `edit-conflict`** whose body holds `current` (the detail resource) and `version`, so the page can show "changed by <name> at <time>" and offer to reload. The check runs inside the existing row lock, so two saves cannot both pass.
- **Without the header:** the write goes through and still raises the version; with `api.require_if_match` on it is 428 `if-match-required`. A background write (an expiry, a scheduler) raises the version too.
- **Tests:** a stale version is 409 with the current state, the right version passes and raises it, two parallel saves with one version: one wins; no header passes while the setting is off and is 428 when it is on; every covered write raises the version; the detail carries it and the `ETag`.

## 2. Schema

- `lock_version` (integer, not null, default 1) on `job_jobs`, `recruitment_offers` and `recruitment_interviews`; set by the repositories' `save` (`lock_version = lock_version + 1` in the same statement as the update). Migration, new. `SettingsTest` lists `api.require_if_match`.

## Open points

None.
