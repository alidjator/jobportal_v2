<?php

namespace Src\Job\Application\Admin;

use Src\Job\Domain\JobStatus;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Domain\Exceptions\AccessDenied;

/**
 * The job counts per status for the tabs of the admin job list, for admins holding `jobs.manage`. The search text is the one of the list (title, or the name of the
 * company); no status filter is applied, so every tab shows what it would list under the same search.
 */
final readonly class CountJobsByStatusForAdmin
{
    private const int MAX_MATCHING_COMPANIES = 200;

    public function __construct(
        private AdminJobReader $reader,
        private CompanyDirectory $companies,
    ) {}

    /** @throws AccessDenied when the actor is not an admin */
    public function execute(Actor $actor, ?string $search): AdminJobStatusCounts
    {
        if (! $actor->isAdmin()) {
            throw new AccessDenied('Only an administrator can count the jobs of every company.');
        }

        $searchCompanyIds = $search === null || $search === '' ? [] : $this->companies->idsByName($search, self::MAX_MATCHING_COMPANIES);
        $found = $this->reader->countByStatus(new AdminJobQuery(search: $search, searchCompanyIds: $searchCompanyIds));

        $byStatus = [];

        foreach (JobStatus::cases() as $status) {
            $byStatus[$status->value] = $found[$status->value] ?? 0;
        }

        return new AdminJobStatusCounts(array_sum($byStatus), $byStatus);
    }
}
