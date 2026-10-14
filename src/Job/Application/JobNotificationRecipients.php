<?php

namespace Src\Job\Application;

use Src\Job\Domain\JobAssignmentRepository;
use Src\Job\Domain\JobRepository;
use Src\Shared\Application\Company\CompanyTeam;
use Src\Shared\Application\Company\SuspendedMembers;

/**
 * Who is told about a job: its assignees. A suspended assignee is not told (Notification drops it), so a job whose assignees are all suspended goes to the
 * active company admins instead, until one assignee is active again.
 */
final readonly class JobNotificationRecipients
{
    public function __construct(
        private JobRepository $jobs,
        private JobAssignmentRepository $assignments,
        private CompanyTeam $team,
        private SuspendedMembers $suspended,
    ) {}

    /** @return list<int> */
    public function ofJob(int $jobId): array
    {
        $assignees = $this->assignments->userIds($jobId);
        $suspended = $this->suspended->among($assignees);

        if ($assignees === [] || array_diff($assignees, $suspended) !== []) {
            return $assignees;
        }

        $companyId = $this->jobs->find($jobId)?->companyId;

        if ($companyId === null) {
            return [];
        }

        $admins = array_keys(array_filter($this->team->roles($companyId), static fn (string $role): bool => $role === 'company_admin'));

        return array_values(array_diff($admins, $this->suspended->among($admins)));
    }
}
