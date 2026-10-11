<?php

namespace Src\Identity\Application\TwoFactor;

use DateTimeImmutable;

/** The short-lived, single-use challenges that sit between the password step and the code step of a psychologist's sign-in. */
interface UserTwoFactorChallenges
{
    /** Stores a new challenge and drops the unused ones the user still had. */
    public function issue(int $userId, string $tokenHash, DateTimeImmutable $expiresAt, DateTimeImmutable $now): void;

    public function findUsable(string $tokenHash, DateTimeImmutable $now): ?UserTwoFactorChallenge;

    public function consume(int $challengeId, DateTimeImmutable $now): void;

    public function deleteForUser(int $userId): void;

    /** Deletes the challenges that expired or were used; returns how many. */
    public function purge(DateTimeImmutable $now): int;
}
