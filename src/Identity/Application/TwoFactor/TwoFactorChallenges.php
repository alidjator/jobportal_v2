<?php

namespace Src\Identity\Application\TwoFactor;

use DateTimeImmutable;

/** The short-lived, single-use challenges that sit between the password step and the code step of an admin sign-in. */
interface TwoFactorChallenges
{
    /** Stores a new challenge and drops the unused ones the admin still had. */
    public function issue(int $adminId, string $tokenHash, DateTimeImmutable $expiresAt, DateTimeImmutable $now): void;

    public function findUsable(string $tokenHash, DateTimeImmutable $now): ?TwoFactorChallenge;

    public function consume(int $challengeId, DateTimeImmutable $now): void;

    public function deleteForAdmin(int $adminId): void;

    /** Deletes the challenges that expired or were used; returns how many. */
    public function purge(DateTimeImmutable $now): int;
}
