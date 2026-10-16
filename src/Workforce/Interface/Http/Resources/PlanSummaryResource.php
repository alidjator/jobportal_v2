<?php

namespace Src\Workforce\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Workforce\Application\Plan\PlanView;

/**
 * A plan without its lines. `estimated_cost` is left out for a reader without the permission to see costs.
 *
 * @property PlanView $resource
 */
class PlanSummaryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $plan = $this->resource->plan;

        return [
            'id' => $plan->id,
            'legal_entity_id' => $plan->legalEntityId,
            'legal_entity_name' => $this->resource->legalEntityName,
            'fiscal_year' => $plan->fiscalYear,
            'starts_on' => $this->resource->startsOn->format('Y-m-d'),
            'ends_on' => $this->resource->endsOn->format('Y-m-d'),
            'version' => $plan->version,
            'status' => $plan->status->value,
            'approval_id' => $plan->approvalId,
            'line_count' => count($plan->lines),
            'total_additions' => $plan->totalAdditions(),
            ...($this->resource->showCosts ? ['estimated_cost' => $plan->totalCost()] : []),
            'submitted_at' => $plan->submittedAt?->format(DateTimeInterface::ATOM),
            'decided_at' => $plan->decidedAt?->format(DateTimeInterface::ATOM),
            'decision_reason' => $plan->decisionReason,
            'created_at' => $plan->createdAt?->format(DateTimeInterface::ATOM),
        ];
    }
}
