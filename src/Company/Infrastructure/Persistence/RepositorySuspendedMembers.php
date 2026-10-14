<?php

namespace Src\Company\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\Company\Domain\MemberStatus;
use Src\Shared\Application\Company\SuspendedMembers;

final readonly class RepositorySuspendedMembers implements SuspendedMembers
{
    public function __construct(private ConnectionInterface $connection) {}

    public function among(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return array_map('intval', $this->connection->table('company_members')
            ->whereIn('user_id', $userIds)
            ->where('status', MemberStatus::Suspended->value)
            ->pluck('user_id')
            ->all());
    }
}
