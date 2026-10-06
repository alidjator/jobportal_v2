<?php

namespace Src\Job\Application\Admin;

use DateTimeImmutable;
use Src\Job\Domain\Events\JobDecided;
use Src\Job\Domain\Job;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * An admin approves a job waiting for review: it goes live (and a first approval sets when it was published). A job whose deadline has passed is not
 * approved (409 `job-deadline-passed`): the admin rejects it with a reason, and the company publishes it again with a new deadline.
 */
final class ApproveJob extends AdminJobDecision
{
    public function execute(Actor $actor, int $jobId, ?string $ipAddress, ?string $userAgent): void
    {
        $this->run($actor, $jobId, null, $ipAddress, $userAgent);
    }

    protected function decision(): string
    {
        return JobDecided::APPROVED;
    }

    protected function apply(Job $job, int $adminId, ?string $reason, DateTimeImmutable $at, DateTimeImmutable $today): Job
    {
        $approved = $job->approve($adminId, $at);

        if ($approved->content->deadline === null || $approved->content->deadline < $today) {
            throw new StateConflict('The deadline of this job has passed.', 'job-deadline-passed');
        }

        return $approved;
    }
}
