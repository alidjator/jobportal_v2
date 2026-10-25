<?php

namespace Src\Assessment\Application\Defaults;

use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Company\CompanyTeam;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * Who may be chosen to score a test on a job: the active members of the company who may see the job (a company admin does, assigned or not), the same members
 * `InvitationChecks` accepts as assessors. For a member who may send tests and see the job.
 */
final readonly class ListJobAssessors
{
    public function __construct(
        private JobDefaultAccess $access,
        private CompanyTeam $team,
        private JobDirectory $jobs,
        private CompanyMemberAccounts $accounts,
    ) {}

    /**
     * @return list<array{id: int, name: string}> by name
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $jobId): array
    {
        $companyId = $this->access->companyId($actor, $jobId);
        $viewAll = $this->team->viewAllUserIds($companyId);
        $people = [];

        foreach ($this->accounts->summaries(array_keys($this->team->roles($companyId))) as $account) {
            if ($account->status === 'active' && $this->jobs->visibleToMember($jobId, $companyId, $account->userId, in_array($account->userId, $viewAll, true)) !== null) {
                $people[] = ['id' => $account->userId, 'name' => $account->name];
            }
        }

        usort($people, static fn (array $a, array $b): int => [mb_strtolower($a['name']), $a['id']] <=> [mb_strtolower($b['name']), $b['id']]);

        return $people;
    }
}
