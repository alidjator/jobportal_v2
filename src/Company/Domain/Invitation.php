<?php

namespace Src\Company\Domain;

use DateTimeImmutable;

/** An invitation to join a company, valid for 7 days and usable once. Only the hash of its token is stored; the plain token travels in the email. */
final class Invitation
{
    public const string VALIDITY = '+7 days';

    public function __construct(
        public readonly ?int $id,
        public readonly int $companyId,
        public readonly string $email,
        public readonly CompanyRole $role,
        public readonly string $tokenHash,
        public readonly ?int $invitedByUserId,
        public readonly ?int $invitedByAdminId,
        public readonly DateTimeImmutable $sentAt,
        public readonly DateTimeImmutable $expiresAt,
        private ?DateTimeImmutable $acceptedAt,
        private ?DateTimeImmutable $revokedAt,
    ) {}

    /** @param  ?int  $invitedByUserId  the company admin who invites, or null when a platform admin does */
    public static function issue(
        int $companyId,
        string $email,
        CompanyRole $role,
        string $tokenHash,
        ?int $invitedByUserId,
        ?int $invitedByAdminId,
        DateTimeImmutable $now,
    ): self {
        return new self(null, $companyId, $email, $role, $tokenHash, $invitedByUserId, $invitedByAdminId, $now, $now->modify(self::VALIDITY), null, null);
    }

    public function acceptedAt(): ?DateTimeImmutable
    {
        return $this->acceptedAt;
    }

    public function revokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }

    /** Pending means it can still be accepted: not accepted, not revoked and not expired. */
    public function isPending(DateTimeImmutable $now): bool
    {
        return $this->acceptedAt === null && $this->revokedAt === null && $this->expiresAt > $now;
    }

    public function accept(DateTimeImmutable $now): void
    {
        $this->acceptedAt = $now;
    }
}
