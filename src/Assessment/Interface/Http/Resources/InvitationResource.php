<?php

namespace Src\Assessment\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Assessment\Application\Delivery\InvitationView;

/**
 * A test sent for an application, as the team sees it.
 *
 * @property InvitationView $resource
 */
final class InvitationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $invitation = $this->resource->invitation;

        return [
            'id' => $invitation->id,
            'application_id' => $invitation->applicationId,
            'test' => ['id' => $invitation->testId, 'title' => $this->resource->testTitle, 'kind' => $this->resource->testKind->value],
            'status' => $invitation->status->value,
            'starts_at' => $invitation->startsAt->format(DateTimeInterface::ATOM),
            'due_at' => $invitation->dueAt->format(DateTimeInterface::ATOM),
            'invited_by_id' => $invitation->invitedByUserId,
            'assessors' => $this->resource->assessors,
            'mode' => $invitation->mode->value,
            'location' => $invitation->location,
            'note' => $invitation->note,
            'attachment' => $invitation->attachment === null ? null : ['name' => $invitation->attachment->name, 'size_bytes' => $invitation->attachment->sizeBytes],
            'closed_at' => $invitation->closedAt?->format(DateTimeInterface::ATOM),
            'created_at' => $invitation->createdAt?->format(DateTimeInterface::ATOM),
        ];
    }
}
