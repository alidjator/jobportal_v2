<?php

namespace Src\Job\Application\Reports;

use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** An admin holding `jobs.manage` lists the jobs with open reports. */
final readonly class ListReportedJobs
{
    public function __construct(private JobReportReader $reader, private CompanyDirectory $companies) {}

    /**
     * @return Page<ReportedJob>
     *
     * @throws AccessDenied when the actor is not an admin
     */
    public function execute(Actor $actor, PageRequest $request): Page
    {
        if (! $actor->isAdmin()) {
            throw new AccessDenied('Only an administrator can read the job reports.');
        }

        $page = $this->reader->reportedJobs($request);
        $summaries = $this->companies->summaries(array_values(array_unique(array_map(static fn (ReportedJob $job): int => $job->companyId, $page->items))));

        return new Page(
            array_map(static fn (ReportedJob $job): ReportedJob => $job->withCompany($summaries[$job->companyId] ?? null), $page->items),
            $page->total,
            $page->request,
        );
    }
}
