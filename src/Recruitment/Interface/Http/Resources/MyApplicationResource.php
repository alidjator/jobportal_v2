<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Application\ApplicationSummary;

/**
 * One line of the applicant's list. The status is the system stage type; the company's own stage names are never shown.
 *
 * @property ApplicationSummary $resource
 */
final class MyApplicationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $row = $this->resource->row;

        return [
            'id' => $row->id,
            'job' => ['id' => $row->jobId, 'title' => $this->resource->jobTitle],
            'company' => ['id' => $row->companyId, 'name' => $this->resource->companyName],
            'status' => $row->status->value,
            'applied_at' => $row->appliedAt->format(DATE_ATOM),
            'viewed_at' => $row->viewedAt?->format(DATE_ATOM),
            /** The application came from karir.pkp: its history has two entries and the profile data was copied at the import. */
            'migrated' => $row->migratedAt !== null,
            'migrated_at' => $row->migratedAt?->format(DATE_ATOM),
        ];
    }
}
