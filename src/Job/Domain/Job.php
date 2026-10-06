<?php

namespace Src\Job\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A job a company posts. `slug` is a cosmetic form of the title (the public identifier is the id); it follows the title. The content can be changed
 * only while the status allows it. `publishedAt` is set once, when the job first goes live, and never changes. The admin decisions (approve, reject,
 * take down, restore) come with the admin part of the Jobs context.
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

        return $this->with(slug: $slug, content: $content);
    }

    /**
     * A draft or a rejected job goes to review, or straight live when no approval is needed. The reason of an earlier rejection is cleared.
     *
     * @throws StateConflict `job-not-publishable`
     */
    public function publish(bool $requiresApproval, DateTimeImmutable $at): self
    {
        if ($this->status !== JobStatus::Draft && $this->status !== JobStatus::Rejected) {
            throw new StateConflict('Only a draft or a rejected job can be published.', 'job-not-publishable');
        }

        return $this->goLiveOrToReview($requiresApproval, $at);
    }

    /** @throws StateConflict `job-not-active` */
    public function close(): self
    {
        if ($this->status !== JobStatus::Active) {
            throw new StateConflict('Only a live job can be closed.', 'job-not-active');
        }

        return $this->with(status: JobStatus::Closed);
    }

    /** @throws StateConflict `job-not-reopenable` */
    public function assertReopenable(): void
    {
        if ($this->status !== JobStatus::Closed && $this->status !== JobStatus::Expired) {
            throw new StateConflict('Only a closed or an expired job can be reopened.', 'job-not-reopenable');
        }
    }

    /**
     * A closed or an expired job goes live again, through review when approval is required. The caller sets the new deadline in the content first.
     *
     * @throws StateConflict `job-not-reopenable`
     */
    public function reopen(bool $requiresApproval, DateTimeImmutable $at): self
    {
        $this->assertReopenable();

        return $this->goLiveOrToReview($requiresApproval, $at);
    }

    /** A live job whose deadline has passed. @throws StateConflict `job-not-active` */
    public function expire(): self
    {
        if ($this->status !== JobStatus::Active) {
            throw new StateConflict('Only a live job can expire.', 'job-not-active');
        }

        return $this->with(status: JobStatus::Expired);
    }

    /** A live job whose core content changed goes back to review. @throws StateConflict `job-not-active` */
    public function sendForReview(): self
    {
        if ($this->status !== JobStatus::Active) {
            throw new StateConflict('Only a live job goes back to review.', 'job-not-active');
        }

        return $this->with(status: JobStatus::PendingReview);
    }

    /** Only a draft is deleted; every other job is closed, so the history of applications stays whole. */
    public function canBeDeleted(): bool
    {
        return $this->status === JobStatus::Draft;
    }

    private function goLiveOrToReview(bool $requiresApproval, DateTimeImmutable $at): self
    {
        if ($requiresApproval) {
            return $this->with(status: JobStatus::PendingReview, statusReason: null);
        }

        return $this->with(status: JobStatus::Active, statusReason: null, publishedAt: $this->publishedAt ?? $at);
    }

    private function with(
        ?string $slug = null,
        ?JobStatus $status = null,
        ?JobContent $content = null,
        ?DateTimeImmutable $publishedAt = null,
        string|false|null $statusReason = false,
    ): self {
        return new self(
            $this->id, $this->companyId, $slug ?? $this->slug, $status ?? $this->status, $statusReason === false ? $this->statusReason : $statusReason,
            $content ?? $this->content, $this->createdByUserId, $publishedAt ?? $this->publishedAt, $this->reviewedAt, $this->reviewedByAdminId,
            $this->createdAt, $this->updatedAt,
        );
    }
}
