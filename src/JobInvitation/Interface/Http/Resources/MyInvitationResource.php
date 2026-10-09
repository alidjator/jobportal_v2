<?php

namespace Src\JobInvitation\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\JobInvitation\Application\InvitationView;

/** An invitation as the candidate sees it: the company, the job and the note, never the member who invited. @property InvitationView $resource */
final class MyInvitationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'company' => ['id' => $this->resource->companyId, 'name' => $this->resource->companyName],
            'job' => ['id' => $this->resource->jobId, 'title' => $this->resource->jobTitle],
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
