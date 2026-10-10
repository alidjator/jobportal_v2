<?php

namespace Src\JobSeeker\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\JobSeeker\Application\Candidates\CandidateListItem;

/**
 * One candidate in the search results; the picture is at `company/candidates/{slug}/avatar`.
 *
 * @property CandidateListItem $resource
 */
final class CandidateListResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->resource->slug,
            'name' => $this->resource->name,
            'has_avatar' => $this->resource->hasAvatar,
            'headline' => $this->resource->headline,
            'province' => $this->resource->province,
            'city' => $this->resource->city,
            'experience_level' => $this->resource->experienceLevel,
            'availability' => $this->resource->availability,
            'highest_education' => $this->resource->highestEducation,
            'updated_at' => $this->resource->updatedAt->format(DateTimeInterface::ATOM),
        ];
    }
}
