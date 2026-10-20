<?php

namespace Src\Job\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Job\Application\JobNotificationType;
use Src\Job\Domain\Events\MemberAssignedToJobs;
use Src\Shared\Application\Notifications\InAppNotifier;
use Src\Shared\Infrastructure\Queue\QueueName;

/**
 * Tells a member who was added to several jobs at once with one notice, instead of one per job. The admin who did it is not told when it was themself.
 * Runs on the `default` queue, retried 3 times with a growing delay.
 */
final class NotifyMemberOfBulkAssignment implements ShouldQueue
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

    public function handle(MemberAssignedToJobs $event): void
    {
        if ($event->userId === $event->assignedByUserId) {
            return;
        }

        $this->notifier->send([$event->userId], JobNotificationType::ASSIGNED_MANY, ['count' => $event->count]);
    }
}
