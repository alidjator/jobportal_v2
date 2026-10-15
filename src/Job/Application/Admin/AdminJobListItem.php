<?php

namespace Src\Job\Application\Admin;

use DateTimeImmutable;
use Src\Job\Domain\JobStatus;
use Src\Shared\Application\Company\CompanySummary;

/** One row of the admin job list. `company` is null only if the company row is gone, which a foreign key prevents. `flagged` is known only in the review queue (null elsewhere). */
final readonly class AdminJobListItem
{
    public function __construct(
        public int $id,
        public string $title,
        public JobStatus $status,
        public int $companyId,
        public ?CompanySummary $company,
        public ?DateTimeImmutable $deadline,
        public ?int $vacancies,
        public int $assigneesCount,
        public ?DateTimeImmutable $publishedAt,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?bool $flagged = null,
    ) {}

    public function withCompany(?CompanySummary $company): self
    {
        return new self(
            $this->id, $this->title, $this->status, $this->companyId, $company, $this->deadline, $this->vacancies, $this->assigneesCount,
            $this->publishedAt, $this->createdAt, $this->updatedAt, $this->flagged,
        );
    }

    public function withFlagged(bool $flagged): self
    {
        return new self(
            $this->id, $this->title, $this->status, $this->companyId, $this->company, $this->deadline, $this->vacancies, $this->assigneesCount,
            $this->publishedAt, $this->createdAt, $this->updatedAt, $flagged,
        );
    }
}
