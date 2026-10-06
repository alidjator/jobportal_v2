<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Domain\Stage;

/** One stage of the company's pipeline. @property Stage $resource */
final class StageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'type' => $this->resource->type->value,
            'name' => $this->resource->name,
            'position' => $this->resource->position,
            'is_default' => $this->resource->isDefault,
        ];
    }
}
