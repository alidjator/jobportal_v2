# Phase 8: more work queues on the admin dashboard (designed 2026-10-20)

Closes MG-C06 of `docs/analysis/multi-persona-gap-analysis.md` (decisions 24 and 25). Builds on `phase-4-dashboards.md` (the admin cards and their permission) and the cards `jobs_queue` and `companies_queue`. Design only: nothing is coded.

## Decisions

- 2026-10-20 **One card per module, each with its own admin permission**, in the response of `GET admin/dashboard`; an admin sees only the queues they may manage (gap-analysis decision 24).
- 2026-10-20 **Queues now:** company name changes, review and reply moderation, chat reports. The job reports are already in `jobs_queue`; the licence queue of #24 comes with #24 as its own card (gap-analysis decision 25).

## 1. The cards

| Card key | Owner | Permission | Fields |
|---|---|---|---|
| `name_changes_queue` | Company | `companies.manage` | `pending`: company name change requests waiting (`company_name_change_requests`, status `pending`) |
| `reviews_queue` | Review | `reviews.moderate` | `pending_reviews`, `pending_replies` (status `pending`), `open_reports`: open reports on reviews and replies |
| `chat_reports_queue` | Messaging | `messages.moderate` | `open_reports`: chat reports with status `open`, and `oldest_open_at` |

- Each card is a `DashboardCardProvider` registered by its context, counts by one indexed query, and holds counts and dates only (no text of a review, a reply or a message). The `*_queue` suffix follows `jobs_queue` and `companies_queue`; the cards sort with the others by key.
- The cards appear only with `dashboard.view` plus the permission of the card, like the two that exist.
- **Not built:** the licence queue (#24) and the counts on the dashboard of a company.

## 2. Tests

Each count follows its table (a handled item leaves it); the card is absent without its permission and present with it; the existing keys and their order are unchanged for an admin without the new permissions (`DashboardTest` lists the keys).

## Open points

None.

## Coded (2026-10-20)

No migration, no `.env` value. `NameChangesQueueCard` (Company), `ReviewsQueueCard` (Review) and `ChatReportsQueueCard` (Messaging) are `DashboardCardProvider`s registered by their service providers. The `companies_queue` card keeps its own `name_changes_waiting` count; `name_changes_queue` is the separate card of the design, so a moderator who only holds `companies.manage` sees both. `oldest_open_at` is an ISO 8601 time in UTC, null when no report is open.
