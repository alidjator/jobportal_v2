<?php

namespace Src\Organization\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Organization\Domain\CostCentre;

/** @property CostCentre $resource */
final class CostCentreResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id, 'legal_entity_id' => $this->resource->legalEntityId, 'code' => $this->resource->code, 'name' => $this->resource->name,
            'status' => $this->resource->status->value,
        ];
    }
}
