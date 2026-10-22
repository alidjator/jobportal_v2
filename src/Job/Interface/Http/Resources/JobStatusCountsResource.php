<?php

namespace Src\Job\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Job\Application\JobStatusCounts;

/**
 * The job counts per status: `all` and one key per status.
 *
 * @property JobStatusCounts $resource
 */
final class JobStatusCountsResource extends JsonResource
{
    /** @return array<string, int> */
    public function toArray(Request $request): array
    {
        return ['all' => $this->resource->all, ...$this->resource->byStatus];
    }
}
