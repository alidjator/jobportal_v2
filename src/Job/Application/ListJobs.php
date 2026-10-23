<?php

namespace Src\Job\Application;

use DateTimeImmutable;
use Src\Job\Domain\JobStatus;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Application\Recruitment\JobHires;
use Src\Shared\Application\References\LocationReferences;
use Src\Shared\Domain\Exceptions\AccessDenied;

/**
 * The jobs of the member's company. A company admin sees all of them (or only their own with `assignedToMe`); a recruiter always sees only the ones assigned
 * to them. Each row says how many hires the job has, who created it and where it is.
 */
final readonly class ListJobs
{
    public function __construct(
        private JobAccess $access,
        private JobReader $reader,
        private JobHires $hires,
        private CompanyMemberAccounts $accounts,
        private LocationReferences $locations,
    ) {}

    /**
     * @return Page<JobListItem>
     *
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     */
    public function execute(Actor $actor, ?JobStatus $status, ?string $search, bool $assignedToMe, JobListSort $sort, PageRequest $request, ?DateTimeImmutable $deadlineTo = null, bool $withoutApplications = false, JobListFilters $filters = new JobListFilters): Page
    {
        $member = $this->access->member($actor);

        $page = $this->reader->page(
            new JobListQuery($member->companyId, $member->viewsAll() && ! $assignedToMe ? null : $member->userId, $status, $search, $sort, $deadlineTo, $withoutApplications, $filters),
            $request,
        );

        $ids = array_map(static fn (JobListItem $item): int => $item->id, $page->items);
        $hires = $this->hires->countPerJob($ids);
        $creators = $this->accounts->summaries(array_values(array_unique(array_filter(array_map(static fn (JobListItem $item): ?int => $item->createdBy, $page->items)))));
        $provinces = $this->locations->provinceNames($this->distinct($page->items, static fn (JobListItem $item): ?int => $item->provinceId));
        $cities = $this->locations->cityNames($this->distinct($page->items, static fn (JobListItem $item): ?int => $item->cityId));

        return new Page(array_map(
            static fn (JobListItem $item): JobListItem => $item->withHires($hires[$item->id] ?? 0)->withNames(
                $item->createdBy === null || ! isset($creators[$item->createdBy]) ? null : $creators[$item->createdBy]->name,
                $item->provinceId === null ? null : ($provinces[$item->provinceId] ?? null),
                $item->cityId === null ? null : ($cities[$item->cityId] ?? null),
            ),
            $page->items,
        ), $page->total, $page->request);
    }

    /**
     * @param  list<JobListItem>  $items
     * @param  callable(JobListItem): ?int  $pick
     * @return list<int>
     */
    private function distinct(array $items, callable $pick): array
    {
        return array_values(array_unique(array_filter(array_map($pick, $items), static fn (?int $id): bool => $id !== null)));
    }
}
