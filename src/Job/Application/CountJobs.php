<?php

namespace Src\Job\Application;

use DateTimeImmutable;
use Src\Job\Domain\JobStatus;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;

/**
 * The job counts per status for the tabs of the company job list. The visibility and the filters are the ones of `ListJobs` (every status filter left out), so
 * every tab shows what it would list under the same search.
 */
final readonly class CountJobs
{
    public function __construct(
        private JobAccess $access,
        private JobReader $reader,
    ) {}

    /** @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended` */
    public function execute(Actor $actor, ?string $search, bool $assignedToMe, ?DateTimeImmutable $deadlineTo = null, bool $withoutApplications = false): JobStatusCounts
    {
        $member = $this->access->member($actor);

        $found = $this->reader->countByStatus(
            new JobListQuery($member->companyId, $member->viewsAll() && ! $assignedToMe ? null : $member->userId, null, $search, deadlineTo: $deadlineTo, withoutApplications: $withoutApplications),
        );

        $byStatus = [];

        foreach (JobStatus::cases() as $status) {
            $byStatus[$status->value] = $found[$status->value] ?? 0;
        }

        return new JobStatusCounts(array_sum($byStatus), $byStatus);
    }
}
