<?php

namespace Src\Approval\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Approval\Application\Instance\ApprovalView;
use Src\Approval\Domain\InstanceStep;
use Src\Approval\Domain\Slot;
use Src\Approval\Domain\SlotKind;

/**
 * A running or ended approval. A step shows its approvers as places: `user` places name the account (null when the relative role named nobody), `role` places the group
 * permission whose holders may fill them; `decided_by` and `on_behalf_of` say who approved and, for a delegate, for whom. `can_decide` is whether the reader's turn is now;
 * `can_replace` whether they may name a replacement for a vacant place.
 *
 * @property ApprovalView $resource
 */
final class ApprovalResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $instance = $this->resource->instance;
        $person = fn (?int $id): ?array => $id === null ? null : ['id' => $id, 'name' => $this->resource->names[$id] ?? null];

        return [
            'id' => $instance->id,
            'subject_type' => $instance->subjectType,
            'subject_id' => $instance->subjectId,
            'title' => $instance->title,
            'status' => $instance->status->value,
            'requester' => $person($instance->requesterId),
            'company_id' => $instance->companyId,
            'facts' => (object) $instance->facts,
            'steps' => array_map(static fn (InstanceStep $step): array => [
                'position' => $step->position,
                'mode' => $step->mode->value,
                'status' => $step->status->value,
                'approvers' => array_map(fn (Slot $slot): array => [
                    'id' => $slot->id,
                    'kind' => $slot->kind->value,
                    'user' => $slot->kind === SlotKind::User ? $person($slot->userId) : null,
                    'permission' => $slot->permission,
                    'status' => $slot->status->value,
                    'decided_by' => $person($slot->decidedByUserId),
                    'on_behalf_of' => $person($slot->onBehalfOfUserId),
                    'decided_at' => $slot->decidedAt?->format(DateTimeInterface::ATOM),
                ], $step->slots),
            ], $instance->steps),
            'outcome' => $instance->outcomeByUserId === null && $instance->outcomeReason === null ? null : ['by' => $person($instance->outcomeByUserId), 'reason' => $instance->outcomeReason],
            'can_decide' => $this->resource->canDecide,
            'can_replace' => $this->resource->canReplace,
            'created_at' => $instance->createdAt->format(DateTimeInterface::ATOM),
            'completed_at' => $instance->completedAt?->format(DateTimeInterface::ATOM),
        ];
    }
}
