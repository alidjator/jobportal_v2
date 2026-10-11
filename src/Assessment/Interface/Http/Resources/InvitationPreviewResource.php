<?php

namespace Src\Assessment\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Assessment\Application\Invitations\InvitationPreview;

/** @property InvitationPreview $resource */
final class InvitationPreviewResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'provider_name' => $this->resource->providerName,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'expires_at' => $this->resource->expiresAt->format(DateTimeInterface::ATOM),
        ];
    }
}
