<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Application\MyInterviewRow;

/**
 * One row of the "my interviews" list.
 *
 * @property MyInterviewRow $resource
 */
final class MyInterviewResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'application_id' => $this->resource->applicationId,
            'job' => ['id' => $this->resource->jobId, 'title' => $this->resource->jobTitle],
            'candidate_name' => $this->resource->candidateName,
            'status' => $this->resource->status->value,
            'format' => $this->resource->format->value,
            'starts_at' => $this->resource->startsAt->format(DATE_ATOM),
            'ends_at' => $this->resource->endsAt->format(DATE_ATOM),
            /** The member's place on the panel: the main interviewer or a panel member; null when they do not sit on it. */
            'role' => $this->resource->role,
            /** The member's scorecard: owed now (`pending`), `sent`, or `not_open` (the interview has not started or the application is final); null without a place on the panel. */
            'scorecard' => $this->resource->scorecard,
            /** The member's own answer to the invitation: `pending`, `accepted` or `declined`; null without a place on the panel. */
            'rsvp' => $this->resource->rsvp,
        ];
    }
}
