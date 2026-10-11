<?php

namespace Src\Shared\Application\Accounts;

/** Remembers which erasure steps finished for which anonymised account. Identity owns the storage. */
interface ErasureReceipts
{
    public function record(string $step, int $userId): void;
}
