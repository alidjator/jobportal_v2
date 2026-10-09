<?php

namespace Src\JobInvitation\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\Shared\Application\JobInvitation\InvitedCandidates;

final readonly class DatabaseInvitedCandidates implements InvitedCandidates
{
    public function __construct(private ConnectionInterface $connection) {}

    public function jobIdsOf(int $userId, array $jobIds): array
    {
        if ($jobIds === []) {
            return [];
        }

        return $this->connection->table('jobinvitation_invitations')->where('user_id', $userId)->whereIn('job_id', $jobIds)->pluck('job_id')
            ->map(fn (mixed $id): int => (int) $id)->values()->all();
    }

    public function userIdsOf(int $jobId, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return $this->connection->table('jobinvitation_invitations')->where('job_id', $jobId)->whereIn('user_id', $userIds)->pluck('user_id')
            ->map(fn (mixed $id): int => (int) $id)->values()->all();
    }
}
