<?php

namespace Src\Organization\Domain;

/** A grade of the group (shared by its legal entities): a code, a name and a rank. No salary range (payroll comes later). */
final readonly class Grade
{
    public function __construct(
        public ?int $id,
        public int $groupId,
        public string $code,
        public string $name,
        public int $rank,
        public ItemStatus $status = ItemStatus::Active,
    ) {}

    public function isActive(): bool
    {
        return $this->status === ItemStatus::Active;
    }

    public function rename(string $name, int $rank): self
    {
        return new self($this->id, $this->groupId, $this->code, $name, $rank, $this->status);
    }

    public function withStatus(ItemStatus $status): self
    {
        return new self($this->id, $this->groupId, $this->code, $this->name, $this->rank, $status);
    }
}
