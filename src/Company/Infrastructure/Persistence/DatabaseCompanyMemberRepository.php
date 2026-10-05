<?php

namespace Src\Company\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Company\Domain\CompanyMember;
use Src\Company\Domain\CompanyMemberRepository;
use Src\Company\Domain\CompanyRole;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyMembership;
use stdClass;

final readonly class DatabaseCompanyMemberRepository implements CompanyMemberRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

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
        $time = $this->clock->now()->format(self::TIME_FORMAT);

        $this->connection->table('company_members')->insert([
            'company_id' => $companyId,
            'user_id' => $userId,
            'role' => $role->value,
            'created_at' => $time,
            'updated_at' => $time,
        ]);
    }

    public function find(int $companyId, int $userId): ?CompanyMember
    {
        $row = $this->connection->table('company_members')->where('company_id', $companyId)->where('user_id', $userId)->first();

        return $row === null ? null : $this->toMember($row);
    }

    public function ofCompany(int $companyId): array
    {
        return array_map(
            fn (stdClass $row): CompanyMember => $this->toMember($row),
            $this->connection->table('company_members')->where('company_id', $companyId)->get()->all(),
        );
    }

    public function lockCompanyAdminIds(int $companyId): array
    {
        return array_map(
            'intval',
            $this->connection->table('company_members')
                ->where('company_id', $companyId)
                ->where('role', CompanyRole::CompanyAdmin->value)
                ->lockForUpdate()
                ->pluck('user_id')
                ->all(),
        );
    }

    public function remove(int $companyId, int $userId): void
    {
        $this->connection->table('company_members')->where('company_id', $companyId)->where('user_id', $userId)->delete();
    }

    public function changeRole(int $companyId, int $userId, CompanyRole $role): void
    {
        $this->connection->table('company_members')->where('company_id', $companyId)->where('user_id', $userId)->update([
            'role' => $role->value,
            'updated_at' => $this->clock->now()->format(self::TIME_FORMAT),
        ]);
    }

    private function toMember(stdClass $row): CompanyMember
    {
        return new CompanyMember(
            (int) $row->user_id,
            CompanyRole::from($row->role),
            new DateTimeImmutable((string) $row->created_at, new DateTimeZone('UTC')),
        );
    }
}
