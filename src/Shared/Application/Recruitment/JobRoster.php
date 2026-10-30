<?php

namespace Src\Shared\Application\Recruitment;

use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * Lets other contexts list the applications of one job that a company member may see, without reading Recruitment's tables: the active ones (the hired, rejected and
 * withdrawn ones are left out unless a stage type asks for them) for a member who holds `applications.view` or `applications.manage` on a job they may see; a hiring manager
 * bound to the stage boundary sees only the applications that carry the marker. Provided by Recruitment.
 */
interface JobRoster
{
    /**
     * The applications in the roster's order (newest applied first), with how well each applicant fitted the job when applying (null: no score).
     *
     * @param  ?string  $stageType  one stage type (`applied`, `screening`, `assessment`, `interview`, `offer`, `hired`, `rejected`, `withdrawn`); null for the active applications
     * @return array<int, int|null> match score by application id
     *
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound the job is not visible to the member
     */
    public function applications(Actor $actor, int $jobId, ?string $stageType = null): array;

    /**
     * The lines of these applications of the roster; an id that is not in the roster is missing from the answer.
     *
     * @param  list<int>  $applicationIds
     * @return array<int, RosterEntry> by application id
     *
     * @throws AccessDenied
     * @throws EntityNotFound
     */
    public function entries(Actor $actor, int $jobId, array $applicationIds, ?string $stageType = null): array;
}
