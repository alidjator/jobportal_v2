<?php

namespace Src\Workforce\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Src\Workforce\Domain\RequisitionAmendment;

/** A requisition with its amendments, the jobs opened for it and, for a `planned` one, the quota of its plan line. */
final class RequisitionResource extends RequisitionSummaryResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'job_count' => $this->resource->jobCount,
            'open_vacancies' => $this->resource->openVacancies,
            'quota' => $this->resource->quota,
            'amendments' => array_map(fn (RequisitionAmendment $amendment): array => [
                'id' => $amendment->id,
                'status' => $amendment->status->value,
                'hires' => $amendment->hires,
                ...($this->resource->showCosts ? ['salary_min' => $amendment->salaryMin, 'salary_max' => $amendment->salaryMax] : []),
                'reason' => $amendment->reason,
                'approval_id' => $amendment->approvalId,
                'decision_reason' => $amendment->decisionReason,
                'requested_by_id' => $amendment->requestedById,
                'decided_at' => $amendment->decidedAt?->format(DateTimeInterface::ATOM),
                'created_at' => $amendment->createdAt?->format(DateTimeInterface::ATOM),
            ], $this->resource->amendments),
        ];
    }
}
