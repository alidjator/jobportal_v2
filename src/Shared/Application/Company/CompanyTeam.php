<?php

namespace Src\Shared\Application\Company;

/** Lets other contexts ask who belongs to a company, without reading Company's tables. Provided by Company. */
interface CompanyTeam
{
    /**
     * The role (`company_admin` or `recruiter`) of every member of the company, keyed by account id. Empty when the company has no members.
     *
     * @return array<int, string>
     */
    public function roles(int $companyId): array;
}
