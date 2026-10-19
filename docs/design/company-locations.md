# Company locations: several work locations per tenant (#27, designed 2026-10-19, not coded)

Step A of `erp-direction.md` section 9: every tenant gets its work locations (head office, branches, sites) in a new `company_locations` table of
the Company context. The current company address becomes the main location. Interview places and requisitions may point to a location. The job
side is split off. #28 gives the API its `locations` array (one item from today's columns), and jobs with several locations come with #20. Employee
placement (`erp-direction.md` D2) will point to these locations later.

The legacy source has one address per company (`companies.address`, `region`, `district`, `subdistrict`, `postcode`, `lat`, `long`) and no
branches. Coded in a coding session.

## Decisions (user, 2026-10-19)

1. **Columns:** name, type (`head_office`, `branch`, `site`), the address with the same BPS chain as the company today (province, city,
   subdistrict, village, postal code, street line), an optional phone, optional coordinates, `is_main` (one per tenant) and status
   `active` / `inactive`. The time zone (WIB, WITA, WIT) is derived from the province, not stored.
2. **Company address columns:** a migration copies each company's address into its main location, then drops `address_line`, `province_id`,
   `city_id`, `subdistrict_id`, `village_id` and `postal_code_id` from `company_companies`. One source of truth. `PATCH company/profile` no
   longer changes the address. The address is edited through the location endpoints.
3. **Retiring a location:** a location that is referenced (requisition, interview, later jobs and placements) can only be deactivated. It
   leaves the pickers, and existing references still show it. A location never referenced may be deleted. The main location can be neither
   deactivated nor deleted: the main mark moves to another active location first.
4. **References in #27:** an optional `location_id` on `recruitment_interviews` and on `workforce_requisitions`. The offer keeps its
   `work_location` text (prefilled from the job, `phase-8-offer-letters.md`). Jobs follow in #28 and #20.
5. **Permission:** managing locations uses the existing `company.profile` (the address is edited under it today). Reading the list is open to
   every member, because the interview and requisition pickers use it.
6. **Public profile:** the active locations with name, type, full address and coordinates, the main location first. No phone, like the
   company contact today.
7. **Interview place:** choosing a location copies its address into the existing `location` text, and `location_id` keeps the link. Invitations
   and `.ics` files already sent stay true when the location changes later. Free text without `location_id` stays allowed.
