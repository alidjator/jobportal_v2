<?php

namespace Src\Shared\Application\Assessment;

use DateTimeImmutable;

/**
 * What a recruiter enters to send a test: the test, when the candidate may start (null: now), the due time and the company members who score the
 * long-text answers, and where it is taken (`online`, or `onsite` at a named location). Whether they are acceptable is decided by Assessment.
 */
final readonly class AssessmentInvitationInput
{
    /** @param  list<int>  $assessorIds */
    public function __construct(
        public int $testId,
        public ?DateTimeImmutable $startsAt,
        public DateTimeImmutable $dueAt,
        public array $assessorIds = [],
        public string $mode = 'online',
        public ?string $location = null,
    ) {}
}
