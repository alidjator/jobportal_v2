<?php

namespace Src\Company\Application\Locations;

use Src\Company\Domain\CompanyLocation;
use Src\Shared\Application\References\LocationReferences;

/** Turns locations into views: the names of the address chain and the time zone. A list is at most `company.max_locations` rows, so the names are read per row. */
final readonly class CompanyLocationViews
{
    public function __construct(private LocationReferences $references) {}

    public function one(CompanyLocation $location): CompanyLocationView
    {
        return $this->many([$location])[0];
    }

    /**
     * @param  list<CompanyLocation>  $locations
     * @return list<CompanyLocationView>
     */
    public function many(array $locations): array
    {
        $provinceIds = array_values(array_unique(array_filter(array_map(static fn (CompanyLocation $location): ?int => $location->address->provinceId, $locations))));
        $codes = $this->references->provinceCodes($provinceIds);
        $views = [];

        foreach ($locations as $location) {
            $address = $location->address;
            $views[] = new CompanyLocationView(
                $location,
                $this->references->names($address->provinceId, $address->cityId, $address->subdistrictId, $address->villageId, $address->postalCodeId),
                LocationTimeZones::forProvinceCode($address->provinceId === null ? null : ($codes[$address->provinceId] ?? null)),
            );
        }

        return $views;
    }
}
