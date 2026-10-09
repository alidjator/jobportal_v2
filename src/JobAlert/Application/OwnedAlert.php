<?php

namespace Src\JobAlert\Application;

use Src\JobAlert\Domain\JobAlert;
use Src\JobAlert\Domain\JobAlertRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Finds the alert of the caller; an alert that is missing or someone else's is the same 404. */
final readonly class OwnedAlert
{
    public function __construct(private JobAlertGuard $guard, private JobAlertRepository $alerts) {}

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     */
    public function find(Actor $actor, int $alertId, bool $forUpdate = false): JobAlert
    {
        return $this->alerts->findOfUser($this->guard->userId($actor), $alertId, $forUpdate) ?? throw EntityNotFound::of('Job alert', $alertId);
    }
}