8. **Requisition location:** optional. When given, it must be an active location of the same tenant. A job opened from the requisition takes it
   as the starting location (#28, #20).
9. **Main location always exists:** every tenant has exactly one main location. It is created with a new company, by the migration for existing
   companies and by the legacy import. Its first name is "Kantor Pusat" with type `head_office`. The address may be empty, as it may today.
10. **Limit:** platform setting `company.max_locations`, default 100. Inactive locations count. Going over it is a 422.
11. **Coordinates:** both or neither, inside the bounding box of Indonesia (latitude -11 to 6, longitude 95 to 141), because addresses are
    Indonesian only (`decisions.md`, location). They are not matched against the province.
12. **Job API (#28):** `locations` replaces the `location` object in the job list and detail, `[{id, name, type, province, city, ...}]`. Until
    #20 it holds one item built from the job's own columns, with `id` null.
13. **Foreign keys:** `company_locations` joins the stable targets of `cross-context-foreign-keys.md`. `recruitment_interviews.location_id`
    and `workforce_requisitions.location_id` get RESTRICT keys to it.
14. **Names:** a location name is unique within a tenant, ignoring case (422 `location-name-taken`). Other tenants may use the same name. Two
    plants both named "Pabrik Jawa Timur" could not be told apart in the pickers, the exports or the emails.
15. **Legacy coordinates:** the legacy `lat` and `long` are carried when both are set and inside the bounding box. Otherwise they are left
    empty with the import note `coordinates-dropped`.
16. **Requisition location changes:** only while the requisition is a draft or returned, like the other draft fields. It is not part of an
    amendment.
17. **Interview text and location:** when `location_id` is given, `location` is filled from the location's address and a sent `location` is
    ignored. Room or floor details go in the interview `note`.

## 1. Table `company_locations`

| Column | Type | Rule |
|---|---|---|
| `id` | bigint | |
| `company_id` | bigint | RESTRICT to `company_companies`, indexed |
| `name` | varchar(100) | required, unique per tenant ignoring case (422 `location-name-taken`) |
| `type` | varchar(15) | `head_office`, `branch`, `site` |
| `is_main` | boolean | partial unique index `(company_id) WHERE is_main`; the main location must be `active` |
| `status` | varchar(10) | `active` (default) or `inactive` |
| `address_line` | varchar(255), nullable | street line |
| `province_id`, `city_id`, `subdistrict_id`, `village_id`, `postal_code_id` | bigint, nullable | RESTRICT to the `reference_*` regions; one matching chain (`LocationReferences`, 422 `invalid-location`), as for companies today |
| `phone` | varchar(32), nullable | same rule as `contact_phone` |
| `latitude`, `longitude` | numeric(9,6), nullable | both or neither, inside the bounding box (422 `invalid-coordinates`) |
| `deactivated_at` | timestamp, nullable | |
| `created_at`, `updated_at` | timestamps | |

- **Time zone:** a small map in the Company domain from the province code to `Asia/Jakarta`, `Asia/Makassar` or `Asia/Jayapura`. It is returned
  as `time_zone` and is null without a province. Interview times stay stored in UTC. The calendar and the emails keep their current rule until
  a design uses the location's zone.
- **Domain:** a `CompanyLocation` entity with an `Address` value object. `erp-direction.md` section 9 puts `Address` in `src/Shared`, and the
  legal entity address and later Employee use the same one.

## 2. Moving the company address (migration)

- One migration creates the table, inserts a main location ("Kantor Pusat", `head_office`, `active`) for every company with its six address
  columns, and drops those columns from `company_companies`. `down` adds the columns back and copies the main location into them.
- **Code that reads the old columns moves to the main location:** `CompanyProfile` and `DatabaseCompanyRepository`, `CompanyProfileResource`
  and `PublicCompanyResource`, the letterhead of the offer letter (`RepositoryCompanyLetterheads`: street, city and province of the main
  location), `CreateCompany` (admin creation with an address writes the main location) and the tests that set the columns
  (`CompanyProfileTest`, `BuildsJobFixtures` and others).
- **Legacy import:** `DatabaseCompanyWriter` writes the address (BPS chain cut at the deepest valid level, as today) into one main location per
  company. The legacy `lat` and `long` are carried when both are set and inside the bounding box, otherwise left empty with the note
  `coordinates-dropped`. `exact_location` and `locality` are not carried, as for jobs (`phase-1-migration.md`).

## 3. API

**Company (member):**
- `GET company/locations`: every location of the tenant (active first, then by name), `filter[status]`. Open to every member.
- `POST company/locations`, `PATCH company/locations/{id}` (send only what changes), with `company.profile`.
- `POST company/locations/{id}/main`: moves the main mark (the target must be active; 409 `location-inactive`).
- `POST company/locations/{id}/deactivate`, `POST company/locations/{id}/activate`: 409 `location-is-main` for the main location.
- `DELETE company/locations/{id}`: 409 `location-is-main`, 409 `location-in-use` when anything references it. A Shared port
  `CompanyLocationUsage` asks each context (Recruitment, Workforce, later Job and Employee) whether it holds the id. The foreign keys are the
  safety net.
- Row: `id`, `name`, `type`, `is_main`, `status`, `address {address_line, province {id, name}, city, subdistrict, village, postal_code}`,
  `phone`, `coordinates {latitude, longitude}` or null, `time_zone`.
- `PATCH company/profile` loses `address_line` and the five region fields. `GET company/profile` returns `main_location` (the row above) in
  their place.
- Changes go to the company activity log like the other profile changes.

**Public:** `GET companies/{slug}` replaces `location` with `locations` (the active ones, main first): `name`, `type`, `is_main`, the address
with names, `coordinates`. No phone.

**Group read rule:** group members read the locations of the tenants they may see (`erp-organization.md`, part D), as they read the profile.

**Platform admin:** the admin company view shows the locations read-only. Admin creation of a company takes the main location's address.

## 4. Interviews and requisitions

- **Interviews:** `recruitment_interviews.location_id` (nullable, RESTRICT, indexed). The scheduling fields (`InterviewFields`, the single move
  and the bulk move) take `location_id` for `onsite`. It must be an active location of the job's company (422 `invalid-location`). When it is
  given, `location` is filled from its address (street, village, subdistrict, city, province, joined with commas, cut to `Interview::LOCATION_MAX`)
  and a sent `location` is ignored. `location_id` without `onsite` is dropped like `location` today. A new time keeps both. The interview
  resources and the calendar return `location_id` beside `location`.
- **Requisitions:** `workforce_requisitions.location_id` (nullable, RESTRICT, indexed) in `SaveRequisitionRequest` and the resource. It must be an
  active location of the requisition's company (422 `invalid-location`). It is not an approval-relevant field: it is not part of an amendment
  and can be changed while the requisition is a draft or returned, like the other draft fields.
- **Port:** `CompanyLocations` in `src/Shared` (`activeLocation(companyId, locationId)` returning the address text and the name, or null).
  It is used by Recruitment and Workforce, which read no Company tables.

## 5. Settings, foreign keys, documents

- Setting `company.max_locations` (integer, default 100, admin-editable). Add it to `tests/Feature/Platform/SettingsTest.php`.
- `cross-context-foreign-keys.md`: `company_locations` joins the stable targets; record the two keys. `CLAUDE.md` (Database, foreign keys)
  gains `company_locations` in the list of targets.
- Migrations: `000174` (table, copy, drop of the company columns), `000175` (`location_id` on interviews and requisitions).
- Front end (`frontend.md`): a "Lokasi" tab in the company settings (list, form with the region pickers and an optional map pin, main mark,
  deactivate). A location picker in the interview dialog and the requisition form. The locations list on the public company page.

## 6. Not in #27

- Job locations: the `locations` array (#28), then several locations per job with search, matching, alerts and recommendations (#20).
- An offer `location_id` (the offer keeps its text).
- A location's own time zone in the calendar, the emails and the scheduler.
- Location-level contacts beyond one phone, opening hours, and a regional minimum wage per location (payroll, `erp-direction.md` D7).
- Employee placement (`erp-direction.md` D2).
