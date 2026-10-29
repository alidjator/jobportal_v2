# Phase 8: escalation of a waiting approval (designed 2026-10-20)

Closes MG-B10 of `docs/analysis/multi-persona-gap-analysis.md` (decision 10). Builds on `erp-requisitions.md` section 4 (the approval workflow and its reminders) and `phase-8-company-team-fixes.md` (the task list). Design only: nothing is coded.

## Decisions

- 2026-10-20 A step that kept waiting after **N reminders** is **escalated once**: a notice and a task for the people who may replace its approver, and one notice for the requester (gap-analysis decision 10; [section 1](#1-the-escalation)).
- 2026-10-20 **Recipients:** whoever may replace an approver today (`DecisionAuthority::canReplace`: a company admin of the tenant, and a holder of `group.approvals.manage` of its group), so a company outside a group is covered too (user; [section 1](#1-the-escalation)).
- 2026-10-20 **Once per step** (not repeated every N reminders); the reminders to the approver go on (user; [section 1](#1-the-escalation)).
- 2026-10-20 **The replace action works on a filled place once the step is escalated.** Today it refuses anything but a vacant place (409 `approval-slot-not-vacant`), which would leave an escalation with nothing to do about an approver who is only ignoring the step (user; [section 2](#2-replacing-an-approver-who-is-not-vacant)).

## 1. The escalation

- **Setting:** `approvals.escalate_after_reminders` (integer, default 3, 0 to 10, `0` turns the escalation off; company overridable like `approvals.reminder_days`). The reminder interval stays `approvals.reminder_days`.
- **Schema:** `approval_instance_steps` gains `reminder_count` (smallint, default 0, raised by `markReminded`) and `escalated_at` (nullable timestamp). A step's counters start with it and end when it is decided; a new step starts at 0. A migration, new.
- **When:** `SendApprovalReminders` (the existing command, unchanged schedule) raises the count after each reminder sent and, when `escalated_at` is empty and the count reached the setting, escalates in the same run, in the same transaction as the reminder.
- **Effects:** `escalated_at` is set and the history records `escalated`; the recipients get the in-app notice `approval_escalated` (title, the subject, how long it has waited; preference `approval_task`) and the **task** `approvals_escalated` on C1 (key, order 27, no company permission, filter `company/approvals?as=approver&escalated=true` for a holder; for a company admin of a tenant the list of their company's escalated approvals); the requester gets one in-app notice `approval_update` ("persetujuan Anda diteruskan ke admin"). No email.
- **Shown:** the approval resources carry `escalated_at` and `reminder_count`; E1 and E2 show the badge "Dieskalasi" and, for a recipient, the replace action.
- **Not a decision:** an escalated step stays pending; the approver can still decide it, and the escalation ends when the step is decided or an approver is replaced.

## 2. Replacing an approver who is not vacant

- `PUT company/approvals/{id}/approvers/{slotId}` accepts a place that is filled when its step has `escalated_at` set; on an unescalated step the answer stays 409 `approval-slot-not-vacant`. Everything else is the rule of `ReplaceApprover` (the replacement is a member of a tenant of the group, not the requester; audited as `approver_replaced`).
- The replaced approver gets an in-app notice (`approval_update`: "Anda diganti sebagai penyetuju untuk ...") and the place is no longer theirs to decide; a decision they send afterwards is 404 like any account with nothing to do with the approval. The step keeps `escalated_at` and the count restarts for the new approver (`reminder_count = 0`) so they are reminded on the normal rhythm; a second escalation can happen only after another replacement, never twice for the same person.
- A delegation (E2) set by the replaced approver does not carry over: the new approver's own delegations apply.

## 3. Tests

The count rises with each reminder and the escalation happens at the setting, once; `0` never escalates; the company override wins; the recipients are the company admins of the tenant and the group holders, not the approver or the requester; the task count equals the escalated approvals of the member's scope; an unescalated step still refuses a replacement of a filled place and an escalated one accepts it; the replaced approver is told and can no longer decide; a decided step stops counting; migration defaults.

## Open points

None.
