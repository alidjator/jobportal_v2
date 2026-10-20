<?php

namespace Src\Identity\Domain;

use DateTimeImmutable;

/**
 * The pending self-service change of an account's sign-in email: the new address, the hash of the code sent there and, for an account without a password, the hash of the code sent to
 * the current address. Usable for 15 minutes and for 5 wrong attempts, like any verification code. Only the hashes are kept.
 */
final class EmailChange
{
    public function __construct(
        public readonly int $userId,
        public readonly string $newEmail,
        public readonly string $newCodeHash,
        public readonly ?string $oldCodeHash,
        private int $attempts,
        public readonly DateTimeImmutable $expiresAt,
        public readonly DateTimeImmutable $createdAt,
    ) {}

    public static function request(int $userId, string $newEmail, string $newCodeHash, ?string $oldCodeHash, DateTimeImmutable $now): self
    {
        return new self($userId, $newEmail, $newCodeHash, $oldCodeHash, 0, $now->modify(VerificationCode::LIFETIME), $now);
    }

    public function attempts(): int
    {
        return $this->attempts;
    }

    public function isUsable(DateTimeImmutable $now): bool
    {
        return $this->expiresAt > $now && $this->attempts < VerificationCode::MAX_ATTEMPTS;
    }

    public function recordFailedAttempt(): void
    {
        $this->attempts++;
    }
}
