<?php

namespace Src\Job\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Src\Job\Domain\JobAssignee;
use Src\Job\Domain\JobAssignmentRepository;
use Src\Job\Domain\JobAssignmentRole;

final readonly class DatabaseJobAssignmentRepository implements JobAssignmentRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function userIds(int $jobId): array
    {
        return array_map('intval', $this->connection->table('job_assignments')->where('job_id', $jobId)->orderBy('id')->pluck('user_id')->all());
    }

    public function assignees(int $jobId): array
    {
        return array_map(
            static fn (object $row): JobAssignee => new JobAssignee((int) $row->user_id, JobAssignmentRole::from((string) $row->role)),
            $this->connection->table('job_assignments')->where('job_id', $jobId)->orderBy('id')->get(['user_id', 'role'])->all(),
        );
    }

    public function jobIdsOf(int $userId): array
    {
        return array_map('intval', $this->connection->table('job_assignments')->where('user_id', $userId)->orderBy('job_id')->pluck('job_id')->all());
    }

    public function countsOfUsers(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $counts = [];

        foreach ($this->connection->table('job_assignments')->whereIn('user_id', $userIds)->groupBy('user_id')->selectRaw('user_id, count(*) as total')->get() as $row) {
            $counts[(int) $row->user_id] = (int) $row->total;
        }

        return $counts;
    }

    public function hiringManagerJobIdsOf(int $userId): array
    {
        return array_map('intval', $this->connection->table('job_assignments')->where('user_id', $userId)->where('role', JobAssignmentRole::HiringManager->value)->orderBy('job_id')->pluck('job_id')->all());
    }

    public function add(int $jobId, int $userId, ?int $assignedByUserId, DateTimeImmutable $at, JobAssignmentRole $role = JobAssignmentRole::Recruiter): void
    {
        $this->connection->table('job_assignments')->insertOrIgnore([
            'job_id' => $jobId,
            'user_id' => $userId,
            'assigned_by_user_id' => $assignedByUserId,
            'role' => $role->value,
            'created_at' => $at->format(self::TIME_FORMAT),
        ]);
    }

    public function changeRole(int $jobId, int $userId, JobAssignmentRole $role): void
    {
        $this->connection->table('job_assignments')->where('job_id', $jobId)->where('user_id', $userId)->update(['role' => $role->value]);
    }

    public function remove(int $jobId, int $userId): void
    {
        $this->connection->table('job_assignments')->where('job_id', $jobId)->where('user_id', $userId)->delete();
    }
}
