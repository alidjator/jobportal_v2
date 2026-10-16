<?php

namespace Src\Assessment\Infrastructure\Tasks;

use Illuminate\Database\ConnectionInterface;
use Src\Shared\Application\Tasks\TaskProvider;
use Src\Shared\Application\Tasks\TaskScope;

/** Submitted tests with a long-text answer that waits for the member, who is one of the assessors of the invitation. */
final readonly class MyScoringTask implements TaskProvider
{
    public function __construct(private ConnectionInterface $connection) {}

    public function key(): string
    {
        return 'my_scoring';
    }

    public function order(): int
    {
        return 40;
    }

    public function permission(): ?string
    {
        return null;
    }

    public function filter(TaskScope $scope): string
    {
        return 'company/assessment/invitations?filter[my_scoring]=pending';
    }

    public function count(TaskScope $scope): int
    {
        return $this->connection->table('assessment_invitation_assessors as assessor')
            ->join('assessment_invitations as invitation', 'invitation.id', '=', 'assessor.invitation_id')
            ->join('assessment_attempts as attempt', 'attempt.invitation_id', '=', 'invitation.id')
            ->join('assessment_results as result', 'result.attempt_id', '=', 'attempt.id')
            ->where('assessor.user_id', $scope->userId)
            ->where('invitation.company_id', $scope->companyId)
            ->where('invitation.status', 'submitted')
            ->where('result.status', 'pending_scoring')
            ->count();
    }
}
