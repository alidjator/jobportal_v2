<?php

namespace Src\Organization\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Organization\Application\Group\GroupSummary;

/**
 * One row of the group list of the platform admin.
 *
 * @property GroupSummary $resource
 */
final class GroupSummaryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $group = $this->resource->group;

        return [
            'id' => $group->id,
            'name' => $group->name,
            'status' => $group->status->value,
            'legal_entity_count' => $this->resource->legalEntityCount,
            'member_count' => $this->resource->memberCount,
            'created_at' => $group->createdAt?->format(DateTimeInterface::ATOM),
        ];
    }
}
