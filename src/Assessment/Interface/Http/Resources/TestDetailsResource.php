<?php

namespace Src\Assessment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Assessment\Application\Authoring\TestDetails;
use Src\Assessment\Domain\Section;

/** @property TestDetails $resource */
final class TestDetailsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...(new TestResource($this->resource->test))->toArray($request),
            'total_seconds' => array_sum(array_map(static fn (Section $section): int => $section->durationSeconds, $this->resource->sections)),
            'sections' => array_map(
                fn (Section $section): array => (new SectionResource($section, $this->resource->questionCounts[(int) $section->id] ?? 0))->toArray($request),
                $this->resource->sections,
            ),
        ];
    }
}
