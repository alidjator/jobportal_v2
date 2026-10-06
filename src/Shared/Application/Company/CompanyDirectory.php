<?php

namespace Src\Shared\Application\Company;

/** Lets other contexts ask about a company, without reading Company's tables. Provided by Company. */
interface CompanyDirectory
{
    public function exists(int $companyId): bool;

    /** True when the company exists and is not suspended. */
    public function isActive(int $companyId): bool;
}
