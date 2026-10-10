<?php

namespace Src\Identity\Application\TwoFactor;

use DateTimeImmutable;

/** The one-time recovery codes of an admin, stored only as hashes. */
interface RecoveryCodes
{
    /**
     * Replaces every code of the admin with the new ones.
     *
     * @param  list<string>  $hashes
     */
    public function replace(int $adminId, array $hashes, DateTimeImmutable $now): void;

    /** Marks one unused code as used; false when there is no such unused code (wrong or already used). */
    public function consume(int $adminId, string $hash, DateTimeImmutable $now): bool;

    public function deleteForAdmin(int $adminId): void;
}
