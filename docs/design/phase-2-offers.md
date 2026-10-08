# Phase 2, step 3: structured offers (R7c, designed 2026-10-08, coded 2026-10-08)

Context: `Recruitment`. Follows the pattern of interviews (`phase-2-interviews.md`).

## Confirmed by the user (2026-10-08)

- **Fields:** position (text, default the job title), monthly salary in Rupiah (bigint), start date, note (up to 2,000 characters), and a deadline (`expires_at`, at most 30 days ahead).
- **Validity:** the recruiter picks the deadline. After it the offer is `expired`: the job seeker can no longer answer. The application stays in the offer stage; the team may send a new offer.
- **Revision:** one active offer (`pending`) per application, enforced by a unique `active_key`. Changing it withdraws the old offer and creates a new one; history is kept and the job seeker is told.
- **Who:** company admins and recruiters assigned to the job. Moving an application to a stage of type `offer` requires offer details unless an active offer exists. Withdrawing an offer does not move the stage.
- **Answer:** the job seeker accepts (application moves to `hired`) or declines with an optional note (application moves to `withdrawn`), as decided in R7c.

## Details (proposed, approved 2026-10-08)

- Statuses: `pending`, `accepted`, `declined`, `withdrawn`, `expired` (set lazily when read, and by an hourly command so notifications go out).
- Reject and job-seeker withdrawal of the application withdraw the active offer.
- Notifications: the job seeker gets in-app and email (`application_update` preference) for sent, revised, withdrawn and expiring-soon; the team gets in-app only for accepted and declined.
- Tables: `recruitment_offers` (migration `000082`), anonymising the applicant deletes their offers.

## Coded (2026-10-08)

- Migration `000082` (`recruitment_offers`, unique `active_key`); it ran on the test and the real database.
- Routes: `GET company/applications/{id}/offers`, `PUT company/applications/{id}/offer` (201 new, 200 replaced), `POST .../offer/withdraw`; `GET me/applications/{id}/offer` (`data` null when none),
  `POST me/applications/{id}/offer/accept|decline {note?}`. The move request takes an optional `offer` object.
- Errors: 422 `offer-required`, `invalid-offer`, `invalid-offer-input` (an offer for a stage that is not an offer stage), `invalid-answer` (decline note over 1,000 characters);
  409 `not-in-offer-stage`, `no-active-offer`, `offer-expired`, `offer-not-pending`.
- Choices made while coding:
  - Moving out of an offer stage in any way (including to `hired`) withdraws the pending offer, and the job seeker is told. This differs from interviews, which stay as they are.
  - Accepting records the move to `hired` with the applicant as actor, so the usual "hired" notification and email still go out. Declining uses the optional note as the application's withdrawal note too.
  - The status shown is as of now, so a pending offer past its deadline reads `expired` before the hourly command `recruitment:process-offer-deadlines` marks it (it also sends the one reminder, 24 hours ahead).
  - Only the member who sent the offer is notified of the answer (in-app). Expiry itself notifies nobody.
  - The salary is `1` to `100,000,000,000`; the start date may be today (Jakarta) or later; the position defaults to the job title.
  - Email: `recruitment.offer_sent`, `_revised`, `_withdrawn`, `_expiring`, following `application_update`. A move to an offer stage therefore sends the stage email and the offer email.
- Tests: `OffersTest` (20), `OfferTest` (5).
