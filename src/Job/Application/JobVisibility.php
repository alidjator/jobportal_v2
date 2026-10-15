<?php

namespace Src\Job\Application;

use Src\Job\Domain\Job;
use Src\Job\Domain\JobStatus;
use Src\Shared\Application\Clock;

/**
 * Whether a job is live for the public: `active`, not on a report hold, and its deadline day not over in Jakarta time (checked here so nothing waits for the hourly expiry).
 * Whether its company is visible is asked of Company separately.
 */
final readonly class JobVisibility
{
    public function __construct(private Clock $clock) {}

    public function isLive(Job $job): bool
    {
        $deadline = $job->content->deadline;

        return $job->status === JobStatus::Active && ! $job->isOnHold() && $deadline !== null && $deadline >= JakartaDate::today($this->clock->now());
    }
}
