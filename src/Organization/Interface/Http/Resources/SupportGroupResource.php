<?php

namespace Src\Organization\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Organization\Application\Support\SupportGroupView;
use Src\Organization\Domain\GroupStatus;

/**
 * A group that gave the platform admin support access that is still running, with the end of that grant.
 *
 * @property SupportGroupView $resource
 */
final class SupportGroupResource extends JsonResource
{
    /** @return array{group_id: int, name: string, status: GroupStatus, expires_at: string} */
    public function toArray(Request $request): array
    {
        $group = $this->resource->group;

        return [
            'group_id' => $group->id,
            'name' => $group->name,
            'status' => $group->status,
            'expires_at' => $this->resource->grant->expiresAt->format(DateTimeInterface::ATOM),
        ];
    }
}
