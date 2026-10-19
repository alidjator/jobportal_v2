<?php

namespace Src\Company\Infrastructure\Persistence;

use Src\Company\Domain\CompanyLocationRepository;
use Src\Shared\Application\Company\CompanyLocationPlace;
use Src\Shared\Application\Company\CompanyLocations;
use Src\Shared\Application\References\LocationReferences;

final readonly class RepositoryCompanyLocations implements CompanyLocations
{
    public function __construct(private CompanyLocationRepository $locations, private LocationReferences $references) {}

    public function activeLocation(int $companyId, int $locationId): ?CompanyLocationPlace
    {
        $location = $this->locations->find($companyId, $locationId);

        if ($location === null || ! $location->isActive()) {
            return null;
        }

        $address = $location->address;
        $names = $this->references->names($address->provinceId, $address->cityId, $address->subdistrictId, $address->villageId, $address->postalCodeId);
        $text = implode(', ', array_filter(
            [$address->line, $names->village, $names->subdistrict, $names->city, $names->province],
            static fn (?string $part): bool => $part !== null && trim($part) !== '',
        ));

        return new CompanyLocationPlace((int) $location->id, $location->name, $text);
    }
}
