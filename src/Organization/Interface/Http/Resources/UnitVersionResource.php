<?php

namespace Src\Organization\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Organization\Domain\UnitVersion;

/** @property UnitVersion $resource */
final class UnitVersionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'valid_from' => $this->resource->validFrom->format('Y-m-d'),
            'valid_to' => $this->resource->validTo?->format('Y-m-d'),
            'name' => $this->resource->name,
            'parent_unit_id' => $this->resource->parentUnitId,
            'unit_type_id' => $this->resource->unitTypeId,
            'cost_centre_id' => $this->resource->costCentreId,
        ];
    }
}
