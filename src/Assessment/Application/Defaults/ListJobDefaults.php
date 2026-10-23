<?php

namespace Src\Assessment\Application\Defaults;

use Src\Assessment\Domain\JobStageTestRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The default tests of a job, one per assessment stage that has one, for a member who may send tests and see the job. */
final readonly class ListJobDefaults
{
    public function __construct(private JobDefaultAccess $access, private JobStageTestRepository $defaults, private JobDefaultViews $views) {}

    /**
     * @return list<JobDefaultView>
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $jobId): array
    {
        $companyId = $this->access->companyId($actor, $jobId);

        return $this->views->many($companyId, $this->defaults->ofJob($jobId));
    }
}
