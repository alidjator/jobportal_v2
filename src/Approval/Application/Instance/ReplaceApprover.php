<?php

namespace Src\Approval\Application\Instance;

use Src\Approval\Domain\ApprovalInstanceRepository;
use Src\Approval\Domain\Events\ApprovalChanged;
use Src\Approval\Domain\SlotKind;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyMembershipResolver;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\Organization\GroupDirectory;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * When an approver leaves while their place waits, a company admin of the tenant (or a holder of `group.approvals.manage` of its group) names a replacement: a member of a tenant of
 * the group, other than the requester. Audited, so the group and the tenant see who was named.
 */
final readonly class ReplaceApprover
{
    public function __construct(
        private ApprovalInstanceRepository $instances,
        private DecisionAuthority $authority,
        private CompanyMembershipResolver $memberships,
        private GroupDirectory $groups,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`
     * @throws EntityNotFound the approval is missing or the account may not replace its approvers
     * @throws BusinessRuleViolation `invalid-approver`
     * @throws StateConflict `approval-not-pending` or `approval-slot-not-vacant`
     */
    public function execute(Actor $actor, int $approvalId, int $slotId, int $userId, ?string $ipAddress, ?string $userAgent): void
    {
        if ($actor->kind !== ActorKind::CompanyMember || $actor->id === null) {
            throw new AccessDenied('Only a company member takes part in approvals.', 'not-a-company-member');
        }

        $this->transactions->run(function () use ($actor, $approvalId, $slotId, $userId, $ipAddress, $userAgent): void {
            $instance = $this->instances->findForUpdate($approvalId);

            if ($instance === null || ! $this->authority->canReplace($instance, $actor)) {
                throw EntityNotFound::of('Approval', $approvalId);
            }

            $company = $userId === $instance->requesterId ? null : $this->memberships->forUser($userId)?->companyId;

            if ($company === null || $this->groups->groupOfCompany($company) !== $instance->groupId) {
                throw new BusinessRuleViolation('The replacement must be a member of a tenant of the group, other than the requester.', 'invalid-approver');
            }

            $previous = null;

            foreach ($instance->steps as $step) {
                foreach ($step->slots as $slot) {
                    if ($slot->id === $slotId && $slot->kind === SlotKind::User) {
                        $previous = $slot->userId;
                    }
                }
            }

            $instance->replace($slotId, $userId);
            $this->instances->save($instance);

            $this->events->dispatch([new ApprovalChanged(
                $approvalId, $instance->subjectType, $instance->subjectId, $instance->companyId, 'approver_replaced', $actor->id,
                ['slot' => $slotId, 'previous_user_id' => $previous, 'user_id' => $userId], $ipAddress, $userAgent, $this->clock->now(),
            )]);
        });
    }
}
