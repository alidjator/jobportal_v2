<?php

namespace Src\JobSeeker\Domain;

use DateTimeImmutable;

/** The companies a job seeker blocked from finding them. */
interface BlockedCompanyRepository
{
    /** @return list<array{company_id: int, blocked_at: DateTimeImmutable}> the most recently blocked first */
    public function forUser(int $userId): array;

    public function count(int $userId): int;

    public function exists(int $userId, int $companyId): bool;

    public function add(int $userId, int $companyId, DateTimeImmutable $at): void;

    /** Removes the block; nothing happens when there is none. */
    public function remove(int $userId, int $companyId): void;
}
