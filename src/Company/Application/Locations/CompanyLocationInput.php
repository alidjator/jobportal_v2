<?php

namespace Src\Company\Application\Locations;

use Src\Company\Domain\LocationType;
use Src\Shared\Domain\Address;

/** What a company admin writes in a new work location. The coordinates are raw numbers: the use case checks that both or neither are given and that they are inside Indonesia. */
final readonly class CompanyLocationInput
{
    public function __construct(
        public string $name,
        public LocationType $type,
        public Address $address,
        public ?string $phone = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
    ) {}
}
