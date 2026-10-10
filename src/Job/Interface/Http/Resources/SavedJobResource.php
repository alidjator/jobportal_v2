<?php

namespace Src\Job\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Job\Application\Saved\SavedJobEntry;

/**
 * A saved job. While the job is live, `job` is the row of the public search; otherwise `available` is false and `job` is null (the title and the saving time
 * remain).
 *
 * @property SavedJobEntry $resource
 */
final class SavedJobResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'job_id' => $this->resource->jobId,
            'title' => $this->resource->title,
            'saved_at' => $this->resource->savedAt->format(DateTimeInterface::ATOM),
            'available' => $this->resource->isAvailable(),
            'job' => $this->resource->job === null ? null : (new PublicJobListResource($this->resource->job))->toArray($request),
        ];
    }
}
