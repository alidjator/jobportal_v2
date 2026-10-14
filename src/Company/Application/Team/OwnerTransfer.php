<?php

namespace Src\Company\Application\Team;

use Src\Company\Domain\Company;
use Src\Company\Domain\CompanyMemberRepository;
use Src\Company\Domain\CompanyRepository;
use Src\Company\Domain\Events\CompanyOwnerTransferred;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * Moves the ownership of a company to an active company admin of the same company, and tells both owners by email. Shared by the owner (`MakeOwner`,
 * with their password) and the platform admin (`SetCompanyOwner`, when the owner is gone). The former owner stays a company admin.
 */
final readonly class OwnerTransfer
{
    public function __construct(
        private CompanyRepository $companies,
        private CompanyMemberRepository $members,
        private CompanyMemberAccounts $accounts,
        private MemberNoticeSender $notices,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @param  ?int  $byAdminId  the platform admin who moves the ownership; null when the owner does
     *
     * @throws EntityNotFound when the target is not a member of the company
     * @throws StateConflict `owner-target-not-company-admin` when the target is not an active company admin
     */
    public function execute(Company $company, int $toUserId, ?int $byAdminId, ?string $ipAddress, ?string $userAgent): void
    {
        $companyId = (int) $company->id;

        $fromUserId = $this->transactions->run(function () use ($companyId, $toUserId): ?int {
            $this->companies->lock($companyId);

            $target = $this->members->find($companyId, $toUserId) ?? throw EntityNotFound::of('Member', $toUserId);

            if (! $target->isCompanyAdmin() || $target->isSuspended()) {
                throw new StateConflict('The owner must be an active company admin of the company.', 'owner-target-not-company-admin');
            }

            $from = $this->members->ownerUserId($companyId);

            if ($from === $toUserId) {
                return $toUserId;
            }

            $this->members->setOwner($companyId, $toUserId);

            return $from;
        });

        if ($fromUserId === $toUserId) {
            return;
        }

        $accounts = $this->accounts->summaries(array_values(array_filter([$fromUserId, $toUserId])));
        $new = $accounts[$toUserId] ?? null;
        $old = $fromUserId === null ? null : ($accounts[$fromUserId] ?? null);

        if ($new !== null) {
            $this->notices->ownershipGained($new->email, $new->locale, $company->name(), $old?->name);
        }

        if ($old !== null && $new !== null) {
            $this->notices->ownershipHandedOver($old->email, $old->locale, $company->name(), $new->name);
        }

        $this->events->dispatch([new CompanyOwnerTransferred($companyId, $fromUserId, $toUserId, $byAdminId, $ipAddress, $userAgent, $this->clock->now())]);
    }
}
