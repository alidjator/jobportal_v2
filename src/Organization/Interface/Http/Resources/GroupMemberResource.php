<?php

namespace Src\Organization\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Organization\Application\Roles\GroupMemberView;
use Src\Shared\Application\Organization\GroupPermission;

/**
 * An account holding a group role, with the tenant it belongs to.
 *
 * @property GroupMemberView $resource
 */
final class GroupMemberResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->resource->member->userId,
            'name' => $this->resource->name,
            'company' => $this->resource->companyId === null ? null : ['id' => $this->resource->companyId, 'name' => $this->resource->companyName],
            'permissions' => array_map(static fn (GroupPermission $permission): string => $permission->value, $this->resource->member->permissions),
        ];
    }
}
