<?php

namespace Src\Shared\Application\Approval;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * What the contexts that own subjects (Workforce, later Recruitment) ask of the Approval context: send a subject to approval, or take it back. The outcome comes back as the
 * domain events `ApprovalGranted`, `ApprovalRejected` and `ApprovalReturned`. Provided by Approval.
 */
interface Approvals
{
    /**
     * Sends the subject through the flow that applies to its tenant, in the caller's transaction. The flow is copied now, so editing it later changes nothing for this approval; the
     * steps whose conditions do not hold are left out, and a flow with no step left approves at once (`ApprovalGranted` follows).
     *
     * @return int the id of the approval
     *
     * @throws StateConflict `approval-flow-missing` when neither the tenant nor its group has a flow for the subject type, or `approval-already-pending` for a subject already in approval
     * @throws BusinessRuleViolation `approval-not-internal-tenant`, `approval-unknown-subject-type` or `approval-requester-only-approver` when a step could only be approved by the requester
     */
    public function submit(ApprovalSubmission $submission): int;

    /** Ends the running approval of the subject without a decision (the subject was withdrawn). Nothing happens when there is none. */
    public function cancel(string $subjectType, int $subjectId, int $actorUserId): void;
}
