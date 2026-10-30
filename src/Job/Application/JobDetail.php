<?php

namespace Src\Job\Application;

use Src\Job\Domain\Job;
use Src\Shared\Application\References\LocationNames;

/**
 * A job as its company sees it: the job itself, the skills and benefits with their names (in the request language), and who is assigned and how many hires it has. The tags are
 * part of the job content as text. `names` holds the name of each classification by API field (`category`, `role`, `job_type`, `job_level`, `work_time`, `experience_level`,
 * `education_level`, `salary_period`), null when the job has none; `location` holds the names of the place chain.
 */
final readonly class JobDetail
{
    /**
     * @param  list<array{id: int, name: string}>  $skills
     * @param  list<array{id: int, name: string}>  $benefits
     * @param  list<array{user_id: int, name: string, role: string, job_role: string}>  $assignees
     * @param  array<string, string|null>  $names
     * @param  list<array{id: int, name: string, type: string, province: ?string, city: ?string, subdistrict: ?string, village: ?string, postal_code: ?string, address_line: ?string}>  $places  the company locations of the job, the main one first
     */
    public function __construct(
        public Job $job,
        public array $skills,
        public array $benefits,
        public array $assignees,
        public int $hiresCount = 0,
        public array $names = [],
        public ?LocationNames $location = null,
        public array $places = [],
        public bool $hiringManagerBound = false,
    ) {}

    /** The same job for a reader who is, or is not, bound to its stage boundary as a hiring manager (`HiringManagerScope` of Recruitment applies the same rule). */
    public function forReader(bool $hiringManagerBound): self
    {
        return new self($this->job, $this->skills, $this->benefits, $this->assignees, $this->hiresCount, $this->names, $this->location, $this->places, $hiringManagerBound);
    }

    /** "Subdistrict, City, Province": the names of the place chain from the most specific to the province, null when the job has no place. */
    public function locationLabel(): ?string
    {
        $parts = array_filter([$this->location?->subdistrict, $this->location?->city, $this->location?->province], static fn (?string $part): bool => $part !== null && $part !== '');

        return $parts === [] ? null : implode(', ', $parts);
    }

    public function filled(): bool
    {
        return FilledJob::is($this->job->content->vacancies, $this->hiresCount);
    }
}
