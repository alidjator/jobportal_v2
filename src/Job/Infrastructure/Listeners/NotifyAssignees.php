<?php

namespace Src\Job\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Job\Application\JobNotificationType;
use Src\Job\Domain\Events\JobAssigneesChanged;
use Src\Shared\Application\Notifications\InAppNotifier;
use Src\Shared\Infrastructure\Queue\QueueName;

/**
 * Tells the members who were assigned to a job (and those who were taken off it) after the change was saved: a failure here never undoes it. The
 * member who made the change is not told about it. A member taken off by the system (they left the company) is not told either: their account is closed.
 * Runs on the `default` queue, retried 3 times with a growing delay.
 */
final class NotifyAssignees implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly InAppNotifier $notifier) {}

    public function viaQueue(): string
    {
        return QueueName::Default->value;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(JobAssigneesChanged $event): void
    {
        $others = static fn (array $userIds): array => array_values(array_filter($userIds, static fn (int $id): bool => $id !== $event->changedByUserId));

        $this->notifier->send($others($event->added), JobNotificationType::ASSIGNED, ['job_id' => $event->jobId]);

        if ($event->changedByUserId !== null) {
            $this->notifier->send($others($event->removed), JobNotificationType::UNASSIGNED, ['job_id' => $event->jobId]);
        }
    }
}
