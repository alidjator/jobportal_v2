# Phase 2, step 3: structured offers (R7c, designed 2026-10-08)

Context: `Recruitment`. Follows the pattern of interviews (`phase-2-interviews.md`).

## Confirmed by the user (2026-10-08)

- **Fields:** position (text, default the job title), monthly salary in Rupiah (bigint), start date, note (up to 2,000 characters), and a deadline (`expires_at`, at most 30 days ahead).
- **Validity:** the recruiter picks the deadline. After it the offer is `expired`: the job seeker can no longer answer. The application stays in the offer stage; the team may send a new offer.
- **Revision:** one active offer (`pending`) per application, enforced by a unique `active_key`. Changing it withdraws the old offer and creates a new one; history is kept and the job seeker is told.
- **Who:** company admins and recruiters assigned to the job. Moving an application to a stage of type `offer` requires offer details unless an active offer exists. Withdrawing an offer does not move the stage.
- **Answer:** the job seeker accepts (application moves to `hired`) or declines with an optional note (application moves to `withdrawn`), as decided in R7c.

## Open details to settle while coding (proposed, to report)

- Statuses: `pending`, `accepted`, `declined`, `withdrawn`, `expired` (set lazily when read, and by an hourly command so notifications go out).
- Reject and job-seeker withdrawal of the application withdraw the active offer.
- Notifications: the job seeker gets in-app and email (`application_update` preference) for sent, revised, withdrawn and expiring-soon; the team gets in-app only for accepted and declined.
- Tables: `recruitment_offers` (migration `000082`), anonymising the applicant deletes their offers.
