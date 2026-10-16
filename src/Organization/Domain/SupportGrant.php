<?php

namespace Src\Organization\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Exceptions\StateConflict;

/** Temporary read-only support access a group admin granted to a named platform admin: it ends at `expiresAt` or when a group admin revokes it. */
final readonly class SupportGrant
{
    public function __construct(
        public ?int $id,
        public int $groupId,
        public int $adminId,
        public int $grantedByUserId,
        public DateTimeImmutable $expiresAt,
        public ?DateTimeImmutable $revokedAt,
        public ?int $revokedByUserId,
        public DateTimeImmutable $createdAt,
    ) {}

    public function isActive(DateTimeImmutable $now): bool
    {
        return $this->revokedAt === null && $this->expiresAt > $now;
    }

    /** @throws StateConflict `support-grant-not-active` */
    public function revoke(int $userId, DateTimeImmutable $now): self
    {
        if (! $this->isActive($now)) {
            throw new StateConflict('The support access has already ended.', 'support-grant-not-active');
        }

        return new self($this->id, $this->groupId, $this->adminId, $this->grantedByUserId, $this->expiresAt, $now, $userId, $this->createdAt);
    }
}
