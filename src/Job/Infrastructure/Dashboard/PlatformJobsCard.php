<?php

namespace Src\Job\Infrastructure\Dashboard;

use Illuminate\Database\ConnectionInterface;
use Src\Job\Domain\JobStatus;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Dashboard\DashboardAudience;
use Src\Shared\Application\Dashboard\DashboardCardProvider;
use Src\Shared\Application\Dashboard\DashboardScope;
use Src\Shared\Application\Dashboard\DashboardWindow;

/** The jobs of the platform: all, the live ones, and how many were created on each of the last 30 days. */
final readonly class PlatformJobsCard implements DashboardCardProvider
{
    public function __construct(private ConnectionInterface $connection, private Clock $clock) {}

    public function key(): string
    {
        return 'jobs';
    }

    public function audiences(): array
    {
        return [DashboardAudience::Admin];
    }

    public function permission(): ?string
    {
        return null;
    }

    public function build(DashboardScope $scope): array
    {
        $window = DashboardWindow::at($this->clock->now());
        $perDay = [];

        foreach ($this->connection->table('job_jobs')
            ->where('created_at', '>=', $window->fromUtc->format('Y-m-d H:i:s'))
            ->groupBy('day')
            ->get([$this->connection->raw(DashboardWindow::dayOf('created_at').' as day'), $this->connection->raw('COUNT(*) as total')]) as $row) {
            $perDay[(string) $row->day] = (int) $row->total;
        }

        return [
            'total' => $this->connection->table('job_jobs')->count(),
            'live' => $this->connection->table('job_jobs')->where('status', JobStatus::Active->value)->count(),
            'daily' => $window->series($perDay),
        ];
    }
}
