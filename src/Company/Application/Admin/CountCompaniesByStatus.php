<?php

namespace Src\Company\Application\Admin;

use Src\Company\Domain\CompanyStatus;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The company counts per status for the tabs of the admin company list, for admins holding `companies.manage`. The query's status is ignored, so every tab shows what it would list under the same other filters. */
final readonly class CountCompaniesByStatus
{
    public function __construct(private AdminCompanyReader $reader) {}

    /** @throws AccessDenied when the actor is not an admin */
    public function execute(Actor $actor, CompanyListQuery $query): AdminCompanyStatusCounts
    {
        if (! $actor->isAdmin()) {
            throw new AccessDenied('Only an administrator can count companies.');
        }

        $found = $this->reader->countByStatus(new CompanyListQuery($query->search, null, $query->verified));

        $byStatus = [];

        foreach (CompanyStatus::cases() as $status) {
            $byStatus[$status->value] = $found[$status->value] ?? 0;
        }

        return new AdminCompanyStatusCounts(array_sum($byStatus), $byStatus);
    }
}
