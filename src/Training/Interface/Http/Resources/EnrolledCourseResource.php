<?php

namespace Src\Training\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Training\Domain\EnrolledCourse;

/** A course of the catalog plus the job seeker's progress in it. @property EnrolledCourse $resource */
final class EnrolledCourseResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...(new CourseResource($this->resource->course))->toArray($request),
            'progress' => $this->resource->progress,
            'last_access_at' => $this->resource->lastAccessAt?->format(DateTimeInterface::ATOM),
        ];
    }
}
