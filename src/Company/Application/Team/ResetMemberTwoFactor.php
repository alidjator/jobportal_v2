<?php

namespace Src\Company\Application\Team;

use Src\Company\Domain\CompanyMemberRepository;
use Src\Company\Domain\CompanyRepository;
use Src\Company\Domain\Events\CompanyMemberTwoFactorReset;
use Src\Shared\Application\Accounts\MemberTwoFactor;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A company admin removes the second factor of a member who lost the device: the factor, the recovery codes and every token of the member go, and the
 * member enrols again at the next sign-in. Never one's own (that is what the account settings are for), and another company admin's only by the owner.
 * A lone company admin who lost the device asks the platform (the console `identity:reset-user-two-factor`).
 */
final readonly class ResetMemberTwoFactor
{
    public function __construct(
        private CompanyAdminGuard $guard,
        private CompanyRepository $companies,
        private CompanyMemberRepository $members,
        private MemberTwoFactor $factors,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-admin-required`, `company-suspended` or `owner-required` (the member is another company admin)
     * @throws EntityNotFound when the account is not a member of the actor's company
     * @throws StateConflict `cannot-reset-self`
     */
    public function execute(Actor $actor, int $userId, ?string $ipAddress, ?string $userAgent): void
    {
        $company = $this->guard->company($actor);
        $companyId = (int) $company->id;

        if ($userId === $actor->id) {
            throw new StateConflict('You cannot reset your own second factor here.', 'cannot-reset-self');
        }

        $this->transactions->run(function () use ($actor, $companyId, $userId): void {
            $this->companies->lock($companyId);

            $member = $this->members->find($companyId, $userId) ?? throw EntityNotFound::of('Member', $userId);

            if ($member->isCompanyAdmin() && $this->members->ownerUserId($companyId) !== $actor->id) {
                throw new AccessDenied("Only the owner of the company can reset another company admin's second factor.", 'owner-required');
            }

            $this->factors->reset($userId);
        });

        $this->events->dispatch([new CompanyMemberTwoFactorReset($companyId, $userId, (int) $actor->id, $ipAddress, $userAgent, $this->clock->now())]);
    }
}
