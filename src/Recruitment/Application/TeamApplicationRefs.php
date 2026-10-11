<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Recruitment\TeamApplicationRef;
use Src\Shared\Application\Recruitment\TeamApplications;

/** The port other contexts use to find an application for a company member: the same visibility rule as every recruitment action (see {@see TeamApplicationLookup}). */
final readonly class TeamApplicationRefs implements TeamApplications
{
    public function __construct(private TeamApplicationLookup $lookup, private JobDirectory $jobs) {}

    public function find(Actor $actor, int $applicationId, bool $forUpdate = false): TeamApplicationRef
    {
        $application = $this->lookup->find($actor, $applicationId, $forUpdate)->application;

        return new TeamApplicationRef(
            $applicationId,
            $application->companyId,
            $application->jobId,
            $application->userId,
            $application->stageType === StageType::Assessment,
            $this->jobs->isTakenDown($application->jobId),
        );
    }
}
