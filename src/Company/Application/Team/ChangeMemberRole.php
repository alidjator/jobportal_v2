<?php

namespace Src\Company\Application\Team;

use Src\Company\Domain\CompanyMemberRepository;
use Src\Company\Domain\CompanyRepository;
use Src\Company\Domain\CompanyRole;
use Src\Company\Domain\Events\CompanyMemberRoleChanged;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A company admin promotes a recruiter or demotes a company admin, themselves included. The last company admin cannot be demoted.
 * Giving a member the role they already have changes and audits nothing.
 */
final readonly class ChangeMemberRole
{
    public function __construct(
        private CompanyAdminGuard $guard,
        private CompanyRepository $companies,
        private CompanyMemberRepository $members,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-admin-required` or `company-suspended`
     * @throws EntityNotFound when the account is not a member of the actor's company
     * @throws StateConflict `last-company-admin` when the member is the only company admin and would be demoted
     */
    public function execute(Actor $actor, int $userId, CompanyRole $role, ?string $ipAddress, ?string $userAgent): void
    {
        $company = $this->guard->company($actor);
        $companyId = (int) $company->id;

        $oldRole = $this->transactions->run(function () use ($companyId, $userId, $role): CompanyRole {
            $this->companies->lock($companyId);

            $member = $this->members->find($companyId, $userId) ?? throw EntityNotFound::of('Member', $userId);

            if ($member->role === $role) {
                return $role;
            }

            if ($member->role === CompanyRole::CompanyAdmin && count($this->members->lockCompanyAdminIds($companyId)) <= 1) {
                throw new StateConflict('A company needs at least one company admin.', 'last-company-admin');
            }

            $this->members->changeRole($companyId, $userId, $role);

            return $member->role;
        });

        if ($oldRole !== $role) {
            $this->events->dispatch([new CompanyMemberRoleChanged(
                $companyId, $userId, $oldRole->value, $role->value, (int) $actor->id, $ipAddress, $userAgent, $this->clock->now(),
            )]);
        }
    }
}
