<?php

namespace Src\Identity\Application\Auth;

/** Hashes verification codes for storage. Only the hash is ever stored or compared. */
interface VerificationCodeHasher
{
    public function make(string $code): string;

    public function matches(string $code, string $hash): bool;
}
