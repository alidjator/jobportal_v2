<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\StageRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * The applications of one job for a member who may see the job: a company admin sees every job of the company, anyone else only the jobs assigned to them.
 * The counts per stage cover every application of the job, whatever the filters; a hiring manager bound to the stage boundary of the job sees (and is counted)
 * only the applications that carry the marker.
 */
final readonly class ListJobApplications
{
    public function __construct(
        private RecruitmentAccess $access,
        private RecruiterApplicationReader $reader,
        private InterviewResults $results,
        private StageRepository $stages,
        private OtherApplications $others,
        private HiringManagerScope $scope,
        private Settings $settings,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound when the member cannot see the job
     */
    public function execute(Actor $actor, RecruiterApplicationQuery $query, PageRequest $request): JobApplicationList
    {
        $member = $this->access->viewer($actor);
        $this->access->job($member, $query->jobId);
        $bound = $this->scope->isBound($member, $query->jobId);
        $query = $query->withStaleAfterDays($this->settings->integer(RecruitmentSettings::STALE_AFTER_DAYS, $member->companyId))->withHiringManagerScope($bound);

        $counts = $this->reader->countByStage($query->jobId, $bound);

        $page = $this->reader->page($query, $request);
        $ids = array_map(static fn (RecruiterApplicationRow $row): int => $row->id, $page->items);
        $results = $this->results->latestForApplications($ids, $member->userId);
        $others = $this->others->counts($member, $ids);

        return new JobApplicationList(
            new Page(array_map(static fn (RecruiterApplicationRow $row): RecruiterApplicationRow => $row->withInterviewResult($results[$row->id] ?? null)->withOtherApplications($others[$row->id] ?? 0), $page->items), $page->total, $page->request),
            array_map(
                static fn ($stage): StageCount => new StageCount((int) $stage->id, $stage->type, $stage->name, $counts[(int) $stage->id] ?? 0),
                $this->stages->forCompany($member->companyId),
            ),
        );
    }
}
