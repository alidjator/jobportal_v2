<?php

namespace Src\JobSeeker\Domain;

use InvalidArgumentException;

/** One education entry in a job seeker's history. The GPA is a decimal text with two decimals (`3.50`), on a 0 to 4 scale. */
final readonly class Education
{
    public function __construct(
        public int $userId,
        public int $educationLevelId,
        public string $institution,
        public ?string $fieldOfStudy,
        public int $startYear,
        public ?int $endYear,
        public bool $isCurrent,
        public ?string $gpa,
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
                throw new InvalidArgumentException("An education entry has no changeable field {$key}.");
            }
        }

        return new self(...[...$values, ...$changes]);
    }
}
