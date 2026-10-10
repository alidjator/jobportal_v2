<?php

namespace Src\TalentPool\Application;

/** A member of an active company who works with its talent pool. */
final readonly class PoolMember
{
    public function __construct(public int $userId, public int $companyId, public bool $isCompanyAdmin) {}
}
