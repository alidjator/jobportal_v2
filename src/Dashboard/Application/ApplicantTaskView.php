<?php

namespace Src\Dashboard\Application;

use DateTimeImmutable;

/** One thing that waits for a job seeker, with the names the screen shows. */
final readonly class ApplicantTaskView
{
    public function __construct(
        public string $key,
        public int $subjectId,
        public ?int $applicationId,
        public int $jobId,
        public ?string $jobTitle,
        public ?string $companyName,
        public ?DateTimeImmutable $dueAt,
        public DateTimeImmutable $createdAt,
    ) {}
}
