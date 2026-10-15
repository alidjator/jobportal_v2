<?php

namespace Src\Job\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Src\Job\Domain\JobReportReason;
use Src\Job\Domain\JobReportRepository;
use Src\Job\Domain\JobReportStatus;

final readonly class DatabaseJobReportRepository implements JobReportRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function hasOpenReport(int $jobId, int $reporterUserId): bool
    {
        return $this->connection->table('job_reports')
            ->where('job_id', $jobId)->where('reporter_user_id', $reporterUserId)->where('status', JobReportStatus::Open->value)
            ->exists();
    }

    public function add(int $jobId, int $reporterUserId, JobReportReason $reason, ?string $note, DateTimeImmutable $at): int
    {
        $time = $at->format(self::TIME_FORMAT);

        return (int) $this->connection->table('job_reports')->insertGetId([
            'job_id' => $jobId,
            'reporter_user_id' => $reporterUserId,
            'reason' => $reason->value,
            'note' => $note,
            'status' => JobReportStatus::Open->value,
            'created_at' => $time,
            'updated_at' => $time,
        ]);
    }

    public function distinctOpenReporters(int $jobId): int
    {
        return (int) $this->connection->table('job_reports')
            ->where('job_id', $jobId)->where('status', JobReportStatus::Open->value)->whereNotNull('reporter_user_id')
            ->distinct()->count('reporter_user_id');
    }

    public function closeOpen(int $jobId, JobReportStatus $status, int $adminId, DateTimeImmutable $at): array
    {
        $open = $this->connection->table('job_reports')->where('job_id', $jobId)->where('status', JobReportStatus::Open->value);
        $reporters = array_values(array_unique(array_map('intval', (clone $open)->whereNotNull('reporter_user_id')->pluck('reporter_user_id')->all())));
        $time = $at->format(self::TIME_FORMAT);

        $closed = $open->update(['status' => $status->value, 'decided_by_admin_id' => $adminId, 'decided_at' => $time, 'updated_at' => $time]);

        return ['closed' => $closed, 'reporters' => $reporters];
    }

    public function eraseReporter(int $userId): void
    {
        $this->connection->table('job_reports')->where('reporter_user_id', $userId)->update(['reporter_user_id' => null, 'note' => null]);
    }
}
