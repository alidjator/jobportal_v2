<?php

namespace Src\Shared\Application\Accounts;

use DateTimeImmutable;

/** A sign-in that is not finished: the account must pass the second factor first. The enrolment data is present only when no factor is confirmed yet. */
final readonly class TwoFactorChallengeRequired
{
    public function __construct(
        public string $token,
        public DateTimeImmutable $expiresAt,
        public ?string $secret = null,
        public ?string $otpauthUrl = null,
        public ?string $qrCode = null,
    ) {}

    public function isEnrolment(): bool
    {
        return $this->secret !== null;
    }
}
