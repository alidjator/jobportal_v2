<?php

namespace Src\Approval\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Approval\Application\Flow\FlowView;
use Src\Approval\Domain\Flow;
use Src\Approval\Domain\FlowApprover;
use Src\Approval\Domain\FlowStep;
use Src\Shared\Application\Approval\ApprovalFact;

/**
 * A subject type with the flow that applies. `source` is `company` (the tenant's own flow), `group` (the group's flow, default for the tenant) or `none`; `flow` is null for `none`.
 * The type lists the facts a condition may read and the relative roles an approver may be.
 *
 * @property FlowView $resource
 */
final class ApprovalFlowResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $type = $this->resource->type;

        return [
            'subject_type' => $type->code,
            'description' => $type->description,
            'facts' => array_map(static fn (ApprovalFact $fact): array => [
                'key' => $fact->key, 'type' => $fact->type, 'label' => $fact->label, 'allowed_values' => $fact->allowedValues === [] ? null : $fact->allowedValues,
            ], $type->facts),
            'relative_roles' => $type->relativeRoles,
            'source' => $this->resource->source,
            'flow' => $this->resource->flow === null ? null : self::steps($this->resource->flow),
        ];
    }

    /** @return array{steps: list<array<string, mixed>>} */
    public static function steps(Flow $flow): array
    {
        return ['steps' => array_map(static fn (FlowStep $step): array => [
            'position' => $step->position,
            'mode' => $step->mode->value,
            'conditions' => array_map(static fn ($condition): array => $condition->toArray(), $step->conditions),
            'approvers' => array_map(static fn (FlowApprover $approver): array => ['kind' => $approver->kind->value, 'value' => $approver->value], $step->approvers),
        ], $flow->steps)];
    }
}
