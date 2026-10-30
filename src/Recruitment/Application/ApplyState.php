<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;

/**
 * Whether the job seeker could apply to a job now, and what stands in the way (`docs/design/phase-8-job-posting.md` section 6). A reason has the code the apply request would
 * answer; `missing` comes with `profile-incomplete` and `reapply_on` (a date) with `already-applied` while a waiting period runs.
 */
final readonly class ApplyState
{
    /**
     * @param  list<array{code: string, missing?: list<string>, reapply_on?: string}>  $reasons
     * @param  array{id: int, status: string, applied_at: DateTimeImmutable}|null  $application  the applicant's live application for the job
     */
    public function __construct(public array $reasons, public ?array $application, public int $applicationsLeftToday) {}

    public function canApply(): bool
    {
        return $this->reasons === [];
    }
}
