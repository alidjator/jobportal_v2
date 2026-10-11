# Phase 5: "application viewed by the recruiter" (gap analysis J2, architecture review finding 17)

## Decisions

- 2026-10-11: In scope (user, finding 17). `recruitment_applications.viewed_at` (nullable timestamp) is set **once**, the first time a recruiter opens the application detail ([Behaviour](#behaviour)).
- 2026-10-11: Trigger (user): only `GetCompanyApplication` (the company member's detail). The list, the export, bulk actions, the resume download and a stage move do not count.
- 2026-10-11: Visible to the applicant in "my application" detail and in "my applications" list as `viewed_at` (ISO 8601 or null) ([API](#api)).
- 2026-10-11: No notification and no email when it is first viewed (user): the applicant sees the field when the app is opened.
- 2026-10-11: Only the time is stored: no viewer id, no name (user). Applications imported from karir.pkp keep `viewed_at` NULL (unknown, not "unseen by design").

## Behaviour

- `GetCompanyApplication::execute` marks the application after `assertCanSee` has passed, so a member who may not see the job never marks anything. A platform admin has no route to this detail, so an admin never marks it.
- Marking is one conditional statement, `UPDATE ... SET viewed_at = :now WHERE id = :id AND viewed_at IS NULL`: no lock, no read first, no change to `updated_at`, safe when two recruiters open it at the same moment (the first wins). It runs for every stage, including rejected and withdrawn applications.
- A GET that writes is accepted because it is idempotent after the first call and invisible to the recruiter.
- `viewed_at` is not personal data of the applicant, so anonymisation keeps it.

## API

- Applicant side: `viewed_at` added to the detail resource and to the list row resource of `/me/applications`. Recruiter side: no change (the recruiter has no use for it; adding it later is trivial).
- Scramble docs and the `@var` shapes of both resources updated; no new error code.

## Coding plan (one fresh session, no checks run by the coder)

1. Migration `000115`: `viewed_at` nullable timestamp on `recruitment_applications`, no index (never filtered or sorted on). Tell the user it is new: `test:database:migrate --env=testing`, then `migrate` on the real database (approval).
2. Port method on the application repository (`markViewed(int $id, DateTimeImmutable $at): void`) with the conditional update; `GetCompanyApplication` calls it with the `Clock`.
3. `viewedAt` in `ApplicationRow` and `ApplicationDetailData` (applicant readers), the two resources, and the `@var` annotations.
4. Tests (`tests/Feature/Recruitment`): first open sets it, second open keeps the first time, a member of another company gets 404 and nothing is set, list and export do not set it, the applicant sees it in detail and list (null before, a time after), a withdrawn application is marked too, `updated_at` is unchanged.
5. Update `gap-analysis.md` J2, `decisions.md` if needed, `docs/HANDOFF.md`, and this file with "coded".

## Status

- 2026-10-11: Coded and checked (`composer check` green, 2012 tests). Migration 000115 ran on the real database (user, 2026-10-11). Test: `tests/Feature/Recruitment/ApplicationViewedTest.php`.
