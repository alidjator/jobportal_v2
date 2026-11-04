<?php

namespace Src\Assessment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Assessment\Domain\Question;
use Src\Assessment\Domain\QuestionImage;
use Src\Assessment\Domain\QuestionOption;

/**
 * A question as its author sees it: every option with the correct answers and the scores.
 *
 * @property Question $resource
 */
final class QuestionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'section_id' => $this->resource->sectionId,
            'position' => $this->resource->position,
            'type' => $this->resource->type->value,
            'text' => $this->resource->text,
            'points' => $this->resource->points,
            'scale_key' => $this->resource->scaleKey,
            'is_sample' => $this->resource->isSample,
            'think_seconds' => $this->resource->thinkSeconds,
            'answer_seconds' => $this->resource->answerSeconds,
            'max_retakes' => $this->resource->maxRetakes,
            /** The pictures of the question in the order shown; `GET tests/{id}/images/{imageId}` sends one. */
            'images' => array_map($this->image(...), $this->resource->images),
            'options' => array_map(fn (QuestionOption $option): array => [
                'label' => $option->label,
                /** The picture of the option, or null. */
                'image' => $option->image === null ? null : $this->image($option->image),
                'is_correct' => $option->isCorrect,
                'score_value' => $option->scoreValue,
                'scale_key' => $option->scaleKey,
            ], $this->resource->options),
        ];
    }

    /** @return array{id: int|null, alt: string, width: int, height: int} */
    private function image(QuestionImage $image): array
    {
        return ['id' => $image->id, 'alt' => $image->alt, 'width' => $image->width, 'height' => $image->height];
    }
}
