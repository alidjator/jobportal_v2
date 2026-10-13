<?php

namespace Src\Company\Application\Team;

use Src\Company\Domain\CompanyMemberRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Job\AssignedJob;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * The jobs a member of the actor's company is assigned to, whatever their status, newest first, so a company admin sees who works on what. Every member
 * has the list, company admins too: they may open every job, the list holds only the jobs they are assigned to.
 */
final readonly class ListMemberJobs
{
    public function __construct(
        private CompanyAdminGuard $guard,
        private CompanyMemberRepository $members,
        private JobDirectory $jobs,
    ) {}

    /**
     * @return list<AssignedJob>
     *
     * @throws AccessDenied `not-a-company-member`, `company-admin-required` or `company-suspended`
     * @throws EntityNotFound when the account is not a member of the actor's company
     */
    public function execute(Actor $actor, int $userId): array
    {
        $companyId = (int) $this->guard->company($actor)->id;

        if ($this->members->find($companyId, $userId) === null) {
            throw EntityNotFound::of('Member', $userId);
        }

        return $this->jobs->assignedJobs($companyId, $userId);
    }
}
