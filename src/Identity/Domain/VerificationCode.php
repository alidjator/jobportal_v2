<?php

namespace Src\Identity\Domain;

use DateTimeImmutable;

/**
 * A single-use code sent to an account's email address. Only the hash is stored. A code is usable for 15 minutes and
 * stops being usable after 5 wrong attempts, so the 6-digit space cannot be searched online.
 */
final class VerificationCode
{
    public const string LIFETIME = '+15 minutes';

    public const int MAX_ATTEMPTS = 5;

    public function __construct(
        public readonly ?int $id,
        public readonly AccountScope $subjectType,
        public readonly int $subjectId,
        public readonly VerificationPurpose $purpose,
        public readonly string $codeHash,
        public readonly DateTimeImmutable $expiresAt,
        private ?DateTimeImmutable $consumedAt,
        private int $attempts,
        public readonly DateTimeImmutable $createdAt,
    ) {}

    public static function issue(AccountScope $subjectType, int $subjectId, VerificationPurpose $purpose, string $codeHash, DateTimeImmutable $now): self
    {
        return new self(null, $subjectType, $subjectId, $purpose, $codeHash, $now->modify(self::LIFETIME), null, 0, $now);
    }

    public function attempts(): int
    {
        return $this->attempts;
    }

    public function consumedAt(): ?DateTimeImmutable
    {
        return $this->consumedAt;
    }

    public function isUsable(DateTimeImmutable $now): bool
    {
        return $this->consumedAt === null
            && $this->expiresAt > $now
            && $this->attempts < self::MAX_ATTEMPTS;
    }

    public function recordFailedAttempt(): void
    {
        $this->attempts++;
    }

    /** Marks the code as used (or replaced by a newer one). It can never be accepted again. */
    public function consume(DateTimeImmutable $now): void
    {
        $this->consumedAt ??= $now;
    }
}
