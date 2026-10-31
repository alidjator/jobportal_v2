<?php

namespace Src\Company\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Company\Application\Presets\PermissionPresetUsage;

/**
 * A permission preset the company saved, with `member_count`: the members who hold exactly its permissions.
 *
 * @property PermissionPresetUsage $resource
 */
final class PermissionPresetResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->preset->id,
            'name' => $this->resource->preset->name,
            'permissions' => $this->resource->preset->permissions,
            'created_by_user_id' => $this->resource->preset->createdByUserId,
            'created_at' => $this->resource->preset->createdAt->format(DateTimeInterface::ATOM),
            'member_count' => $this->resource->memberCount,
            'updated_at' => $this->resource->preset->updatedAt->format(DateTimeInterface::ATOM),
        ];
    }
}
