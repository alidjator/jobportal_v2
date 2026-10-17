<?php

namespace Src\Shared\Application\Recruitment;

use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Lets other contexts find an application for the company member who may see its job, without reading Recruitment's tables. Provided by Recruitment. */
interface TeamApplications
{
    /**
     * The application, locked for update when asked. An application of another company, or of a job the member may not see, is the same 404 as a missing one.
     *
     * @param  ?CompanyPermission  $permission  what the action needs besides reading (`applications.view`); null for a read
     * @param  bool  $panelOpens  whether a member on an interview panel of the application, holding `interviews.participate`, may read it without seeing the job
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound
     */
    public function find(Actor $actor, int $applicationId, bool $forUpdate = false, ?CompanyPermission $permission = null, bool $panelOpens = false): TeamApplicationRef;
}
