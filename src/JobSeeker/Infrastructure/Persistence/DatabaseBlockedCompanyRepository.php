<?php

namespace Src\JobSeeker\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\JobSeeker\Domain\BlockedCompanyRepository;

final readonly class DatabaseBlockedCompanyRepository implements BlockedCompanyRepository
{
    public function __construct(private ConnectionInterface $connection) {}

    public function forUser(int $userId): array
    {
        $utc = new DateTimeZone('UTC');

        return $this->connection->table('jobseeker_blocked_companies')->where('user_id', $userId)->orderByDesc('created_at')->orderByDesc('id')->get(['company_id', 'created_at'])
            ->map(static fn (object $row): array => ['company_id' => (int) $row->company_id, 'blocked_at' => new DateTimeImmutable((string) $row->created_at, $utc)])
            ->all();
    }

    public function count(int $userId): int
    {
        return $this->connection->table('jobseeker_blocked_companies')->where('user_id', $userId)->count();
    }

    public function exists(int $userId, int $companyId): bool
    {
        return $this->connection->table('jobseeker_blocked_companies')->where('user_id', $userId)->where('company_id', $companyId)->exists();
    }

    public function add(int $userId, int $companyId, DateTimeImmutable $at): void
    {
        $this->connection->table('jobseeker_blocked_companies')->insertOrIgnore(['user_id' => $userId, 'company_id' => $companyId, 'created_at' => $at->format('Y-m-d H:i:s')]);
    }

    public function remove(int $userId, int $companyId): void
    {
        $this->connection->table('jobseeker_blocked_companies')->where('user_id', $userId)->where('company_id', $companyId)->delete();
    }
}
