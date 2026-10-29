# Phase 8: three company-side gaps: undeliverable email, bulk job assignment and approval tasks (designed 2026-10-20)

Closes MG-P03, MG-B03 and MG-B09 of `docs/analysis/multi-persona-gap-analysis.md` (decisions 18 to 23). Builds on `phase-8-recruiter-workday.md` (the task list), `phase-8-company-permissions.md` (`jobs.assign`), `phase-8-platform-admin.md` section 6 (`email_deliverable`) and `erp-requisitions.md` section 4 (approvals). Design only: nothing is coded.

## Decisions

- 2026-10-20 **MG-P03:** the company resources of an application carry `email_deliverable` only, never the reason; on the list and detail of C6 and C7, for members who may already read the application (gap-analysis decisions 18 and 19; [section 1](#1-mg-p03-undeliverable-email)).
- 2026-10-20 **MG-B03:** a member is added to many jobs from the member page C22; only adds, recruiter role, all or nothing, at most 100 jobs, needs `jobs.assign`, one summary notice (gap-analysis decisions 20 and 21 and the answers of 2026-10-20; [section 2](#2-mg-b03-bulk-job-assignment)).
- 2026-10-20 **MG-B09:** the task `approvals_waiting` counts what the inbox E1 lists for the member; approvers only (gap-analysis decisions 22 and 23; [section 3](#3-mg-b09-approval-tasks)).

## 1. MG-P03: undeliverable email

- **Field:** `email_deliverable` (boolean; false when the applicant's address is on the suppression list) on every row of `GET company/applications` and `GET company/jobs/{id}/applications` and on `GET company/applications/{id}`; never the reason (bounce or complaint) and never the address. A member who may not read the application cannot see it (the resources are the same).
- **Source:** the port `ApplicantContacts` (provided by Identity) gains `undeliverable(list<int> $userIds): list<int>`, answered through `EmailSuppressions::reasons` for the addresses of the accounts; one call per page. An account without an address on file (anonymised) is not flagged.
- **Front end:** C6 and C7 show a warning "Email kandidat tidak dapat menerima email; hubungi lewat telepon atau chat" next to the candidate (`frontend.md`).
- **Tests:** a suppressed address flags the row and the detail, a clean one does not, the field never carries a reason, a member without the permission to read gets the usual 404, one port call per page.

## 2. MG-B03: bulk job assignment

- **Endpoint:** `POST company/members/{userId}/jobs` with `{job_ids: [1..100 ids]}`; needs `jobs.assign`; the target is any member of the company (admin or recruiter), including one who cannot sign in. Answer 200 `{added: n, already: m}`.
- **Effect:** the member is **added** to the assignees of each job as a **recruiter**; the assignees already on a job stay, and a job that already has the member is counted in `already`. The roles of existing assignees never change (a hiring manager keeps the role and its boundary).
- **Rules:** every id must be a job of the company (imported jobs included, any status), else 422 `invalid-assignments` with `errors.job_ids.<position>` per id and **nothing is written** (all or nothing); the list has no duplicates (they collapse); one transaction; the jobs are locked in ascending id so two requests cannot deadlock.
- **Events and notices:** each job still dispatches `JobAssigneesChanged` (the audit and the job-level listeners stay as they are) with `bulk: true`, which the per-job notice listener skips; after the commit one notice goes to the member, "Anda ditugaskan ke N lowongan" (type `job.assigned_many`, same preference as the single assignment notice, link to `company/members/{id}/jobs`). The company activity log gets one entry `company.member_jobs_assigned` (count only, the member, no job titles).
- **Not built:** selecting jobs on C3 and assigning several members, removing in bulk, and a role other than recruiter.
- **Tests:** a recruiter who saw no job sees the jobs after the call; existing assignees and a hiring manager's role stay; one foreign id rejects everything with its position; the 101st id is 422; `jobs.assign` is required; imported jobs are accepted; one notice, not N; the audit has an entry per job and one for the company; a rerun gives `already`.

## 3. MG-B09: approval tasks

- **Provider:** `ApprovalsWaitingTask` (Approval Infrastructure, key `approvals_waiting`, order 28, before the reschedule requests), no company permission (every approver may decide), filter `company/approvals?as=approver`, count = what `GET company/approvals` lists for the member as approver: steps in their name, steps delegated to them today, steps of a group role they hold (one query shared with `MyApprovals::mine`, so the two cannot disagree).
- **Not built:** a task for the requester when an approval is returned for revision (the existing return notice stays).
- **Tests:** the count equals the rows of the list; a delegated step counts and stops counting when the delegation ends; a decided step leaves the count; a requester never counts their own request; a member without any step has no item.

## Open points

None.

## Coded (2026-10-20)

No migration. No `.env` value.

- **MG-P03:** `ApplicantContacts::undeliverable` (Identity, through `EmailSuppressions::reasons`); `ApplicationRowEnricher` asks once per page; `GetCompanyApplication` once for the detail. `RecruiterApplicationRow` and `RecruiterApplicationDetailData` gained `userId` (never exposed). The list of C6/C7 and the detail carry `email_deliverable`.
- **MG-B03:** `POST company/members/{userId}/jobs` is in the Job context (`AssignMemberToJobs`, `CompanyJobController::assignMember`), next to the job assignee routes; the `GET` of the same path stays in Company. The per-job event `JobAssigneesChanged` has `bulk: true` (skipped by `NotifyAssignees`); the new event `MemberAssignedToJobs` feeds `NotifyMemberOfBulkAssignment` (`job.assigned_many`, data `{count}`) and the company audit entry `company.member_jobs_assigned` (group `team` of the activity log). The error `errors` keys are `job_ids.<position>` (the position in the list sent). A target who is not a member of the company is a 404.
- **MG-B09:** `MyApprovals::waitingCount` shares its query with `mine(approver)`; `ApprovalsWaitingTask` (order 28) is registered by the Approval service provider and builds the actor from the member id only.
