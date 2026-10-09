<?php

namespace Src\JobAlert\Application;

use Src\JobAlert\Domain\JobAlert;
use Src\JobAlert\Domain\JobAlertRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Starts a paused alert again, from now: the jobs that went live while it was paused are not sent. Resuming a running alert changes nothing. */
final readonly class ResumeJobAlert
{
    public function __construct(private OwnedAlert $owned, private JobAlertRepository $alerts, private Clock $clock, private TransactionManager $transactions) {}

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $alertId): JobAlert
    {
        return $this->transactions->run(function () use ($actor, $alertId): JobAlert {
            $resumed = $this->owned->find($actor, $alertId, forUpdate: true)->resume($this->clock->now());
            $this->alerts->update($resumed);

            return $resumed;
        });
    }
}
