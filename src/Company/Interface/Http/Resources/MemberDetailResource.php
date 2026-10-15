<?php

namespace Src\Company\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Company\Application\Team\MemberDetail;

/** @property MemberDetail $resource */
final class MemberDetailResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $member = $this->resource;

        return [
            'user_id' => $member->userId,
            'name' => $member->name,
            'email' => $member->email,
            'role' => $member->role->value,
            'is_owner' => $member->isOwner,
            'preset' => $member->preset,
            'permissions' => $member->permissions,
            'status' => $member->status,
            'suspended_at' => $member->suspendedAt?->format(DateTimeInterface::ATOM),
            'suspended_by' => $member->suspendedBy,
            'joined_at' => $member->joinedAt->format(DateTimeInterface::ATOM),
            'last_login_at' => $member->lastLoginAt?->format(DateTimeInterface::ATOM),
            'assigned_job_count' => $member->assignedJobCount,
            'two_factor' => $member->twoFactor,
        ];
    }
}
