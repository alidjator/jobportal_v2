<?php

namespace Src\Assessment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Assessment\Domain\Test;

/**
 * A test in the catalogue a company reads: what it measures, never how.
 *
 * @property Test $resource
 */
final class CatalogTestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'kind' => $this->resource->kind()->value,
            'title' => $this->resource->title(),
            'description' => $this->resource->description(),
            'measured_capabilities' => $this->resource->capabilities(),
        ];
    }
}
