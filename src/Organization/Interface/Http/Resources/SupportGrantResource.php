<?php

namespace Src\Organization\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Organization\Application\Support\SupportGrantView;

/** @property SupportGrantView $resource */
final class SupportGrantResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $grant = $this->resource->grant;

        return [
            'id' => $grant->id,
            'admin' => ['id' => $grant->adminId, 'name' => $this->resource->adminName],
            'granted_by' => ['id' => $grant->grantedByUserId, 'name' => $this->resource->grantedByName],
            'status' => $this->resource->status(),
            'created_at' => $grant->createdAt->format(DateTimeInterface::ATOM),
            'expires_at' => $grant->expiresAt->format(DateTimeInterface::ATOM),
            'revoked_at' => $grant->revokedAt?->format(DateTimeInterface::ATOM),
        ];
    }
}
