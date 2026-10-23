<?php

namespace Src\Job\Application;

use DateTimeImmutable;
use Src\Job\Domain\JobStatus;
use Src\Job\Domain\WorkMode;

/** One row of the company's job list. `applicationsCount` counts the active applications; `provinceId` and `cityId` are the job's own address, `createdBy` its creator. `hiresCount` is the number of its applications in a hired stage; the job is filled when it has vacancies and at least that many hires. */
final readonly class JobListItem
{
    public function __construct(
        public int $id,
        public string $title,
        public JobStatus $status,
        public ?DateTimeImmutable $deadline,
        public ?int $vacancies,
        public int $assigneesCount,
        public ?DateTimeImmutable $publishedAt,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public int $hiresCount = 0,
        public ?string $summary = null,
        public ?WorkMode $workMode = null,
        public ?int $provinceId = null,
        public ?int $cityId = null,
        public ?int $createdBy = null,
        public int $applicationsCount = 0,
        public ?string $createdByName = null,
        public ?string $province = null,
        public ?string $city = null,
    ) {}

    public function withHires(int $hiresCount): self
    {
        return $this->with(hiresCount: $hiresCount);
    }

    /** The names behind the ids of the row: the creator and the place. */
    public function withNames(?string $createdByName, ?string $province, ?string $city): self
    {
        return $this->with(createdByName: $createdByName, province: $province, city: $city);
    }

    private function with(?int $hiresCount = null, ?string $createdByName = null, ?string $province = null, ?string $city = null): self
    {
        return new self(
            $this->id, $this->title, $this->status, $this->deadline, $this->vacancies, $this->assigneesCount, $this->publishedAt, $this->createdAt, $this->updatedAt,
            $hiresCount ?? $this->hiresCount, $this->summary, $this->workMode, $this->provinceId, $this->cityId, $this->createdBy, $this->applicationsCount,
            $createdByName ?? $this->createdByName, $province ?? $this->province, $city ?? $this->city,
        );
    }

    public function filled(): bool
    {
        return FilledJob::is($this->vacancies, $this->hiresCount);
    }
}
