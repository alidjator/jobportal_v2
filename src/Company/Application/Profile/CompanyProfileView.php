<?php

namespace Src\Company\Application\Profile;

use Src\Company\Application\Locations\CompanyLocationView;
use Src\Company\Domain\Company;

/** A company's own profile together with its main location (null only for a company that has none stored, which should not happen). */
final readonly class CompanyProfileView
{
    public function __construct(
        public Company $company,
        public ?CompanyLocationView $mainLocation,
    ) {}
}
