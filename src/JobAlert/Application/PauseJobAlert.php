<?php

namespace Src\JobAlert\Application;

use Src\JobAlert\Domain\JobAlert;
use Src\JobAlert\Domain\JobAlertRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Stops an alert without deleting it. Pausing a paused alert changes nothing. */
final readonly class PauseJobAlert
{
    public function __construct(private OwnedAlert $owned, private JobAlertRepository $alerts, private Clock $clock, private TransactionManager $transactions) {}

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $alertId): JobAlert
    {
        return $this->transactions->run(function () use ($actor, $alertId): JobAlert {
            $paused = $this->owned->find($actor, $alertId, forUpdate: true)->pause($this->clock->now());
            $this->alerts->update($paused);

            return $paused;
        });
    }
}
