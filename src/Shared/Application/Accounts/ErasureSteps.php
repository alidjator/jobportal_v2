<?php

namespace Src\Shared\Application\Accounts;

/** Every erasure step of every context. */
interface ErasureSteps
{
    /** @return list<ErasureStep> */
    public function all(): array;
}
