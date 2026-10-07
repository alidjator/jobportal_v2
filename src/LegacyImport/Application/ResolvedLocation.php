<?php

namespace Src\LegacyImport\Application;

/** A location chain as far as it was valid: every level below the first invalid one is null. */
final readonly class ResolvedLocation
{
    public function __construct(
        public ?int $provinceId = null,
        public ?int $cityId = null,
        public ?int $subdistrictId = null,
        public ?int $villageId = null,
        public ?int $postalCodeId = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->provinceId === null;
    }
}
