<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Application\CompanyInterviewDetail;

/**
 * An interview for the team as in the list of its application, with the job and the candidate the interview page shows in its header.
 *
 * @property CompanyInterviewDetail $resource
 */
final class CompanyInterviewDetailResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...(new CompanyInterviewResource($this->resource->view))->toArray($request),
            'job' => ['id' => $this->resource->jobId, 'title' => $this->resource->jobTitle],
            'candidate_name' => $this->resource->candidateName,
        ];
    }
}
