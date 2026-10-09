<?php

namespace Src\Job\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Job\Application\JobPerformance;

/** How one job did over the last 30 Jakarta days. @property JobPerformance $resource */
final class JobPerformanceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'job_id' => $this->resource->jobId,
            'title' => $this->resource->title,
            'status' => $this->resource->status->value,
            'from' => $this->resource->from,
            'to' => $this->resource->to,
            'views' => $this->resource->views,
            'applications' => $this->resource->applications,
            'applications_per_view' => $this->resource->applicationsPerView(),
            'views_daily' => $this->resource->viewsDaily,
            'applications_daily' => $this->resource->applicationsDaily,
        ];
    }
}
