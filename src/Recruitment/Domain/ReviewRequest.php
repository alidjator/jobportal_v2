<?php

namespace Src\Recruitment\Domain;

use DateTimeImmutable;

/**
 * A request of a recruiter to the hiring managers of a job to review chosen applications by `$dueOn` (a date in Jakarta time, read as midnight of that day). The applications and
 * the reviewers are kept next to it. Answers change nothing about the applications.
 */
final readonly class ReviewRequest
{
    public const int MESSAGE_MAX = 1000;

    public const int APPLICATIONS_MAX = 50;

    public const int DUE_MAX_DAYS = 30;

    public function __construct(
        public int $companyId,
        public int $jobId,
        public int $requestedBy,
        public ?string $message,
        public DateTimeImmutable $dueOn,
        public ReviewRequestStatus $status,
        public DateTimeImmutable $createdAt,
        public ?int $id = null,
        public ?DateTimeImmutable $remindedAt = null,
        public ?DateTimeImmutable $overdueNotifiedAt = null,
    ) {}

    /** Whether answers are still welcome: only a closed request refuses them. */
    public function acceptsAnswers(): bool
    {
        return $this->status !== ReviewRequestStatus::Closed;
    }

    /** Whether the due date passed without every answer: an open request after the end of its due day. */
    public function isOverdue(DateTimeImmutable $today): bool
    {
        return $this->status === ReviewRequestStatus::Open && $today > $this->dueOn;
    }
}
