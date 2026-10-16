<?php

namespace Src\Organization\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Organization\Domain\UnitType;

/** @property UnitType $resource */
final class UnitTypeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->resource->id, 'code' => $this->resource->code, 'name' => $this->resource->name, 'level_order' => $this->resource->levelOrder, 'status' => $this->resource->status->value];
    }
}
