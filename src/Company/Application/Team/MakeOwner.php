<?php

namespace Src\Company\Application\Team;

use Src\Company\Domain\CompanyMemberRepository;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** The owner of a company hands the ownership to another active company admin. It asks for the owner's current password, so a session left open cannot do it. */
final readonly class MakeOwner
{
    public function __construct(
        private CompanyAdminGuard $guard,
        private CompanyMemberRepository $members,
        private CompanyMemberAccounts $accounts,
        private OwnerTransfer $transfer,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-admin-required`, `company-suspended` or `owner-required`
     * @throws BusinessRuleViolation `invalid-password`
     * @throws EntityNotFound when the account is not a member of the actor's company
     * @throws StateConflict `owner-target-not-company-admin`
     */
    public function execute(Actor $actor, int $userId, string $password, ?string $ipAddress, ?string $userAgent): void
    {
        $company = $this->guard->company($actor);

        if ($this->members->ownerUserId((int) $company->id) !== $actor->id) {
            throw new AccessDenied('Only the owner of the company can hand the ownership over.', 'owner-required');
        }

        if (! $this->accounts->passwordMatches((int) $actor->id, $password)) {
            throw new BusinessRuleViolation('The password is not correct.', 'invalid-password');
        }

        $this->transfer->execute($company, $userId, null, $ipAddress, $userAgent);
    }
}
