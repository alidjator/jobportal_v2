# Phase 2, step 1: internal notes and ratings (R7a, designed and coded 2026-10-08, committed `bbb0a18`)

Phase 2 order (confirmed 2026-10-08): 1 notes and ratings (R7a), 2 interview scheduling (R7b), 3 offers (R7c), 4 bulk actions (R5), 5 CSV export (R13), 6 chat with attachments (J9).
Each step is designed before it is coded. Context: `Recruitment`.

## Decisions

- 2026-10-08: the six Phase 2 steps in this order: notes and ratings, interview scheduling, offers, bulk actions, CSV export, chat.
- 2026-10-08: notes and ratings as written under "Confirmed" and "Proposed details" (the proposed details were approved as a whole).

## Confirmed by the user (2026-10-08)

- **Notes** are internal: seen by the members who may see the job (assigned recruiters and company admins), never by the job seeker.
- **Edit and delete:** the author may edit and delete their own note (an edited note shows `edited_at`). A company admin may delete any note of the company (moderation) but never edit
  someone else's.
- **Size:** a note has at most 2,000 characters; there is no limit on the number of notes.
- **Ratings:** 1 to 5 stars, one per member per application, changeable and withdrawable, with no comment (comments go in notes). The applicant card shows the average and the number of raters;
  the team sees each member's rating.
- **Final states:** notes are allowed on a rejected, withdrawn or hired application; ratings are locked there (not created, changed or withdrawn).

## Details (approved 2026-10-08)

- Tables `recruitment_application_notes` (`application_id`, `author_id`, `body`, `created_at`, `updated_at`, `edited_at`) and `recruitment_application_ratings` (`application_id`, `member_id`,
  `stars` 1 to 5, timestamps, unique on application and member). Rows are deleted with the application.
- Endpoints under `company/applications/{id}`: `GET notes`, `POST notes {body}`, `PUT notes/{noteId} {body}`, `DELETE notes/{noteId}`, `PUT rating {stars}`, `DELETE rating`. Access is the same as
  the application detail (an application the member cannot see is a 404). Writing a note by a member who left the company is impossible (their account no longer has access).
- The application detail gets `rating {average, count, mine, members[{user_id, name, stars}]}`; the applicants list rows get `rating {average, count}` (one grouped query, no per-row lookups). Notes are not in the list.
- When the applicant's account is anonymised, the notes of their applications are deleted with the other personal content (they may quote the applicant) and the ratings are kept. A removed member's
  notes stay with the author shown by the name the account port gives (null name once erased).
- No audit log entries (same rule as the pipeline: the rows are the record). No notification is sent.

## Coded (2026-10-08)

- Migrations `000079` (notes) and `000080` (ratings, a check on 1 to 5 and a unique key per member). They ran on the test database and, with the user's approval, on the real database.
- Use cases: `ListApplicationNotes`, `AddApplicationNote`, `UpdateApplicationNote`, `DeleteApplicationNote`, `RateApplication` (rate and withdraw), over `TeamApplicationLookup` (the same
  access as the application detail). Errors: 422 `invalid-note`, 422 `invalid-rating`, 403 `not-note-author`, 409 `application-final` (ratings on a rejected, withdrawn or hired application).
- A note whose text is only blank is a plain validation 422 (the empty-string middleware makes it null); a text over 2,000 characters is `invalid-note`.
- Rating changes answer 200 with the rating summary after the change. The list rows carry `rating {average, count}` and the detail `rating` as above; the job seeker's own endpoints show neither.
- The anonymisation (`DatabaseApplicantDataEraser`) deletes the notes of the user's applications and keeps the ratings. Tests: `ApplicationNotesTest` (13).
