<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A member who may sit on an interview panel: the account id to send as `interviewer_id` or in `participant_ids`, and the name to show.
 *
 * @property array{id: int, name: string} $resource
 */
final class InterviewerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            /** @var int */
            'id' => $this->resource['id'],
            /** @var string */
            'name' => $this->resource['name'],
        ];
    }
}
