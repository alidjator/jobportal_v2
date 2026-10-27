<?php

namespace Src\Assessment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Assessment\Application\Catalog\SendableTest;

/**
 * A test the member may send. `kind` is `skill` or `psychological`, `source` is `company` (its own), `library` or `battery` (granted to the company).
 * `total_seconds` is the time the sections last together.
 *
 * @property SendableTest $resource
 */
final class SendableTestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            /** `skill` or `psychological` */
            'kind' => $this->resource->kind,
            /** `company`, `library` or `battery` */
            'source' => $this->resource->source,
            'description' => $this->resource->description,
            'section_count' => $this->resource->sectionCount,
            'total_seconds' => $this->resource->totalSeconds,
        ];
    }
}
