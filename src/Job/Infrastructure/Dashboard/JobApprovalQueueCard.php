<?php

namespace Src\Job\Infrastructure\Dashboard;

use Illuminate\Database\ConnectionInterface;
use Src\Job\Application\JobPermissions;
use Src\Job\Domain\JobStatus;
use Src\Shared\Application\Dashboard\DashboardAudience;
use Src\Shared\Application\Dashboard\DashboardCardProvider;
use Src\Shared\Application\Dashboard\DashboardScope;

/** The jobs that wait for an admin's decision; shown only to an admin who may manage jobs. */
final readonly class JobApprovalQueueCard implements DashboardCardProvider
{
    public function __construct(private ConnectionInterface $connection) {}

    public function key(): string
    {
        return 'jobs_queue';
    }

    public function audiences(): array
    {
        return [DashboardAudience::Admin];
    }

    public function permission(): string
    {
        return JobPermissions::JOBS_MANAGE;
    }

    public function build(DashboardScope $scope): array
    {
        return ['waiting_for_approval' => $this->connection->table('job_jobs')->where('status', JobStatus::PendingReview->value)->count()];
    }
}
