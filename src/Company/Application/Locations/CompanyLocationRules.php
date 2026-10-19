<?php

namespace Src\Company\Application\Locations;

use Src\Company\Domain\CompanyLocation;
use Src\Company\Domain\CompanyLocationRepository;
use Src\Shared\Application\References\LocationReferences;
use Src\Shared\Domain\Address;
use Src\Shared\Domain\Coordinates;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** The rules a location's fields follow, shared by creating and editing: the name, the address chain and the coordinates. */
final readonly class CompanyLocationRules
{
    public function __construct(private LocationReferences $locations, private CompanyLocationRepository $repository) {}

    /** @throws BusinessRuleViolation `invalid-location-name` or `location-name-taken` */
    public function name(int $companyId, string $name, ?int $exceptId = null): string
    {
        $name = trim($name);

        if ($name === '' || mb_strlen($name) > CompanyLocation::NAME_MAX) {
            throw new BusinessRuleViolation('The location name must have 1 to 100 characters.', 'invalid-location-name');
        }

        if ($this->repository->nameTaken($companyId, $name, $exceptId)) {
            throw new BusinessRuleViolation('Another location of this company already has this name.', 'location-name-taken');
        }

        return $name;
    }

    /** @throws BusinessRuleViolation `invalid-location` */
    public function address(Address $address): Address
    {
        if (! $this->locations->isValidChain($address->provinceId, $address->cityId, $address->subdistrictId, $address->villageId, $address->postalCodeId)) {
            throw new BusinessRuleViolation(
                'The address is not a matching chain: each level must belong to the one above it, and a level needs the levels above it.',
                'invalid-location',
            );
        }

        $line = $address->line === null ? null : trim($address->line);

        return new Address($line === '' ? null : $line, $address->provinceId, $address->cityId, $address->subdistrictId, $address->villageId, $address->postalCodeId);
    }

    /** @throws BusinessRuleViolation `invalid-coordinates` */
    public function coordinates(?float $latitude, ?float $longitude): ?Coordinates
    {
        if ($latitude === null && $longitude === null) {
            return null;
        }

        if ($latitude === null || $longitude === null) {
            throw new BusinessRuleViolation('Give both the latitude and the longitude, or neither.', 'invalid-coordinates');
        }

        return Coordinates::of($latitude, $longitude);
    }

    public function phone(?string $phone): ?string
    {
        $phone = $phone === null ? null : trim($phone);

        return $phone === '' ? null : $phone;
    }
}
