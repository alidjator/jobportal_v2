<?php

namespace Src\Organization\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Organization\Domain\Grade;

/** @property Grade $resource */
final class GradeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->resource->id, 'code' => $this->resource->code, 'name' => $this->resource->name, 'rank' => $this->resource->rank, 'status' => $this->resource->status->value];
    }
}
