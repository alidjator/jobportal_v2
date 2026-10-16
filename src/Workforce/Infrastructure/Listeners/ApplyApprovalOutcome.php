<?php

namespace Src\Workforce\Infrastructure\Listeners;

use Src\Shared\Application\Approval\ApprovalGranted;
use Src\Shared\Application\Approval\ApprovalRejected;
use Src\Shared\Application\Approval\ApprovalReturned;
use Src\Workforce\Application\Plan\ApplyPlanOutcome;
use Src\Workforce\Application\WorkforceApprovalSubjects;

/** Moves the subjects of the Workforce context on when their approval ends. It runs at once, so the subject is up to date when the decision's response goes out. */
final readonly class ApplyApprovalOutcome
{
    public function __construct(private ApplyPlanOutcome $plans) {}

    public function onGranted(ApprovalGranted $event): void
    {
        if ($event->subjectType === WorkforceApprovalSubjects::PLAN) {
            $this->plans->granted($event->subjectId, $event->approvalId, $event->decidedByUserId);
        }
    }

    public function onRejected(ApprovalRejected $event): void
    {
        if ($event->subjectType === WorkforceApprovalSubjects::PLAN) {
            $this->plans->rejected($event->subjectId, $event->approvalId, $event->reason, $event->decidedByUserId);
        }
    }

    public function onReturned(ApprovalReturned $event): void
    {
        if ($event->subjectType === WorkforceApprovalSubjects::PLAN) {
            $this->plans->returned($event->subjectId, $event->approvalId, $event->reason, $event->decidedByUserId);
        }
    }
}
