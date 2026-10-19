<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\StageRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * The applications of one job for a member who may see the job: a company admin sees every job of the company, anyone else only the jobs assigned to them.
 * The counts per stage follow the search and the filters except the stage ones, so each column shows what it would hold; a hiring manager bound to the stage
 * boundary of the job sees (and is counted) only the applications that carry the marker.
 */
final readonly class ListJobApplications
{
    public function __construct(
        private RecruitmentAccess $access,
        private RecruiterApplicationReader $reader,
        private ApplicationRowEnricher $enricher,
        private ApplicantFilterResolver $filters,
        private StageRepository $stages,
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
        $jobId = (int) $query->jobId;
        $this->access->job($member, $jobId);
        $bound = $this->scope->isBound($member, $jobId);
        $query = $query->withStaleAfterDays($this->settings->integer(RecruitmentSettings::STALE_AFTER_DAYS, $member->companyId))->withHiringManagerScope($bound);
        $query = $query->withFilters($this->filters->resolve($member->companyId, $query->filters, [$jobId]));

        return new JobApplicationList(
            $this->enricher->enrich($member, $this->reader->page($query, $request)),
            StageCount::forStages($this->stages->forCompany($member->companyId), $this->reader->countByStage($query)),
        );
    }
}
