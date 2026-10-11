<?php

namespace Src\Identity\Application\TwoFactor;

use DateTimeImmutable;

/** The one-time recovery codes of a public user with a second factor, stored only as hashes. */
interface UserRecoveryCodes
{
    /**
     * Replaces every code of the user with the new ones.
     *
     * @param  list<string>  $hashes
     */
    public function replace(int $userId, array $hashes, DateTimeImmutable $now): void;

    /** Marks one unused code as used; false when there is no such unused code (wrong or already used). */
    public function consume(int $userId, string $hash, DateTimeImmutable $now): bool;

    public function deleteForUser(int $userId): void;
}
