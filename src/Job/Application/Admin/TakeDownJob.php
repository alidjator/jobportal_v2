<?php

namespace Src\Job\Application\Admin;

use DateTimeImmutable;
use Src\Job\Domain\Events\JobDecided;
use Src\Job\Domain\Job;
use Src\Shared\Application\Actor;

/** An admin takes a live job down, with a reason the company reads. Only an admin can put it back (`RestoreJob`). */
final class TakeDownJob extends AdminJobDecision
{
    public function execute(Actor $actor, int $jobId, string $reason, ?string $ipAddress, ?string $userAgent): void
    {
        $this->run($actor, $jobId, $reason, $ipAddress, $userAgent);
    }

    protected function decision(): string
    {
        return JobDecided::TAKEN_DOWN;
    }

    protected function apply(Job $job, int $adminId, ?string $reason, DateTimeImmutable $at, DateTimeImmutable $today): Job
    {
        return $job->takeDown((string) $reason, $adminId, $at);
    }
}
