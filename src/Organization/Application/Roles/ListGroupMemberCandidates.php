<?php

namespace Src\Organization\Application\Roles;

use Src\Organization\Domain\LegalEntity;
use Src\Organization\Domain\LegalEntityRepository;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Company\CompanyTeam;
use Src\Shared\Application\Company\TenantRegistry;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * The accounts that can be given a group role: the active members of every tenant of the group (a group role sits on top of the membership of a tenant), with their name and tenant. For
 * the member who holds `group.members.manage`, who need not be a company admin and so cannot read the team list of a company.
 */
final readonly class ListGroupMemberCandidates
{
    public function __construct(
        private GroupRoleAccess $access,
        private LegalEntityRepository $entities,
        private TenantRegistry $tenants,
        private CompanyTeam $team,
        private CompanyMemberAccounts $accounts,
        private CompanyDirectory $companies,
    ) {}

    /**
     * @return list<GroupMemberCandidate> by name
     *
     * @throws AccessDenied
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, ?int $groupId): array
    {
        $groupId = $this->access->groupId($actor, $groupId);
        $entityIds = array_map(static fn (LegalEntity $entity): int => (int) $entity->id, $this->entities->ofGroup($groupId));
        $companyIds = array_values($entityIds === [] ? [] : $this->tenants->tenantsOf($entityIds));

        $companyOf = [];

        foreach ($companyIds as $companyId) {
            foreach ($this->team->activeUserIds($companyId) as $userId) {
                $companyOf[$userId] = $companyId;
            }
        }

        $summaries = $companyOf === [] ? [] : $this->accounts->summaries(array_keys($companyOf));
        $names = $companyIds === [] ? [] : $this->companies->summaries($companyIds);
        $candidates = [];

        foreach ($summaries as $userId => $summary) {
            $companyId = $companyOf[$userId];
            $candidates[] = new GroupMemberCandidate($userId, $summary->name, $companyId, ($names[$companyId] ?? null)?->name);
        }

        usort($candidates, static fn (GroupMemberCandidate $a, GroupMemberCandidate $b): int => [mb_strtolower($a->name), $a->userId] <=> [mb_strtolower($b->name), $b->userId]);

        return $candidates;
    }
}
