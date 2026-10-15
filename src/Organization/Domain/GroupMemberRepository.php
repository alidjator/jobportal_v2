<?php

namespace Src\Organization\Domain;

interface GroupMemberRepository
{
    public function findByUser(int $userId): ?GroupMember;

    /** @return list<GroupMember> every member of the group, oldest first */
    public function ofGroup(int $groupId): array;

    /** Adds the role, or replaces the permissions of the one the account already holds. */
    public function save(GroupMember $member): void;

    public function delete(int $userId): void;

    /**
     * How many members each group holds, keyed by group id.
     *
     * @param  list<int>  $groupIds
     * @return array<int, int>
     */
    public function countByGroup(array $groupIds): array;
}
