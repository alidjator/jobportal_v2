<?php

namespace Src\Assessment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Assessment\Application\Catalog\CatalogEntry;
use Src\Assessment\Domain\Question;
use Src\Assessment\Domain\QuestionImage;
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
            /** The example report is `GET company/assessment/catalog/{id}/example-report`. */
            'has_example_report' => $this->resource->hasExampleReport,
            'samples' => array_map(static fn (Question $question): array => [
                'id' => $question->id,
                'type' => $question->type->value,
                'text' => $question->text,
                /** The pictures of the sample in the order shown; `GET company/assessment/catalog/{id}/samples/{questionId}/images/{imageId}` sends one. */
                'images' => array_map(static fn (QuestionImage $image): array => ['id' => $image->id, 'alt' => $image->alt, 'width' => $image->width, 'height' => $image->height], $question->images),
                'options' => array_map(static fn (QuestionOption $option): string => $option->label, $question->type->hasChoices() ? $question->options : []),
                /** The picture of each option, in the order of `options` (null for an option without one). */
                'option_images' => array_map(static fn (QuestionOption $option): ?array => $option->image === null ? null : ['id' => $option->image->id, 'alt' => $option->image->alt, 'width' => $option->image->width, 'height' => $option->image->height], $question->type->hasChoices() ? $question->options : []),
            ], $this->resource->samples),
        ];
    }
}
