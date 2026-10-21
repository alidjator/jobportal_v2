<?php

namespace Src\Company\Application\Admin;

use DateTimeImmutable;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;

interface AdminCompanyReader
{
    /** @return Page<CompanyListItem> */
    public function page(CompanyListQuery $query, PageRequest $request): Page;

    /**
     * The number of companies per status under the search and verification filters of the query (its status filter is ignored); a status without a company is absent.
     *
     * @return array<string, int> keyed by the status value
     */
    public function countByStatus(CompanyListQuery $query): array;

    /** How many members the company has and how many invitations can still be accepted at `$now`. */
    public function counts(int $companyId, DateTimeImmutable $now): CompanyCounts;
}
