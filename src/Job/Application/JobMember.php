<?php

namespace Src\Job\Application;

use Src\Shared\Application\Company\CompanyMembership;
use Src\Shared\Application\Company\CompanyPermission;

/** The company member who acts on jobs: their account, their company and what they may do there. */
final readonly class JobMember
{
    public function __construct(
        public int $userId,
        public int $companyId,
        public CompanyMembership $membership,
    ) {}

    /** Whether the member sees every job of the company (`jobs.view_all`) and not only the ones assigned to them. */
    public function viewsAll(): bool
    {
        return $this->membership->can(CompanyPermission::JobsViewAll);
    }
}
