<?php

namespace Src\Workforce\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Shared\Application\Organization\CostCentreOption;
use Src\Shared\Application\Organization\GradeOption;
use Src\Shared\Application\Organization\PositionSummary;
use Src\Shared\Application\Organization\UnitOption;
use Src\Workforce\Application\WorkforceOptions;

/**
 * The choices of the plan and requisition forms for a member of an internal tenant: the active positions (with their unit and grade range), units, grades and cost centres of their legal
 * entity as they are today, and the active accounts of the tenant that may be named hiring manager.
 *
 * @property WorkforceOptions $resource
 */
final class WorkforceOptionsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'legal_entity_id' => $this->resource->legalEntityId,
            'positions' => array_map(static fn (PositionSummary $position): array => [
                'id' => $position->id,
                'code' => $position->code,
                'title' => $position->title,
                'unit_id' => $position->unitId,
                'min_grade_id' => $position->minGradeId,
                'max_grade_id' => $position->maxGradeId,
            ], $this->resource->positions),
            'units' => array_map(static fn (UnitOption $unit): array => ['id' => $unit->id, 'code' => $unit->code, 'name' => $unit->name, 'cost_centre_id' => $unit->costCentreId], $this->resource->units),
            'grades' => array_map(static fn (GradeOption $grade): array => ['id' => $grade->id, 'code' => $grade->code, 'name' => $grade->name, 'rank' => $grade->rank], $this->resource->grades),
            'cost_centres' => array_map(static fn (CostCentreOption $centre): array => ['id' => $centre->id, 'code' => $centre->code, 'name' => $centre->name], $this->resource->costCentres),
            /** @var list<array{id: int, name: string}> */
            'hiring_managers' => $this->resource->managers,
        ];
    }
}
