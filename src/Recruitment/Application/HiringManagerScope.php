<?php

namespace Src\Recruitment\Application;

use Src\Shared\Application\Job\JobDirectory;

/**
 * Where a hiring manager is bound to the stage boundary (`docs/design/phase-8-hiring-managers.md` section 2): on a job where the member is assigned as a hiring
 * manager, unless they hold `jobs.view_all` (every company admin does), they see only the applications that carry the marker. On every other job they keep the
 * job-wide scope of an assignee.
 */
final readonly class HiringManagerScope
{
    public function __construct(private JobDirectory $jobs, private HiringManagerVisibility $visibility) {}

    /**
     * The jobs where the member is bound.
     *
     * @return list<int>
     */
    public function boundJobIds(RecruitmentMember $member): array
    {
        return $member->viewsAll() ? [] : $this->jobs->hiringManagerJobIds($member->companyId, $member->userId);
    }

    public function isBound(RecruitmentMember $member, int $jobId): bool
    {
        return in_array($jobId, $this->boundJobIds($member), true);
    }

    /** Whether the member may see the application of a job they may see: always, unless they are bound on the job and it carries no marker yet. */
    public function canSee(RecruitmentMember $member, int $applicationId, int $jobId): bool
    {
        return ! $this->isBound($member, $jobId) || $this->visibility->isVisible($applicationId);
    }
}
