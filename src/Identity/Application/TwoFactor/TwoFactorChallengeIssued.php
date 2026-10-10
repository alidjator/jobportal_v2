<?php

namespace Src\Identity\Application\TwoFactor;

use DateTimeImmutable;

/** The answer to a right password: a challenge to finish with a code, plus the enrolment data when no second factor is confirmed yet. */
final readonly class TwoFactorChallengeIssued
{
    public function __construct(
        public string $token,
        public DateTimeImmutable $expiresAt,
        public ?TwoFactorEnrolment $enrolment,
    ) {}
}
