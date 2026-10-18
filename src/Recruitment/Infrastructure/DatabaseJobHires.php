<?php

namespace Src\Recruitment\Infrastructure;

use Illuminate\Database\ConnectionInterface;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Recruitment\JobHires;

final readonly class DatabaseJobHires implements JobHires
{
    public function __construct(private ConnectionInterface $connection) {}

    public function countPerJob(array $jobIds): array
    {
        if ($jobIds === []) {
            return [];
        }

        $counts = [];

        foreach ($this->connection->table('recruitment_applications')
            ->whereIn('job_id', $jobIds)
            ->where('stage_type', StageType::Hired->value)
            ->groupBy('job_id')
            ->get(['job_id', $this->connection->raw('COUNT(*) as total')]) as $row) {
            $counts[(int) $row->job_id] = (int) $row->total;
        }

        return $counts;
    }
}
