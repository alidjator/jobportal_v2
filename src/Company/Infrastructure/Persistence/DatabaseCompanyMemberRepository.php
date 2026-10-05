<?php

namespace Src\Company\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\Company\Domain\CompanyMemberRepository;
use Src\Company\Domain\CompanyRole;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyMembership;

final readonly class DatabaseCompanyMemberRepository implements CompanyMemberRepository
{
    public function __construct(
        private ConnectionInterface $connection,
        private Clock $clock,
    ) {}

    public function findByUserId(int $userId): ?CompanyMembership
    {
        $row = $this->connection->table('company_members')->where('user_id', $userId)->first(['company_id', 'role']);

        return $row === null ? null : new CompanyMembership((int) $row->company_id, (string) $row->role);
    }

    public function add(int $companyId, int $userId, CompanyRole $role): void
    {
        $time = $this->clock->now()->format('Y-m-d H:i:s');

        $this->connection->table('company_members')->insert([
            'company_id' => $companyId,
            'user_id' => $userId,
            'role' => $role->value,
            'created_at' => $time,
            'updated_at' => $time,
        ]);
    }
}
