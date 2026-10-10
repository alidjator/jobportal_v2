<?php

namespace Src\Review\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Exceptions\StateConflict;

/** A company's one reply to a review; it is moderated like the review, and editing a published reply sends it back to the queue. */
final readonly class Reply
{
    public function __construct(
        public int $reviewId,
        public string $body,
        public ReplyStatus $status,
        public ?ModerationReason $reason,
        public ?string $note,
        public int $writtenByUserId,
        public ?int $moderatedByAdminId,
        public ?DateTimeImmutable $moderatedAt,
        public ?DateTimeImmutable $publishedAt,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?int $id = null,
    ) {}

    public static function write(int $reviewId, string $body, int $userId, DateTimeImmutable $now): self
    {
        return new self($reviewId, $body, ReplyStatus::Pending, null, null, $userId, null, null, null, $now, $now);
    }

    public function isPublic(): bool
    {
        return $this->status === ReplyStatus::Published;
    }

    public function edited(string $body, int $userId, DateTimeImmutable $now): self
    {
        return $this->with(['body' => $body, 'status' => ReplyStatus::Pending, 'reason' => null, 'note' => null, 'writtenByUserId' => $userId, 'moderatedByAdminId' => null, 'moderatedAt' => null, 'publishedAt' => null, 'updatedAt' => $now]);
    }

    /** @throws StateConflict `reply-not-pending` */
    public function approved(int $adminId, DateTimeImmutable $now): self
    {
        $this->mustBePending('Only a reply that is waiting can be published.');

        return $this->with(['status' => ReplyStatus::Published, 'reason' => null, 'note' => null, 'moderatedByAdminId' => $adminId, 'moderatedAt' => $now, 'publishedAt' => $now, 'updatedAt' => $now]);
    }

    /** @throws StateConflict `reply-not-pending` */
    public function rejected(ModerationReason $reason, ?string $note, int $adminId, DateTimeImmutable $now): self
    {
        $this->mustBePending('Only a reply that is waiting can be rejected.');

        return $this->with(['status' => ReplyStatus::Rejected, 'reason' => $reason, 'note' => $note, 'moderatedByAdminId' => $adminId, 'moderatedAt' => $now, 'updatedAt' => $now]);
    }

    private function mustBePending(string $message): void
    {
        if ($this->status !== ReplyStatus::Pending) {
            throw new StateConflict($message, 'reply-not-pending');
        }
    }

    /** @param  array<string, mixed>  $changes  constructor arguments by name */
    private function with(array $changes): self
    {
        return new self(...[...get_object_vars($this), ...$changes]);
    }
}
