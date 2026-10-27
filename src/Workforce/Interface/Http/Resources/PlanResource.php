<?php

namespace Src\Workforce\Interface\Http\Resources;

use Illuminate\Http\Request;
use Src\Workforce\Application\Plan\PlanView;
use Src\Workforce\Domain\PlanLine;

/**
 * A plan with its lines, by position code.
 *
 * @property PlanView $resource
 */
final class PlanResource extends PlanSummaryResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $lines = array_map(function (PlanLine $line): array {
            $position = $this->resource->positions[$line->positionId] ?? null;

            return [
                'position_id' => $line->positionId,
                'position_code' => $position?->code,
                'position_title' => $position?->title,
                'opening_headcount' => $line->openingHeadcount,
                'target_headcount' => $line->targetHeadcount,
                'additions' => $line->additions(),
                'months' => $line->months,
                ...($this->resource->showCosts ? ['estimated_annual_cost' => $line->estimatedAnnualCost] : []),
            ];
        }, $this->resource->plan->lines);

        usort($lines, static fn (array $a, array $b): int => [$a['position_code'] ?? '', $a['position_id']] <=> [$b['position_code'] ?? '', $b['position_id']]);

        return [...parent::toArray($request), 'lines' => $lines];
    }
}
