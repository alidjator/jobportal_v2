<?php

namespace Src\Approval\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A subject going through approval: the steps that applied to it when it was submitted (the flow is copied then, so editing a flow changes nothing here), each with its slots. Steps
 * run one after the other; the approval ends approved when the last step is, and rejected, returned or cancelled whenever that happens first.
 */
final class ApprovalInstance
{
    /**
     * @param  array<string, int|bool|string>  $facts
     * @param  list<InstanceStep>  $steps
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $subjectType,
        public readonly int $subjectId,
        public readonly int $companyId,
        public readonly int $groupId,
        public readonly int $requesterId,
        public readonly string $title,
        public readonly array $facts,
        public InstanceStatus $status,
        public array $steps,
        public readonly DateTimeImmutable $createdAt,
        public ?string $outcomeReason = null,
        public ?int $outcomeByUserId = null,
        public ?DateTimeImmutable $completedAt = null,
    ) {}

    public function isPending(): bool
    {
        return $this->status === InstanceStatus::Pending;
    }

    public function currentStep(): ?InstanceStep
    {
        foreach ($this->steps as $step) {
            if ($step->status === StepStatus::Pending) {
                return $step;
            }
        }

        return null;
    }

    /** Notes that the approvers of the step being decided were reminded. */
    public function markReminded(DateTimeImmutable $now): void
    {
        if ($this->isPending() && $this->currentStep() !== null) {
            $this->currentStep()->remindedAt = $now;
        }
    }

    /** Starts the approval: the first step becomes the one being decided, or, with no step, the approval is approved at once. */
    public function start(DateTimeImmutable $now): void
    {
        $this->advance($now);
    }

    /**
     * An approver fills a slot of the current step. Returns whether that approved the whole approval.
     *
     * @throws StateConflict `approval-not-pending` or `approval-slot-not-open`
     */
    public function approve(int $slotId, int $byUserId, ?int $onBehalfOfUserId, DateTimeImmutable $now): bool
    {
        $step = $this->requireCurrentStep();
        $slot = $this->slotOf($step, $slotId);

        if ($slot->status !== SlotStatus::Open) {
            throw new StateConflict('This approval place is not open.', 'approval-slot-not-open');
        }

        $slot->status = SlotStatus::Approved;
        $slot->decidedByUserId = $byUserId;
        $slot->onBehalfOfUserId = $onBehalfOfUserId;
        $slot->decidedAt = $now;

        if ($step->isSatisfied()) {
            $step->status = StepStatus::Approved;
            $step->completedAt = $now;
            $this->advance($now, $byUserId);
        }

        return $this->status === InstanceStatus::Approved;
    }

    /** @throws StateConflict `approval-not-pending` */
    public function reject(string $reason, int $byUserId, DateTimeImmutable $now): void
    {
        $this->end(InstanceStatus::Rejected, $reason, $byUserId, $now);
    }

    /** @throws StateConflict `approval-not-pending` */
    public function returnForRevision(string $reason, int $byUserId, DateTimeImmutable $now): void
    {
        $this->end(InstanceStatus::Returned, $reason, $byUserId, $now);
    }

    /** @throws StateConflict `approval-not-pending` */
    public function cancel(DateTimeImmutable $now): void
    {
        $this->end(InstanceStatus::Cancelled, null, null, $now);
    }

    /**
     * The account left: its open slots wait for a replacement. Returns how many slots that touched.
     */
    public function vacate(int $userId): int
    {
        $count = 0;

        if (! $this->isPending()) {
            return 0;
        }

        foreach ($this->steps as $step) {
            if ($step->status === StepStatus::Approved) {
                continue;
            }

            foreach ($step->slots as $slot) {
                if ($slot->kind === SlotKind::User && $slot->userId === $userId && $slot->status === SlotStatus::Open) {
                    $slot->status = SlotStatus::Vacant;
                    $count++;
                }
            }
        }

        return $count;
    }

    /** @throws StateConflict `approval-not-pending` or `approval-slot-not-vacant` */
    public function replace(int $slotId, int $userId): void
    {
        if (! $this->isPending()) {
            throw new StateConflict('This approval has ended.', 'approval-not-pending');
        }

        foreach ($this->steps as $step) {
            foreach ($step->slots as $slot) {
                if ($slot->id === $slotId) {
                    if ($slot->status !== SlotStatus::Vacant || $step->status === StepStatus::Approved) {
                        throw new StateConflict('This approval place is not vacant.', 'approval-slot-not-vacant');
                    }

                    $slot->userId = $userId;
                    $slot->status = SlotStatus::Open;

                    return;
                }
            }
        }

        throw new StateConflict('This approval place does not exist.', 'approval-slot-not-vacant');
    }

    /** @throws StateConflict `approval-not-pending` */
    private function requireCurrentStep(): InstanceStep
    {
        $step = $this->isPending() ? $this->currentStep() : null;

        return $step ?? throw new StateConflict('This approval is not waiting for a decision.', 'approval-not-pending');
    }

    /** @throws StateConflict `approval-slot-not-open` */
    private function slotOf(InstanceStep $step, int $slotId): Slot
    {
        foreach ($step->slots as $slot) {
            if ($slot->id === $slotId) {
                return $slot;
            }
        }

        throw new StateConflict('This approval place is not part of the step being decided.', 'approval-slot-not-open');
    }

    private function advance(DateTimeImmutable $now, ?int $byUserId = null): void
    {
        foreach ($this->steps as $step) {
            if ($step->status === StepStatus::Waiting) {
                $step->status = StepStatus::Pending;
                $step->activatedAt = $now;

                return;
            }
        }

        $this->status = InstanceStatus::Approved;
        $this->outcomeByUserId = $byUserId;
        $this->completedAt = $now;
    }

    /** @throws StateConflict `approval-not-pending` */
    private function end(InstanceStatus $status, ?string $reason, ?int $byUserId, DateTimeImmutable $now): void
    {
        if (! $this->isPending()) {
            throw new StateConflict('This approval has ended.', 'approval-not-pending');
        }

        $this->status = $status;
        $this->outcomeReason = $reason;
        $this->outcomeByUserId = $byUserId;
        $this->completedAt = $now;
    }
}
