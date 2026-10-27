<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Shared\Interface\Http\MemberChoice;

/**
 * A member who may sit on an interview panel: the account id to send as `interviewer_id` or in `participant_ids`, and the name to show.
 *
 * @property MemberChoice $resource
 */
final class InterviewerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
        ];
    }
}
