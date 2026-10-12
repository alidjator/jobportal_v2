<?php

namespace Src\Assessment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Assessment\Application\Video\VideoState;

/** @property VideoState $resource */
final class VideoStateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $state = $this->resource;

        return [
            'takes_used' => $state->takesUsed,
            'takes_allowed' => $state->takesAllowed,
            'answer' => $state->answer === null ? null : VideoTakeResource::summary($state->answer),
            'recording' => $state->recording === null ? null : VideoTakeResource::summary($state->recording),
        ];
    }
}
