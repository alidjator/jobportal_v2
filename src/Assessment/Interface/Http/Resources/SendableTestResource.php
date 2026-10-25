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
            /** @var int */
            'id' => $this->resource->id,
            /** @var string */
            'title' => $this->resource->title,
            /** @var string `skill` or `psychological` */
            'kind' => $this->resource->kind,
            /** @var string `company`, `library` or `battery` */
            'source' => $this->resource->source,
            /** @var string|null */
            'description' => $this->resource->description,
            /** @var int */
            'section_count' => $this->resource->sectionCount,
            /** @var int */
            'total_seconds' => $this->resource->totalSeconds,
        ];
    }
}
