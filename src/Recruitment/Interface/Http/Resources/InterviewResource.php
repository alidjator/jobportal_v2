<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Application\InterviewView;
use Src\Recruitment\Domain\Interview;

/**
 * An interview, for the team and for the job seeker alike. `proposals_left` counts how many more times the job seeker may propose another time, and
 * `proposed_starts_at` is the time waiting for the team's answer (null when none).
 *
 * @property InterviewView $resource
 */
final class InterviewResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $interview = $this->resource->interview;

        return [
            'id' => $interview->id,
            'application_id' => $interview->applicationId,
            'status' => $interview->status->value,
            'starts_at' => $interview->startsAt->format(DATE_ATOM),
            'ends_at' => $interview->endsAt->format(DATE_ATOM),
            'duration_minutes' => $interview->durationMinutes(),
            'format' => $interview->format->value,
            'location' => $interview->location,
            'meeting_url' => $interview->meetingUrl,
            'interviewer' => ['id' => $interview->interviewerId, 'name' => $this->resource->interviewerName],
            'proposed_starts_at' => $interview->proposedStartsAt?->format(DATE_ATOM),
            'proposals_left' => max(0, Interview::PROPOSALS_MAX - $interview->proposalsUsed),
            'created_at' => $interview->createdAt->format(DATE_ATOM),
        ];
    }
}
