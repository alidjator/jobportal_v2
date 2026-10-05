<?php

namespace Src\Company\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Company\Application\Team\MemberView;

/** @property MemberView $resource */
final class MemberResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->resource->userId,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'role' => $this->resource->role->value,
            'status' => $this->resource->status,
            'joined_at' => $this->resource->joinedAt->format(DateTimeInterface::ATOM),
            'last_login_at' => $this->resource->lastLoginAt?->format(DateTimeInterface::ATOM),
        ];
    }
}
