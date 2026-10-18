<?php

namespace Src\Shared\Application\Job;

use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** Closes a live job on behalf of a company member, with the checks of the ordinary close (`jobs.publish`). Provided by Job. */
interface JobCloser
{
    /**
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound when the member cannot see the job
     * @throws StateConflict `job-not-active`
     */
    public function close(Actor $actor, int $jobId, ?string $ipAddress, ?string $userAgent): void;
}
