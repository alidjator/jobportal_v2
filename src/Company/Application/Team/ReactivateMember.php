<?php

namespace Src\Company\Application\Team;

use Src\Company\Domain\CompanyMemberRepository;
use Src\Company\Domain\CompanyRepository;
use Src\Company\Domain\Events\CompanyMemberReactivated;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** A company admin lifts the suspension of a member: the status goes back to active and the member is emailed; they sign in again. A member who is not suspended changes nothing. */
final readonly class ReactivateMember
{
    public function __construct(
        private CompanyAdminGuard $guard,
        private CompanyRepository $companies,
        private CompanyMemberRepository $members,
        private CompanyMemberAccounts $accounts,
        private MemberNoticeSender $notices,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-admin-required` or `company-suspended`
     * @throws EntityNotFound when the account is not a member of the actor's company
     */
    public function execute(Actor $actor, int $userId, ?string $ipAddress, ?string $userAgent): void
    {
        $company = $this->guard->company($actor);
        $companyId = (int) $company->id;

        $reactivated = $this->transactions->run(function () use ($companyId, $userId): bool {
            $this->companies->lock($companyId);

            $member = $this->members->find($companyId, $userId) ?? throw EntityNotFound::of('Member', $userId);

            if (! $member->isSuspended()) {
                return false;
            }

            $this->members->reactivate($companyId, $userId);

            return true;
        });

        if (! $reactivated) {
            return;
        }

        $account = $this->accounts->summaries([$userId])[$userId] ?? null;

        if ($account !== null) {
            $this->notices->reactivated($account->email, $account->locale, $company->name());
        }

        $this->events->dispatch([new CompanyMemberReactivated($companyId, $userId, (int) $actor->id, $ipAddress, $userAgent, $this->clock->now())]);
    }
}
