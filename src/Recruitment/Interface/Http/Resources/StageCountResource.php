<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Application\StageCount;

/**
 * One column of the applicant pipeline: a stage of the company and the number of applications of the list in it.
 *
 * @property StageCount $resource
 */
final class StageCountResource extends JsonResource
{
    /** @return array{id: int, type: string, name: string, count: int} */
    public function toArray(Request $request): array
    {
        return ['id' => $this->resource->stageId, 'type' => $this->resource->type->value, 'name' => $this->resource->name, 'count' => $this->resource->count];
    }
}
