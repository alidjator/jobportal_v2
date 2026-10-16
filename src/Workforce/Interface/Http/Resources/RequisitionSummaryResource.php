<?php

namespace Src\Workforce\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Workforce\Application\Requisition\RequisitionView;

/**
 * A requisition. The salary budget is left out for a reader without the permission to see costs.
 *
 * @property RequisitionView $resource
 */
class RequisitionSummaryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $requisition = $this->resource->requisition;

        return [
            'id' => $requisition->id,
            'status' => $requisition->status->value,
            'type' => $requisition->type->value,
            'legal_entity_id' => $requisition->legalEntityId,
            'position' => ['id' => $requisition->positionId, 'code' => $this->resource->position?->code, 'title' => $this->resource->position?->title],
            'unit_id' => $requisition->unitId,
            'requested_grade_id' => $requisition->requestedGradeId,
            'hires' => $requisition->hires,
            'job_type_id' => $requisition->jobTypeId,
            'contract_months' => $requisition->contractMonths,
            'target_join_date' => $requisition->targetJoinDate->format('Y-m-d'),
            'fiscal_year' => $requisition->fiscalYear,
            'cost_centre_id' => $requisition->costCentreId,
            ...($this->resource->showCosts ? ['salary_min' => $requisition->salaryMin, 'salary_max' => $requisition->salaryMax] : []),
            'justification' => $requisition->justification,
            'replacement' => $requisition->replacementName === null ? null : ['name' => $requisition->replacementName, 'leaving_date' => $requisition->replacementLeavingDate?->format('Y-m-d')],
            'hiring_manager_user_id' => $requisition->hiringManagerUserId,
            'requester_id' => $requisition->requesterId,
            'approval_id' => $requisition->approvalId,
            'decision_reason' => $requisition->decisionReason,
            'closed_reason' => $requisition->closedReason,
            'submitted_at' => $requisition->submittedAt?->format(DateTimeInterface::ATOM),
            'decided_at' => $requisition->decidedAt?->format(DateTimeInterface::ATOM),
            'closed_at' => $requisition->closedAt?->format(DateTimeInterface::ATOM),
            'created_at' => $requisition->createdAt?->format(DateTimeInterface::ATOM),
        ];
    }
}
