<?php

namespace Src\ReferenceData\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\ReferenceData\Application\Locations\LocationItem;

/** An area; a field the level does not have (a postal code has no name) is left out. @property LocationItem $resource */
final class LocationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            ...($this->resource->bpsCode !== null ? ['bps_code' => $this->resource->bpsCode] : []),
            ...($this->resource->name !== null ? ['name' => $this->resource->name] : []),
            ...$this->resource->extras,
        ];
    }
}
