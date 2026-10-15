# Phase 8: applicants in the talent pool, inviting from the pool and a richer pool list (designed 2026-10-14, not coded)

Gaps found against Mekari Talenta's talent pool (help center article 54505692565529). Not copied on purpose: uploading CVs of passive candidates
(the company would hold personal data of people who are not our users, without their consent), moving a pool candidate into a pipeline without an
application (it skips the candidate's consent; we invite instead) and the company editing a candidate's profile (the profile belongs to the
candidate). Extends `phase-4-saved-jobs-and-talent-pool.md` (TalentPool), `phase-3-invitations.md` (JobInvitation) and `phase-1-applications.md`.
Coded in a coding session.

## Decisions

- 2026-10-14: an applicant can be saved to the pool from their application only with **consent given when applying**: an optional checkbox, off by
  default, that the applicant may revoke later; a findable candidate (opted in to the candidate search) can be saved as today (decided by the
  user).
- 2026-10-14: an applicant can be saved from **every stage except `hired`** (decided by the user).
- 2026-10-14: pool candidates can be **invited in bulk** to one job (decided by the user).
- 2026-10-14: a pool entry that rests on an application is kept **24 months after the last consenting application** (decided by the user).

## 1. Saving applicants

### Consent

- The apply request (`POST jobs/{id}/applications`) takes `talent_pool_consent: bool` (default false). The form shows "Izinkan {company} menyimpan
  profil saya untuk lowongan lain" under the submit button, unticked.
- Stored as `recruitment_applications.talent_pool_consent_at` (nullable). The job seeker's application detail shows it with a switch:
  `PUT me/applications/{id}/talent-pool-consent` gives it (also later, from any stage), `DELETE` revokes it (both 204). The team's application detail
  shows `talent_pool_consent: bool`.
- **Revoking** removes the company's pool entry that rests on it, unless another application to that company still carries consent or the person is
  findable (then the entry stays on that basis). The note goes with a removed entry.

### Saving

- `POST company/talent-pool` takes either `candidate` (the slug, as today) or `application_id` (new), plus `category_id?` and `note?`;
  `talent_pool.manage`, and the member must be able to see the application.
- From an application: 409 `application-hired` for a `hired` application, 409 `talent-pool-consent-missing` when the application has no consent and
  the person is not findable; a person already in the pool is 409 `already-in-talent-pool` as today.
- New columns on `talentpool_entries`: `source` (`search`, `application`), `application_id` (nullable; the consenting application the entry was
  saved from), `saved_by_user_id` (nullable for entries saved before).

### Basis and visibility

- An entry is **available** while the person is findable **or** has an application to this company with consent whose `applied_at` is within 24
  months. Otherwise it shows `available: false` and nothing about the person, and its note is cleared at night, as today.
- **What the team sees** for an entry available only through consent: the snapshot of the latest consenting application (name, headline, city,
  latest position, skills), never the live profile and no contact data; for a findable person, the candidate-search profile as today.
- **Retention:** a nightly command (with `talentpool:clear-hidden-notes`) deletes entries whose only basis was consent and whose last consenting
  application is older than 24 months. Anonymisation deletes every entry as today and clears the consent with the snapshot.
- Restricted candidates (`phase-8-rejections.md`): their entries stay and carry the `restricted` badge.
- Front end: "Simpan ke talent pool" in the application menu (C6, C7) and in the "Tandai terpenuhi" dialog of `phase-8-hiring.md`, disabled with
  "Pelamar tidak mengizinkan" when consent is missing.

## 2. Inviting from the pool

- `POST company/jobs/{id}/invitations/bulk {entry_ids: [] (1 to 50), note?}`: one invitation per entry to a live job, through the existing
  invitation rules (one per candidate and job, `already-applied`, the job seen by the member). Each one takes a slot of the daily cap
  `invitations.max_per_day`; when the cap runs out the rest are skipped with `invitation-limit`. Answers 200 with
  `data: [{entry_id, status: invited|skipped, code?, invitation_id?}]`.
- **Who may be invited** (single and bulk) widens from "findable" to "findable, **or** in this company's pool with an available consent basis". The
  single endpoint keeps its slug for findable candidates and also takes `talent_pool_entry_id`.
- Skip codes: `entry-unavailable`, `already-invited`, `already-applied`, `candidate-restricted`, `invitation-limit`.
- The invitation, its email (preference `invitation`) and its statuses are unchanged; the candidate never sees that they came from a pool.
- Front end: checkboxes in the pool list with "Undang ke lowongan" (job picker and note).

## 3. A richer pool list

- `GET company/talent-pool` adds per available entry: `headline`, `city`, `latest_position` (`title`, `employer`), `skills` (up to 5 names),
  `source`, `saved_by {id, name}` (null when unknown), `saved_at`, `last_application` with this company (`job_title`, `status`, `applied_at`, and
  `id` only when the member may see that application) and `last_invitation` (`job_title`, `status`, `sent_at`).
- **Filters:** `filter[q]` (name, headline, skills, latest position; case-insensitive contains), `filter[source]`, `filter[category_id]` as today.
  **Sort:** `saved_at` (default) or `last_activity` (the latest application or invitation).
- **How:** the pool holds at most 1,000 entries per company, so the list loads the summaries of all available entries through ports
  (`CandidateDirectory` for findable people, a new Recruitment port for the consent snapshots and the last application, a JobInvitation port for the
  last invitation), filters and sorts in memory, then pages. No copy of personal data is stored in TalentPool.
- `GET company/talent-pool/{id}` (new): the entry with the same summary, plus the full candidate-search profile for a findable person or the
  snapshot (without contact data) otherwise, and links to the applications the member may see.

## ERP later: a pool shared across a group (recorded 2026-10-15, not designed)

A note for the ONESIS ERP (`erp-direction.md`, `erp-organization.md`); nothing changes in this design.

- **Module:** the pipeline (`Recruitment`) and the talent pool (`TalentPool`) belong to ONESIS Talent; ONESIS Career only shows the candidate their
  status and lets them give or revoke consent and findability.
- **Idea:** the pool is per company today (at most 1,000). A group such as PKP may want a **pool shared by its internal tenants**, for example a
  good candidate rejected by one legal entity offered to another.
- **Consent:** the consent of section 1 names one company ("Izinkan {company} menyimpan profil saya ..."). Sharing with the other legal entities
  of the group is a disclosure to other controllers under UU 27/2022, so a group pool needs its own consent that names the group, and revoking it
  removes the shared entries. A findable profile (candidate search) is already visible to every company and needs nothing new.
- **Open when designed:** who reads and adds (a group permission such as `group.talent_pool.manage`, or every internal tenant of the group), the
  limit of a group pool, whether notes are shared, and a legal question in `docs/legal-review.md` on the consent wording.

## Changes in short

| Piece | Change |
|---|---|
| Migration | `recruitment_applications.talent_pool_consent_at`; `talentpool_entries.source`, `application_id`, `saved_by_user_id` |
| API | `talent_pool_consent` on apply; `PUT`/`DELETE me/applications/{id}/talent-pool-consent`; `application_id` on `POST company/talent-pool`; `POST company/jobs/{id}/invitations/bulk`; `talent_pool_entry_id` on the single invitation; summary fields, `filter[q]`, `filter[source]`, sort on `GET company/talent-pool`; `GET company/talent-pool/{id}` |
| Ports | Recruitment: consent basis, snapshot summary and last application per person; JobInvitation: last invitation per person; JobSeeker `CandidateDirectory`: profile summaries |
| Scheduler | nightly deletion of consent-only entries past 24 months |
| Errors | 409 `application-hired`, `talent-pool-consent-missing`; skip codes `entry-unavailable`, `already-invited`, `already-applied`, `candidate-restricted`, `invitation-limit` |
| Front end | consent checkbox on the apply form and switch on the job seeker's application; "Simpan ke talent pool" on applications; pool list with summaries, search, sort and bulk invite; pool entry page |
