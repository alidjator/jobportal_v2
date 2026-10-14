<?php

namespace Src\Company\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Company\Application\Invitations\InvitationPreview;

/** @property InvitationPreview $resource */
final class InvitationPreviewResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'company_name' => $this->resource->companyName,
            'email' => $this->resource->email,
            'name' => $this->resource->name,
            'role' => $this->resource->role->value,
            'expires_at' => $this->resource->expiresAt->format(DateTimeInterface::ATOM),
        ];
    }
}
