<?php

namespace Src\Job\Application;

use DateTimeImmutable;
use Src\Job\Domain\JobStatus;

/** One row of the company's job list. `hiresCount` is the number of its applications in a hired stage; the job is filled when it has vacancies and at least that many hires. */
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
    ) {}

    public function withHires(int $hiresCount): self
    {
        return new self($this->id, $this->title, $this->status, $this->deadline, $this->vacancies, $this->assigneesCount, $this->publishedAt, $this->createdAt, $this->updatedAt, $hiresCount);
    }

    public function filled(): bool
    {
        return FilledJob::is($this->vacancies, $this->hiresCount);
    }
}
