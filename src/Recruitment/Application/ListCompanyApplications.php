<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\StageRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Domain\Exceptions\AccessDenied;

/**
 * The applications of the company across its jobs (`docs/design/phase-8-applicant-search.md` section 4), for a member who may read applications. The scope is the jobs
 * the member may see, the rule of the job lists: every job for a company admin or `jobs.view_all`, otherwise the assigned jobs. On a job where the member is a bound
 * hiring manager only the applications that carry the marker are listed. Without a stage filter only active applications show (not hired, rejected or withdrawn).
 * `$query->jobIds`, when set, narrows the list to those of the jobs in scope; a job outside the scope is silently left out, as it does not exist for the member.
 * The counts per stage follow the search and the filters except the stage ones.
 */
final readonly class ListCompanyApplications
{
    public function __construct(
        private RecruitmentAccess $access,
        private RecruiterApplicationReader $reader,
        private ApplicationRowEnricher $enricher,
        private ApplicantFilterResolver $filters,
        private StageRepository $stages,
        private HiringManagerScope $scope,
        private JobDirectory $jobs,
        private Settings $settings,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     */
    public function execute(Actor $actor, RecruiterApplicationQuery $query, PageRequest $request): JobApplicationList
    {
        $member = $this->access->viewer($actor);
        $visible = $member->viewsAll() ? null : $this->jobs->assignedJobIds($member->companyId, $member->userId);
        $jobIds = $this->inScope($visible, $query->jobIds);

        $query = $query->acrossJobs($member->companyId, $jobIds)
            ->withStaleAfterDays($this->settings->integer(RecruitmentSettings::STALE_AFTER_DAYS, $member->companyId))
            ->withBoundJobs($this->scope->boundJobIds($member));
        $query = $query->withFilters($this->filters->resolve($member->companyId, $query->filters, $jobIds));

        $page = $this->enricher->enrich($member, $this->reader->page($query, $request));
        $titles = $this->jobs->titles(array_values(array_unique(array_map(static fn (RecruiterApplicationRow $row): int => $row->jobId, $page->items))));

        return new JobApplicationList(
            new Page(array_map(static fn (RecruiterApplicationRow $row): RecruiterApplicationRow => $row->withJobTitle($titles[$row->jobId] ?? null), $page->items), $page->total, $page->request),
            StageCount::forStages($this->stages->forCompany($member->companyId), $this->reader->countByStage($query)),
        );
    }

    /**
     * The jobs to list: the ones the member sees, narrowed to the ones asked for.
     *
     * @param  list<int>|null  $visible  null when the member sees every job
     * @param  list<int>|null  $asked
     * @return list<int>|null null when no limit applies
     */
    private function inScope(?array $visible, ?array $asked): ?array
    {
        if ($asked === null) {
            return $visible;
        }

        return $visible === null ? $asked : array_values(array_intersect($asked, $visible));
    }
}
