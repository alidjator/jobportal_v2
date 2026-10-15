<?php

namespace Src\Job\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Job\Application\JobNotificationRecipients;
use Src\Job\Application\JobNotificationType;
use Src\Job\Domain\Events\JobHeld;
use Src\Shared\Application\Notifications\InAppNotifier;
use Src\Shared\Infrastructure\Queue\QueueName;

/**
 * Tells the members assigned to a job it is under review by an admin. The data holds the job id only: never who reported it or why. Runs on the `default` queue,
 * retried 3 times with a growing delay, after the hold was saved.
 */
final class NotifyAssigneesOfHold implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(
        private readonly JobNotificationRecipients $recipients,
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

    public function handle(JobHeld $event): void
    {
        $this->notifier->send($this->recipients->ofJob($event->jobId), JobNotificationType::HELD, ['job_id' => $event->jobId]);
    }
}
