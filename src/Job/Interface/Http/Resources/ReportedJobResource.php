<?php

namespace Src\Job\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Job\Application\Reports\ReportedJob;

/**
 * One job with open reports, as an admin sees it in the report list.
 *
 * @property ReportedJob $resource
 */
final class ReportedJobResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $company = $this->resource->company;

        return [
            'job_id' => $this->resource->jobId,
            'title' => $this->resource->title,
            'status' => $this->resource->status->value,
            'company' => $company === null ? ['id' => $this->resource->companyId, 'name' => null, 'slug' => null] : ['id' => $company->id, 'name' => $company->name, 'slug' => $company->slug],
            'open_reports' => $this->resource->openCount,
            'reasons' => (object) $this->resource->reasons,
            'first_reported_at' => $this->resource->firstReportedAt->format(DateTimeInterface::ATOM),
            'last_reported_at' => $this->resource->lastReportedAt->format(DateTimeInterface::ATOM),
            'held' => $this->resource->heldAt !== null,
            'held_at' => $this->resource->heldAt?->format(DateTimeInterface::ATOM),
        ];
    }
}
