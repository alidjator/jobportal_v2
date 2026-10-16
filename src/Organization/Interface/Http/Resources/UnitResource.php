<?php

namespace Src\Organization\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Organization\Application\Structure\UnitView;

/**
 * A unit with the version valid on the day that was read: `valid_from` and `valid_to` (null while open) say how long that version lasts.
 *
 * @property UnitView $resource
 */
final class UnitResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $unit = $this->resource->unit;
        $version = $this->resource->version;

        return [
            'id' => $unit->id,
            'legal_entity_id' => $unit->legalEntityId,
            'code' => $unit->code,
            'status' => $unit->status->value,
            'name' => $version->name,
            'parent_unit_id' => $version->parentUnitId,
            'unit_type_id' => $version->unitTypeId,
            'cost_centre_id' => $version->costCentreId,
            'valid_from' => $version->validFrom->format('Y-m-d'),
            'valid_to' => $version->validTo?->format('Y-m-d'),
        ];
    }
}
