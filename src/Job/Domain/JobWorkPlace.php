<?php

namespace Src\Job\Domain;

/**
 * A work location of the company as a job holds it: the copy taken when the job was saved (its name, type and address as ids of the place chain), so searching
 * and showing the job never read the company's tables. The first place of a job is its main one and also fills the job's own address.
 */
final readonly class JobWorkPlace
{
    public function __construct(
        public int $locationId,
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
