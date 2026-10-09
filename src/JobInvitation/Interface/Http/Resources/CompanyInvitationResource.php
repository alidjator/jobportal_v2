<?php

namespace Src\JobInvitation\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\JobInvitation\Application\InvitationView;

/** An invitation as the company sees it: the candidate, the member who invited and the decline reason. @property InvitationView $resource */
final class CompanyInvitationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'job_id' => $this->resource->jobId,
            'job_title' => $this->resource->jobTitle,
            'candidate' => ['slug' => $this->resource->candidateSlug, 'name' => $this->resource->candidateName],
            'invited_by' => ['id' => $this->resource->invitedByUserId, 'name' => $this->resource->invitedByName],
            'note' => $this->resource->note,
            'status' => $this->resource->status->value,
            'expired' => $this->resource->expired,
            'decline_reason' => $this->resource->declineReason,
            'viewed_at' => $this->resource->viewedAt?->format(DateTimeInterface::ATOM),
            'responded_at' => $this->resource->respondedAt?->format(DateTimeInterface::ATOM),
            'application_id' => $this->resource->applicationId,
            'created_at' => $this->resource->createdAt->format(DateTimeInterface::ATOM),
        ];
    }
}
