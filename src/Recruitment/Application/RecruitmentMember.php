<?php

namespace Src\Recruitment\Application;

use Src\Shared\Application\Company\CompanyMembership;
use Src\Shared\Application\Company\CompanyPermission;

/** The caller of a recruitment action: a member of an active company, with the permissions checked for them. */
final readonly class RecruitmentMember
{
    public function __construct(public int $userId, public int $companyId, public CompanyMembership $membership) {}

    /** Whether the member sees every job of the company (`jobs.view_all`) and not only the assigned ones. */
    public function viewsAll(): bool
    {
        return $this->membership->can(CompanyPermission::JobsViewAll);
    }

    /** The override of the "person concerned or a company admin" rules stays on the role, not on `jobs.view_all`. */
    public function isCompanyAdmin(): bool
    {
        return $this->membership->isCompanyAdmin();
    }
}
