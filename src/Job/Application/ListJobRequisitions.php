<?php

namespace Src\Job\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Workforce\RequisitionGate;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The choices for the form of a new job (`jobs.create`): whether the tenant opens its jobs from a requisition and the requisitions it may open one for. A tenant that is not an internal one has none. */
final readonly class ListJobRequisitions
{
    public function __construct(private JobAccess $access, private RequisitionGate $requisitions) {}

    /** @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended` */
    public function execute(Actor $actor): JobRequisitionChoices
    {
        $member = $this->access->member($actor);
        $this->access->require($member, CompanyPermission::JobsCreate);

        return new JobRequisitionChoices($this->requisitions->isRequired($member->companyId), $this->requisitions->open($member->companyId));
    }
}
