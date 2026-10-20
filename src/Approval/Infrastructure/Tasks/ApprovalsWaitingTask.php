<?php

namespace Src\Approval\Infrastructure\Tasks;

use Src\Approval\Application\Instance\MyApprovals;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Tasks\TaskProvider;
use Src\Shared\Application\Tasks\TaskScope;

/**
 * The approvals that wait for the member's decision: steps in their name, steps delegated to them today and steps of a group role they hold. The count is the one of the
 * inbox (`MyApprovals::waitingCount`), so the task list and `GET company/approvals` cannot disagree. Every approver may decide, so no company permission is needed.
 */
final readonly class ApprovalsWaitingTask implements TaskProvider
{
    public function __construct(private MyApprovals $approvals) {}

    public function key(): string
    {
        return 'approvals_waiting';
    }

    public function order(): int
    {
        return 28;
    }

    public function permission(): ?string
    {
        return null;
    }

    public function filter(TaskScope $scope): string
    {
        return 'company/approvals?as=approver';
    }

    public function count(TaskScope $scope): int
    {
        return $this->approvals->waitingCount(Actor::companyMember($scope->userId));
    }
}
