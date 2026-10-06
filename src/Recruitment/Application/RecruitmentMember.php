<?php

namespace Src\Recruitment\Application;

/** The caller of a recruitment action: a member of an active company. */
final readonly class RecruitmentMember
{
    public function __construct(public int $userId, public int $companyId, public bool $isCompanyAdmin) {}
}
