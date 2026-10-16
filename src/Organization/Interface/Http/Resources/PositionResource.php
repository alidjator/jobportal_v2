<?php

namespace Src\Organization\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Organization\Application\Structure\PositionView;

/**
 * A position with the version valid on the day that was read: `valid_from` and `valid_to` (null while open) say how long that version lasts.
 *
 * @property PositionView $resource
 */
final class PositionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $position = $this->resource->position;
        $version = $this->resource->version;

        return [
            'id' => $position->id,
            'legal_entity_id' => $position->legalEntityId,
            'code' => $position->code,
            'status' => $position->status->value,
            'title' => $version->title,
            'unit_id' => $version->unitId,
            'min_grade_id' => $version->minGradeId,
            'max_grade_id' => $version->maxGradeId,
            'reports_to_position_id' => $version->reportsToPositionId,
            'job_role_id' => $version->jobRoleId,
            'valid_from' => $version->validFrom->format('Y-m-d'),
            'valid_to' => $version->validTo?->format('Y-m-d'),
        ];
    }
}
