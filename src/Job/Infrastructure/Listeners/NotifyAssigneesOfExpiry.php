<?php

namespace Src\Job\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Job\Application\JobNotificationType;
use Src\Job\Domain\Events\JobStatusChanged;
use Src\Job\Domain\JobAssignmentRepository;
use Src\Shared\Application\Notifications\InAppNotifier;
use Src\Shared\Infrastructure\Queue\QueueName;

/**
 * Tells the members assigned to a job that it expired (the deadline passed). Other status changes are the member's own doing and tell nobody. Runs on the
 * `default` queue, retried 3 times with a growing delay, after the change was saved: a failure here never undoes it.
 */
final class NotifyAssigneesOfExpiry implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(
        private readonly JobAssignmentRepository $assignments,
        private readonly InAppNotifier $notifier,
    ) {}

    public function viaQueue(): string
    {
        return QueueName::Default->value;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(JobStatusChanged $event): void
    {
        if ($event->change !== JobStatusChanged::EXPIRED) {
            return;
        }

        $this->notifier->send($this->assignments->userIds($event->jobId), JobNotificationType::EXPIRED, ['job_id' => $event->jobId]);
    }
}
