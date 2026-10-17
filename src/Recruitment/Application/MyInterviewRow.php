<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;
use Src\Recruitment\Domain\InterviewFormat;
use Src\Recruitment\Domain\InterviewStatus;

/**
 * One interview of the "my interviews" list. `role` is `main` or `panel` for the member's place on the panel (null when they do not sit on it); `scorecard` is
 * `pending` (owed now), `sent`, or `not_open` (not yet, or the application is final), null without a place on the panel; `rsvp` is the member's own answer to the invitation
 * (`pending`, `accepted`, `declined`), null without a place on the panel.
 */
final readonly class MyInterviewRow
{
    public function __construct(
        public int $id,
        public int $applicationId,
        public int $jobId,
        public ?string $jobTitle,
        public ?string $candidateName,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public InterviewStatus $status,
        public InterviewFormat $format,
        public ?string $role,
        public ?string $scorecard,
        public ?string $rsvp = null,
    ) {}

    public function withJobTitle(?string $title): self
    {
        return new self($this->id, $this->applicationId, $this->jobId, $title, $this->candidateName, $this->startsAt, $this->endsAt, $this->status, $this->format, $this->role, $this->scorecard, $this->rsvp);
    }
}
