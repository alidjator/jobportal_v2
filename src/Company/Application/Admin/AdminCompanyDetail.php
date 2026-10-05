<?php

namespace Src\Company\Application\Admin;

use Src\Company\Domain\Company;

/** A company as an admin sees it: the whole profile, the counts, and (while suspended) when and why. */
final readonly class AdminCompanyDetail
{
    public function __construct(
        public Company $company,
        public CompanyCounts $counts,
    ) {}
}
