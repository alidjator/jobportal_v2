<?php

namespace Src\Company\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Company\Domain\CompanyMember;
use Src\Company\Domain\CompanyMemberRepository;
use Src\Company\Domain\CompanyRole;
use Src\Company\Domain\MemberStatus;
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
        // One query: the permissions come back as a comma-separated list, because this runs on every request of a company member.
        $row = $this->connection->table('company_members as m')
            ->leftJoin('company_member_permissions as p', 'p.company_member_id', '=', 'm.id')
            ->where('m.user_id', $userId)
            ->groupBy('m.id', 'm.company_id', 'm.role')
            ->selectRaw("m.company_id, m.role, string_agg(p.permission, ',' order by p.id) as permissions")
            ->first();

        return $row === null
            ? null
            : new CompanyMembership((int) $row->company_id, (string) $row->role, $row->permissions === null ? [] : explode(',', (string) $row->permissions));
    }

    public function add(int $companyId, int $userId, CompanyRole $role, array $permissions, bool $isOwner = false): void
    {
        $time = $this->clock->now()->format(self::TIME_FORMAT);

        $memberId = $this->connection->table('company_members')->insertGetId([
            'company_id' => $companyId,
            'user_id' => $userId,
            'role' => $role->value,
            'is_owner' => $isOwner && $role === CompanyRole::CompanyAdmin,
            'created_at' => $time,
            'updated_at' => $time,
        ]);

        if ($role === CompanyRole::Member) {
            $this->insertPermissions((int) $memberId, $permissions);
        }
    }

    public function find(int $companyId, int $userId): ?CompanyMember
    {
        $row = $this->connection->table('company_members')->where('company_id', $companyId)->where('user_id', $userId)->first();

        return $row === null ? null : $this->toMembers([$row])[0];
    }

    public function ofCompany(int $companyId): array
    {
        return $this->toMembers($this->connection->table('company_members')->where('company_id', $companyId)->get()->all());
    }

    public function lockCompanyAdminIds(int $companyId): array
    {
        return array_map(
            'intval',
            $this->connection->table('company_members')
                ->where('company_id', $companyId)
                ->where('role', CompanyRole::CompanyAdmin->value)
                ->where('status', MemberStatus::Active->value)
                ->lockForUpdate()
                ->pluck('user_id')
                ->all(),
        );
    }

    public function remove(int $companyId, int $userId): void
    {
        $this->connection->table('company_members')->where('company_id', $companyId)->where('user_id', $userId)->delete();
    }

    public function ownerUserId(int $companyId): ?int
    {
        $id = $this->connection->table('company_members')->where('company_id', $companyId)->where('is_owner', true)->value('user_id');

        return $id === null ? null : (int) $id;
    }

    public function setOwner(int $companyId, int $userId): void
    {
        $time = $this->clock->now()->format(self::TIME_FORMAT);

        // The partial unique index allows one owner, so the old flag goes first.
        $this->connection->table('company_members')->where('company_id', $companyId)->where('is_owner', true)->update(['is_owner' => false, 'updated_at' => $time]);
        $this->connection->table('company_members')->where('company_id', $companyId)->where('user_id', $userId)->update(['is_owner' => true, 'updated_at' => $time]);
    }

    public function suspend(int $companyId, int $userId, int $byUserId): void
    {
        $time = $this->clock->now()->format(self::TIME_FORMAT);

        $this->connection->table('company_members')->where('company_id', $companyId)->where('user_id', $userId)->update([
            'status' => MemberStatus::Suspended->value,
            'suspended_at' => $time,
            'suspended_by_user_id' => $byUserId,
            'updated_at' => $time,
        ]);
    }

    public function reactivate(int $companyId, int $userId): void
    {
        $this->connection->table('company_members')->where('company_id', $companyId)->where('user_id', $userId)->update([
            'status' => MemberStatus::Active->value,
            'suspended_at' => null,
            'suspended_by_user_id' => null,
            'updated_at' => $this->clock->now()->format(self::TIME_FORMAT),
        ]);
    }

    public function changeRole(int $companyId, int $userId, CompanyRole $role): void
    {
        $this->connection->table('company_members')->where('company_id', $companyId)->where('user_id', $userId)->update([
            'role' => $role->value,
            'updated_at' => $this->clock->now()->format(self::TIME_FORMAT),
        ]);
    }

    public function replacePermissions(int $companyId, int $userId, array $permissions): void
    {
        $memberId = (int) $this->connection->table('company_members')->where('company_id', $companyId)->where('user_id', $userId)->value('id');

        $this->connection->table('company_member_permissions')->where('company_member_id', $memberId)->delete();
        $this->insertPermissions($memberId, $permissions);
        $this->connection->table('company_members')->where('id', $memberId)->update(['updated_at' => $this->clock->now()->format(self::TIME_FORMAT)]);
    }

    /** @param  list<string>  $permissions */
    private function insertPermissions(int $memberId, array $permissions): void
    {
        if ($permissions === []) {
            return;
        }

        $time = $this->clock->now()->format(self::TIME_FORMAT);

        $this->connection->table('company_member_permissions')->insert(array_map(
            static fn (string $permission): array => ['company_member_id' => $memberId, 'permission' => $permission, 'created_at' => $time],
            array_values(array_unique($permissions)),
        ));
    }

    /**
     * The permission names of the members, keyed by member row id, in one query.
     *
     * @param  list<int>  $memberIds
     * @return array<int, list<string>>
     */
    private function permissionsOf(array $memberIds): array
    {
        $byMember = [];

        foreach ($this->connection->table('company_member_permissions')->whereIn('company_member_id', $memberIds)->orderBy('id')->get(['company_member_id', 'permission']) as $row) {
            $byMember[(int) $row->company_member_id][] = (string) $row->permission;
        }

        return $byMember;
    }

    /**
     * @param  list<stdClass>  $rows
     * @return list<CompanyMember>
     */
    private function toMembers(array $rows): array
    {
        $permissions = $this->permissionsOf(array_map(static fn (stdClass $row): int => (int) $row->id, $rows));

        return array_map(static fn (stdClass $row): CompanyMember => new CompanyMember(
            (int) $row->user_id,
            CompanyRole::from($row->role),
            $permissions[(int) $row->id] ?? [],
            new DateTimeImmutable((string) $row->created_at, new DateTimeZone('UTC')),
            (bool) $row->is_owner,
            MemberStatus::from((string) $row->status),
            $row->suspended_at === null ? null : new DateTimeImmutable((string) $row->suspended_at, new DateTimeZone('UTC')),
            $row->suspended_by_user_id === null ? null : (int) $row->suspended_by_user_id,
        ), $rows);
    }
}
