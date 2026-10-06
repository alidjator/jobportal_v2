<?php

namespace Src\JobSeeker\Domain;

use DateTimeImmutable;
use InvalidArgumentException;

/** One job in a job seeker's history. Dates are months: the first day of the month, midnight UTC. */
final readonly class WorkExperience
{
    public function __construct(
        public int $userId,
        public string $jobTitle,
        public string $companyName,
        public ?int $jobTypeId,
        public ?string $locationText,
        public DateTimeImmutable $startDate,
        public ?DateTimeImmutable $endDate,
        public bool $isCurrent,
        public ?string $description,
        public ?int $id = null,
    ) {}

    /**
     * A copy with some fields replaced; the keys are property names.
     *
     * @param  array<string, mixed>  $changes
     */
    public function with(array $changes): self
    {
        $values = get_object_vars($this);

        foreach (array_keys($changes) as $key) {
            if (in_array($key, ['id', 'userId'], true) || ! array_key_exists($key, $values)) {
                throw new InvalidArgumentException("A work experience has no changeable field {$key}.");
            }
        }

        return new self(...[...$values, ...$changes]);
    }
}
