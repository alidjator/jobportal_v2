<?php

namespace Src\JobSeeker\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\JobSeeker\Domain\WorkExperience;

/**
 * One work experience; months are `YYYY-MM`.
 *
 * @property WorkExperience $resource
 */
final class ExperienceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'job_title' => $this->resource->jobTitle,
            'company_name' => $this->resource->companyName,
            'job_type_id' => $this->resource->jobTypeId,
            'location_text' => $this->resource->locationText,
            'start_date' => $this->resource->startDate->format('Y-m'),
            'end_date' => $this->resource->endDate?->format('Y-m'),
            'is_current' => $this->resource->isCurrent,
            'description' => $this->resource->description,
        ];
    }
}
