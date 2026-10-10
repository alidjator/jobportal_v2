<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Application\ApplicationRatingView;
use Src\Recruitment\Application\MemberRating;

/**
 * The ratings of an application: average, count, the caller's own stars and every member's.
 *
 * @property ApplicationRatingView $resource
 */
final class ApplicationRatingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'average' => $this->resource->average,
            'count' => $this->resource->count,
            'mine' => $this->resource->mine,
            /** @var list<array{user_id: int, name: string, stars: int}> */
            'members' => array_map(static fn (MemberRating $rating): array => ['user_id' => $rating->userId, 'name' => $rating->name, 'stars' => $rating->stars], $this->resource->members),
        ];
    }
}
