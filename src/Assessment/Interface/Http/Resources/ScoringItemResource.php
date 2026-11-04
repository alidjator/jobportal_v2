<?php

namespace Src\Assessment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Assessment\Application\Results\ScoringItem;
use Src\Assessment\Domain\QuestionImage;

/**
 * A written or video answer (the text is null for a video) with the score its assessor gave it, if any.
 *
 * @property ScoringItem $resource
 */
final class ScoringItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'question_id' => $this->resource->questionId,
            'question' => $this->resource->questionText,
            /** The pictures of the question in the order shown; `GET company/assessment/invitations/{id}/scoring/questions/{questionId}/images/{imageId}` sends one. */
            'images' => array_map(static fn (QuestionImage $image): array => ['id' => $image->id, 'alt' => $image->alt, 'width' => $image->width, 'height' => $image->height], $this->resource->images),
            'type' => $this->resource->type->value,
            'points' => $this->resource->points,
            'answer' => $this->resource->answerText,
            'score' => $this->resource->score,
        ];
    }
}
