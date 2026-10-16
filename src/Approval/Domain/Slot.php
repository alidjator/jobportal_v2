<?php

namespace Src\Approval\Domain;

use DateTimeImmutable;

/** One place to fill in a step: a named account (`userId`, null when the relative role named nobody), or any holder of a group `permission`. */
final class Slot
{
    public function __construct(
        public readonly ?int $id,
        public readonly SlotKind $kind,
        public ?int $userId,
        public readonly ?string $permission,
        public SlotStatus $status,
        public ?int $decidedByUserId = null,
        public ?int $onBehalfOfUserId = null,
        public ?DateTimeImmutable $decidedAt = null,
    ) {}

    public static function user(?int $userId): self
    {
        return new self(null, SlotKind::User, $userId, null, $userId === null ? SlotStatus::Vacant : SlotStatus::Open);
    }

    public static function role(string $permission): self
    {
        return new self(null, SlotKind::Role, null, $permission, SlotStatus::Open);
    }
}
