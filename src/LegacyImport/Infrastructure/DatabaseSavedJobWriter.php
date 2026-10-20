<?php

namespace Src\LegacyImport\Infrastructure;

use Illuminate\Database\ConnectionInterface;
use Src\LegacyImport\Application\SavedJobWriter;

final readonly class DatabaseSavedJobWriter implements SavedJobWriter
{
    public function __construct(private ConnectionInterface $connection) {}

    public function notLive(array $jobIds): array
    {
        if ($jobIds === []) {
            return [];
        }

        return array_map('intval', $this->connection->table('job_jobs')->whereIn('id', $jobIds)->where('status', '!=', 'active')->pluck('id')->all());
    }

    public function insert(int $userId, int $jobId, string $savedAt): ?int
    {
        $inserted = $this->connection->table('job_saved_jobs')->insertOrIgnore(['user_id' => $userId, 'job_id' => $jobId, 'saved_at' => $savedAt]);

        return $inserted === 0 ? null : (int) $this->connection->table('job_saved_jobs')->where('user_id', $userId)->where('job_id', $jobId)->value('id');
    }
}
