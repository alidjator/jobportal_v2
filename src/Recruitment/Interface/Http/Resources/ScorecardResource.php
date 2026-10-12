<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Application\ScorecardView;
use Src\Recruitment\Domain\ScorecardAnswer;

/**
 * A panel member's scorecard. `status` is null for an empty scorecard that was not saved yet. `edited_at` is set once a sent scorecard was edited; the edits
 * themselves are under `scorecards/{id}/revisions`.
 *
 * @property ScorecardView $resource
 */
final class ScorecardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $scorecard = $this->resource->scorecard;

        return [
            'id' => $scorecard->id,
            'interview_id' => $scorecard->interviewId,
            'member' => ['id' => $scorecard->memberId, 'name' => $this->resource->memberName],
            'status' => $scorecard->id === null ? null : $scorecard->status->value,
            'recommendation' => $scorecard->recommendation?->value,
            'note' => $scorecard->note,
            'score' => $scorecard->score,
            /** @var list<array{question_id: int, score: int|null, note: string|null}> */
            'answers' => array_map(static fn (ScorecardAnswer $answer): array => ['question_id' => $answer->questionId, 'score' => $answer->score, 'note' => $answer->note], $scorecard->answers),
            'submitted_at' => $scorecard->submittedAt?->format(DATE_ATOM),
            'edited_at' => $scorecard->editedAt?->format(DATE_ATOM),
        ];
    }
}
