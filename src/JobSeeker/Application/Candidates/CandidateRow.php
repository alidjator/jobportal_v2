<?php

namespace Src\JobSeeker\Application\Candidates;

use DateTimeImmutable;
use Src\JobSeeker\Domain\Availability;

/** A candidate as the search reads them: the profile columns a company may see in a list, as ids. */
final readonly class CandidateRow
{
    public function __construct(
        public int $userId,
        public string $slug,
        public string $name,
        public ?string $avatarPath,
        public ?string $headline,
        public ?int $provinceId,
        public ?int $cityId,
        public ?int $experienceLevelId,
        public ?Availability $availability,
        public ?int $highestEducationLevelId,
        public DateTimeImmutable $updatedAt,
    ) {}
}
