<?php

namespace Src\Organization\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Organization\Domain\PositionVersion;

/** @property PositionVersion $resource */
final class PositionVersionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'valid_from' => $this->resource->validFrom->format('Y-m-d'),
            'valid_to' => $this->resource->validTo?->format('Y-m-d'),
            'title' => $this->resource->title,
            'unit_id' => $this->resource->unitId,
            'min_grade_id' => $this->resource->minGradeId,
            'max_grade_id' => $this->resource->maxGradeId,
            'reports_to_position_id' => $this->resource->reportsToPositionId,
            'job_role_id' => $this->resource->jobRoleId,
        ];
    }
}
