<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\InterviewKit;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

final readonly class GetInterviewKit
{
    public function __construct(private RecruitmentAccess $access, private InterviewKitLookup $lookup) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $kitId): InterviewKit
    {
        return $this->lookup->find($this->access->anyOf($actor, CompanyPermission::JobsCreate, CompanyPermission::InterviewsManage, CompanyPermission::InterviewKitsManage), $kitId);
    }
}
