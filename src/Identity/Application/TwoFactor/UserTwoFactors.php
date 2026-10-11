<?php

namespace Src\Identity\Application\TwoFactor;

use DateTimeImmutable;

/** Where the second factor of a public user (a psychologist) is kept. The secret is stored encrypted. */
interface UserTwoFactors
{
    public function find(int $userId): ?UserTwoFactor;

    /** Stores a fresh, unconfirmed secret; a pending secret is replaced. */
    public function begin(int $userId, string $secret, DateTimeImmutable $now): void;

    /** Confirms the factor at its first right code and remembers the accepted time step. */
    public function confirm(int $userId, int $step, DateTimeImmutable $now): void;

    /** Remembers the accepted time step so the same code cannot be used again. */
    public function recordStep(int $userId, int $step, DateTimeImmutable $now): void;

    /** Removes the factor, its recovery codes and its challenges (a lost device). */
    public function reset(int $userId): void;
}
