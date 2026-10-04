<?php

namespace Src\ReferenceData\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\ReferenceData\Application\Masters\MasterItem;

/** The admin view of a master item: both languages, order and active flag. @property MasterItem $resource */
final class AdminMasterItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'slug' => $this->resource->slug,
            'name' => $this->resource->name->toArray(),
            'sort_order' => $this->resource->sortOrder,
            'is_active' => $this->resource->isActive,
            ...$this->resource->extras,
        ];
    }
}
