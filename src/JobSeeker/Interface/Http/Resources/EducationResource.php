<?php

namespace Src\JobSeeker\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\JobSeeker\Domain\Education;

/** One education entry. @property Education $resource */
final class EducationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'education_level_id' => $this->resource->educationLevelId,
            'institution' => $this->resource->institution,
            'field_of_study' => $this->resource->fieldOfStudy,
            'start_year' => $this->resource->startYear,
            'end_year' => $this->resource->endYear,
            'is_current' => $this->resource->isCurrent,
            'gpa' => $this->resource->gpa,
            'description' => $this->resource->description,
        ];
    }
}
