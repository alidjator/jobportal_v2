<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Domain\Video\InterviewAttendance;

/**
 * One time somebody was in the video room. `left_at` is null while they are still there, or when the room closed without the video server reporting that
 * they left.
 *
 * @property InterviewAttendance $resource
 */
final class AttendanceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->resource->userId,
            /** @var 'company'|'candidate' */
            'role' => $this->resource->role,
            'joined_at' => $this->resource->joinedAt->format(DATE_ATOM),
            'left_at' => $this->resource->leftAt?->format(DATE_ATOM),
        ];
    }
}
