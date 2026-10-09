<?php

namespace Src\JobAlert\Application;

use Src\JobAlert\Domain\JobAlertRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

final readonly class DeleteJobAlert
{
    public function __construct(private OwnedAlert $owned, private JobAlertRepository $alerts) {}

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $alertId): void
    {
        $this->alerts->delete((int) $this->owned->find($actor, $alertId)->id);
    }
}
