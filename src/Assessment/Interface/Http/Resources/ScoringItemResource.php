<?php

namespace Src\Assessment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Assessment\Application\Results\ScoringItem;

/** A written answer with the score its assessor gave it, if any. @property ScoringItem $resource */
final class ScoringItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'question_id' => $this->resource->questionId,
            'question' => $this->resource->questionText,
            'points' => $this->resource->points,
            'answer' => $this->resource->answerText,
            'score' => $this->resource->score,
        ];
    }
}
