<?php

namespace Src\Approval\Domain;

use DateTimeImmutable;

/** A step of a running approval: its slots, and how many of them must approve (`any` one, or `all`). */
final class InstanceStep
{
    /** @param  list<Slot>  $slots */
    public function __construct(
        public readonly ?int $id,
        public readonly int $position,
        public readonly StepMode $mode,
        public StepStatus $status,
        public array $slots,
        public ?DateTimeImmutable $activatedAt = null,
        public ?DateTimeImmutable $completedAt = null,
    ) {}

    public function isSatisfied(): bool
    {
        $approved = array_filter($this->slots, static fn (Slot $slot): bool => $slot->status === SlotStatus::Approved);

        return $this->mode === StepMode::Any ? $approved !== [] : count($approved) === count($this->slots);
    }
}
