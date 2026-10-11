<?php

namespace Src\Identity\Application\TwoFactor;

use DateTimeImmutable;

/** The TOTP second factor of a public user who must have one. It counts only once it is confirmed. */
final readonly class UserTwoFactor
{
    public function __construct(
        public string $secret,
        public ?DateTimeImmutable $confirmedAt,
        public ?int $lastStep,
    ) {}

    public function isConfirmed(): bool
    {
        return $this->confirmedAt !== null;
    }
}
