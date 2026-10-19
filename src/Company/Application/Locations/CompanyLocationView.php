<?php

namespace Src\Company\Application\Locations;

use Src\Company\Domain\CompanyLocation;
use Src\Shared\Application\References\LocationNames;

/** A location with the names of its address and its time zone, as the API returns it. The province also keeps its id (the pickers need it). */
final readonly class CompanyLocationView
{
    public function __construct(
        public CompanyLocation $location,
        public LocationNames $names,
        public ?string $timeZone,
    ) {}
}
