<?php

namespace Src\Job\Application;

use Src\Job\Application\Views\JobViewLog;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Dashboard\DashboardWindow;
use Src\Shared\Application\Recruitment\ApplicationCounts;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * A member reads how one job they may see did over the last 30 Jakarta days, the same window and counting as the job performance card of the dashboard, but with the
 * daily series of this job alone.
 */
final readonly class GetJobPerformance
{
    public function __construct(
        private JobAccess $access,
        private JobViewLog $views,
        private ApplicationCounts $applications,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound when the member cannot see the job
     */
    public function execute(Actor $actor, int $jobId): JobPerformance
    {
        $job = $this->access->job($this->access->member($actor), $jobId);
        $window = DashboardWindow::at($this->clock->now());
        $viewsPerDay = $this->views->dailyViews($jobId, $window->from);
        $applicationsPerDay = $this->applications->perDay([$jobId], $window->fromUtc);

        return new JobPerformance(
            $jobId,
            $job->content->title,
            $job->status,
            $window->from,
            $window->today,
            array_sum($viewsPerDay),
            array_sum($applicationsPerDay),
            $window->series($viewsPerDay),
            $window->series($applicationsPerDay),
        );
    }
}
