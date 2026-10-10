# Company reviews (designed and coded 2026-10-10)

Gap J10b (`decisions.md`): "Company reviews by job seekers: admin-moderated; only job seekers who applied to that company may write; the company gets one public reply per review (also moderated); **the author's name is always shown** (the user's choice; risk reported: may suppress honesty and expose authors to conflict)". The legacy application has no reviews, so there is nothing to import.

## Decided (2026-10-10, user)

1. **Rating:** an overall rating of 1 to 5 stars (required) and three optional aspect ratings of 1 to 5: work culture, pay and benefits, hiring process.
2. **Who may write:** any job seeker with at least one application to the company, whatever its status (a withdrawn one counts, as in `ApplicantRelations::hasApplied`). One review per person per company; the author may edit it (it is moderated again) or delete it.
3. **Summary:** the public list answers the count, the average and the average of each aspect only when the company has **at least 3 published reviews** (an aspect average needs 3 ratings of that aspect). Below that the reviews are listed without any average.
4. **Reports:** a signed-in job seeker, and the company, can report a published review to the admins; the admin reviews it and keeps it or takes it down.

## Rules

- **Visibility:** a review is public only when `published`. A company that the public cannot see (suspended or hidden, `PublicCompanies::card` is null) answers 404 for its reviews, like its page.
- **The author's name is always shown** (decision J10b): `author.name`, no profile link, no avatar. An account that was anonymised reads as `Deleted user`.
- **Text:** `title` 3 to 100 characters and `body` 50 to 3,000 characters, plain text (no HTML, no Markdown, the client escapes). No contact details are filtered by code; the moderator rejects with reason `personal-data`.
- **Status:** `pending` (new or edited, waiting), `published`, `rejected` (with a reason code and an optional note, shown only to the author, who may edit and send it again), `removed` (taken down by an admin after publishing; the author sees the reason, cannot edit it). Editing a `published` review makes it `pending` again and hides it until approved; editing a `rejected` one makes it `pending`.
- **Reasons** (rejecting or removing): `spam`, `abusive`, `not-about-company`, `personal-data`, `false-claim`, `other`.
- **Reply:** one reply per review, written and edited only by a **company admin** (`company_admin` role), only on a `published` review. The reply has a body of 10 to 1,500 characters and its own status (`pending`, `published`, `rejected`); editing a published reply makes it `pending` again; the company may delete it. Only a published reply is public. The admin moderates it like a review, with the same reasons.
- **Reports:** reason `spam`, `abusive`, `false-claim`, `personal-data` or `other`, an optional note of at most 500 characters. One open report per reporter per review; a company reports only reviews of its own page, a job seeker cannot report their own review. A report stays `open` until an admin resolves it (`dismissed`, or `actioned` when the review was taken down). Reports are never shown to the author or the public.
- **Notifications (in-app only):** the author gets `review.published`, `review.rejected`, `review.removed` and `review.reply_published`; the company admins get `review.received` when a review is published and a reply to take care of is not in the notification (no reminder). The data carries the review and company ids, never the text.
- **Limits:** a job seeker may send at most 10 review writes (create, edit, report) per hour; the company at most 30 reply and report writes per hour.
- **Anonymisation of the author's account:** the review and its reply stay public if they were published, the ratings stay in the averages, but the title and body are erased (the review is then rating-only) and the author reads `Deleted user`; the person's open reports are deleted. A review that was not published is deleted. Review listens to `UserAnonymized`, idempotent, queue `default`.
- **Company deleted or erased:** not supported today (companies are suspended, not deleted); the keys are RESTRICT.

## API

Public (no token needed; the token is read if present only for `mine`):

- `GET companies/{slug}/reviews`: published reviews, newest first by default (`sort=newest|highest|lowest`, `per_page` up to 50, plain pagination like the other lists). Answer: `data` list of `{id, rating, aspects {culture, pay, hiring}, title, body, author {name}, published_at, reply {body, published_at} or null}`, plus `summary {count, average, aspects {culture, pay, hiring}}` where the averages are null below 3 (`count` is always given). Cache headers like the other public lists (`public;max_age=60;etag`).

