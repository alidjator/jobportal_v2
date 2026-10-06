<?php

namespace Src\Job\Application\Admin;

use DateTimeImmutable;
use Src\Job\Domain\Events\JobDecided;
use Src\Job\Domain\Job;
use Src\Shared\Application\Actor;

/** An admin rejects a job waiting for review, with a reason the company reads. The company can edit the job and publish it again. */
final class RejectJob extends AdminJobDecision
{
    public function execute(Actor $actor, int $jobId, string $reason, ?string $ipAddress, ?string $userAgent): void
    {
        $this->run($actor, $jobId, $reason, $ipAddress, $userAgent);
    }

    protected function decision(): string
    {
        return JobDecided::REJECTED;
    }

    protected function apply(Job $job, int $adminId, ?string $reason, DateTimeImmutable $at, DateTimeImmutable $today): Job
    {
        return $job->reject((string) $reason, $adminId, $at);
    }
}
