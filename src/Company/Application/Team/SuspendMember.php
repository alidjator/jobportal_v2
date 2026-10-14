<?php

namespace Src\Company\Application\Team;

use Src\Company\Domain\CompanyMemberRepository;
use Src\Company\Domain\CompanyRepository;
use Src\Company\Domain\Events\CompanyMemberSuspended;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A company admin suspends a member's access for a while. Every token of the member is revoked, so their sessions end at once; assignments, panels,
 * permissions, notes and conversations stay, so a reactivation restores everything. The member gets an email, with the admin's message when there is one.
 * Suspending a member who is already suspended changes nothing.
 */
final readonly class SuspendMember
{
    public function __construct(
        private CompanyAdminGuard $guard,
        private CompanyRepository $companies,
        private CompanyMemberRepository $members,
        private LastCompanyAdminRule $lastAdmin,
        private CompanyMemberAccounts $accounts,
        private MemberNoticeSender $notices,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-admin-required` or `company-suspended`
     * @throws EntityNotFound when the account is not a member of the actor's company
     * @throws StateConflict `cannot-suspend-self`, `member-is-owner` or `last-company-admin`
     */
    public function execute(Actor $actor, int $userId, ?string $message, ?string $ipAddress, ?string $userAgent): void
    {
        $company = $this->guard->company($actor);
        $companyId = (int) $company->id;

        if ($userId === $actor->id) {
            throw new StateConflict('You cannot suspend yourself.', 'cannot-suspend-self');
        }

        $suspended = $this->transactions->run(function () use ($actor, $companyId, $userId): bool {
            $this->companies->lock($companyId);

            $member = $this->members->find($companyId, $userId) ?? throw EntityNotFound::of('Member', $userId);

            if ($member->isSuspended()) {
                return false;
            }

            if ($member->isOwner) {
                throw new StateConflict('The owner of the company cannot be suspended.', 'member-is-owner');
            }

            $this->lastAdmin->assertAnotherRemains($companyId, $member);
            $this->members->suspend($companyId, $userId, (int) $actor->id);
            $this->accounts->revokeSessions($userId);

            return true;
        });

        if (! $suspended) {
            return;
        }

        $account = $this->accounts->summaries([$userId])[$userId] ?? null;

        if ($account !== null) {
            $this->notices->suspended($account->email, $account->locale, $company->name(), $message === null || trim($message) === '' ? null : trim($message));
        }

        $this->events->dispatch([new CompanyMemberSuspended($companyId, $userId, (int) $actor->id, $ipAddress, $userAgent, $this->clock->now())]);
    }
}
