<?php

namespace Src\JobSeeker\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\JobSeeker\Application\Candidates\RecommendedCandidate;

/**
 * A candidate recommended for a job: the row of the candidate search with the match and whether the company already invited them.
 *
 * @property RecommendedCandidate $resource
 */
final class RecommendedCandidateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...(new CandidateListResource($this->resource->candidate))->toArray($request),
            'match' => ['percent' => $this->resource->match->percent, 'signals' => $this->resource->match->signals],
            'invited' => $this->resource->invited,
        ];
    }
}
