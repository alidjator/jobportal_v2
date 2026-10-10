<?php

namespace Src\Job\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Job\Domain\SavedJob;
use Src\Job\Domain\SavedJobRepository;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use stdClass;

final readonly class DatabaseSavedJobRepository implements SavedJobRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function save(int $userId, int $jobId, DateTimeImmutable $savedAt): bool
    {
        // `ON CONFLICT DO NOTHING`: a unique violation would abort the surrounding transaction.
        return $this->connection->table('job_saved_jobs')->insertOrIgnore([
            'user_id' => $userId,
            'job_id' => $jobId,
            'saved_at' => $savedAt->setTimezone(new DateTimeZone('UTC'))->format(self::TIME_FORMAT),
        ]) > 0;
    }

    public function remove(int $userId, int $jobId): void
    {
        $this->connection->table('job_saved_jobs')->where('user_id', $userId)->where('job_id', $jobId)->delete();
    }

    public function count(int $userId): int
    {
        return $this->connection->table('job_saved_jobs')->where('user_id', $userId)->count();
    }

    public function ofUser(int $userId, PageRequest $request): Page
    {
        $total = $this->count($userId);
        $rows = $total === 0 ? [] : $this->connection->table('job_saved_jobs as s')
            ->join('job_jobs as j', 'j.id', '=', 's.job_id')
            ->where('s.user_id', $userId)
            ->orderByDesc('s.saved_at')->orderByDesc('s.id')
            ->offset($request->offset())->limit($request->perPage)
            ->get(['s.job_id', 'j.title', 's.saved_at'])->all();

        return new Page(
            array_map(static fn (stdClass $row): SavedJob => new SavedJob((int) $row->job_id, (string) $row->title, new DateTimeImmutable((string) $row->saved_at, new DateTimeZone('UTC'))), $rows),
            $total,
            $request,
        );
    }

    public function deleteOfUser(int $userId): void
    {
        $this->connection->table('job_saved_jobs')->where('user_id', $userId)->delete();
    }
}
