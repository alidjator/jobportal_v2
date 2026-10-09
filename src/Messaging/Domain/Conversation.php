<?php

namespace Src\Messaging\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * The thread between one company and one job seeker. The company can close it (the job seeker can then not write; neither side can until it is reopened)
 * and reopen it. Immutable: a change returns a new instance.
 */
final readonly class Conversation
{
    public function __construct(
        public int $companyId,
        public int $jobSeekerUserId,
        public int $startedByUserId,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $closedAt = null,
        public ?int $closedByUserId = null,
        public ?DateTimeImmutable $lastMessageAt = null,
        public ?int $id = null,
    ) {}

    public function isClosed(): bool
    {
        return $this->closedAt !== null;
    }

    /** @throws StateConflict `conversation-closed` */
    public function assertOpen(): void
    {
        if ($this->isClosed()) {
            throw new StateConflict('This conversation is closed. The company must reopen it first.', 'conversation-closed');
        }
    }

    public function close(int $byUserId, DateTimeImmutable $at): self
    {
        return new self($this->companyId, $this->jobSeekerUserId, $this->startedByUserId, $this->createdAt, $this->closedAt ?? $at, $this->closedByUserId ?? $byUserId, $this->lastMessageAt, $this->id);
    }

    public function reopen(): self
    {
        return new self($this->companyId, $this->jobSeekerUserId, $this->startedByUserId, $this->createdAt, null, null, $this->lastMessageAt, $this->id);
    }

    public function withLastMessageAt(DateTimeImmutable $at): self
    {
        return new self($this->companyId, $this->jobSeekerUserId, $this->startedByUserId, $this->createdAt, $this->closedAt, $this->closedByUserId, $at, $this->id);
    }
}
