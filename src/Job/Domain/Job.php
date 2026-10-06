<?php

namespace Src\Job\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A job a company posts. `slug` is a cosmetic form of the title (the public identifier is the id); it follows the title. The content can be changed
 * only while the status allows it. Publishing, closing, expiring and the admin decisions come with the later parts of the Jobs context.
 */
final readonly class Job
{
    public function __construct(
        public ?int $id,
        public int $companyId,
        public string $slug,
        public JobStatus $status,
        public ?string $statusReason,
        public JobContent $content,
        public int $createdByUserId,
        public ?DateTimeImmutable $publishedAt,
        public ?DateTimeImmutable $reviewedAt,
        public ?int $reviewedByAdminId,
        public ?DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt,
    ) {}

    public static function draft(int $companyId, string $slug, JobContent $content, int $createdByUserId): self
    {
        return new self(null, $companyId, $slug, JobStatus::Draft, null, $content, $createdByUserId, null, null, null, null, null);
    }

    /** @throws StateConflict `job-not-editable` */
    public function assertEditable(): void
    {
        if (! $this->status->isEditable()) {
            throw new StateConflict('A job in this status cannot be edited.', 'job-not-editable');
        }
    }

    /** @throws StateConflict `job-not-editable` */
    public function changeContent(string $slug, JobContent $content): self
    {
        $this->assertEditable();

        return new self(
            $this->id, $this->companyId, $slug, $this->status, $this->statusReason, $content, $this->createdByUserId, $this->publishedAt,
            $this->reviewedAt, $this->reviewedByAdminId, $this->createdAt, $this->updatedAt,
        );
    }

    /** Only a draft is deleted; every other job is closed, so the history of applications stays whole. */
    public function canBeDeleted(): bool
    {
        return $this->status === JobStatus::Draft;
    }
}
