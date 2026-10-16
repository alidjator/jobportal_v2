<?php

namespace Src\Organization\Domain;

use DateTimeImmutable;

interface SupportGrantRepository
{
    public function find(int $id): ?SupportGrant;

    public function create(SupportGrant $grant): SupportGrant;

    public function save(SupportGrant $grant): void;

    /** The grant of the group to the admin that is neither revoked nor expired at the time, if any. */
    public function activeFor(int $groupId, int $adminId, DateTimeImmutable $now): ?SupportGrant;

    /** @return list<SupportGrant> the group's grants that ended or were created since the time, newest first */
    public function ofGroupSince(int $groupId, DateTimeImmutable $since): array;

    /** @return list<SupportGrant> the grants to the admin that are active at the time, by expiry */
    public function activeOfAdmin(int $adminId, DateTimeImmutable $now): array;
}
