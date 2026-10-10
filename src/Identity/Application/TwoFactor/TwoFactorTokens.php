<?php

namespace Src\Identity\Application\TwoFactor;

/** Random challenge tokens and recovery codes, and the one-way hash they are stored under. */
interface TwoFactorTokens
{
    /** A new single-use challenge token (the plain text goes to the client, only its hash is stored). */
    public function challengeToken(): string;

    /** @return list<string> `$count` new recovery codes in their display form (`xxxxx-xxxxx`) */
    public function recoveryCodes(int $count): array;

    /** Hashes a challenge token or a recovery code; a recovery code is normalised first (case and dashes do not matter). */
    public function hash(string $value): string;
}
