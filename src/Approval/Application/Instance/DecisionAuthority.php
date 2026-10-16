<?php

namespace Src\Approval\Application\Instance;

use DateTimeImmutable;
use Src\Approval\Domain\ApprovalInstance;
use Src\Approval\Domain\DelegationRepository;
use Src\Approval\Domain\Slot;
use Src\Approval\Domain\SlotKind;
use Src\Approval\Domain\SlotStatus;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Organization\GroupPermissions;

/**
 * Which open slot of the step being decided an account may fill, and for whom: its own (named, or as a holder of the group permission of a role slot), or one of an approver
 * who delegated to it for today. The requester never decides. Every approver may decide the steps assigned to them without a separate permission.
 */
final readonly class DecisionAuthority
{
    public function __construct(private DelegationRepository $delegations, private GroupPermissions $groups, private Clock $clock) {}

    public function today(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->clock->now()->format('Y-m-d'));
    }

    /** @return array{group_id?: int, permissions?: list<string>} the group role of the account, empty without one */
    public function held(Actor $actor): array
    {
        return $this->groups->heldBy($actor);
    }

    /** @return list<int> the approvers who let the account decide for them today */
    public function delegatorsToday(int $userId): array
    {
        return $this->delegations->delegatorsOf($userId, $this->today());
    }

    /** @return array{slot: Slot, onBehalfOf: int|null}|null */
    public function slotFor(ApprovalInstance $instance, Actor $actor, DateTimeImmutable $today): ?array
    {
        $step = $instance->isPending() ? $instance->currentStep() : null;

        if ($step === null || $actor->id === null || $actor->id === $instance->requesterId) {
            return null;
        }

        $held = $this->groups->heldBy($actor);
        $permissions = ($held['group_id'] ?? null) === $instance->groupId ? $held['permissions'] : [];
        $delegators = $this->delegations->delegatorsOf($actor->id, $today);
        $viaDelegation = null;

        foreach ($step->slots as $slot) {
            if ($slot->status !== SlotStatus::Open) {
                continue;
            }

            if ($slot->kind === SlotKind::User && $slot->userId === $actor->id) {
                return ['slot' => $slot, 'onBehalfOf' => null];
            }

            if ($slot->kind === SlotKind::Role && in_array($slot->permission, $permissions, true)) {
                return ['slot' => $slot, 'onBehalfOf' => null];
            }

            if ($viaDelegation === null && $slot->kind === SlotKind::User && in_array($slot->userId, $delegators, true)) {
                $viaDelegation = ['slot' => $slot, 'onBehalfOf' => $slot->userId];
            }
        }

        return $viaDelegation;
    }

    /**
     * Whether the account has anything to do with the approval: it asked for it, holds a slot of it (or may decide for one), or may replace its approvers (a company admin of the
     * tenant, or a holder of `group.approvals.manage`). Anyone else cannot tell that it exists.
     */
    public function isInvolved(ApprovalInstance $instance, Actor $actor, DateTimeImmutable $today): bool
    {
        if ($actor->id === null) {
            return false;
        }

        if ($instance->requesterId === $actor->id || $this->canReplace($instance, $actor)) {
            return true;
        }

        $held = $this->groups->heldBy($actor);
        $permissions = ($held['group_id'] ?? null) === $instance->groupId ? $held['permissions'] : [];
        $delegators = $this->delegations->delegatorsOf($actor->id, $today);

        foreach ($instance->steps as $step) {
            foreach ($step->slots as $slot) {
                if (($slot->kind === SlotKind::User && ($slot->userId === $actor->id || in_array($slot->userId, $delegators, true)))
                    || ($slot->kind === SlotKind::Role && in_array($slot->permission, $permissions, true))) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Whether the account may name a replacement for a vacant slot: a company admin of the tenant, or a holder of `group.approvals.manage` of its group. */
    public function canReplace(ApprovalInstance $instance, Actor $actor): bool
    {
        if ($actor->membership?->companyId === $instance->companyId && $actor->isCompanyAdmin()) {
            return true;
        }

        $held = $this->groups->heldBy($actor);

        return ($held['group_id'] ?? null) === $instance->groupId && in_array('group.approvals.manage', $held['permissions'], true);
    }
}
