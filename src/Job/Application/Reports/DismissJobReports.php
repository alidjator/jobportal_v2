<?php

namespace Src\Job\Application\Reports;

use Src\Job\Domain\Events\JobReportsClosed;
use Src\Job\Domain\JobReportRepository;
use Src\Job\Domain\JobReportStatus;
use Src\Job\Domain\JobRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** An admin holding `jobs.manage` decides the open reports of a job are unfounded: they close as `dismissed` and the hold, if any, is lifted. */
final readonly class DismissJobReports
{
    public function __construct(
        private JobRepository $jobs,
        private JobReportRepository $reports,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound when the job does not exist
     * @throws StateConflict `no-open-reports`
     */
    public function execute(Actor $actor, int $jobId, ?string $ipAddress, ?string $userAgent): void
    {
        if (! $actor->isAdmin()) {
            throw new AccessDenied('Only an administrator can decide about job reports.');
        }

        $adminId = (int) $actor->id;
        $now = $this->clock->now();

        $event = $this->transactions->run(function () use ($jobId, $adminId, $ipAddress, $userAgent, $now): JobReportsClosed {
            $this->jobs->lock($jobId);
            $job = $this->jobs->find($jobId) ?? throw EntityNotFound::of('Job', $jobId);

            $closure = $this->reports->closeOpen($jobId, JobReportStatus::Dismissed, $adminId, $now);

            if ($closure['closed'] === 0) {
                throw new StateConflict('This job has no open reports.', 'no-open-reports');
            }

            $released = $job->isOnHold();

            if ($released) {
                $this->jobs->save($job->releaseHold());
            }

            return new JobReportsClosed($jobId, $job->companyId, JobReportsClosed::DISMISSED, $closure['closed'], $released, $closure['reporters'], $adminId, $ipAddress, $userAgent, $now);
        });

        $this->events->dispatch([$event]);
    }
}
