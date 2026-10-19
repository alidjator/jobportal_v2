<?php

namespace Src\Shared\Application\Company;

/** One context's answer to "do you hold this work location?". A location that is held can only be deactivated, never deleted. Each context registers its own in `CompanyLocationUsages`. */
interface CompanyLocationUsage
{
    public function holds(int $companyId, int $locationId): bool;
}
