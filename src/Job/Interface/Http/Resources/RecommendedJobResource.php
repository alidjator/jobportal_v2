<?php

namespace Src\Job\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Job\Application\Matching\RecommendedJob;

/**
 * A recommended job: the row of the public search with the match, including the fit of each signal.
 *
 * @property RecommendedJob $resource
 */
final class RecommendedJobResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...(new PublicJobListResource($this->resource->job))->toArray($request),
            'match' => ['percent' => $this->resource->match->percent, 'signals' => $this->resource->match->signals],
        ];
    }
}
