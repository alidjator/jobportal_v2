<?php

namespace Src\Company\Domain;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * What a company says about itself. Bio and vision are Markdown (clients render and sanitise them). The classifications and the location
 * are ids of the shared masters and of the location chain; whether they are valid is checked by the use cases, which can ask ReferenceData.
 */
final readonly class CompanyProfile
{
    public function __construct(
        public string $name,
        public ?string $website,
        public ?DateTimeImmutable $establishedOn,
        public ?string $bio,
        public ?string $vision,
        public int $industryId,
        public int $organizationTypeId,
        public ?int $teamSizeId,
        public ?string $addressLine,
        public ?int $provinceId,
        public ?int $cityId,
        public ?int $subdistrictId,
        public ?int $villageId,
        public ?int $postalCodeId,
        public ?string $contactEmail,
        public ?string $contactPhone,
        public bool $isVisible,
    ) {}

    /** The profile a company starts with: only the required fields, visible to the public. */
    public static function minimal(string $name, int $industryId, int $organizationTypeId): self
    {
        return new self($name, null, null, null, null, $industryId, $organizationTypeId, null, null, null, null, null, null, null, null, null, true);
    }

    /**
     * A copy with some fields replaced. The keys are property names; a key that is not a property is a programming error and throws.
     *
     * @param  array<string, mixed>  $changes
     */
    public function with(array $changes): self
    {
        $values = get_object_vars($this);

        foreach (array_keys($changes) as $key) {
            if (! array_key_exists($key, $values)) {
                throw new InvalidArgumentException("The company profile has no field {$key}.");
            }
        }

        return new self(...[...$values, ...$changes]);
    }
}
