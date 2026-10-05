<?php

namespace Src\Shared\Application\Company;

/** Lets other contexts ask whether a company exists, without reading Company's tables. Provided by Company. */
interface CompanyDirectory
{
    public function exists(int $companyId): bool;
}
