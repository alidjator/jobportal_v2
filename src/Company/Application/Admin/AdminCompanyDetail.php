<?php

namespace Src\Company\Application\Admin;

use Src\Company\Application\Locations\CompanyLocationView;
use Src\Company\Domain\Company;

/** A company as an admin sees it: the whole profile, its work locations (read only), the counts, and (while suspended) when and why. */
final readonly class AdminCompanyDetail
{
    public function __construct(
        public Company $company,
        public CompanyCounts $counts,
        /** @var list<CompanyLocationView> */
        public array $locations = [],
    ) {}
}
