<?php

namespace Src\Job\Application;

use DateTimeImmutable;
use Src\Job\Domain\JobStatus;

/** One row of the company's job list. */
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
    ) {}
}
