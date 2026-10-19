<?php

namespace Src\LegacyImport\Application;

/** A company ready to be written, with the account that becomes its company admin. */
final readonly class NewCompany
{
    public function __construct(
        public string $name,
        public string $slug,
        public int $ownerUserId,
        public ?string $website,
        public ?string $establishedOn,
        public ?string $bio,
        public ?string $vision,
        public int $industryId,
        public int $organizationTypeId,
        public ?int $teamSizeId,
        public ?string $addressLine,
        public ResolvedLocation $location,
        public ?float $latitude,
        public ?float $longitude,
        public bool $isVisible,
        public ?string $verifiedAt,
        public string $createdAt,
        public string $updatedAt,
    ) {}
}
