<?php

namespace Src\Job\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** A member reads one job they may see, with the names of its skills and benefits and its assignees. */
final readonly class GetJob
{
    public function __construct(
        private JobAccess $access,
        private JobDetailBuilder $details,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound when the member cannot see the job
     */
    public function execute(Actor $actor, int $jobId, string $language): JobDetail
    {
        $member = $this->access->member($actor);

        return $this->details->build($this->access->job($member, $jobId), $language);
    }
}
