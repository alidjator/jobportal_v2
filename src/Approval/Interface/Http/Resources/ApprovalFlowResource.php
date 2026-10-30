<?php

namespace Src\Approval\Interface\Http\Resources;

use DateTimeInterface;
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
            /** @var list<array{key: string, type: string, label: string, unit: ?string, allowed_values: ?list<string>}> */
            'facts' => array_map(static fn (ApprovalFact $fact): array => [
                'key' => $fact->key, 'type' => $fact->type, 'label' => $fact->label, 'unit' => $fact->unit, 'allowed_values' => $fact->allowedValues === [] ? null : $fact->allowedValues,
            ], $type->facts),
            'relative_roles' => $type->relativeRoles,
            'source' => $this->resource->source,
            'flow' => $this->resource->flow === null ? null : self::steps($this->resource->flow),
            /** When the flow that applies was last saved; null when there is none. A flow inherited from the group carries the group's time. */
            'updated_at' => $this->resource->flow?->updatedAt?->format(DateTimeInterface::ATOM),
            /** The name of the account that saved the flow last; null when it is not known (a flow saved before it was recorded) or when there is no flow. */
            'updated_by' => $this->resource->updatedByName,
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
