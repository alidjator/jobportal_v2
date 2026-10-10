<?php

namespace Src\Training\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Training\Domain\Course;

/** @property Course $resource */
final class CourseResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'summary' => $this->resource->summary,
            'image_url' => $this->resource->imageUrl,
            'starts_at' => $this->resource->startsAt?->format(DateTimeInterface::ATOM),
            'ends_at' => $this->resource->endsAt?->format(DateTimeInterface::ATOM),
            'url' => $this->resource->url,
        ];
    }
}
