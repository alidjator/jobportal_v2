<?php

namespace Src\Approval\Application\Flow;

use Src\Approval\Domain\ApproverKind;
use Src\Approval\Domain\ConditionOperator;
use Src\Approval\Domain\FlowApprover;
use Src\Approval\Domain\FlowCondition;
use Src\Approval\Domain\FlowOwner;
use Src\Approval\Domain\FlowStep;
use Src\Approval\Domain\OwnerType;
use Src\Shared\Application\Approval\ApprovalFact;
use Src\Shared\Application\Approval\ApprovalSubjectType;
use Src\Shared\Application\Company\CompanyMembershipResolver;
use Src\Shared\Application\Organization\GroupDirectory;
use Src\Shared\Application\Organization\GroupPermission;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * What a flow must satisfy: 1 to 10 steps; at most 5 conditions per step, each on a fact of the subject type with an operator and a value that fit it; 1 to 10 approvers
 * per step, none twice: a member of the owner's tenant (of any tenant of the group for a group flow), a group permission, or a relative role the subject type names.
 */
final readonly class FlowRules
{
    public const int MAX_STEPS = 10;

    public const int MAX_CONDITIONS = 5;

    public const int MAX_APPROVERS = 10;

    public function __construct(private CompanyMembershipResolver $memberships, private GroupDirectory $groups) {}

    /**
     * @param  list<FlowStep>  $steps
     *
     * @throws BusinessRuleViolation `invalid-flow-steps`, `invalid-flow-condition` or `invalid-flow-approver`
     */
    public function assertValid(FlowOwner $owner, int $groupId, ApprovalSubjectType $type, array $steps): void
    {
        if ($steps === [] || count($steps) > self::MAX_STEPS) {
            throw new BusinessRuleViolation('A flow has 1 to '.self::MAX_STEPS.' steps.', 'invalid-flow-steps');
        }

        foreach ($steps as $step) {
            $this->assertConditions($type, $step);
            $this->assertApprovers($owner, $groupId, $type, $step);
        }
    }

    private function assertConditions(ApprovalSubjectType $type, FlowStep $step): void
    {
        if (count($step->conditions) > self::MAX_CONDITIONS) {
            throw new BusinessRuleViolation("Step {$step->position} has more than ".self::MAX_CONDITIONS.' conditions.', 'invalid-flow-condition');
        }

        foreach ($step->conditions as $condition) {
            $fact = $type->fact($condition->fact) ?? throw new BusinessRuleViolation("Step {$step->position}: {$type->code} has no fact {$condition->fact}.", 'invalid-flow-condition');

            if (! $this->fits($fact, $condition)) {
                throw new BusinessRuleViolation("Step {$step->position}: the condition on {$condition->fact} does not fit the fact (type {$fact->type}).", 'invalid-flow-condition');
            }
        }
    }

    private function fits(ApprovalFact $fact, FlowCondition $condition): bool
    {
        $value = $condition->value;

        return match ($fact->type) {
            ApprovalFact::INTEGER => is_int($value) && in_array($condition->operator, [ConditionOperator::Gte, ConditionOperator::Lte, ConditionOperator::Eq], true),
            ApprovalFact::BOOLEAN => is_bool($value) && $condition->operator === ConditionOperator::Eq,
            default => match ($condition->operator) {
                ConditionOperator::Eq => is_string($value) && $this->allowed($fact, [$value]),
                ConditionOperator::In => is_array($value) && $value !== [] && array_is_list($value) && array_filter($value, static fn (mixed $item): bool => ! is_string($item)) === [] && $this->allowed($fact, $value),
                default => false,
            },
        };
    }

    /** @param  list<string>  $values */
    private function allowed(ApprovalFact $fact, array $values): bool
    {
        return $fact->allowedValues === [] || array_diff($values, $fact->allowedValues) === [];
    }

    private function assertApprovers(FlowOwner $owner, int $groupId, ApprovalSubjectType $type, FlowStep $step): void
    {
        if ($step->approvers === [] || count($step->approvers) > self::MAX_APPROVERS) {
            throw new BusinessRuleViolation("Step {$step->position} has 1 to ".self::MAX_APPROVERS.' approvers.', 'invalid-flow-approver');
        }

        $seen = [];

        foreach ($step->approvers as $approver) {
            $key = $approver->kind->value.':'.$approver->value;

            if (isset($seen[$key]) || ! $this->approverFits($owner, $groupId, $type, $approver)) {
                throw new BusinessRuleViolation("Step {$step->position}: the approver {$key} is not valid or is named twice.", 'invalid-flow-approver');
            }

            $seen[$key] = true;
        }
    }

    private function approverFits(FlowOwner $owner, int $groupId, ApprovalSubjectType $type, FlowApprover $approver): bool
    {
        return match ($approver->kind) {
            ApproverKind::Relative => in_array($approver->value, $type->relativeRoles, true),
            ApproverKind::GroupRole => in_array($approver->value, GroupPermission::values(), true),
            ApproverKind::Member => ctype_digit($approver->value) && $this->isMemberOfScope($owner, $groupId, (int) $approver->value),
        };
    }

    private function isMemberOfScope(FlowOwner $owner, int $groupId, int $userId): bool
    {
        $companyId = $this->memberships->forUser($userId)?->companyId;

        if ($companyId === null) {
            return false;
        }

        return $owner->type === OwnerType::Company ? $companyId === $owner->id : $this->groups->groupOfCompany($companyId) === $groupId;
    }
}
