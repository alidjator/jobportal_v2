<?php

namespace Src\Organization\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Organization\Application\Group\GroupView;
use Src\Organization\Application\Group\LegalEntityView;

/**
 * A group with its legal entities. A platform admin also reads the suspension reason; a group member reads `my_permissions` instead.
 *
 * @property GroupView $resource
 */
final class GroupResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $group = $this->resource->group;
        $byMember = $this->resource->myPermissions !== null;

        return [
            'id' => $group->id,
            'name' => $group->name,
            'status' => $group->status->value,
            ...($byMember ? ['my_permissions' => $this->resource->myPermissions] : ['status_reason' => $group->statusReason, 'suspended_at' => $group->suspendedAt?->format(DateTimeInterface::ATOM)]),
            'member_count' => $this->resource->memberCount,
            'legal_entities' => array_map(static fn (LegalEntityView $view): array => (new LegalEntityResource($view))->resolve(), $this->resource->legalEntities),
            'created_at' => $group->createdAt?->format(DateTimeInterface::ATOM),
        ];
    }
}
