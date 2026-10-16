<?php

namespace Src\Approval\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Approval\Application\Instance\ApprovalNotifier;
use Src\Approval\Domain\ApprovalInstanceRepository;
use Src\Approval\Domain\Events\ApprovalChanged;
use Src\Shared\Infrastructure\Queue\QueueName;

/**
 * Tells the people in an approval about a change to it: the approvers when it reaches their step (a submission, or the step before was approved), the requester of the outcome and of an
 * approver who left. A step that is only partly approved (mode `all`) tells nobody. Runs on the `default` queue; the notifications are written from the state of the approval
 * when the job runs.
 */
final class NotifyApprovalParticipants implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly ApprovalInstanceRepository $instances, private readonly ApprovalNotifier $notifier) {}

    public function viaQueue(): string
    {
        return QueueName::Default->value;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(ApprovalChanged $event): void
    {
        $instance = $event->approvalId === null ? null : $this->instances->find($event->approvalId);

        if ($instance === null) {
            return;
        }

        match ($event->change) {
            'submitted' => $instance->isPending() ? $this->notifier->approvers($instance, false) : null,
            'step_approved' => $instance->isPending() && $instance->currentStep()?->position !== ($event->detail['step'] ?? null) ? $this->notifier->approvers($instance, false) : null,
            'granted', 'rejected', 'returned', 'approver_vacated' => $this->notifier->requester($instance, $event->change),
            default => null,
        };
    }
}
