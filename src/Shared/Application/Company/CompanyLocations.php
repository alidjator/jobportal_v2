<?php

namespace Src\Shared\Application\Company;

/** Lets other contexts (Recruitment, Workforce) pick a work location of their tenant without reading Company's tables. Provided by Company. */
interface CompanyLocations
{
    /** The location when it belongs to the company and is active; otherwise null. The address text is street, village, subdistrict, city and province joined with commas (empty without an address). */
    public function activeLocation(int $companyId, int $locationId): ?CompanyLocationPlace;
}
