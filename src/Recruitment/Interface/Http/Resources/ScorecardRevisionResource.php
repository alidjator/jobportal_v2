<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Domain\ScorecardRevision;

/**
 * The content of a sent scorecard before one edit. `others_visible` is true when another member's scorecard was already sent at the time of the edit, so the
 * edit may have been influenced by it.
 *
 * @property ScorecardRevision $resource
 */
final class ScorecardRevisionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'previous' => $this->resource->previous,
            'others_visible' => $this->resource->othersVisible,
            'edited_at' => $this->resource->createdAt->format(DATE_ATOM),
        ];
    }
}
