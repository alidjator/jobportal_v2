<?php

namespace Src\Company\Application\Admin;

use DateTimeImmutable;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;

interface AdminCompanyReader
{
    /** @return Page<CompanyListItem> */
    public function page(CompanyListQuery $query, PageRequest $request): Page;

    /** How many members the company has and how many invitations can still be accepted at `$now`. */
    public function counts(int $companyId, DateTimeImmutable $now): CompanyCounts;
}
