<?php

namespace Src\Job\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Job\Application\Admin\AdminJobStatusCounts;

/**
 * The job counts per status: `all` and one key per status.
 *
 * @property AdminJobStatusCounts $resource
 */
final class AdminJobStatusCountsResource extends JsonResource
{
    /** @return array<string, int> */
    public function toArray(Request $request): array
    {
        return ['all' => $this->resource->all, ...$this->resource->byStatus];
    }
}
