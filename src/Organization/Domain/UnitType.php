<?php

namespace Src\Organization\Domain;

/** A unit type of the group (directorate, division, department, ...): the level order only sorts and labels, it does not constrain the tree. */
final readonly class UnitType
{
    public function __construct(
        public ?int $id,
        public int $groupId,
        public string $code,
        public string $name,
        public int $levelOrder,
        public ItemStatus $status = ItemStatus::Active,
    ) {}

    public function isActive(): bool
    {
        return $this->status === ItemStatus::Active;
    }

    public function rename(string $name, int $levelOrder): self
    {
        return new self($this->id, $this->groupId, $this->code, $name, $levelOrder, $this->status);
    }

    public function withStatus(ItemStatus $status): self
    {
        return new self($this->id, $this->groupId, $this->code, $this->name, $this->levelOrder, $status);
    }
}
