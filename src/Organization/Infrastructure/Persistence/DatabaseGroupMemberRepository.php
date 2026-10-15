<?php

namespace Src\Organization\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\Organization\Domain\GroupMember;
use Src\Organization\Domain\GroupMemberRepository;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Organization\GroupPermission;
use stdClass;

final readonly class DatabaseGroupMemberRepository implements GroupMemberRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private ConnectionInterface $connection,
        private Clock $clock,
    ) {}

    public function findByUser(int $userId): ?GroupMember
    {
        $row = $this->connection->table('organization_group_members')->where('user_id', $userId)->first();

        return $row === null ? null : $this->toMember($row);
    }

    public function ofGroup(int $groupId): array
    {
        return $this->connection->table('organization_group_members')->where('group_id', $groupId)->orderBy('id')->get()
            ->map(fn (stdClass $row): GroupMember => $this->toMember($row))
            ->all();
    }

    public function save(GroupMember $member): void
    {
        $now = $this->clock->now()->format(self::TIME_FORMAT);

        $existing = $this->connection->table('organization_group_members')->where('user_id', $member->userId)->value('id');
        $id = $existing === null
            ? (int) $this->connection->table('organization_group_members')->insertGetId(['group_id' => $member->groupId, 'user_id' => $member->userId, 'created_at' => $now, 'updated_at' => $now])
            : (int) $existing;

        if ($existing !== null) {
            $this->connection->table('organization_group_members')->where('id', $id)->update(['updated_at' => $now]);
        }

        $wanted = array_map(static fn (GroupPermission $permission): string => $permission->value, $member->permissions);
        $permissions = $this->connection->table('organization_group_member_permissions');

        $permissions->where('group_member_id', $id)->whereNotIn('permission', $wanted)->delete();

        $held = $this->connection->table('organization_group_member_permissions')->where('group_member_id', $id)->pluck('permission')->all();
        $new = array_values(array_diff($wanted, $held));

        if ($new !== []) {
            $this->connection->table('organization_group_member_permissions')->insert(array_map(
                static fn (string $permission): array => ['group_member_id' => $id, 'permission' => $permission, 'created_at' => $now],
                $new,
            ));
        }
    }

    public function delete(int $userId): void
    {
        // The permissions go with the member (cascade).
        $this->connection->table('organization_group_members')->where('user_id', $userId)->delete();
    }

    public function countByGroup(array $groupIds): array
    {
        if ($groupIds === []) {
            return [];
        }

        $counts = [];

        foreach ($this->connection->table('organization_group_members')->whereIn('group_id', $groupIds)->groupBy('group_id')->selectRaw('group_id, COUNT(*) as total')->get() as $row) {
            $counts[(int) $row->group_id] = (int) $row->total;
        }

        return $counts;
    }

    private function toMember(stdClass $row): GroupMember
    {
        $names = $this->connection->table('organization_group_member_permissions')->where('group_member_id', $row->id)->pluck('permission')->all();

        // Catalog order, so the answer does not depend on the order of the rows.
        $permissions = array_values(array_filter(GroupPermission::cases(), static fn (GroupPermission $permission): bool => in_array($permission->value, $names, true)));

        return new GroupMember((int) $row->id, (int) $row->group_id, (int) $row->user_id, $permissions);
    }
}
