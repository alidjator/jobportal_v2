<?php

namespace Src\Job\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Job\Application\Reports\JobReportEntry;

/**
 * One report of a job, as an admin reads it. The reporter is null once the account was erased.
 *
 * @property JobReportEntry $resource
 */
final class JobReportEntryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'reason' => $this->resource->reason->value,
            'note' => $this->resource->note,
            'status' => $this->resource->status->value,
            'reporter_user_id' => $this->resource->reporterUserId,
            'created_at' => $this->resource->createdAt->format(DateTimeInterface::ATOM),
            'decided_at' => $this->resource->decidedAt?->format(DateTimeInterface::ATOM),
            'decided_by_admin_id' => $this->resource->decidedByAdminId,
        ];
    }
}
