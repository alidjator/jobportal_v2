<?php

namespace Src\Company\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Shared\Application\Job\AssignedJob;

/** @property AssignedJob $resource */
final class MemberJobResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'status' => $this->resource->status,
            'deadline' => $this->resource->deadline?->format('Y-m-d'),
        ];
    }
}
