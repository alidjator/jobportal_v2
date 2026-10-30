<?php

namespace Src\JobInvitation\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\JobInvitation\Application\EmailInvitationView;

/**
 * An invitation by email as the company sees it. The name and the email are null once the invitation ended without a claim; the CV is read from its own endpoint.
 *
 * @property EmailInvitationView $resource
 */
final class CompanyEmailInvitationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'job_id' => $this->resource->jobId,
            'job_title' => $this->resource->jobTitle,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'channel' => $this->resource->channel->value,
            'note' => $this->resource->note,
            'status' => $this->resource->status->value,
            'invited_by' => ['id' => $this->resource->invitedByUserId, 'name' => $this->resource->invitedByName],
            'has_cv' => $this->resource->hasCv,
            'resent_count' => $this->resource->resentCount,
            'application_id' => $this->resource->applicationId,
            'opened_at' => $this->resource->openedAt?->format(DateTimeInterface::ATOM),
            'responded_at' => $this->resource->respondedAt?->format(DateTimeInterface::ATOM),
            'expires_at' => $this->resource->expiresAt->format(DateTimeInterface::ATOM),
            'created_at' => $this->resource->createdAt->format(DateTimeInterface::ATOM),
        ];
    }
}
