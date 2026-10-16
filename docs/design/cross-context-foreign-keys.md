# Selective cross-context foreign keys (designed 2026-10-10, decisions confirmed, coded and checked)

Step 2 after the PostgreSQL port (`postgres-migration.md`, committed `5ad9e30`). Until now no foreign key crossed a bounded context, except to the `reference_*` tables, to `company_companies` (from Job, Recruitment, Messaging, Company) and the benefit proposal. The columns that point to accounts, to jobs and to applications of another context are plain indexed columns. This step adds the keys that protect core data, and only those.

## Confirmed by the user (2026-10-10)

- **Selective:** a key only towards stable targets: `identity_users`, `identity_admins`, `company_companies`, `job_jobs`, and the `reference_*` tables (already done).
- **Never** on logs, audit, views, `*_daily` summary tables, notifications, the legacy id map and the match deliveries: those must survive the death of what they describe, and they are written in bulk.
- **Action:** `ON DELETE RESTRICT`. The database refuses to delete a row that is still used. One exception, decided below.
- **Order:** orphan rows are checked first, then the keys are added; the decision is recorded here.

## Why it is safe to point at accounts

An account is never deleted: erasure **anonymises** it (`UserAnonymized`) and the row stays, so a key to `identity_users` never blocks erasure. Companies are suspended, not deleted. A job can be deleted only while it is a draft (`DeleteJob`), so a job with applications, invitations or views is never removed. Admins are never hard-deleted in the code today.

## Keys to add

All keys are `RESTRICT`, named explicitly (63 character limit on PostgreSQL), added in one new migration after the table creations. Every referencing column listed already has an index or is the first column of a unique index; the ones marked (index) need one added, so that a delete or update of the parent does not scan the table.

**To `identity_users`**

| Table | Column |
|---|---|
| `company_members` | `user_id` |
| `job_assignments` | `user_id`, `assigned_by_user_id` (nullable) |
| `job_jobs` | `created_by_user_id` (index) |
| `recruitment_screening_questions` | `created_by_user_id` (index) |
| `recruitment_applications` | `user_id` |
| `recruitment_application_notes` | `author_id` (index) |
| `recruitment_application_ratings` | `member_id` |
| `recruitment_interviews` | `interviewer_id`, `created_by` (index) |
| `recruitment_offers` | `created_by` (index) |
| `jobseeker_profiles`, `jobseeker_experiences`, `jobseeker_educations`, `jobseeker_skills`, `jobseeker_languages`, `jobseeker_links`, `jobseeker_resumes`, `jobseeker_cv_generations` | `user_id` |
| `messaging_conversations` | `job_seeker_user_id`, `started_by_user_id`, `closed_by_user_id` (nullable) |
| `messaging_messages` | `sender_user_id` |
| `messaging_conversation_reads` | `user_id` |
| `jobalert_alerts` | `user_id` |
| `jobinvitation_invitations` | `user_id`, `invited_by_user_id` (index) |
| `company_invitations` | `invited_by_user_id` (nullable) |

**To `identity_admins`:** `job_jobs.reviewed_by_admin_id`, `company_companies.created_by_admin_id`, `company_documents.reviewed_by_admin_id`, `content_legal_documents.created_by_admin_id`, `platform_settings.updated_by_admin_id`, `platform_setting_overrides.updated_by_admin_id`, `company_invitations.invited_by_admin_id`.

**To `company_companies`:** `jobinvitation_invitations.company_id`.

**Added with `erp-organization.md` (2026-10-16):** `company_companies.legal_entity_id` (unique, RESTRICT) to `organization_legal_entities`, and `organization_group_members.user_id` (unique, RESTRICT) to `identity_users`.

**Added with the approval instances (`erp-requisitions.md` section 4, 2026-10-16):** `approval_instances.company_id` to `company_companies`; `approval_instances.requester_id`, `outcome_by_user_id`, `approval_instance_slots.user_id`, `decided_by_user_id`, `on_behalf_of_user_id` and `approval_delegations.approver_user_id`, `delegate_user_id` to `identity_users` (all RESTRICT). `approval_instances.group_id` and the owner of `approval_flows` are plain columns (one column names a group or a company).

