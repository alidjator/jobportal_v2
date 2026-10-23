<?php

namespace Src\Job\Application\Browse;

use DateTimeImmutable;
use Src\Job\Domain\WorkMode;
use Src\Shared\Application\Company\PublicCompanyCard;
use Src\Shared\Application\Matching\MatchScore;

/**
 * One row of the public job search, in the language of the request. The salary is null when the company hides it or gave none. `match` is set only for a job seeker
 * with a complete profile; `saved` only for a signed-in job seeker (whether they saved the job).
 */
final readonly class PublicJobListItem
{
    /**
     * @param  array{id: int, name: string}|null  $jobType
     * @param  array{id: int, name: string}|null  $jobLevel
     * @param  array{min: ?int, max: ?int, period: array{id: int, name: string}|null}|null  $salary
     * @param  list<array{id: int|null, name: string|null, type: string|null, city: string|null, province: string|null}>  $locations  the work locations, the main one first; empty for a remote job without a place
     */
    public function __construct(
        public int $id,
        public string $slug,
        public string $title,
        public PublicCompanyCard $company,
        public array $locations,
        public ?array $jobType,
        public ?array $jobLevel,
        public ?WorkMode $workMode,
        public ?array $salary,
        public ?DateTimeImmutable $deadline,
        public ?DateTimeImmutable $publishedAt,
        public ?MatchScore $match = null,
        public ?bool $saved = null,
        public ?string $summary = null,
        public ?string $salaryNote = null,
    ) {}

    public function withMatch(?MatchScore $match): self
    {
        return new self(
            $this->id, $this->slug, $this->title, $this->company, $this->locations, $this->jobType, $this->jobLevel, $this->workMode, $this->salary,
            $this->deadline, $this->publishedAt, $match ?? new MatchScore(null, []), $this->saved, $this->summary, $this->salaryNote,
        );
    }

    public function withSaved(bool $saved): self
    {
        return new self(
            $this->id, $this->slug, $this->title, $this->company, $this->locations, $this->jobType, $this->jobLevel, $this->workMode, $this->salary,
            $this->deadline, $this->publishedAt, $this->match, $saved, $this->summary, $this->salaryNote,
        );
    }
}
