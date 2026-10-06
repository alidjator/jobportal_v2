<?php

namespace Src\Job\Application\Admin;

use DateTimeImmutable;
use Src\Job\Domain\Events\JobDecided;
use Src\Job\Domain\Job;
use Src\Shared\Application\Actor;

/**
 * An admin lifts the take-down of a job: it goes live again. A job whose deadline has passed becomes `expired` instead, so a dead job is never shown and the
 * company can reopen it with a new deadline (it cannot edit or reopen a job that is taken down, so refusing would leave it stuck).
 */
final class RestoreJob extends AdminJobDecision
{
    public function execute(Actor $actor, int $jobId, ?string $ipAddress, ?string $userAgent): void
    {
        $this->run($actor, $jobId, null, $ipAddress, $userAgent);
    }

    protected function decision(): string
    {
        return JobDecided::RESTORED;
    }

    protected function apply(Job $job, int $adminId, ?string $reason, DateTimeImmutable $at, DateTimeImmutable $today): Job
    {
        $deadline = $job->content->deadline;

        return $job->restore($adminId, $at, deadlinePassed: $deadline === null || $deadline < $today);
    }
}
