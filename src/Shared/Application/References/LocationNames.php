<?php

namespace Src\Shared\Application\References;

/** The names of a location chain: place names are untranslated proper names, the postal code is its digits. A level that was not given is null. */
final readonly class LocationNames
{
    public function __construct(
        public ?string $province,
        public ?string $city,
        public ?string $subdistrict,
        public ?string $village,
        public ?string $postalCode,
    ) {}
}
