<?php

namespace Src\Recruitment\Infrastructure;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Src\Shared\Application\Dashboard\DashboardWindow;
use Src\Shared\Application\Recruitment\ApplicationCounts;

final readonly class DatabaseApplicationCounts implements ApplicationCounts
{
    public function __construct(private ConnectionInterface $connection) {}

    public function perJob(array $jobIds, DateTimeImmutable $since): array
    {
        if ($jobIds === []) {
            return [];
        }

        $counts = [];

        foreach ($this->connection->table('recruitment_applications')
            ->whereIn('job_id', $jobIds)
            ->where('applied_at', '>=', $since->format('Y-m-d H:i:s'))
            ->groupBy('job_id')
            ->get(['job_id', $this->connection->raw('COUNT(*) as total')]) as $row) {
            $counts[(int) $row->job_id] = (int) $row->total;
        }

        return $counts;
    }

    public function perDay(array $jobIds, DateTimeImmutable $since): array
    {
        if ($jobIds === []) {
            return [];
        }

        $counts = [];

        foreach ($this->connection->table('recruitment_applications')
            ->whereIn('job_id', $jobIds)
            ->where('applied_at', '>=', $since->format('Y-m-d H:i:s'))
            ->groupBy('day')
            ->get([$this->connection->raw(DashboardWindow::dayOf('applied_at').' as day'), $this->connection->raw('COUNT(*) as total')]) as $row) {
            $counts[(string) $row->day] = (int) $row->total;
        }

        return $counts;
    }
}
