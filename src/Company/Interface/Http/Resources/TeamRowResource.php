<?php

namespace Src\Company\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Company\Application\Team\TeamRow;

/**
 * A row of the team list: a member (`id` is the account id) or an open invitation (`id` is the invitation id).
 *
 * @property TeamRow $resource
 */
final class TeamRowResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $row = $this->resource;
        $common = [
            'type' => $row->type,
            'id' => $row->id,
            'name' => $row->name,
            'email' => $row->email,
            'role' => $row->role->value,
            'status' => $row->status,
            'permissions' => $row->permissions,
            'actions' => $row->actions,
        ];

        if ($row->type === TeamRow::TYPE_INVITATION) {
            return $common + [
                'sent_at' => $row->sentAt?->format(DateTimeInterface::ATOM),
                'expires_at' => $row->expiresAt?->format(DateTimeInterface::ATOM),
                'invited_by' => $row->invitedBy,
            ];
        }

        return $common + [
            'is_owner' => $row->isOwner,
            'joined_at' => $row->joinedAt?->format(DateTimeInterface::ATOM),
            'last_login_at' => $row->lastLoginAt?->format(DateTimeInterface::ATOM),
            'two_factor' => $row->twoFactor,
        ];
    }
}