Job seeker (`auth:user`, role job seeker):

- `POST companies/{slug}/reviews` `{rating, culture_rating, pay_rating, hiring_rating, title, body}`: 201 with the review as the author sees it (including `status`). Errors: 403 `review-requires-application`, 409 `already-reviewed`, 404 hidden company, 422 validation.
- `GET me/reviews` and `GET me/reviews/{id}`: the author's own reviews with `status`, `rejection_reason`, `rejection_note` and the reply with its status; `PUT me/reviews/{id}` (409 `review-not-editable` for a `removed` one), `DELETE me/reviews/{id}` (204).
- `POST reviews/{id}/report` `{reason, note}`: 204; 404 when the review is not published; 403 `own-review`; 409 `already-reported`.

Company (`auth:user`, company member; the reply writes need `company_admin`):

- `GET company/reviews`: the company's published reviews with the reply and its status (members see the author's name like the public) and `summary`.
- `PUT company/reviews/{id}/reply` `{body}`, `DELETE company/reviews/{id}/reply`: company admin only (403 otherwise); 404 when the review is not the company's or not published.
- `POST company/reviews/{id}/report` `{reason, note}`: company admin only; 204.

Admin (`auth:admin`, permission `reviews.moderate`, needs `identity:sync-permissions`):

- `GET admin/reviews` (filters `status`, `company_id`, `reported=1`; newest waiting first), `GET admin/reviews/{id}` (with reply, reports and the author's account id), `POST admin/reviews/{id}/approve`, `POST admin/reviews/{id}/reject` `{reason, note}`, `POST admin/reviews/{id}/remove` `{reason, note}`.
- `GET admin/review-replies` (filter `status`), `POST admin/review-replies/{reviewId}/approve`, `.../reject` `{reason, note}`, `.../remove` `{reason, note}` (a taken-down reply is deleted with a `removed` marker kept for the audit trail only; the company may write a new one).
- `GET admin/review-reports` (filter `status`, default `open`), `POST admin/review-reports/{id}/dismiss`, and taking a review down resolves its open reports as `actioned`.

Errors use the existing catalog (`php artisan api:error-catalog` after coding adds the new codes).

## Where it lives

A new bounded context **`Review`** (`src/Review`, domain, application, infrastructure, interface), tables prefixed `review_`.

- **Table `review_company_reviews`:** `id`, `company_id`, `user_id` (plain indexed columns; the keys to `company_companies` and `identity_users` are listed in the cross-context migration and `CrossContextForeignKeysTest` like the other contexts), `rating`, `culture_rating`, `pay_rating`, `hiring_rating` (smallint, CHECK 1 to 5, aspects nullable), `title`, `body` (nullable after anonymisation), `status` (CHECK), `reason`, `note`, `moderated_by_admin_id`, `moderated_at`, `published_at`, timestamps. Unique (`company_id`, `user_id`); index (`company_id`, `status`, `published_at`), index (`status`, `created_at`).
- **Table `review_replies`:** `review_id` (unique, FK cascade), `body`, `status`, `reason`, `note`, `written_by_user_id`, `moderated_by_admin_id`, `moderated_at`, `published_at`, timestamps.
- **Table `review_reports`:** `id`, `review_id` (FK cascade), `reporter_kind` (`user`, `company`), `reporter_id` (user id, or company id), `reason`, `note`, `status` (`open`, `dismissed`, `actioned`), `resolved_by_admin_id`, `resolved_at`, timestamps; unique (`review_id`, `reporter_kind`, `reporter_id`).
- **Ports used (all exist):** `ApplicantRelations::hasApplied`, `PublicCompanies` (slug to card, hidden company), `CompanyDirectory`, `ApplicantContacts` (names; null reads as `Deleted user`), `InAppNotifier`, `CompanyMemberAccounts`/`CompanyTeam` (the company admins to notify), `EventDispatcher`, `TransactionManager`. No new Shared port is expected beyond a name lookup if `ApplicantContacts::contact` is too narrow (decided when coding).
- **Audit** (Platform listener like the other contexts): `review.approved`, `review.rejected`, `review.removed`, `review.reply_approved`, `review.reply_rejected`, `review.reply_removed`, `review.report_dismissed`.
- **Permissions:** `reviews.moderate` in a `ReviewPermissions` catalog entry, group `reviews`.
- **Summary figures** come from one aggregate query per company, cached 10 minutes and dropped when a review is published, edited, removed or erased.
- **Not in the Company page:** the company page (`GET companies/{slug}`) does not carry the rating yet; a client calls the reviews route. Showing the rating on the company card and in job lists is a later step.

## Privacy and honesty

Reviews are opinions of people who applied; the name is shown by decision (risk reported before: names may suppress honest reviews and expose authors to conflict with the company; a `Deleted user` option stays available through account deletion). Reports and moderation notes never reach the public. The company never learns anything about the author beyond the shown name and the review itself. Legal review should cover defamation and takedown handling (`docs/legal-review.md` gets a line when coding).

## Not in this phase

Verified employment or interview-experience fields, review helpfulness votes, reviews of a specific job, review search and filters beyond sorting, emailing the company when a review is published, a rating on the company card, showing the review count in the job search, a public API for reviewers' other reviews.

## Tests (to write with the code)

Writing rights (no application, withdrawn application counts, hidden company, one per company); creating, editing (published goes back to pending, rejected resubmits, removed refuses) and deleting; the moderation queue and every transition including the reasons and notifications; public list (only published, sort, name shown, anonymised author, reply only when published); summary hidden below 3 and aspect averages; reply rules (company admin only, only on a published review, edit goes back to pending, admin moderation); reports (who, once, own review, resolve, taking down resolves them); anonymisation (text erased, ratings stay, unpublished deleted); limits (429); permission `reviews.moderate`; audit entries; cache invalidation.

## What was coded (2026-10-10)

- **Context `Review`** (`src/Review`), migration `000107` (`review_reviews`, `review_replies`, `review_reports`; the keys to accounts, admins and companies are RESTRICT and listed in `CrossContextForeignKeysTest`; `review_reports.reporter_id` has none on purpose; one open report per reporter per review is a partial unique index).
- **Routes:** public `GET companies/{slug}/reviews` (60/min per address, `max_age=60;etag`); job seeker `POST companies/{slug}/reviews`, `GET|PUT|DELETE me/reviews[/{id}]`, `POST reviews/{id}/report` (10 writes per hour per account); company `GET company/reviews`, `PUT|DELETE company/reviews/{id}/reply`, `POST company/reviews/{id}/report` (30 per hour); admin (`reviews.moderate`) `admin/reviews` (list, show, `approve`, `reject`, `remove`), `admin/review-replies` (list, `approve`, `reject`, `remove`) and `admin/review-reports` (list, `dismiss`).
- **Error codes:** `review-requires-application`, `already-reviewed`, `review-not-editable`, `review-not-pending`, `review-not-published`, `reply-not-pending`, `already-reported`, `own-review`, `report-not-open` (catalog regenerated).
- **Shared ports extended:** `PublicCompanies::cardBySlug()` (Company) and `ApplicantContacts::names()` (Identity; an erased account is missing and reads `Deleted user`).
- **Summary:** `CachedReviewSummaries` keeps it 10 minutes; publishing, editing a published review, taking down, deleting and erasing drop it. A report on a removed reply is not possible (a removed reply is deleted; only the audit entry remains).
- **Audit:** `review.approved`, `review.rejected`, `review.removed`, `review.reply_approved`, `review.reply_rejected`, `review.reply_removed`, `review.report_dismissed` (`RecordReviewAuditEntries`, with the company id and the reason code).
- **Notifications:** `review.published`, `review.rejected`, `review.removed`, `review.reply_published` (author) and `review.received` (company admins).
- **Tests:** `tests/Feature/Review/ReviewTest.php` (32 tests).
- **On the real server:** `php artisan migrate`, `identity:sync-permissions` (`reviews.moderate`).
