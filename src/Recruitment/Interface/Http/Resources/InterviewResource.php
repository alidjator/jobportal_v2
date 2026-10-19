<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Application\InterviewView;
use Src\Recruitment\Domain\Interview;
use Src\Recruitment\Domain\InterviewFormat;

/**
 * An interview, for the team and for the job seeker alike. `proposals_left` counts how many more times the job seeker may propose another time, and
 * `proposed_starts_at` is the time waiting for the team's answer (null when none), also inside `proposal` with the reason and when it was made. A confirmed
 * interview with a proposal stays confirmed at `starts_at` until the team answers. `recorded` tells that the team will record the interview and
 * `recording_consent` is the job seeker's answer (null until they answer).
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
            /** The note for the job seeker that goes with the invitation (plain text), or null. */
            'note' => $interview->note,
            'recorded' => $interview->recorded,
            /** @var 'granted'|'refused'|'withdrawn'|null */
            'recording_consent' => $interview->recordingConsent?->value,
            /** The number the job seeker confirmed: the one a voice interview is dialled on, or the fallback of a video interview. */
            'candidate_phone' => $interview->candidatePhone,
            /** The platform number a voice interview calls from (the same for every candidate). */
            'caller_number' => $interview->format === InterviewFormat::Voice ? config('services.asterisk.caller_id') : null,
            'interviewer' => ['id' => $interview->interviewerId, 'name' => $this->resource->interviewerName],
            'proposed_starts_at' => $interview->proposedStartsAt?->format(DATE_ATOM),
            /** The open proposal of the job seeker, or null. */
            'proposal' => $interview->proposedStartsAt === null ? null : [
                'starts_at' => $interview->proposedStartsAt->format(DATE_ATOM),
                'reason' => $interview->proposalReason,
                'made_at' => $interview->proposalMadeAt?->format(DATE_ATOM),
            ],
            /** @var int */
            'proposals_left' => max(0, Interview::PROPOSALS_MAX - $interview->proposalsUsed),
            'created_at' => $interview->createdAt->format(DATE_ATOM),
        ];
    }
}
