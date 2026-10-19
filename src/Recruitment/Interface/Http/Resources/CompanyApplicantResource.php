<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Application\RecruiterApplicationRow;

/**
 * One line of the applicants across jobs: the line of the job list (`CompanyApplicationResource`) and the job the application is for.
 *
 * @property RecruiterApplicationRow $resource
 */
final class CompanyApplicantResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...(new CompanyApplicationResource($this->resource))->toArray($request),
            'job' => ['id' => $this->resource->jobId, 'title' => $this->resource->jobTitle],
        ];
    }
}
