<?php

namespace Src\Job\Application;

use Src\Job\Domain\JobStatus;

/**
 * What a member asks of the job list of their company. `assignedToUserId` limits the list to the jobs assigned to that account: it is always set for a
 * recruiter (they see only their jobs) and is set for a company admin who asked for `assigned_to_me`. `search` matches the title, whatever the letter case.
 */
final readonly class JobListQuery
{
    public function __construct(
        public int $companyId,
        public ?int $assignedToUserId = null,
        public ?JobStatus $status = null,
        public ?string $search = null,
        public JobListSort $sort = JobListSort::UpdatedAtDescending,
    ) {}
}
