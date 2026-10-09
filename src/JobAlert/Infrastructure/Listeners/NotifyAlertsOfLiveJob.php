<?php

namespace Src\JobAlert\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Job\Domain\Events\JobDecided;
use Src\Job\Domain\Events\JobStatusChanged;
use Src\JobAlert\Application\NotifyOfLiveJob;
use Src\Shared\Infrastructure\Queue\QueueName;

/**
 * Runs when a job goes live: published by the company, or approved by an admin. A reopened job, a restored one and a job that was sent back to review are not
 * "new", and an alert never tells about the same job twice anyway. Runs on the `bulk` queue, retried 3 times with a growing delay, after the change was saved.
 */
final class NotifyAlertsOfLiveJob implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly NotifyOfLiveJob $notify) {}

    public function viaQueue(): string
    {
        return QueueName::Bulk->value;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(JobStatusChanged|JobDecided $event): void
    {
        $live = $event instanceof JobStatusChanged
            ? $event->change === JobStatusChanged::PUBLISHED && $event->to === 'active'
            : $event->decision === JobDecided::APPROVED && $event->to === 'active';

        if ($live) {
            $this->notify->execute($event->jobId);
        }
    }
}
