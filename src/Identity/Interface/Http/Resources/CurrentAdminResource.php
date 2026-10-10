<?php

namespace Src\Identity\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Identity\Application\Account\CurrentAdmin;

/**
 * The signed-in administrator with their roles and effective permissions.
 *
 * @property CurrentAdmin $resource
 */
final class CurrentAdminResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...(new AdminResource($this->resource->admin))->toArray($request),
            'roles' => $this->resource->access->roles,
            'permissions' => $this->resource->access->permissions,
        ];
    }
}
