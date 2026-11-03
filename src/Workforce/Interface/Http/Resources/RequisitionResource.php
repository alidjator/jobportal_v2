<?php

namespace Src\Workforce\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Src\Workforce\Application\Requisition\RequisitionView;
use Src\Workforce\Domain\RequisitionAmendment;
use Src\Workforce\Domain\RequisitionDocument;

/**
 * A requisition with its amendments, the jobs opened for it and, for a `planned` one, the quota of its plan line.
 *
 * @property RequisitionView $resource
 */
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
            /** The documents attached to the requisition, oldest first; `GET .../documents/{id}` sends one. */
            'documents' => array_map(static fn (RequisitionDocument $document): array => [
                'id' => $document->id,
                'name' => $document->name,
                'content_type' => $document->contentType,
                'size_bytes' => $document->sizeBytes,
                'uploaded_by_id' => $document->uploadedById,
                'created_at' => $document->createdAt?->format(DateTimeInterface::ATOM),
            ], $this->resource->documents),
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
