# ERP installation type: single company and multi company (designed 2026-10-28, coded the same day)

Extends `erp-direction.md` and `career-modes.md` (sections 1, 3a, 3b, 3c) and relates to `self-hosted.md`. It changes no schema. Plans, billing,
payments, orders and invoices stay out of scope.

## Decisions

- 2026-10-28: **the installation type belongs to the ERP, not to the job portal** (decided by the user). An installation has one type, `single` or `multi`, end to end across Console, Workspace and Career; the job portal is an adaptation on top of it.
- 2026-10-28: **the marketplace is not a third type: it is `multi` of independent companies plus the job portal adaptations** (decided by the user, option A): verification, the public company directory, self registration of companies and discovery across companies.
- 2026-10-28: **`single` is `multi` with a limit of one company**, not a second code path: every table keeps its company key, so going from `single` to `multi` needs no data migration. Going back is not supported.
- 2026-10-28: **the name is "installation type"** (decided by the user, after "character" was found not to be a common term; Odoo and ERPNext call the second value "multi-company"). In code and in the API: `installation_type` (`single` | `multi`) and, for `multi`, `multi_kind` (`group` | `independent`).
- 2026-10-28: **the Console screen "Career portal" became "Installation type"** (decided by the user): one choice of three, instead of a site mode and a scope; the companies are asked for only when the type needs them.

## 1. The types

| Installation type | Kind of `multi` | Meaning | Stored as (`platform_portal_identity`) | Job portal adaptations |
|---|---|---|---|---|
| `single` | none | one company, end to end | mode `company`, scope `company` | none: no verification, no group level, no discovery |
| `multi` | `group` | several legal entities of one group, one owner | mode `company`, scope `group` | group site (`career-modes.md` 3a); no verification |
| `multi` | `independent` | several companies with no relation to each other, run by the operator | mode `marketplace` (no identity row) | verification, directory, registration, discovery |

- **Installation type** says what the data and the flow look like: how many companies, whether a group exists above them, whether they know each other.
- **Adaptation** says what the job portal adds. It is derived from the type and the kind, never switched on its own.

## 2. One source, derived switches

- The installation type is read from one place, the live portal identity, through one port (`Shared\Application\Portal`); the existing `VerificationFeature` and `GroupFeature` become answers derived from the type, not separate sources.
- Proposed derivation (no schema change; the type is computed from `mode` and `scope`):

| Switch | `single` | `multi` / `group` | `multi` / `independent` |
|---|---|---|---|
| Group level (members, group workforce, group approvals, portal settings, support access) | off | on | not applicable (no group) |
| Structure (legal entities, units, positions, grades, cost centres) | on | on | on, per company |
| Verification of companies | off | off | on |
| Company directory, reviews, following | off | off | on (frozen parts stay frozen) |
| Several companies in the pickers (company lookup, tenant link) | off | on | on |
| Career site shows | the company | the group, filter by legal entity | every live job of every company |

- The portal answers `GET portal` with `installation_type` and `multi_kind` next to `mode` and `scope`; the three applications read those and nothing else.

## 3. What each application does

| | `single` | `multi` / `group` | `multi` / `independent` |
|---|---|---|---|
| **Console** | operator of one installation: no company list, no tenant link, no verification; reference data and users stay | companies of the group, groups pages, tenant link | company list and detail, verification queues, directory, campaigns |
| **Workspace** | one company, no company switcher, company roles only | company switcher, company and group roles, group pages | one company per user, company roles only; verification and documents pages |
| **Career** | the company's site, "About us" | the group site, legal entity on every job | the marketplace |

## 4. Rules that hold for every type

1. **The gate is in the API first**, the menu second (404 with a code per switch, as `verification-unavailable` and `groups-unavailable` today). No type is enforced by hiding.
2. **Data is kept, only hidden**, so a change of kind (for example `group` to `independent`) loses nothing.
3. **One account per installation** for a job seeker, whatever the installation type; an applicant of a legal entity is seen by that entity, and across the group only through `group.recruitment.view`.
4. **`single` refuses a second company** at the API (the use case that creates a company) and in Console; this is the only place where `single` differs in code from `multi`.
5. **The same test suite runs on each type**, with the type as a fixture; the gates of one type are tested as 404 on the others.

## 4b. The limit of `single` (coded 2026-10-28)

- The port `Shared\Application\Portal\SingleCompany` (Platform: `PortalSingleCompany`) is true while the live identity is `single`.
- `CreateCompany` refuses a company when the installation holds one already, whatever its status; `LinkTenant` refuses a tenant when one company is linked already. Both answer 409 `single-company-installation`. Legal entities and the structure are not limited.
- Nothing is removed from an installation that already has more companies: the limit only stops what is added.

## 4c. Console (coded 2026-10-28)

- Console reads `installation_type` of `GET portal`. On a `single` installation it leaves out the button that adds a company once the list has one, and the link button of a legal entity once an entity of the group has its tenant. Workspace and Career change nothing: Workspace has no company switcher to hide, and the group-level and verification switches they read already follow the same mode and scope.
- **The screen "Installation type"** (menu Platform, route `/portal`, permission `portal.manage`, API `admin/portal`, which keep their names) holds one choice of three: single company, multi company of one group, multi company of independent companies. The companies are asked for under the first two (one company, or the companies of one group); the third takes none. The draft, the preview and the publish are as before. The choice is stored as the mode and the scope.
- **Workspace on `single`** (decided 2026-10-28, after the first cut kept it): the page "Group and entities" (`/organisasi`) is left out of the menu and goes to the units. The legal entities are set up by the platform admin in Console (the Groups pages); the structure (units, positions, grades, cost centres) stays in Workspace. The API gate is closed too: creating, changing, deactivating and reactivating a legal entity (`organization/legal-entities*`) and reading the group with its legal entities (`GET organization/group`) answer 404 `groups-unavailable` on `single`. The structure (units, positions, unit types, grades, cost centres) stays and uses the one legal entity of the member's company by itself: `legal_entity_id` is optional on `single` (`StructureEntity`), and the entity of another company is 404; on `multi` it is required (422 `legal-entity-required`). The group permissions of a member moved from `GET organization/group` to `GET company/me` (`group`: id, name and permissions, null without a group role), so they are known on every installation. Workspace shows no legal entity picker on `single`.
- The brand (logo, colours, welcome text) is not on this screen; the company admin or the group admin sets it in Workspace.

## 5. Questions to settle before coding

1. **Where the type lives.** Decided with the recommendation: keep `mode` and `scope` and compute `installation_type` and `multi_kind` (no migration); rename the stored columns only when a fourth kind appears.
2. **Users across companies.** In `multi` / `group`, may a Workspace user belong to several legal entities of the group? Today a user belongs to one company and holds group roles on top. Recommendation: keep that.
3. **Master data in `multi` / `group`**: unit types and screening presets are shared at the group today; confirm that stays.
4. **Marketplace host.** `career-modes.md` section 2 leaves the marketplace host open; the installation type does not change that.

## 6. Order of work (proposed)

1. API: the type on `GET portal` (`installation_type`, `multi_kind`); `VerificationFeature` and `GroupFeature` read it; tests for the three types. Done.
2. API: `single` refuses a second company and a second tenant. Done.
3. Frontend: Console reads the type, hides the add buttons of `single` and has the new screen. Done. The company switcher of Workspace for `multi` is not built.
4. Docs: `career-modes.md` sections 1, 3b and 3c point here. Done.
