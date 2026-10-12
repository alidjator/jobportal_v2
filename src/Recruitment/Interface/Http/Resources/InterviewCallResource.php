<?php

namespace Src\Recruitment\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Domain\Voice\InterviewCall;

/**
 * One call of the team to the candidate. `status` is `dialing`, `ringing`, `answered`, `no_answer`, `busy`, `failed` or `ended`; `duration_seconds` is set once an
 * answered call ended. The dialled number is not repeated: the team has it in the interview.
 *
 * @property InterviewCall $resource
 */
final class InterviewCallResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $call = $this->resource;

        return [
            'id' => $call->id,
            'interview_id' => $call->interviewId,
            'started_by' => $call->startedBy,
            /** @var 'dialing'|'ringing'|'answered'|'no_answer'|'busy'|'failed'|'ended' */
            'status' => $call->status->value,
            'dialed_at' => $call->dialedAt->format(DateTimeInterface::ATOM),
            'answered_at' => $call->answeredAt?->format(DateTimeInterface::ATOM),
            'ended_at' => $call->endedAt?->format(DateTimeInterface::ATOM),
            'duration_seconds' => $call->durationSeconds,
        ];
    }
}
