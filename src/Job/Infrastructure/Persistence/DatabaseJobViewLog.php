<?php

namespace Src\Job\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\Job\Application\Views\JobViewLog;

final readonly class DatabaseJobViewLog implements JobViewLog
{
    public function __construct(private ConnectionInterface $connection) {}

    public function add(int $jobId, string $viewerKey, string $day): void
    {
        $this->connection->table('job_views')->insertOrIgnore(['job_id' => $jobId, 'viewer_key' => $viewerKey, 'day' => $day, 'created_at' => now()]);
    }

    public function foldBefore(string $before): int
    {
        return $this->connection->transaction(function () use ($before): int {
            $rows = $this->connection->table('job_views')
                ->where('day', '<', $before)
                ->groupBy('job_id', 'day')
                ->get(['job_id', 'day', $this->connection->raw('COUNT(*) as views')]);

            foreach ($rows->chunk(500) as $chunk) {
                $this->connection->table('job_view_daily')->upsert(
                    $chunk->map(fn (object $row): array => ['job_id' => (int) $row->job_id, 'day' => (string) $row->day, 'views' => (int) $row->views])->all(),
                    ['job_id', 'day'],
                    ['views'],
                );
            }

            return $this->connection->table('job_views')->where('day', '<', $before)->delete();
        });
    }

    public function eraseViewer(int $userId): int
    {
        return $this->connection->table('job_views')->where('viewer_key', 'u'.$userId)->delete();
    }
}
