<?php

namespace Src\Assessment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Shared\Interface\Http\MemberChoice;

/**
 * A member who may be chosen to score a test on a job: the account id to send in `assessor_ids`, and the name to show.
 *
 * @property MemberChoice $resource
 */
final class AssessorResource extends JsonResource
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
