<?php

namespace Src\Recruitment\Application;

use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * Whether a member may see an application: it belongs to the member's company and to a job the member may see (a hiring manager bound to the job's stage boundary sees it only when it carries the marker). Anything else does not exist for them, so a
 * foreign or unassigned application cannot be told from a missing one.
 */
final readonly class CompanyApplicationAccess
{
    public function __construct(private RecruitmentAccess $access, private HiringManagerScope $scope) {}

    /** @throws EntityNotFound */
    public function assertCanSee(RecruitmentMember $member, int $applicationId, int $companyId, int $jobId): void
    {
        if ($companyId !== $member->companyId) {
            throw EntityNotFound::of('Application', $applicationId);
        }

        try {
            $this->access->job($member, $jobId);
        } catch (EntityNotFound) {
            throw EntityNotFound::of('Application', $applicationId);
        }

        // A hiring manager sees an application only once it has reached the boundary of the job or was sent to them.
        if (! $this->scope->canSee($member, $applicationId, $jobId)) {
            throw EntityNotFound::of('Application', $applicationId);
        }
    }
}
