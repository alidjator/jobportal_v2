<?php

namespace Src\Job\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Job\Application\JobNotificationType;
use Src\Job\Domain\Events\JobDecided;
use Src\Job\Domain\JobAssignmentRepository;
use Src\Shared\Application\Notifications\InAppNotifier;
use Src\Shared\Infrastructure\Queue\QueueName;

/**
 * Tells the members assigned to a job what an admin decided: approved, rejected (with the reason), taken down (with the reason) or restored. The data holds
 * the job id and the reason only, never the admin's name. Runs on the `default` queue, retried 3 times with a growing delay, after the decision was saved:
 * a failure here never undoes it.
 */
final class NotifyAssigneesOfDecision implements ShouldQueue
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

    public function handle(JobDecided $event): void
    {
        $data = ['job_id' => $event->jobId];

        $type = match ($event->decision) {
            JobDecided::APPROVED => JobNotificationType::APPROVED,
            JobDecided::RESTORED => JobNotificationType::RESTORED,
            JobDecided::REJECTED => JobNotificationType::REJECTED,
            JobDecided::TAKEN_DOWN => JobNotificationType::TAKEN_DOWN,
            default => throw new \LogicException("Unknown job decision {$event->decision}."),
        };

        if ($event->decision === JobDecided::REJECTED || $event->decision === JobDecided::TAKEN_DOWN) {
            $data['reason'] = $event->reason;
        }

        $this->notifier->send($this->assignments->userIds($event->jobId), $type, $data);
    }
}
