<?php

namespace Src\Identity\Application\Auth;

/** What a run of `SendImportedPasswordResets` found and did. `sendable` is what is left to send after the suppressed addresses and the accounts that already had a reset code. */
final readonly class ImportedResetReport
{
    public function __construct(
        public int $withoutPassword,
        public int $suppressed,
        public int $alreadyIssued,
        public int $sendable,
        public int $sent,
    ) {}
}
