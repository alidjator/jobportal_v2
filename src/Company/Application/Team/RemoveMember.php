<?php

namespace Src\Company\Application\Team;

use Src\Company\Domain\CompanyMemberRepository;
use Src\Company\Domain\CompanyRepository;
use Src\Company\Domain\CompanyRole;
use Src\Company\Domain\Events\CompanyMemberRemoved;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A company admin removes a member, themselves included. The membership is released and the account is deactivated with every token
 * revoked, but not anonymised, so what the member did stays attributed to their name. The last company admin cannot be removed.
 */
final readonly class RemoveMember
{
    public function __construct(
        private CompanyAdminGuard $guard,
        private CompanyRepository $companies,
        private CompanyMemberRepository $members,
        private CompanyMemberAccounts $accounts,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-admin-required` or `company-suspended`
     * @throws EntityNotFound when the account is not a member of the actor's company
     * @throws StateConflict `last-company-admin` when the member is the only company admin
     */
    public function execute(Actor $actor, int $userId, ?string $ipAddress, ?string $userAgent): void
    {
        $company = $this->guard->company($actor);
        $companyId = (int) $company->id;

        $role = $this->transactions->run(function () use ($companyId, $userId): CompanyRole {
            $this->companies->lock($companyId);

            $member = $this->members->find($companyId, $userId) ?? throw EntityNotFound::of('Member', $userId);

            if ($member->role === CompanyRole::CompanyAdmin && count($this->members->lockCompanyAdminIds($companyId)) <= 1) {
                throw new StateConflict('A company needs at least one company admin.', 'last-company-admin');
            }

            $this->members->remove($companyId, $userId);
            $this->accounts->deactivate($userId);

            return $member->role;
        });

        $this->events->dispatch([new CompanyMemberRemoved($companyId, $userId, $role->value, (int) $actor->id, $ipAddress, $userAgent, $this->clock->now())]);
    }
}
