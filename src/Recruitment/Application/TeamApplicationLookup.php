<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ApplicationRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Finds the application for the member who may see its job: a foreign or unassigned application is a plain 404. */
final readonly class TeamApplicationLookup
{
    public function __construct(
        private RecruitmentAccess $access,
        private CompanyApplicationAccess $applicationAccess,
        private ApplicationRepository $applications,
    ) {}

    /**
     * @param  ?CompanyPermission  $permission  what the action needs besides reading (`applications.view`); null for a read
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound
     */
    public function find(Actor $actor, int $applicationId, bool $forUpdate = false, ?CompanyPermission $permission = null): TeamApplication
    {
        $member = $this->access->viewer($actor, $permission);
        $application = $this->applications->find($applicationId, $forUpdate) ?? throw EntityNotFound::of('Application', $applicationId);
        $this->applicationAccess->assertCanSee($member, $applicationId, $application->companyId, $application->jobId);

        return new TeamApplication($member, $application);
    }
}
