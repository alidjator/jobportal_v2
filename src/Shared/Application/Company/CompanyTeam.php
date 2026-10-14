<?php

namespace Src\Shared\Application\Company;

/** Lets other contexts ask who belongs to a company, without reading Company's tables. Provided by Company. */
interface CompanyTeam
{
    /**
     * The role (`company_admin` or `member`) of every member of the company, keyed by account id. Empty when the company has no members.
     *
     * @return array<int, string>
     */
    public function roles(int $companyId): array;

    /**
     * The accounts of the company that see every job of the company (`jobs.view_all`, which every company admin holds), for checks like "this member
     * works on this job".
     *
     * @return list<int>
     */
    public function viewAllUserIds(int $companyId): array;

    /** The account of the company's owner, or null when it has none. */
    public function ownerUserId(int $companyId): ?int;
}
