<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\InterviewPanel;
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
        private InterviewPanel $panel,
    ) {}

    /**
     * @param  ?CompanyPermission  $permission  what the action needs besides reading (`applications.view`); null for a read
     * @param  bool  $panelOpens  whether a member on the interview panel of the application, holding `interviews.participate`, may do it without seeing the job
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound
     */
    public function find(Actor $actor, int $applicationId, bool $forUpdate = false, ?CompanyPermission $permission = null, bool $panelOpens = false): TeamApplication
    {
        try {
            return $this->findThroughJob($actor, $applicationId, $forUpdate, $permission);
        } catch (AccessDenied|EntityNotFound $refused) {
            return ($panelOpens ? $this->findThroughPanel($actor, $applicationId, $forUpdate) : null) ?? throw $refused;
        }
    }

    /**
     * @throws AccessDenied
     * @throws EntityNotFound
     */
    private function findThroughJob(Actor $actor, int $applicationId, bool $forUpdate, ?CompanyPermission $permission): TeamApplication
    {
        $member = $this->access->viewer($actor, $permission);
        $application = $this->applications->find($applicationId, $forUpdate) ?? throw EntityNotFound::of('Application', $applicationId);
        $this->applicationAccess->assertCanSee($member, $applicationId, $application->companyId, $application->jobId);

        return new TeamApplication($member, $application);
    }

    /** The application through an interview panel: the caller holds `interviews.participate` and sits on a panel of it. Null when they do not. */
    private function findThroughPanel(Actor $actor, int $applicationId, bool $forUpdate): ?TeamApplication
    {
        try {
            $member = $this->access->permitted($actor, CompanyPermission::InterviewsParticipate);
        } catch (AccessDenied) {
            return null;
        }

        $application = $this->applications->find($applicationId, $forUpdate);

        if ($application === null || $application->companyId !== $member->companyId || ! $this->panel->includes($member->userId, $applicationId)) {
            return null;
        }

        return new TeamApplication($member, $application, panelOnly: true);
    }
}
