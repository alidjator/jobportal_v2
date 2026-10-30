<?php

namespace Src\Approval\Application\Instance;

use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Company\CompanyTeam;
use Src\Shared\Domain\Exceptions\AccessDenied;

/**
 * The colleagues a member of a company can name in an approval: the active members of the own company with their name only (no email, role or permissions). It serves the picker of a
 * delegate and the names in the text of an approval flow, for every member, because every member may delegate and an approver needs no permission of their own.
 */
final readonly class ApprovalPeople
{
    public function __construct(private CompanyTeam $team, private CompanyMemberAccounts $accounts) {}

    /**
     * @return list<array{id: int, name: string}> by name
     *
     * @throws AccessDenied `not-a-company-member`
     */
    public function execute(Actor $actor): array
    {
        if ($actor->kind !== ActorKind::CompanyMember || $actor->id === null || $actor->membership === null) {
            throw new AccessDenied('Only a company member takes part in approvals.', 'not-a-company-member');
        }

        $ids = $this->team->activeUserIds($actor->membership->companyId);
        $people = [];

        foreach ($ids === [] ? [] : $this->accounts->summaries($ids) as $id => $summary) {
            $people[] = ['id' => $id, 'name' => $summary->name];
        }

        usort($people, static fn (array $a, array $b): int => [mb_strtolower($a['name']), $a['id']] <=> [mb_strtolower($b['name']), $b['id']]);

        return $people;
    }
}
