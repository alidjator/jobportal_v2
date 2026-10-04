<?php

namespace Src\ReferenceData\Application\Locations;

/** One area: its BPS code and name (a postal code has neither, only its code in `extras`). */
final readonly class LocationItem
{
    /** @param  array<string, string|null>  $extras */
    public function __construct(
        public int $id,
        public ?string $bpsCode,
        public ?string $name,
        public array $extras,
    ) {}
}
