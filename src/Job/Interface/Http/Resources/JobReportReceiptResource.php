<?php

namespace Src\Job\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Job\Application\Reports\JobReportReceipt;

/**
 * The report a job seeker just made. Nothing here says whether the job was put on hold.
 *
 * @property JobReportReceipt $resource
 */
final class JobReportReceiptResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'job_id' => $this->resource->jobId,
            'reason' => $this->resource->reason->value,
            'status' => 'open',
            'created_at' => $this->resource->createdAt->format(DateTimeInterface::ATOM),
        ];
    }
}
