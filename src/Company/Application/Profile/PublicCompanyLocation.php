<?php

namespace Src\Company\Application\Profile;

use Src\Company\Domain\LocationType;
use Src\Shared\Application\References\LocationNames;
use Src\Shared\Domain\Coordinates;

/** An active work location on the public company page: name, type and address with names, and the coordinates. It holds no phone, like the company contact. */
final readonly class PublicCompanyLocation
{
    public function __construct(
        public string $name,
        public LocationType $type,
        public bool $isMain,
        public ?string $addressLine,
        public LocationNames $names,
        public ?Coordinates $coordinates,
    ) {}
}
