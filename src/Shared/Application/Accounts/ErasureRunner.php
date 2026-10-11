<?php

namespace Src\Shared\Application\Accounts;

/** Runs one erasure step and leaves a receipt, so the daily reconciliation knows the step is done. */
final readonly class ErasureRunner
{
    public function __construct(private ErasureReceipts $receipts) {}

    public function run(ErasureStep $step, int $userId): void
    {
        $step->execute($userId);

        $this->receipts->record($step->name(), $userId);
    }
}
