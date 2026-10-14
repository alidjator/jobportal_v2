<?php

namespace Src\Company\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Company\Application\Team\PendingInvitationView;

/** @property PendingInvitationView $resource */
final class PendingInvitationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'role' => $this->resource->role->value,
            'permissions' => $this->resource->permissions,
            'sent_at' => $this->resource->sentAt->format(DateTimeInterface::ATOM),
            'expires_at' => $this->resource->expiresAt->format(DateTimeInterface::ATOM),
            'invited_by' => $this->resource->invitedBy,
        ];
    }
}
