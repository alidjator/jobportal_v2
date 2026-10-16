<?php

namespace Src\Organization\Domain;

/** The identity of a position (a job title, not a seat) of a legal entity: a stable id and code. Its data lives in the dated `PositionVersion`s. */
final readonly class Position
{
    public function __construct(
        public ?int $id,
        public int $legalEntityId,
        public string $code,
        public ItemStatus $status = ItemStatus::Active,
    ) {}

    public function isActive(): bool
    {
        return $this->status === ItemStatus::Active;
    }

    public function withStatus(ItemStatus $status): self
    {
        return new self($this->id, $this->legalEntityId, $this->code, $status);
    }
}
