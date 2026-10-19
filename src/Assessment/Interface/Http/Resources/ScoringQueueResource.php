<?php

namespace Src\Assessment\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Assessment\Application\Results\ScoringQueueEntry;

/** A test waiting for the member to score: the invitation fields plus the candidate, the job and the submission time. @property ScoringQueueEntry $resource */
final class ScoringQueueResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...(new InvitationResource($this->resource->view))->toArray($request),
            'candidate' => ['id' => $this->resource->view->invitation->applicantUserId, 'name' => $this->resource->candidateName],
            'job' => ['id' => $this->resource->view->invitation->jobId, 'title' => $this->resource->jobTitle],
            'submitted_at' => $this->resource->submittedAt->format(DateTimeInterface::ATOM),
        ];
    }
}
