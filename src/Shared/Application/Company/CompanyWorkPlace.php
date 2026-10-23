<?php

namespace Src\Shared\Application\Company;

/** An active work location of a tenant with its address as ids of the place chain, for a context that keeps its own copy of the place (a job). `type` is `head_office`, `branch` or `site`. */
final readonly class CompanyWorkPlace
{
    public function __construct(
        public int $id,
        public string $name,
        public string $type,
        public ?int $provinceId,
        public ?int $cityId,
        public ?int $subdistrictId,
        public ?int $villageId,
        public ?int $postalCodeId,
        public ?string $addressLine,
    ) {}
}
