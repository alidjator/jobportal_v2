<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Application\InterviewView;

/**
 * An interview as the team sees it: the interview itself plus the kit its questions came from (`kit.id` is null once the kit was deleted) and the extra
 * panel members. The job seeker never gets these fields.
 *
 * @property InterviewView $resource
 */
final class CompanyInterviewResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $interview = $this->resource->interview;

        return [
            ...(new InterviewResource($this->resource))->toArray($request),
            /** The work location the address was copied from (`GET company/locations`); null for free text. */
            'location_id' => $interview->locationId,
            'kit' => $interview->kitName === null ? null : ['id' => $interview->kitId, 'name' => $interview->kitName],
            /** The team's own note on a no-show (status `no_show`); the job seeker never sees it. */
            'no_show_note' => $interview->noShowNote,
            /** The answer of the main interviewer to the invitation. */
            'interviewer_rsvp' => $this->resource->interviewerRsvp === null ? null : (new InterviewRsvpResource($this->resource->interviewerRsvp))->toArray($request),
            /** The extra panel members, each with the answer to the invitation (`rsvp`). */
            'participants' => array_map(static fn (array $member): array => [
                'id' => $member['id'],
                'name' => $member['name'],
                'rsvp' => (new InterviewRsvpResource($member['rsvp']))->toArray($request),
            ], $this->resource->participants),
        ];
    }
}
