<?php

namespace Src\Assessment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Assessment\Application\Catalog\CatalogEntry;
use Src\Assessment\Domain\Question;
use Src\Assessment\Domain\QuestionOption;

/**
 * A catalogue test with its size and its sample questions. A sample shows the wording and the choices, never the correct answer or the scoring.
 *
 * @property CatalogEntry $resource
 */
final class CatalogEntryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...(new CatalogTestResource($this->resource->test))->toArray($request),
            'instructions' => $this->resource->test->instructions(),
            'section_count' => $this->resource->sectionCount,
            'total_seconds' => $this->resource->totalSeconds,
            'samples' => array_map(static fn (Question $question): array => [
                'type' => $question->type->value,
                'text' => $question->text,
                'options' => array_map(static fn (QuestionOption $option): string => $option->label, $question->type->hasChoices() ? $question->options : []),
            ], $this->resource->samples),
        ];
    }
}
