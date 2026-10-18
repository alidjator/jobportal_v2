<?php

namespace Src\Shared\Application\Candidate;

/** A findable candidate as a company's list shows them: the slug and name plus headline, city, latest position and up to five skill names. */
final readonly class CandidateSummary
{
    /**
     * @param  array{title: string, employer: string}|null  $latestPosition
     * @param  list<string>  $skills
     */
    public function __construct(
        public int $userId,
        public string $slug,
        public string $name,
        public ?string $headline,
        public ?string $city,
        public ?array $latestPosition,
        public array $skills,
    ) {}
}
