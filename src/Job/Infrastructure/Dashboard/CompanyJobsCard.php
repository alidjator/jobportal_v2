<?php

namespace Src\Job\Infrastructure\Dashboard;

use Illuminate\Database\ConnectionInterface;
use Src\Job\Domain\JobStatus;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Dashboard\DashboardAudience;
use Src\Shared\Application\Dashboard\DashboardCardProvider;
use Src\Shared\Application\Dashboard\DashboardScope;

/** The jobs the member may see, counted by status. */
final readonly class CompanyJobsCard implements DashboardCardProvider
{
    public function __construct(private ConnectionInterface $connection) {}

    public function key(): string
    {
        return 'jobs';
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
        $byStatus = array_fill_keys(array_map(fn (JobStatus $status): string => $status->value, JobStatus::cases()), 0);

        foreach ($this->connection->table('job_jobs')
            ->where('company_id', $scope->companyId)
            ->when($scope->jobIds !== null, fn ($query) => $query->whereIn('id', $scope->jobIds ?? []))
            ->groupBy('status')
            ->get(['status', $this->connection->raw('COUNT(*) as total')]) as $row) {
            $byStatus[(string) $row->status] = (int) $row->total;
        }

        return ['by_status' => $byStatus, 'total' => array_sum($byStatus)];
    }
}
