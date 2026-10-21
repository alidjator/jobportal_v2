<?php

namespace Src\Approval\Application\Instance;

use DateTimeImmutable;
use Src\Approval\Domain\Delegation;
use Src\Approval\Domain\DelegationRepository;
use Src\Approval\Domain\Events\ApprovalChanged;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyMembershipResolver;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\Organization\GroupDirectory;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * An approver lets one other account decide in their place for a date range (up to 90 days, starting today or later). The delegate must be a member of a tenant of the same ERP
 * group and not the approver. A decision by the delegate records both names. The approver ends a delegation at any time.
 */
final readonly class ApprovalDelegations
{
    public const int MAX_DAYS = 90;

    public function __construct(
        private DelegationRepository $delegations,
        private CompanyMembershipResolver $memberships,
        private GroupDirectory $groups,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @return list<Delegation> the delegations of the account that have not ended
     *
     * @throws AccessDenied `not-a-company-member`
     */
    public function mine(Actor $actor): array
    {
        $this->member($actor);

        return $this->delegations->ofApprover((int) $actor->id, $this->today());
    }

    /**
     * @throws AccessDenied `not-a-company-member`
     * @throws BusinessRuleViolation `invalid-delegate` or `invalid-delegation-range`
     */
    public function create(Actor $actor, int $delegateUserId, DateTimeImmutable $from, DateTimeImmutable $to, ?string $ipAddress, ?string $userAgent): Delegation
    {
        $companyId = $this->member($actor);
        $today = $this->today();

        if ($from < $today || $to < $from || $from->modify('+'.(self::MAX_DAYS - 1).' days') < $to) {
            throw new BusinessRuleViolation('A delegation starts today or later and lasts at most :max days.', 'invalid-delegation-range', parameters: ['max' => self::MAX_DAYS]);
        }

        $delegateCompany = $delegateUserId === $actor->id ? null : $this->memberships->forUser($delegateUserId)?->companyId;
        $groupId = $this->groups->groupOfCompany($companyId);

        if ($delegateCompany === null || $groupId === null || $this->groups->groupOfCompany($delegateCompany) !== $groupId) {
            throw new BusinessRuleViolation('The delegate must be another member of a tenant of the same group.', 'invalid-delegate');
        }

        $now = $this->clock->now();
        $delegation = $this->delegations->create(new Delegation(null, (int) $actor->id, $delegateUserId, $from, $to, $now));

        $this->events->dispatch([new ApprovalChanged(
            null, null, null, $companyId, 'delegation_created', $actor->id,
            ['delegate_user_id' => $delegateUserId, 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')], $ipAddress, $userAgent, $now,
        )]);

        return $delegation;
    }

    /**
     * @throws AccessDenied `not-a-company-member`
     * @throws EntityNotFound the delegation is missing or is not the account's own
     */
    public function remove(Actor $actor, int $id, ?string $ipAddress, ?string $userAgent): void
    {
        $companyId = $this->member($actor);
        $delegation = $this->delegations->find($id);

        if ($delegation === null || $delegation->approverUserId !== $actor->id) {
            throw EntityNotFound::of('Delegation', $id);
        }

        $this->delegations->delete($id);

        $this->events->dispatch([new ApprovalChanged(
            null, null, null, $companyId, 'delegation_removed', $actor->id, ['delegate_user_id' => $delegation->delegateUserId], $ipAddress, $userAgent, $this->clock->now(),
        )]);
    }

    /**
     * @return int the company of the account
     *
     * @throws AccessDenied `not-a-company-member`
     */
    private function member(Actor $actor): int
    {
        if ($actor->kind !== ActorKind::CompanyMember || $actor->id === null || $actor->membership === null) {
            throw new AccessDenied('Only a company member takes part in approvals.', 'not-a-company-member');
        }

        return $actor->membership->companyId;
    }

    private function today(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->clock->now()->format('Y-m-d'));
    }
}
