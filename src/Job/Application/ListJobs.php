<?php

namespace Src\Job\Application;

use DateTimeImmutable;
use Src\Job\Domain\JobStatus;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Application\Recruitment\JobHires;
use Src\Shared\Domain\Exceptions\AccessDenied;

/**
 * The jobs of the member's company. A company admin sees all of them (or only their own with `assignedToMe`); a recruiter always sees only the ones assigned
 * to them. Each row says how many hires the job has.
 */
final readonly class ListJobs
{
    public function __construct(
        private JobAccess $access,
        private JobReader $reader,
        private JobHires $hires,
    ) {}

    /**
     * @return Page<JobListItem>
     *
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     */
    public function execute(Actor $actor, ?JobStatus $status, ?string $search, bool $assignedToMe, JobListSort $sort, PageRequest $request, ?DateTimeImmutable $deadlineTo = null, bool $withoutApplications = false): Page
    {
        $member = $this->access->member($actor);

        $page = $this->reader->page(
            new JobListQuery($member->companyId, $member->viewsAll() && ! $assignedToMe ? null : $member->userId, $status, $search, $sort, $deadlineTo, $withoutApplications),
            $request,
        );

        $hires = $this->hires->countPerJob(array_map(static fn (JobListItem $item): int => $item->id, $page->items));

        return new Page(array_map(static fn (JobListItem $item): JobListItem => $item->withHires($hires[$item->id] ?? 0), $page->items), $page->total, $page->request);
    }
}
