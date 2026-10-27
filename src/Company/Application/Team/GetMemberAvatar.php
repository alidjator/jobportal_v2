<?php

namespace Src\Company\Application\Team;

use Src\Company\Domain\CompanyMemberRepository;
use Src\Shared\Application\Accounts\AccountAvatar;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * The profile picture of a colleague, for any member of the same company (a team shows its people; no permission is needed). A person of another company, one without a picture and a
 * file that has gone missing are the same 404.
 */
final readonly class GetMemberAvatar
{
    public function __construct(private CompanyMemberRepository $members, private CompanyMemberAccounts $accounts) {}

    /**
     * @throws AccessDenied `not-a-company-member`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $userId): AccountAvatar
    {
        if ($actor->kind !== ActorKind::CompanyMember || $actor->membership === null) {
            throw new AccessDenied('Only a company member can see the team.', 'not-a-company-member');
        }

        if ($this->members->find($actor->membership->companyId, $userId) === null) {
            throw EntityNotFound::of('Member', $userId);
        }

        return $this->accounts->avatar($userId) ?? throw new EntityNotFound('There is no profile picture.', 'avatar-not-found');
    }
}
