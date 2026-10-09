<?php

namespace Src\JobSeeker\Application\Candidates;

use DateTimeImmutable;

/** One candidate in the search results. */
final readonly class CandidateListItem
{
    /**
     * @param  array{id: int, name: string|null}|null  $province
     * @param  array{id: int, name: string|null}|null  $city
     * @param  array{id: int, name: string|null}|null  $experienceLevel
     * @param  array{id: int, name: string|null}|null  $highestEducation
     */
    public function __construct(
        public string $slug,
        public string $name,
        public bool $hasAvatar,
        public ?string $headline,
        public ?array $province,
        public ?array $city,
        public ?array $experienceLevel,
        public ?string $availability,
        public ?array $highestEducation,
        public DateTimeImmutable $updatedAt,
    ) {}
}
