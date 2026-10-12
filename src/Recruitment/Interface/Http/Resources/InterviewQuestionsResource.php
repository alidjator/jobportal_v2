<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Application\InterviewQuestionList;

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
            'questions' => InterviewKitResource::questions($this->resource->questions),
        ];
    }
}
