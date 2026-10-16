<?php

namespace Src\Approval\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Approval\Application\Flow\FlowInput;
use Src\Approval\Application\Flow\FlowRules;
use Src\Approval\Domain\ApproverKind;
use Src\Approval\Domain\ConditionOperator;
use Src\Approval\Domain\FlowApprover;
use Src\Approval\Domain\FlowCondition;
use Src\Approval\Domain\FlowStep;
use Src\Approval\Domain\StepMode;

/**
 * The steps of a flow, in order: `steps[].mode` (`any` or `all`), `steps[].conditions[]` (`fact`, `operator`, `value`; optional) and `steps[].approvers[]` (`kind` `member`,
 * `group_role` or `relative`, and its `value`). Whether the facts, values and approvers fit the subject type is the use case's rule.
 */
final class SaveApprovalFlowRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'steps' => ['required', 'array', 'min:1', 'max:'.FlowRules::MAX_STEPS],
            'steps.*' => ['array'],
            'steps.*.mode' => ['required', Rule::enum(StepMode::class)],
            'steps.*.conditions' => ['nullable', 'array', 'max:'.FlowRules::MAX_CONDITIONS],
            'steps.*.conditions.*.fact' => ['required', 'string', 'max:60'],
            'steps.*.conditions.*.operator' => ['required', Rule::enum(ConditionOperator::class)],
            'steps.*.conditions.*.value' => ['required'],
            'steps.*.approvers' => ['required', 'array', 'min:1', 'max:'.FlowRules::MAX_APPROVERS],
            'steps.*.approvers.*.kind' => ['required', Rule::enum(ApproverKind::class)],
            'steps.*.approvers.*.value' => ['required', 'string', 'max:100'],
        ];
    }

    public function flow(): FlowInput
    {
        $steps = [];

        foreach (array_values($this->array('steps')) as $index => $step) {
            $steps[] = new FlowStep(
                $index + 1,
                StepMode::from((string) $step['mode']),
                array_map(static fn (array $condition): FlowCondition => new FlowCondition((string) $condition['fact'], ConditionOperator::from((string) $condition['operator']), $condition['value']), array_values($step['conditions'] ?? [])),
                array_map(static fn (array $approver): FlowApprover => new FlowApprover(ApproverKind::from((string) $approver['kind']), (string) $approver['value']), array_values($step['approvers'])),
            );
        }

        return new FlowInput($steps);
    }
}
