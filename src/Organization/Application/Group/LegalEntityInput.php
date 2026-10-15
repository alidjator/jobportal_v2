<?php

namespace Src\Organization\Application\Group;

use Src\Organization\Domain\LegalForm;

/** The data of a new legal entity, as the client sent it. */
final readonly class LegalEntityInput
{
    public function __construct(
        public string $legalName,
        public LegalForm $legalForm,
        public ?string $npwp = null,
        public ?string $nib = null,
        public ?string $addressLine = null,
        public ?int $provinceId = null,
        public ?int $cityId = null,
        public ?int $subdistrictId = null,
        public ?int $villageId = null,
        public ?int $postalCodeId = null,
    ) {}
}
