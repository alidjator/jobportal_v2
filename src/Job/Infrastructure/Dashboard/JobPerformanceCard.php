<?php

namespace Src\Job\Infrastructure\Dashboard;

use Illuminate\Database\ConnectionInterface;
use Src\Job\Domain\JobStatus;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Dashboard\DashboardAudience;
use Src\Shared\Application\Dashboard\DashboardCardProvider;
use Src\Shared\Application\Dashboard\DashboardScope;
use Src\Shared\Application\Dashboard\DashboardWindow;
use Src\Shared\Application\Recruitment\ApplicationCounts;

/**
 * How the jobs the member may see did in the last 30 days: per job the views, the applications and the applications per view (the live jobs and any job that was
 * looked at or applied to, the 20 most viewed), and the daily series of views and applications over all of them.
 */
final readonly class JobPerformanceCard implements DashboardCardProvider
{
    private const int LIMIT = 20;

    public function __construct(
        private ConnectionInterface $connection,
        private ApplicationCounts $applications,
        private Clock $clock,
    ) {}

    public function key(): string
    {
        return 'job_performance';
    }

    public function audiences(): array
    {
        return [DashboardAudience::Company];
    }

    public function permission(): ?string
    {
        return CompanyPermission::ApplicationsView->value;
    }

    public function build(DashboardScope $scope): array
    {
        $window = DashboardWindow::at($this->clock->now());

        $jobs = $this->connection->table('job_jobs')
            ->where('company_id', $scope->companyId)
            ->when($scope->jobIds !== null, fn ($query) => $query->whereIn('id', $scope->jobIds ?? []))
            ->get(['id', 'title', 'status']);
        $ids = $jobs->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

        $views = [];
        $viewsPerDay = [];

        if ($ids !== []) {
            foreach ($this->connection->table('job_views')
                ->whereIn('job_id', $ids)
                ->where('day', '>=', $window->from)
                ->groupBy('job_id', 'day')
                ->get(['job_id', 'day', $this->connection->raw('COUNT(*) as total')]) as $row) {
                $views[(int) $row->job_id] = ($views[(int) $row->job_id] ?? 0) + (int) $row->total;
                $viewsPerDay[(string) $row->day] = ($viewsPerDay[(string) $row->day] ?? 0) + (int) $row->total;
            }
        }

        $applications = $this->applications->perJob($ids, $window->fromUtc);
        $rows = [];

        foreach ($jobs as $job) {
            $id = (int) $job->id;
            $seen = $views[$id] ?? 0;
            $applied = $applications[$id] ?? 0;

            if ($job->status !== JobStatus::Active->value && $seen === 0 && $applied === 0) {
                continue;
            }

            $rows[] = [
                'job_id' => $id,
                'title' => (string) $job->title,
                'status' => (string) $job->status,
                'views' => $seen,
                'applications' => $applied,
                'applications_per_view' => $seen === 0 ? null : round($applied / $seen, 4),
            ];
        }

        usort($rows, fn (array $a, array $b): int => [$b['views'], $b['applications'], $a['job_id']] <=> [$a['views'], $a['applications'], $b['job_id']]);

        return [
            'jobs' => array_slice($rows, 0, self::LIMIT),
            'views_daily' => $window->series($viewsPerDay),
            'applications_daily' => $window->series($this->applications->perDay($ids, $window->fromUtc)),
        ];
    }
}
