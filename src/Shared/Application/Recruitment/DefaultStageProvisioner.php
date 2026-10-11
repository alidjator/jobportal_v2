<?php

namespace Src\Shared\Application\Recruitment;

/**
 * Gives a company its eight default application stages. Provided by Recruitment and called by Company inside the transaction that creates the
 * company, so a company never exists without them. Idempotent: a company that already has stages is left untouched.
 */
interface DefaultStageProvisioner
{
    public function provision(int $companyId): void;
}
