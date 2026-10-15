<?php

namespace Src\Job\Application\Reports;

use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;

interface JobReportReader
{
    /**
     * The jobs with open reports, without their company (the use case adds it through Company): jobs on hold first, then the most reported, then the most
     * recently reported.
     *
     * @return Page<ReportedJob>
     */
    public function reportedJobs(PageRequest $request): Page;

    /**
     * Every report of one job, whatever its status, the newest first.
     *
     * @return Page<JobReportEntry>
     */
    public function reportsOfJob(int $jobId, PageRequest $request): Page;
}
