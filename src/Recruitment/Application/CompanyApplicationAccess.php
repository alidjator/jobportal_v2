<?php

namespace Src\Recruitment\Application;

use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * Whether a member may see an application: it belongs to the member's company and to a job the member may see. Anything else does not exist for them, so a
 * foreign or unassigned application cannot be told from a missing one.
 */
final readonly class CompanyApplicationAccess
{
    public function __construct(private RecruitmentAccess $access) {}

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
    }
}
