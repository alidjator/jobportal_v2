<?php

namespace Src\LegacyImport\Application;

/** Writes accounts into the new schema (the Infrastructure implementation writes `identity_users` directly: the documented exception of this context). */
interface AccountWriter
{
    public function emailTaken(string $email): bool;

    /** @return int the id of the new account */
    public function insert(NewAccount $account): int;

    /** Sets the avatar of an account that has none; false when the account already had one (nothing is overwritten). */
    public function setAvatarIfEmpty(int $userId, string $path): bool;
}
