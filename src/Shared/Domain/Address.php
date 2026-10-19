<?php

namespace Src\Shared\Domain;

/**
 * An Indonesian street address: a street line and the chain of the location masters (province, city, subdistrict, village, postal code). Every part is optional, as
 * an address may be filled in later. Whether the ids form one matching chain is checked by the use case that writes the address (`LocationReferences`).
 */
final readonly class Address
{
    public function __construct(
        public ?string $line = null,
        public ?int $provinceId = null,
        public ?int $cityId = null,
        public ?int $subdistrictId = null,
        public ?int $villageId = null,
        public ?int $postalCodeId = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->line === null && $this->provinceId === null;
    }
}
