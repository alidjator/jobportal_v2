<?php

namespace Src\Shared\Application\Job;

use DateTimeImmutable;

/** A job that went live at least once, as the time-to-fill report needs it. `publishedAt` is the moment it first went live (UTC). */
final readonly class PublishedJob
{
    /** @param list<int> $assigneeUserIds the accounts the job is assigned to now */
    public function __construct(
        public int $id,
        public int $companyId,
        public string $title,
        public ?int $categoryId,
        public DateTimeImmutable $publishedAt,
        public ?int $vacancies,
        public array $assigneeUserIds,
    ) {}

    /** How many hires fill the job: its vacancies, or one when none are stated. */
    public function positions(): int
    {
        return max(1, $this->vacancies ?? 1);
    }
}
