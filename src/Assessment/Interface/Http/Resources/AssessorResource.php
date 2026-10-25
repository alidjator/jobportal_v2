<?php

namespace Src\Assessment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A member who may be chosen to score a test on a job: the account id to send in `assessor_ids`, and the name to show.
 *
 * @property array{id: int, name: string} $resource
 */
final class AssessorResource extends JsonResource
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
