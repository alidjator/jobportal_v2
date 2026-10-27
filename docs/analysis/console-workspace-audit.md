# Console against the Workspace (audit of 2026-10-27)

The user asked for the Console (the admin's pages and Portal Mitra) to look and work like the Workspace "1:1". The frame was done first (`8fad79a`: folding menu groups,
user card, language switch, institution in the header), then the multi-tab strip (`ebbfdd7`). This audit compares the 38 views of `apps/console/src/views` with the rules of
`onesis-talent/CLAUDE.md` and with how the Workspace builds the same kind of page. Checked by reading and by grep (no screen was compared pixel by pixel).

## Already the same

- One layout (`DashboardLayout`), one header (`ProfileMenu`, theme, language), one sidebar (folding groups, user card), the compact density of rule 11 (central values in `packages/ui`),
  the tab strip, `StateBoundary`, `ServerTable`, `UiTabNav`, `FormCheck`, `ActionFormDialog`, `ConfirmDialog`, `BaseBreadcrumb`, the dashboard cards (`StatCard`, `BarCard`, `QueueCard`) and
  the sign-in shell (`AuthShell`).
- No inline `style`, no Vuetify left, no `mr-`/`ml-` outside the template's own modal footer (`ltr:ml-2 rtl:mr-2`, copied 1:1). The spacing test of `packages/ui` scans the Console too.
- Page grids use `gap-4` (26 times) and `gap-3` (10), as the Workspace does; `gap-x-6` appears only on the dashboard and one form, as in the Workspace's dashboards.
- The raw `form-check` markup that remains (`BatteryRecommendationTab`, `BatteryQuestionDialog`, the sign-in "codes saved") follows rule 14 (a flex row with `gap-2`).

## Differences found

| # | Difference | Where | Rule | Status |
|---|---|---|---|---|
| D1 | The list search field is the template's non-compact copy: icon at `top-[14px] left-[15px]`, input `pl-10 leading-none`. The Workspace's compact one is `top-[9px] left-3`, `pl-9` | `CompanyListPage`, `JobListPage`, `TestListPage`, `ProviderListPage` | 1:1 with the Workspace | **fixed 2026-10-27** |
| D2 | The actions of a whole page are not in a sticky action bar. They sit in the body of a card or a tab (`CompanyDetailsTab`, `JobDetailsTab`, `ProviderDetailPage`), in a plain card (`TestEditorPage`), or in the page (`TeamPage`, `PortalPage`). The Workspace puts them in `StickyActionCard` with `UiActionButton` (`pages/lowongan/[id]/index.vue`) | the 5 detail pages and 3 toolbars above | 10 | in progress: `TestEditorPage`, `CompanyDetailPage`, `JobDetailPage` and the institution card of `ProviderDetailPage` **done 2026-10-27** (the bar of the Workspace's own test editor: title, plain outline buttons, status and size in the body; the buttons keep no icon because archive and restore have no row in the table of rule 9, as in the Workspace) |
| D3 | The action buttons have no icons where the table of rule 9 point 4 has one: invite (`custom-send`) on `TeamPage` and the invite card of `ProviderDetailPage`; approve and reject on `JobDetailsTab`; the Workspace's `UiActionButton` carries them | those pages | 9 | invite buttons **fixed 2026-10-27**; the rest goes with D2 (the bar decides which siblings take an icon) |
| D4 | The page is not wrapped in `PageAccess`; access is the route's `meta.permission` and the guard. Same result for the user (a refusal page), a different mechanism | all admin pages | none (design) | accepted |
| D5 | No notification bell and no announcement banner in the header | `App.vue` | `frontend.md` 11.1 | open: needs a notification endpoint for admins and psychologists (the API has one for public users only) |
| D6 | The list pages put the tabs and the filters in one `table-card`; the Workspace's lists use a card with the search field and a filter panel (`JobFilterPanel`) above the table | the 4 list pages | none (both from the template) | accepted: the Console lists have two filters at most |

## What was changed with this audit

- D1: the four search fields now read like the Workspace's (`top-[9px] left-3`, `pl-9`).
- D3: the invite buttons of `TeamPage` and of the institution page have the `custom-send` icon (toolbar and card header, the only button of their bar).

## Next

- D2 with the rest of D3: a `StickyActionCard` on the company, job, institution and battery pages, and on the team and portal toolbars, with `UiActionButton`s that take their icons
  from the table of rule 9. One page at a time, because each moves buttons out of tabs and cards.
- D5 when the notification endpoint exists (a task of its own: an in-app notification list for admins and psychologists, then the bell and the banner).
