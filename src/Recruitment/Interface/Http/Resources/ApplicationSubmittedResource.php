<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Domain\Application;

/** What the applicant gets back after applying: the id, the job and the stage type. The must-have result is never shown to the applicant. @property Application $resource */
final class ApplicationSubmittedResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'job_id' => $this->resource->jobId,
            'status' => $this->resource->stageType->value,
            'applied_at' => $this->resource->appliedAt->format(DATE_ATOM),
        ];
    }
}
