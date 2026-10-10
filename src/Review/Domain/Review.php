<?php

namespace Src\Review\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A job seeker's review of a company they applied to. It waits (`pending`) until an admin publishes it; a rejected one can be edited and sent again; a published
 * one that is edited waits again; a removed one is final. The author's name is shown with a published review.
 */
final readonly class Review
{
    public function __construct(
        public int $companyId,
        public int $userId,
        public ReviewContent $content,
        public ReviewStatus $status,
        public ?ModerationReason $reason,
        public ?string $note,
        public ?int $moderatedByAdminId,
        public ?DateTimeImmutable $moderatedAt,
        public ?DateTimeImmutable $publishedAt,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?int $id = null,
    ) {}

    public static function write(int $companyId, int $userId, ReviewContent $content, DateTimeImmutable $now): self
    {
        return new self($companyId, $userId, $content, ReviewStatus::Pending, null, null, null, null, null, $now, $now);
    }

    public function isPublic(): bool
    {
        return $this->status === ReviewStatus::Published;
    }

    /** @throws StateConflict `review-not-editable` for a removed review */
    public function edited(ReviewContent $content, DateTimeImmutable $now): self
    {
        if ($this->status === ReviewStatus::Removed) {
            throw new StateConflict('A review that was taken down cannot be edited.', 'review-not-editable');
        }

        return $this->with(['content' => $content, 'status' => ReviewStatus::Pending, 'reason' => null, 'note' => null, 'moderatedByAdminId' => null, 'moderatedAt' => null, 'publishedAt' => null, 'updatedAt' => $now]);
    }

    /** @throws StateConflict `review-not-pending` */
    public function approved(int $adminId, DateTimeImmutable $now): self
    {
        $this->mustBe(ReviewStatus::Pending, 'review-not-pending', 'Only a review that is waiting can be published.');

        return $this->with(['status' => ReviewStatus::Published, 'reason' => null, 'note' => null, 'moderatedByAdminId' => $adminId, 'moderatedAt' => $now, 'publishedAt' => $now, 'updatedAt' => $now]);
    }

    /** @throws StateConflict `review-not-pending` */
    public function rejected(ModerationReason $reason, ?string $note, int $adminId, DateTimeImmutable $now): self
    {
        $this->mustBe(ReviewStatus::Pending, 'review-not-pending', 'Only a review that is waiting can be rejected.');

        return $this->with(['status' => ReviewStatus::Rejected, 'reason' => $reason, 'note' => $note, 'moderatedByAdminId' => $adminId, 'moderatedAt' => $now, 'updatedAt' => $now]);
    }

    /** @throws StateConflict `review-not-published` */
    public function removed(ModerationReason $reason, ?string $note, int $adminId, DateTimeImmutable $now): self
    {
        $this->mustBe(ReviewStatus::Published, 'review-not-published', 'Only a published review can be taken down.');

        return $this->with(['status' => ReviewStatus::Removed, 'reason' => $reason, 'note' => $note, 'moderatedByAdminId' => $adminId, 'moderatedAt' => $now, 'updatedAt' => $now]);
    }

    private function mustBe(ReviewStatus $status, string $code, string $message): void
    {
        if ($this->status !== $status) {
            throw new StateConflict($message, $code);
        }
    }

    /** @param  array<string, mixed>  $changes  constructor arguments by name */
    private function with(array $changes): self
    {
        return new self(...[...get_object_vars($this), ...$changes]);
    }
}
