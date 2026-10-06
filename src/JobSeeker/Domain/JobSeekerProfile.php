<?php

namespace Src\JobSeeker\Domain;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * The profile of one job seeker. Every field but the account is optional. An account that never saved its profile has the empty one.
 * Contact data lives here, not on the account.
 */
final readonly class JobSeekerProfile
{
    public function __construct(
        public int $userId,
        public ?string $phone = null,
        public ?string $whatsapp = null,
        public ?DateTimeImmutable $birthDate = null,
        public ?Gender $gender = null,
        public ?MaritalStatus $maritalStatus = null,
        public ?int $religionId = null,
        public ?int $nationalityId = null,
        public ?int $expectedSalary = null,
        public ?int $expectedSalaryPeriodId = null,
        public ?int $experienceLevelId = null,
        public ?Availability $availability = null,
        public ?DateTimeImmutable $availableFrom = null,
        public ?int $provinceId = null,
        public ?int $cityId = null,
        public ?int $subdistrictId = null,
        public ?int $villageId = null,
        public ?int $postalCodeId = null,
        public ?string $addressLine = null,
        public ?string $headline = null,
        public ?string $summary = null,
        public bool $isDiscoverable = false,
        public bool $resumeDiscoverable = false,
    ) {}

    public static function empty(int $userId): self
    {
        return new self($userId);
    }

    /**
     * A copy with some fields replaced; the keys are property names.
     *
     * @param  array<string, mixed>  $changes
     */
    public function with(array $changes): self
    {
        $values = get_object_vars($this);

        foreach (array_keys($changes) as $key) {
            if ($key === 'userId' || ! array_key_exists($key, $values)) {
                throw new InvalidArgumentException("The job seeker profile has no changeable field {$key}.");
            }
        }

        return new self(...[...$values, ...$changes]);
    }
}