**Added with the manpower plans (`erp-requisitions.md` section 2, 2026-10-16):** `workforce_plans.created_by_id` (RESTRICT) to `identity_users`. The legal entity, the positions and the approval of a plan are plain columns (the structure is deactivated, never deleted).

**Added with support access (`erp-governance.md` section 1.1, 2026-10-16):** `organization_support_grants.admin_id` (RESTRICT) to `identity_admins`, and `granted_by_user_id`, `revoked_by_user_id` (RESTRICT) to `identity_users`.

**To `job_jobs`:** `recruitment_applications.job_id`, `recruitment_job_questions.job_id` (`CASCADE`, see Decided), `jobinvitation_invitations.job_id`.

**To `recruitment_applications`:** `jobinvitation_invitations.application_id` (nullable), `assessment_invitations.application_id`.

## Deliberately without a key

- `recruitment_application_stage_history.actor_id`: holds a user or an admin (or null for the system), so it cannot point at one table. The history is also a log.
- `recruitment_application_stage_history.from_stage_id` and `to_stage_id`: an extra stage can be deleted later.
- `jobseeker_profile_views` (candidate, company, member), `job_views`, `job_view_daily`, `jobalert_deliveries.job_id`, `matching_deliveries`: logs, views and delivery records.
- `platform_audit_logs` (actor, subject), `notifications`, `legacyimport_id_map`, the three `*_daily` summary tables, `identity_verification_codes.subject_id`, the polymorphic `model_id` of the admin role and permission pivots.

## Costs and things to do when coding

- **Tests:** many feature tests insert rows with an invented `user_id`, `job_id` or `company_id`. Each now needs a real parent row (through the existing test helpers). This is the largest part of the work and it is how the keys get proven.
- **Legacy import:** it must write parents before children (users, companies, jobs, then applications and the rest). To be read and tested against the synthetic legacy tables.
- **Orphan check:** a read-only script (`SELECT ... WHERE NOT EXISTS`) over every listed column, run on `jobportal_v2` before the migration. The database is empty today, so it is a safety net; the migration itself fails loudly if a row is orphaned.
- **Migration order:** the keys live in one migration numbered after `000101`, like `000059`, `000060` and `000061` did for the first set; the tables stay creatable in any order.
- **Tests of the keys:** one test per target table: a row with a missing parent is refused, and a parent that is still used cannot be deleted.

## Decided (2026-10-10, user)

1. `recruitment_job_questions.job_id` is `ON DELETE CASCADE` (an attachment of the job, like `job_job_skills`), so a draft with attached screening questions can still be deleted. Every other key is `RESTRICT`.
2. The "who did it" columns (author of a note, interviewer, sender, inviter, assigner) get a key too, because accounts are never deleted.

## What was coded (2026-10-10)

- Migration `2026_10_10_000102_add_cross_context_foreign_keys` (the lists above; explicit names cut to 63 characters with a checksum; it adds an index first where the column is not the lead of one). `recruitment_job_questions.job_id` is the only `CASCADE`.
- Tests: `CrossContextForeignKeysTest` (every key and its action, the tables without keys, a missing parent refused, a used parent not deletable, a draft job going away with its attached questions). `CreatesReferenceRows` got `userRowId` and `adminRowId`; the tests that inserted invented ids (schema tests, `MyProfileTest`, `ErasureTest`, `AnalyticsTest`, `CvGeneratorTest`, the admin company and document tests, `CompanyTeamTest`, `PublicJobTest`) use real rows. The key lists in `JobTablesTest` and `CompanyForeignKeysTest` now include the account and admin columns.
- Not done: the legacy import was only read (it writes in dependency order, and the creator ids come from the imported accounts); its tests decide.
