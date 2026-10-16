<?php

namespace Src\Job\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\Job\Domain\JobStatus;
use Src\Shared\Application\Job\RequisitionJobs;

final readonly class DatabaseRequisitionJobs implements RequisitionJobs
{
    public function __construct(private ConnectionInterface $connection) {}

    public function openVacancies(int $requisitionId, ?int $exceptJobId = null): int
    {
        $query = $this->connection->table('job_jobs')->where('requisition_id', $requisitionId)->whereNotIn('status', [JobStatus::Closed->value, JobStatus::Expired->value]);

        if ($exceptJobId !== null) {
            $query->where('id', '!=', $exceptJobId);
        }

        return (int) $query->sum('vacancies');
    }

    public function count(int $requisitionId): int
    {
        return $this->connection->table('job_jobs')->where('requisition_id', $requisitionId)->count();
    }
}
