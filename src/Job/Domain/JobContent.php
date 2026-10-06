<?php

namespace Src\Job\Domain;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Everything a company writes in a job. The classifications, the salary period, the location and the skills and benefits are ids of the shared masters;
 * whether they are valid is checked by the use cases, which can ask ReferenceData. The description is Markdown (clients render and sanitise it). A draft
 * may leave anything but the title empty: completeness is checked when the job is published.
 */
final readonly class JobContent
{
    /**
     * @param  list<int>  $skillIds
     * @param  list<string>  $tags  normalised keywords (lower case)
     * @param  list<int>  $benefitIds
     */
    public function __construct(
        public string $title,
        public ?string $description,
        public ?int $categoryId,
        public ?int $roleId,
        public ?int $jobTypeId,
        public ?int $jobLevelId,
        public ?int $workTimeId,
        public ?int $experienceLevelId,
        public ?int $educationLevelId,
        public ?int $vacancies,
        public ?DateTimeImmutable $deadline,
        public ?int $salaryMin,
        public ?int $salaryMax,
        public ?int $salaryPeriodId,
        public bool $salaryVisible,
        public ?WorkMode $workMode,
        public ?int $provinceId,
        public ?int $cityId,
        public ?int $subdistrictId,
        public ?int $villageId,
        public ?int $postalCodeId,
        public ?string $addressLine,
        public array $skillIds,
        public array $tags,
        public array $benefitIds,
    ) {}

    /** The content of a new draft: only the title, a visible salary and no lists. */
    public static function draft(string $title): self
    {
        return new self($title, null, null, null, null, null, null, null, null, null, null, null, null, null, true, null, null, null, null, null, null, null, [], [], []);
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
                throw new InvalidArgumentException("The job content has no field {$key}.");
            }
        }

        return new self(...[...$values, ...$changes]);
    }

    /**
     * Whether a core field differs from the other content: the title, the description, the salary (amounts, period and visibility), the work mode and
     * the location with its street address. These are what a reviewer approved, so changing one of them sends a live job back to review.
     */
    public function coreDiffersFrom(self $other): bool
    {
        return $this->title !== $other->title
            || $this->description !== $other->description
            || $this->salaryMin !== $other->salaryMin
            || $this->salaryMax !== $other->salaryMax
            || $this->salaryPeriodId !== $other->salaryPeriodId
            || $this->salaryVisible !== $other->salaryVisible
            || $this->workMode !== $other->workMode
            || $this->provinceId !== $other->provinceId
            || $this->cityId !== $other->cityId
            || $this->subdistrictId !== $other->subdistrictId
            || $this->villageId !== $other->villageId
            || $this->postalCodeId !== $other->postalCodeId
            || $this->addressLine !== $other->addressLine;
    }

    public function hasSalaryAmount(): bool
    {
        return $this->salaryMin !== null || $this->salaryMax !== null;
    }
}
