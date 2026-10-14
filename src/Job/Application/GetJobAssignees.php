<?php

namespace Src\Job\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** A member reads who is assigned to a job they may see. */
final readonly class GetJobAssignees
{
    public function __construct(
        private JobAccess $access,
        private JobDetailBuilder $details,
    ) {}

    /**
     * @return list<array{user_id: int, name: string, role: string}>
     *
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound when the member cannot see the job
     */
    public function execute(Actor $actor, int $jobId): array
    {
        $member = $this->access->member($actor);

        return $this->details->assignees($this->access->job($member, $jobId));
    }
}
