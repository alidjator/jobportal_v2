<?php

namespace Src\Assessment\Application\Defaults;

use Src\Assessment\Domain\JobStageTestRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Removes the default test of a job for a stage. A stage without one is not an error: removing is idempotent. */
final readonly class RemoveJobDefault
{
    public function __construct(private JobDefaultAccess $access, private JobStageTestRepository $defaults) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $jobId, int $stageId): void
    {
        $this->access->companyId($actor, $jobId);

        $this->defaults->delete($jobId, $stageId);
    }
}
