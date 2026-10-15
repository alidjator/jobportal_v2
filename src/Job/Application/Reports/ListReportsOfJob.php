<?php

namespace Src\Job\Application\Reports;

use Src\Job\Domain\JobRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** An admin holding `jobs.manage` reads every report of one job. */
final readonly class ListReportsOfJob
{
    public function __construct(private JobReportReader $reader, private JobRepository $jobs) {}

    /**
     * @return Page<JobReportEntry>
     *
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound when the job does not exist
     */
    public function execute(Actor $actor, int $jobId, PageRequest $request): Page
    {
        if (! $actor->isAdmin()) {
            throw new AccessDenied('Only an administrator can read the job reports.');
        }

        if ($this->jobs->find($jobId) === null) {
            throw EntityNotFound::of('Job', $jobId);
        }

        return $this->reader->reportsOfJob($jobId, $request);
    }
}
