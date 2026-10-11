<?php

namespace Src\Company\Application\Team;

use DateTimeImmutable;
use Src\Company\Domain\CompanyMemberRepository;
use Src\Company\Domain\CompanyRepository;
use Src\Company\Domain\Events\CompanyMemberRemoved;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;

/**
 * Releases the membership of an account whose personal data was erased, so no company keeps a member that no longer exists. Announces
 * `CompanyMemberRemoved` (the member is the one who removed themselves), which makes Job reassign their jobs. Running it again, or for an account
 * that belongs to no company, changes nothing. The account was deactivated when the deletion was requested, so it is not deactivated again.
 *
 * A company admin who is the last one by now (the others left during the grace period) is released too: the erasure cannot wait.
 */
final readonly class ReleaseMemberOfAnonymizedAccount
{
    public function __construct(
        private CompanyRepository $companies,
        private CompanyMemberRepository $members,
        private TransactionManager $transactions,
        private EventDispatcher $events,
    ) {}

    public function execute(int $userId, DateTimeImmutable $occurredAt): void
    {
        $membership = $this->members->findByUserId($userId);

        if ($membership === null) {
            return;
        }

        $released = $this->transactions->run(function () use ($membership, $userId): bool {
            $this->companies->lock($membership->companyId);

            if ($this->members->find($membership->companyId, $userId) === null) {
                return false;
            }

            $this->members->remove($membership->companyId, $userId);

            return true;
        });

        if ($released) {
            $this->events->dispatch([new CompanyMemberRemoved($membership->companyId, $userId, $membership->role, $userId, null, null, $occurredAt)]);
        }
    }
}
