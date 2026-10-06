<?php

namespace Src\Job\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Src\Job\Domain\JobAssignmentRepository;

final readonly class DatabaseJobAssignmentRepository implements JobAssignmentRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function userIds(int $jobId): array
    {
        return array_map('intval', $this->connection->table('job_assignments')->where('job_id', $jobId)->orderBy('id')->pluck('user_id')->all());
    }

    public function jobIdsOf(int $userId): array
    {
        return array_map('intval', $this->connection->table('job_assignments')->where('user_id', $userId)->orderBy('job_id')->pluck('job_id')->all());
    }

    public function add(int $jobId, int $userId, ?int $assignedByUserId, DateTimeImmutable $at): void
    {
        $this->connection->table('job_assignments')->insertOrIgnore([
            'job_id' => $jobId,
            'user_id' => $userId,
            'assigned_by_user_id' => $assignedByUserId,
            'created_at' => $at->format(self::TIME_FORMAT),
        ]);
    }

    public function remove(int $jobId, int $userId): void
    {
        $this->connection->table('job_assignments')->where('job_id', $jobId)->where('user_id', $userId)->delete();
    }
}
