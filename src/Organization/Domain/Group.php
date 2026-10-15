<?php

namespace Src\Organization\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * One ERP customer: its legal entities, structure and group roles belong to it and to no other group. A suspended group keeps all its data; its internal tenants
 * are suspended with it and its group roles are refused.
 */
final readonly class Group
{
    public function __construct(
        public ?int $id,
        public string $name,
        public GroupStatus $status,
        public ?string $statusReason,
        public ?DateTimeImmutable $suspendedAt,
        public ?DateTimeImmutable $createdAt = null,
    ) {}

    public static function create(string $name): self
    {
        return new self(null, $name, GroupStatus::Active, null, null);
    }

    public function isActive(): bool
    {
        return $this->status === GroupStatus::Active;
    }

    /** @throws StateConflict `group-already-suspended` */
    public function suspend(string $reason, DateTimeImmutable $at): self
    {
        if (! $this->isActive()) {
            throw new StateConflict('The group is already suspended.', 'group-already-suspended');
        }

        return new self($this->id, $this->name, GroupStatus::Suspended, $reason, $at, $this->createdAt);
    }

    /** @throws StateConflict `group-not-suspended` */
    public function restore(): self
    {
        if ($this->isActive()) {
            throw new StateConflict('The group is not suspended.', 'group-not-suspended');
        }

        return new self($this->id, $this->name, GroupStatus::Active, null, null, $this->createdAt);
    }
}
