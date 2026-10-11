<?php

namespace Src\Assessment\Domain;

use DateTimeImmutable;

/**
 * An invitation to become a psychologist of a provider, valid for 7 days and usable once. It holds what the admin entered about the
 * psychologist; the psychologist record is created when it is accepted. Only the hash of its token is stored; the plain token travels in the email.
 */
final class PsychologistInvitation
{
    public const string VALIDITY = '+7 days';

    public function __construct(
        public readonly ?int $id,
        public readonly int $providerId,
        public readonly string $email,
        public readonly string $name,
        public readonly string $strNumber,
        public readonly DateTimeImmutable $strValidUntil,
        public readonly string $silpNumber,
        public readonly DateTimeImmutable $silpValidUntil,
        public readonly string $tokenHash,
        public readonly int $invitedByAdminId,
        public readonly DateTimeImmutable $sentAt,
        public readonly DateTimeImmutable $expiresAt,
        private ?DateTimeImmutable $acceptedAt,
        private ?DateTimeImmutable $revokedAt,
    ) {}

    public static function issue(
        int $providerId,
        string $email,
        string $name,
        string $strNumber,
        DateTimeImmutable $strValidUntil,
        string $silpNumber,
        DateTimeImmutable $silpValidUntil,
        string $tokenHash,
        int $invitedByAdminId,
        DateTimeImmutable $now,
    ): self {
        return new self(
            null, $providerId, $email, $name, $strNumber, $strValidUntil, $silpNumber, $silpValidUntil,
            $tokenHash, $invitedByAdminId, $now, $now->modify(self::VALIDITY), null, null,
        );
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

    /** A pending invitation can no longer be accepted. Revoking one that was accepted changes nothing. */
    public function revoke(DateTimeImmutable $now): void
    {
        if ($this->acceptedAt === null) {
            $this->revokedAt ??= $now;
        }
    }
}
