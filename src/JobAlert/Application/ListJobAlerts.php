<?php

namespace Src\JobAlert\Application;

use Src\JobAlert\Domain\JobAlert;
use Src\JobAlert\Domain\JobAlertRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** All the alerts of the caller, oldest first (there are only a few: the limit is an admin setting). */
final readonly class ListJobAlerts
{
    public function __construct(private JobAlertGuard $guard, private JobAlertRepository $alerts) {}

    /**
     * @return list<JobAlert>
     *
     * @throws AccessDenied `job-seeker-only`
     */
    public function execute(Actor $actor): array
    {
        return $this->alerts->ofUser($this->guard->userId($actor));
    }
}
