<?php

namespace Src\Assessment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Assessment\Application\Results\ScoringItem;

/** A written or video answer (the text is null for a video) with the score its assessor gave it, if any. @property ScoringItem $resource */
final class ScoringItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'question_id' => $this->resource->questionId,
            'question' => $this->resource->questionText,
            'type' => $this->resource->type->value,
            'points' => $this->resource->points,
            'answer' => $this->resource->answerText,
            'score' => $this->resource->score,
        ];
    }
}
