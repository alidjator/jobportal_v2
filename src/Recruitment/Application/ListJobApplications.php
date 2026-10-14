<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\StageRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * The applications of one job for a member who may see the job: a company admin sees every job of the company, anyone else only the jobs assigned to them.
 * The counts per stage cover every application of the job, whatever the filters.
 */
final readonly class ListJobApplications
{
    public function __construct(
        private RecruitmentAccess $access,
        private RecruiterApplicationReader $reader,
        private InterviewResults $results,
        private StageRepository $stages,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound when the member cannot see the job
     */
    public function execute(Actor $actor, RecruiterApplicationQuery $query, PageRequest $request): JobApplicationList
    {
        $member = $this->access->viewer($actor);
        $this->access->job($member, $query->jobId);

        $counts = $this->reader->countByStage($query->jobId);

        $page = $this->reader->page($query, $request);
        $results = $this->results->latestForApplications(array_map(static fn (RecruiterApplicationRow $row): int => $row->id, $page->items), $member->userId);

        return new JobApplicationList(
            new Page(array_map(static fn (RecruiterApplicationRow $row): RecruiterApplicationRow => $row->withInterviewResult($results[$row->id] ?? null), $page->items), $page->total, $page->request),
            array_map(
                static fn ($stage): StageCount => new StageCount((int) $stage->id, $stage->type, $stage->name, $counts[(int) $stage->id] ?? 0),
                $this->stages->forCompany($member->companyId),
            ),
        );
    }
}
