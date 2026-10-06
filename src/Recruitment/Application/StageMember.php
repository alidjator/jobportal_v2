<?php

namespace Src\Recruitment\Application;

/** The caller of a stage action: a member of an active company. */
final readonly class StageMember
{
    public function __construct(public int $userId, public int $companyId, public bool $isCompanyAdmin) {}
}
