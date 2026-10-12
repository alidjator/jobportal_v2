<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Application\Video\VideoLobby;

/**
 * The lobby of a video interview: `idle`, `waiting` (the job seeker has waited since `waiting_since`) or `admitted`.
 *
 * @property VideoLobby $resource
 */
final class VideoLobbyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            /** @var 'idle'|'waiting'|'admitted' */
            'state' => $this->resource->state,
            'waiting_since' => $this->resource->waitingSince?->format(DATE_ATOM),
            'admitted_at' => $this->resource->admittedAt?->format(DATE_ATOM),
        ];
    }
}
