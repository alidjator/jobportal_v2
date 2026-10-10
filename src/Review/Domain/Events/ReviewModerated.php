<?php

namespace Src\Review\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** An admin decided on a review, a reply or a report. `action` is one of the constants; `subjectId` is the review id (or the report id for a dismissed report). */
final readonly class ReviewModerated implements DomainEvent
{
    public const string REVIEW_APPROVED = 'review_approved';

    public const string REVIEW_REJECTED = 'review_rejected';

    public const string REVIEW_REMOVED = 'review_removed';

    public const string REPLY_APPROVED = 'reply_approved';

    public const string REPLY_REJECTED = 'reply_rejected';

    public const string REPLY_REMOVED = 'reply_removed';

    public const string REPORT_DISMISSED = 'report_dismissed';

    public function __construct(
        public string $action,
        public int $subjectId,
        public int $companyId,
        public int $adminId,
        public ?string $reason,
        public ?string $ipAddress,
        public ?string $userAgent,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
