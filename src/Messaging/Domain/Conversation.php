<?php

namespace Src\Messaging\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * The thread between one company and one job seeker. The company can close it (the job seeker can then not write; neither side can until it is reopened)
 * and reopen it. The job seeker can block it: the company can then not write until the job seeker unblocks, and the job seeker still reads. Immutable: a change returns a new instance.
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
        public ?DateTimeImmutable $blockedBySeekerAt = null,
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

    public function isBlocked(): bool
    {
        return $this->blockedBySeekerAt !== null;
    }

    /** @throws StateConflict `conversation-blocked` */
    public function assertNotBlockedForCompany(): void
    {
        if ($this->isBlocked()) {
            throw new StateConflict('The candidate closed this conversation. You cannot send messages in it.', 'conversation-blocked');
        }
    }

    public function blockBySeeker(DateTimeImmutable $at): self
    {
        return $this->with(blockedBySeekerAt: $this->blockedBySeekerAt ?? $at);
    }

    public function unblock(): self
    {
        return $this->with(blockedBySeekerAt: null);
    }

    public function close(int $byUserId, DateTimeImmutable $at): self
    {
        return $this->with(closedAt: $this->closedAt ?? $at, closedByUserId: $this->closedByUserId ?? $byUserId);
    }

    public function reopen(): self
    {
        return $this->with(closedAt: null, closedByUserId: null);
    }

    public function withLastMessageAt(DateTimeImmutable $at): self
    {
        return $this->with(lastMessageAt: $at);
    }

    /** A copy with the given fields replaced (a nullable field is replaced by naming it, even with null). */
    private function with(mixed ...$changes): self
    {
        $values = [
            'closedAt' => $this->closedAt,
            'closedByUserId' => $this->closedByUserId,
            'lastMessageAt' => $this->lastMessageAt,
            'blockedBySeekerAt' => $this->blockedBySeekerAt,
            ...$changes,
        ];

        return new self(
            $this->companyId, $this->jobSeekerUserId, $this->startedByUserId, $this->createdAt,
            $values['closedAt'], $values['closedByUserId'], $values['lastMessageAt'], $this->id, $values['blockedBySeekerAt'],
        );
    }
}
