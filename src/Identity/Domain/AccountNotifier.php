<?php

namespace Src\Identity\Domain;

/**
 * Efek samping email/notifikasi. Implementasi wajib best-effort: tidak boleh melempar exception.
 */
interface AccountNotifier
{
    /** @param array<string,mixed> $user */
    public function userRegistered(array $user): void;

    public function resetCodeIssued(int $userId, string $code): void;
}
