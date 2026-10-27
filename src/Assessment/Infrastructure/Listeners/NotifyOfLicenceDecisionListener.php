<?php

namespace Src\Assessment\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Assessment\Application\Delivery\AssessmentNotificationType;
use Src\Assessment\Domain\Events\LicenceDecided;
use Src\Shared\Application\Notifications\InAppNotifier;
use Src\Shared\Infrastructure\Queue\QueueName;

/** The in-app notice to the psychologist that their licence submission was approved or rejected. It runs on the `default` queue, retried 3 times with a growing delay. */
final class NotifyOfLicenceDecisionListener implements ShouldQueue
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

    public function handle(LicenceDecided $event): void
    {
        $this->notifier->send(
            [$event->userId],
            $event->approved ? AssessmentNotificationType::LICENCE_APPROVED : AssessmentNotificationType::LICENCE_REJECTED,
            ['submission_id' => $event->submissionId, 'kind' => $event->kind],
        );
    }
}
