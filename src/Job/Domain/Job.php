<?php

namespace Src\Job\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A job a company posts. `slug` is a cosmetic form of the title (the public identifier is the id); it follows the title. The content can be changed
 * only while the status allows it. `publishedAt` is set once, when the job first goes live (directly, or by the first approval), and never changes.
 * An admin's decision stores when it was made and by whom (`reviewedAt`, `reviewedByAdminId`) and, for a rejection or a take-down, the reason.
 * `reportHoldAt` is set once when enough job seekers reported the job: while it is set the job is not live for the public, whatever its status.
 * `requisitionId` names the requisition the job was opened for (ERP tenants; null for any other job). `hiringManagerFromStageId` is the stage of Recruitment from
 * which a hiring manager of the job sees applications (null: the company's first interview stage).
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
        public ?DateTimeImmutable $reportHoldAt = null,
        public ?int $requisitionId = null,
        public ?int $hiringManagerFromStageId = null,
    ) {}

    public static function draft(int $companyId, string $slug, JobContent $content, int $createdByUserId, ?int $requisitionId = null): self
    {
        return new self(null, $companyId, $slug, JobStatus::Draft, null, $content, $createdByUserId, null, null, null, null, null, null, $requisitionId);
    }

    /** @throws StateConflict `job-not-editable` */
    public function assertEditable(): void
    {
        if (! $this->status->isEditable()) {
            throw new StateConflict('A job in this status cannot be edited.', 'job-not-editable');
        }
    }

    /** The boundary may change in any status: it is not part of the content a posting freezes. */
    public function withHiringManagerBoundary(?int $stageId): self
    {
        return $this->with(hiringManagerFromStageId: $stageId);
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

    /** An admin approves a job waiting for review: it goes live, and the first approval sets the time it was published. @throws StateConflict `job-not-pending-review` */
    public function approve(int $adminId, DateTimeImmutable $at): self
    {
        $this->assertPendingReview();

        return $this->with(status: JobStatus::Active, statusReason: null, publishedAt: $this->publishedAt ?? $at, reviewedAt: $at, reviewedByAdminId: $adminId);
    }

    /** An admin rejects a job waiting for review. The company can edit it and publish it again. @throws StateConflict `job-not-pending-review` */
    public function reject(string $reason, int $adminId, DateTimeImmutable $at): self
    {
        $this->assertPendingReview();

        return $this->with(status: JobStatus::Rejected, statusReason: $reason, reviewedAt: $at, reviewedByAdminId: $adminId);
    }

    /** An admin takes a live job down. Only an admin can restore it. @throws StateConflict `job-not-active` */
    public function takeDown(string $reason, int $adminId, DateTimeImmutable $at): self
    {
        if ($this->status !== JobStatus::Active) {
            throw new StateConflict('Only a live job can be taken down.', 'job-not-active');
        }

        return $this->with(status: JobStatus::TakenDown, statusReason: $reason, reviewedAt: $at, reviewedByAdminId: $adminId, reportHoldAt: null);
    }

    public function isOnHold(): bool
    {
        return $this->reportHoldAt !== null;
    }

    /** Enough reporters put a live job on hold; a job already on hold keeps its first time. */
    public function hold(DateTimeImmutable $at): self
    {
        return $this->isOnHold() ? $this : $this->with(reportHoldAt: $at);
    }

    public function releaseHold(): self
    {
        return $this->with(reportHoldAt: null);
    }

    /**
     * An admin lifts the take-down of a job: it goes live again, or becomes `expired` when its deadline has passed (the company can then reopen it).
     *
     * @throws StateConflict `job-not-taken-down`
     */
    public function restore(int $adminId, DateTimeImmutable $at, bool $deadlinePassed): self
    {
        if ($this->status !== JobStatus::TakenDown) {
            throw new StateConflict('Only a job that was taken down can be restored.', 'job-not-taken-down');
        }

        return $this->with(
            status: $deadlinePassed ? JobStatus::Expired : JobStatus::Active,
            statusReason: null,
            reviewedAt: $at,
            reviewedByAdminId: $adminId,
        );
    }

    /** Only a draft is deleted; every other job is closed, so the history of applications stays whole. */
    public function canBeDeleted(): bool
    {
        return $this->status === JobStatus::Draft;
    }

    private function assertPendingReview(): void
    {
        if ($this->status !== JobStatus::PendingReview) {
            throw new StateConflict('Only a job waiting for review can be decided.', 'job-not-pending-review');
        }
    }

    private function goLiveOrToReview(bool $requiresApproval, DateTimeImmutable $at): self
    {
        if ($requiresApproval) {
            return $this->with(status: JobStatus::PendingReview, statusReason: null);
        }

        return $this->with(status: JobStatus::Active, statusReason: null, publishedAt: $this->publishedAt ?? $at);
    }

    /** A copy with some properties replaced (named arguments; `null` is a value, so a reason can be cleared). */
    private function with(mixed ...$changes): self
    {
        return new self(...[...get_object_vars($this), ...$changes]);
    }
}
