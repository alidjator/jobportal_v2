<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Application\InterviewQuestionList;
use Src\Recruitment\Domain\InterviewQuestion;

/**
 * The questions an interview keeps, with the interviewer guidance. `locked` is true once a scorecard was started and the list can no longer change.
 *
 * @property InterviewQuestionList $resource
 */
final class InterviewQuestionsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'interview_id' => $this->resource->interviewId,
            'kit' => $this->resource->kitName === null ? null : ['id' => $this->resource->kitId, 'name' => $this->resource->kitName],
            'locked' => $this->resource->locked,
            'questions' => array_map(
                static fn (InterviewQuestion $item, int $index): array => ['id' => $item->id, 'position' => $index + 1, 'text' => $item->question->text, 'guidance' => $item->question->guidance, 'weight' => $item->question->weight],
                $this->resource->questions,
                array_keys($this->resource->questions),
            ),
        ];
    }
}
