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
 * A company admin removes a member. The membership is released and the account is deactivated with every token revoked (sessions end at once), but
 * not anonymised, so what the member did stays attributed to their name. The owner, oneself and the last active company admin cannot be removed.
 * The jobs the member held go to a company admin the remover picks, or to the owner when they pick none (Job does that on `CompanyMemberRemoved`).
 */
final readonly class RemoveMember
{
    public function __construct(
        private CompanyAdminGuard $guard,
        private CompanyRepository $companies,
        private CompanyMemberRepository $members,
        private LastCompanyAdminRule $lastAdmin,
        private CompanyMemberAccounts $accounts,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @param  ?int  $transferToUserId  an active company admin other than the removed member who takes over their jobs; null means the owner
     *
     * @throws AccessDenied `not-a-company-member`, `company-admin-required` or `company-suspended`
     * @throws EntityNotFound when the account is not a member of the actor's company
     * @throws StateConflict `cannot-remove-self`, `member-is-owner`, `last-company-admin` or `transfer-target-not-company-admin`
     */
    public function execute(Actor $actor, int $userId, ?int $transferToUserId, ?string $ipAddress, ?string $userAgent): void
    {
        $company = $this->guard->company($actor);
        $companyId = (int) $company->id;

        if ($userId === $actor->id) {
            throw new StateConflict('You cannot remove yourself; another company admin can.', 'cannot-remove-self');
        }

        $role = $this->transactions->run(function () use ($companyId, $userId, $transferToUserId): CompanyRole {
            $this->companies->lock($companyId);

            $member = $this->members->find($companyId, $userId) ?? throw EntityNotFound::of('Member', $userId);

            if ($member->isOwner) {
                throw new StateConflict('The owner of the company cannot be removed; hand the ownership over first.', 'member-is-owner');
            }

            if ($transferToUserId !== null) {
                $target = $transferToUserId === $userId ? null : $this->members->find($companyId, $transferToUserId);

                if ($target === null || ! $target->isCompanyAdmin() || $target->isSuspended()) {
                    throw new StateConflict('The jobs can only go to an active company admin of the company.', 'transfer-target-not-company-admin');
                }
            }

            $this->lastAdmin->assertAnotherRemains($companyId, $member);
            $this->members->remove($companyId, $userId);
            $this->accounts->deactivate($userId);

            return $member->role;
        });

        $this->events->dispatch([new CompanyMemberRemoved($companyId, $userId, $role->value, (int) $actor->id, $transferToUserId, $ipAddress, $userAgent, $this->clock->now())]);
    }
}
