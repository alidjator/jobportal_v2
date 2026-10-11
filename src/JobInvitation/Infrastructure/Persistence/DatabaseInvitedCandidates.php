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

    public function jobIdsOfUsers(array $userIds, array $jobIds): array
    {
        if ($userIds === [] || $jobIds === []) {
            return [];
        }

        $invited = [];

        foreach ($this->connection->table('jobinvitation_invitations')->whereIn('user_id', $userIds)->whereIn('job_id', $jobIds)->distinct()->get(['user_id', 'job_id']) as $row) {
            $invited[(int) $row->user_id][] = (int) $row->job_id;
        }

        return $invited;
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
