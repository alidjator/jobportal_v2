<?php

namespace Src\Messaging\Application;

use Src\Messaging\Domain\SenderSide;

/**
 * Which conversations a participant sees: a job seeker, their own; a company admin, all of the company; a recruiter, those they started and those with a job
 * seeker who applied to a job assigned to them (`visibleSeekerIds`).
 */
final readonly class ConversationAudience
{
    /** @param  list<int>  $visibleSeekerIds  only for a recruiter */
    public function __construct(
        public SenderSide $side,
        public int $userId,
        public ?int $companyId,
        public bool $seesAllJobs,
        public array $visibleSeekerIds = [],
    ) {}
}
