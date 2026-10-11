<?php

namespace Src\Shared\Application\Accounts;

/**
 * One context's part of erasing the personal data of an anonymised account. It must be safe to run again: the daily
 * reconciliation runs a step again when its receipt is missing.
 */
interface ErasureStep
{
    /** Stable name of the step; the receipt is stored under it, so never rename a step that has run in production. */
    public function name(): string;

    public function execute(int $userId): void;
}
