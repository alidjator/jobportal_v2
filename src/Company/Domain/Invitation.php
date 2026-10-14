<?php

namespace Src\Company\Domain;

use DateTimeImmutable;

/** An invitation to join a company, valid for 7 days and usable once. Only the hash of its token is stored; the plain token travels in the email. */
final class Invitation
{
    public const string VALIDITY = '+7 days';

    /** An invitation that ended (expired, revoked or accepted) is kept this long, listed while expired, then deleted: it holds an email address. */
    public const string KEEP_AFTER_END = '30 days';

    /** @param  ?list<string>  $permissions */
    public function __construct(
        public readonly ?int $id,
        public readonly int $companyId,
        public readonly string $email,
        public readonly CompanyRole $role,
        public readonly ?string $name,
        public readonly ?array $permissions,
        public readonly string $tokenHash,
        public readonly ?int $invitedByUserId,
        public readonly ?int $invitedByAdminId,
        public readonly DateTimeImmutable $sentAt,
        public readonly DateTimeImmutable $expiresAt,
        private ?DateTimeImmutable $acceptedAt,
        private ?DateTimeImmutable $revokedAt,
    ) {}

    /**
     * @param  ?int  $invitedByUserId  the company admin who invites, or null when a platform admin does
     * @param  ?string  $name  the name the inviter typed, which the invitee may change when accepting
     * @param  ?list<string>  $permissions  the permissions checked for a member, applied when the invitation is accepted; null for a company admin
     */
    public static function issue(
        int $companyId,
        string $email,
        CompanyRole $role,
        ?string $name,
        ?array $permissions,
        string $tokenHash,
        ?int $invitedByUserId,
        ?int $invitedByAdminId,
        DateTimeImmutable $now,
    ): self {
        return new self(null, $companyId, $email, $role, $name, $permissions, $tokenHash, $invitedByUserId, $invitedByAdminId, $now, $now->modify(self::VALIDITY), null, null);
    }

    public function acceptedAt(): ?DateTimeImmutable
    {
        return $this->acceptedAt;
    }

    public function revokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }

    /** Not accepted, not revoked, and past its expiry: still listed, and it can be resent. */
    public function isExpired(DateTimeImmutable $now): bool
    {
        return $this->acceptedAt === null && $this->revokedAt === null && $this->expiresAt <= $now;
    }

    /** The same invitation with another role, name and permissions: the link stays the same. */
    public function edited(CompanyRole $role, ?string $name, ?array $permissions): self
    {
        return new self(
            $this->id, $this->companyId, $this->email, $role, $name, $permissions, $this->tokenHash, $this->invitedByUserId, $this->invitedByAdminId,
            $this->sentAt, $this->expiresAt, $this->acceptedAt, $this->revokedAt,
        );
    }

    /** The same invitation with a new token and 7 more days from `$now`: the old link stops working. */
    public function resent(string $tokenHash, DateTimeImmutable $now): self
    {
        return new self(
            $this->id, $this->companyId, $this->email, $this->role, $this->name, $this->permissions, $tokenHash, $this->invitedByUserId, $this->invitedByAdminId,
            $now, $now->modify(self::VALIDITY), $this->acceptedAt, $this->revokedAt,
        );
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

    /** A pending invitation can no longer be accepted. Revoking one that is not pending changes nothing. */
    public function revoke(DateTimeImmutable $now): void
    {
        if ($this->acceptedAt === null) {
            $this->revokedAt ??= $now;
        }
    }
}
