<?php

namespace Src\Job\Application;

use Src\Job\Domain\JobStatus;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;

/**
 * The jobs of the member's company. A company admin sees all of them (or only their own with `assignedToMe`); a recruiter always sees only the ones assigned
 * to them.
 */
final readonly class ListJobs
{
    public function __construct(
        private JobAccess $access,
        private JobReader $reader,
    ) {}

    /**
     * @return Page<JobListItem>
     *
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     */
    public function execute(Actor $actor, ?JobStatus $status, ?string $search, bool $assignedToMe, JobListSort $sort, PageRequest $request): Page
    {
        $member = $this->access->member($actor);

        return $this->reader->page(
            new JobListQuery($member->companyId, $member->isCompanyAdmin && ! $assignedToMe ? null : $member->userId, $status, $search, $sort),
            $request,
        );
    }
}
